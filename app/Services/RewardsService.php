<?php

namespace App\Services;

use App\Enums\RewardSource;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\LeaderboardSnapshot;
use App\Models\PointTransaction;
use App\Models\User;
use App\Notifications\DailyCheckinRewarded;
use App\Notifications\DailySpinGranted;
use App\Notifications\SpinWheelPrizeWon;
use App\Notifications\WeeklyLeaderboardBonus;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class RewardsService
{
    private const BUSINESS_TIMEZONE = 'Africa/Lagos';

    public function __construct(
        private readonly WalletService $walletService,
    ) {}

    /**
     * @return array{success: bool, message: string, transaction?: PointTransaction, streak?: int}
     */
    public function processCheckin(User $user): array
    {
        $today = Carbon::now()->toDateString();

        if ($user->last_checkin_date?->toDateString() === $today) {
            return [
                'success' => false,
                'message' => 'You have already checked in today. Come back tomorrow!',
            ];
        }

        $yesterday = Carbon::yesterday()->toDateString();
        $streak = ($user->last_checkin_date?->toDateString() === $yesterday)
            ? $user->checkin_streak + 1
            : 1;

        $points = (int) config('rewards.checkin.base_points');
        $milestones = config('rewards.checkin.milestones', []);

        if (isset($milestones[$streak])) {
            $points += (int) $milestones[$streak];
        }

        $transaction = DB::transaction(function () use ($user, $today, $streak, $points) {
            $user = User::where('id', $user->id)->lockForUpdate()->firstOrFail();

            if ($user->last_checkin_date?->toDateString() === $today) {
                throw new RuntimeException('Already checked in today.');
            }

            $user->checkin_streak = $streak;
            $user->last_checkin_date = $today;
            $user->save();

            return $this->walletService->awardBonusPoints(
                $user,
                $points,
                [
                    'source' => RewardSource::Checkin->value,
                    'description' => "Daily check-in (day {$streak})",
                    'streak' => $streak,
                ],
                sendBonusAwardedNotification: false,
            );
        });

        $user->notify(new DailyCheckinRewarded($transaction, $streak));

        return [
            'success' => true,
            'message' => "Checked in! +{$points} bonus pts added.",
            'transaction' => $transaction,
            'streak' => $streak,
        ];
    }

    /**
     * @return array{points_won: int, segment_index: int, message: string}
     */
    public function spinWheel(User $user): array
    {
        $segments = config('rewards.spin_wheel.segments', []);

        if ($segments === []) {
            throw new RuntimeException('Spin wheel is not configured.');
        }

        return DB::transaction(function () use ($user, $segments) {
            $user = User::where('id', $user->id)->lockForUpdate()->firstOrFail();

            if ($user->spins_balance < 1) {
                throw new RuntimeException('No spins available.');
            }

            $segmentIndex = $this->pickWeightedSegment($segments);
            $pointsWon = (int) $segments[$segmentIndex]['points'];

            $user->spins_balance--;
            $user->save();

            $transaction = $this->walletService->awardBonusPoints(
                $user,
                $pointsWon,
                [
                    'source' => RewardSource::Spin->value,
                    'description' => 'Spin wheel prize',
                    'segment_index' => $segmentIndex,
                ],
                sendBonusAwardedNotification: false,
            );

            $user->notify(new SpinWheelPrizeWon($transaction));

            return [
                'points_won' => $pointsWon,
                'segment_index' => $segmentIndex,
                'message' => "You won {$pointsWon} bonus points!",
            ];
        });
    }

    public function claimAll(User $user): PointTransaction
    {
        return $this->walletService->claimAllBonusPoints($user);
    }

    public function grantDailySpins(): int
    {
        $grant = (int) config('rewards.spin_wheel.daily_grant', 1);
        $notified = 0;

        User::query()->eachById(function (User $user) use ($grant, &$notified): void {
            if ($user->spins_balance < $grant) {
                $user->spins_balance = $grant;
                $user->save();
                $user->notify(new DailySpinGranted($grant));
                $notified++;
            }
        });

        return $notified;
    }

    public function processWeeklyLeaderboard(): int
    {
        // The job runs at Monday 00:00, so reward the Monday-Sunday week that just ended.
        $currentWeekStart = Carbon::now(self::BUSINESS_TIMEZONE)
            ->startOfWeek(Carbon::MONDAY);
        $weekStart = $currentWeekStart->copy()->subWeek();
        $weekEnd = $currentWeekStart->copy();
        $weekStartDate = $weekStart->toDateString();
        $topRanks = (int) config('rewards.weekly_leaderboard.top_ranks', 3);
        $bonuses = config('rewards.weekly_leaderboard.bonuses', []);

        $rankings = $this->weeklyBidRankings(
            $weekStart->copy()->utc(),
            $weekEnd->copy()->utc(),
        );

        if ($rankings->isEmpty()) {
            return 0;
        }

        $awarded = 0;

        foreach ($rankings->take($topRanks) as $index => $row) {
            $rank = $index + 1;
            $bonus = (int) ($bonuses[$rank] ?? 0);

            if ($bonus <= 0) {
                continue;
            }

            $user = User::find($row->user_id);

            if ($user === null) {
                continue;
            }

            $alreadyAwarded = LeaderboardSnapshot::query()
                ->where('user_id', $user->id)
                ->where('week_start', $weekStartDate)
                ->exists();

            if ($alreadyAwarded) {
                continue;
            }

            DB::transaction(function () use ($user, $rank, $bonus, $row, $weekStartDate): void {
                $transaction = $this->walletService->awardBonusPoints(
                    $user,
                    $bonus,
                    [
                        'source' => RewardSource::Leaderboard->value,
                        'description' => "Weekly leaderboard rank #{$rank}",
                        'rank' => $rank,
                        'week_start' => $weekStartDate,
                    ],
                    sendBonusAwardedNotification: false,
                );

                LeaderboardSnapshot::create([
                    'user_id' => $user->id,
                    'rank' => $rank,
                    'total_bid_pts' => (int) $row->total_bid_pts,
                    'week_start' => $weekStartDate,
                    'bonus_points_awarded' => $bonus,
                ]);

                $user->notify(new WeeklyLeaderboardBonus($transaction, $rank));
            });

            $awarded++;
        }

        return $awarded;
    }

    /**
     * @param  array<int, array{points: int, probability: int}>  $segments
     */
    private function pickWeightedSegment(array $segments): int
    {
        $roll = random_int(1, 100);
        $cumulative = 0;

        foreach ($segments as $index => $segment) {
            $cumulative += (int) $segment['probability'];

            if ($roll <= $cumulative) {
                return $index;
            }
        }

        return array_key_last($segments);
    }

    /**
     * @return Collection<int, object{user_id: int, total_bid_pts: int}>
     */
    private function weeklyBidRankings(Carbon $weekStart, Carbon $weekEnd): Collection
    {
        return DB::table('point_transactions')
            ->where('type', TransactionType::BID_DEBIT->value)
            ->where('status', TransactionStatus::COMPLETED->value)
            ->where('created_at', '>=', $weekStart)
            ->where('created_at', '<', $weekEnd)
            ->selectRaw('user_id, SUM(amount) as total_bid_pts')
            ->groupBy('user_id')
            ->orderByDesc('total_bid_pts')
            ->get();
    }
}
