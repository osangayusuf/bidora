<?php

namespace App\Services;

use App\Enums\RewardSource;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\PointTransaction;
use App\Models\User;
use App\Models\UserAchievement;
use App\Support\MsisdnMasker;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TaskCenterPageService
{
    /**
     * @return array<string, mixed>
     */
    public function buildProps(User $user): array
    {
        $conversionRate = (float) config('points.bonus_conversion_rate');
        $bonusPoints = (int) $user->bonus_points;

        return [
            'checkin' => $this->buildCheckinProps($user),
            'achievements' => $this->buildAchievementsProps($user),
            'spin' => $this->buildSpinProps($user),
            'leaderboard' => $this->buildLeaderboardProps($user),
            'wallet' => [
                'unclaimed_points' => $bonusPoints,
                'spendable_on_claim' => (int) floor($bonusPoints * $conversionRate),
                'recent_rewards' => $this->buildRecentRewards($user),
            ],
            'referral' => [
                'code' => $user->referral_code,
                'url' => route('register', ['ref' => $user->referral_code]),
                'count' => (int) User::where('referred_by', $user->id)->count(),
                'signup_reward_points' => (int) config('points.referral.referrer_signup_points'),
                'deposit_reward_points' => (int) config('points.referral.referrer_first_deposit_points'),
                'points_earned' => (int) PointTransaction::where('user_id', $user->id)
                    ->where('type', TransactionType::BONUS_AWARD)
                    ->whereIn('metadata->source', [
                        RewardSource::ReferralSignup->value,
                        RewardSource::ReferralDeposit->value,
                    ])
                    ->sum('amount'),
            ],
            'user_points' => (int) $user->points_balance,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildCheckinProps(User $user): array
    {
        $today = Carbon::now()->toDateString();
        $weekStart = Carbon::now()->startOfWeek();
        $checkinDates = $this->checkinDatesForWeek($user, $weekStart);

        $weekDays = [];

        for ($i = 0; $i < 7; $i++) {
            $day = $weekStart->copy()->addDays($i);
            $dateStr = $day->toDateString();

            $weekDays[] = [
                'label' => $day->format('D'),
                'date' => $dateStr,
                'checked' => in_array($dateStr, $checkinDates, true),
                'is_today' => $day->isToday(),
            ];
        }

        return [
            'streak' => (int) $user->checkin_streak,
            'last_date' => $user->last_checkin_date?->toDateString(),
            'can_checkin' => $user->last_checkin_date?->toDateString() !== $today,
            'week_days' => $weekDays,
        ];
    }

    /**
     * @return list<string>
     */
    private function checkinDatesForWeek(User $user, Carbon $weekStart): array
    {
        return PointTransaction::query()
            ->where('user_id', $user->id)
            ->where('type', TransactionType::BONUS_AWARD)
            ->where('created_at', '>=', $weekStart)
            ->where('metadata->source', RewardSource::Checkin->value)
            ->get()
            ->map(fn (PointTransaction $tx) => $tx->created_at->toDateString())
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildAchievementsProps(User $user): array
    {
        $definitions = config('rewards.achievements', []);
        $records = UserAchievement::query()
            ->where('user_id', $user->id)
            ->get()
            ->keyBy('achievement_key');

        $achievements = [];

        foreach ($definitions as $key => $definition) {
            $record = $records->get($key);

            $achievements[] = [
                'key' => $key,
                'label' => $definition['label'],
                'description' => $definition['description'],
                'icon' => $definition['icon'],
                'points' => (int) $definition['points'],
                'target' => (int) $definition['target'],
                'progress' => $record?->progress ?? 0,
                'completed_at' => $record?->completed_at?->toIso8601String(),
            ];
        }

        return $achievements;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildSpinProps(User $user): array
    {
        $segments = config('rewards.spin_wheel.segments', []);

        return [
            'available_spins' => (int) $user->spins_balance,
            'segment_labels' => array_map(
                fn (array $segment): string => ((int) $segment['points']).' pts',
                $segments,
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildLeaderboardProps(User $user): array
    {
        $weekStart = Carbon::now()->startOfWeek();
        $rankings = $this->weeklyBidRankings($weekStart);

        $topUsers = [];
        $userRank = null;

        foreach ($rankings as $index => $row) {
            $rank = $index + 1;

            if ($rank <= 5) {
                $topUser = User::find($row->user_id);

                $topUsers[] = [
                    'rank' => $rank,
                    'msisdn_masked' => MsisdnMasker::mask($topUser?->phone),
                    'total_bid_pts' => (int) $row->total_bid_pts,
                ];
            }

            if ((int) $row->user_id === $user->id) {
                $userRank = $rank;
            }
        }

        return [
            'top_users' => $topUsers,
            'week_ends_at' => Carbon::now()->endOfWeek()->setTime(23, 55, 0)->toIso8601String(),
            'user_rank' => $userRank,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildRecentRewards(User $user): array
    {
        return PointTransaction::query()
            ->where('user_id', $user->id)
            ->where('type', TransactionType::BONUS_AWARD)
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn (PointTransaction $tx): array => [
                'source' => (string) ($tx->metadata['source'] ?? 'bonus'),
                'description' => (string) ($tx->metadata['description'] ?? 'Bonus reward'),
                'points' => (int) $tx->amount,
                'claimed_at' => null,
                'created_at' => $tx->created_at->toIso8601String(),
            ])
            ->all();
    }

    /**
     * @return Collection<int, object{user_id: int, total_bid_pts: int}>
     */
    private function weeklyBidRankings(Carbon $weekStart): Collection
    {
        return DB::table('point_transactions')
            ->where('type', TransactionType::BID_DEBIT->value)
            ->where('status', TransactionStatus::COMPLETED->value)
            ->where('created_at', '>=', $weekStart)
            ->selectRaw('user_id, SUM(amount) as total_bid_pts')
            ->groupBy('user_id')
            ->orderByDesc('total_bid_pts')
            ->get();
    }
}
