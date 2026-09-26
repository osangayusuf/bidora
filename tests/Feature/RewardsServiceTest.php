<?php

use App\Enums\RewardSource;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\Auction;
use App\Models\Bid;
use App\Models\LeaderboardSnapshot;
use App\Models\PointTransaction;
use App\Models\User;
use App\Notifications\AchievementUnlocked;
use App\Notifications\BonusPointsAwarded;
use App\Notifications\BonusPointsClaimed;
use App\Notifications\DailyCheckinRewarded;
use App\Notifications\SpinWheelPrizeWon;
use App\Notifications\WeeklyLeaderboardBonus;
use App\Services\AchievementService;
use App\Services\RewardsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

it('awards bonus points on check-in and sends task notification', function () {
    Notification::fake();

    $user = User::factory()->create([
        'checkin_streak' => 0,
        'last_checkin_date' => null,
        'bonus_points' => 0,
    ]);

    $result = app(RewardsService::class)->processCheckin($user);

    expect($result['success'])->toBeTrue()
        ->and($user->fresh()->checkin_streak)->toBe(1)
        ->and($user->fresh()->bonus_points)->toBeGreaterThan(0);

    Notification::assertSentTo($user, DailyCheckinRewarded::class);
    Notification::assertNotSentTo($user, BonusPointsAwarded::class);
});

it('prevents duplicate check-in on the same day', function () {
    $user = User::factory()->create([
        'last_checkin_date' => now()->toDateString(),
        'checkin_streak' => 3,
    ]);

    $result = app(RewardsService::class)->processCheckin($user);

    expect($result['success'])->toBeFalse();
});

it('spins decrement balance and award bonus without generic notification', function () {
    Notification::fake();

    $user = User::factory()->create(['spins_balance' => 1, 'bonus_points' => 0]);

    $result = app(RewardsService::class)->spinWheel($user);

    expect($result['points_won'])->toBeGreaterThan(0)
        ->and($user->fresh()->spins_balance)->toBe(0)
        ->and($user->fresh()->bonus_points)->toBe($result['points_won']);

    Notification::assertSentTo($user, SpinWheelPrizeWon::class);
    Notification::assertNotSentTo($user, BonusPointsAwarded::class);
});

it('claims all bonus points using conversion rate', function () {
    Notification::fake();

    config(['points.bonus_conversion_rate' => 2.0]);

    $user = User::factory()->create(['bonus_points' => 50, 'points_balance' => 0]);

    $transaction = app(RewardsService::class)->claimAll($user);

    $user->refresh();

    expect($user->bonus_points)->toBe(0)
        ->and($user->points_balance)->toBe(100)
        ->and($transaction->amount)->toEqual(100);

    Notification::assertSentTo($user, BonusPointsClaimed::class);
});

it('unlocks first bid achievement after bidding evaluation', function () {
    Notification::fake();

    $user = User::factory()->create();
    $auction = Auction::factory()->create();
    $bid = Bid::factory()->create([
        'user_id' => $user->id,
        'auction_id' => $auction->id,
    ]);

    app(AchievementService::class)->evaluateAfterBid($user, $bid, $auction);

    $achievement = $user->userAchievements()->where('achievement_key', 'first_bid')->first();

    expect($achievement)->not->toBeNull()
        ->and($achievement->completed_at)->not->toBeNull();

    Notification::assertSentTo($user, AchievementUnlocked::class);
});

it('records check-in bonus award metadata source', function () {
    Notification::fake();

    $user = User::factory()->create(['bonus_points' => 0]);

    app(RewardsService::class)->processCheckin($user);

    $transaction = $user->pointTransactions()
        ->where('type', TransactionType::BONUS_AWARD)
        ->first();

    expect($transaction->metadata['source'])->toBe(RewardSource::Checkin->value);
});

it('grantDailySpins sets spins_balance to 1 for all users', function () {
    Notification::fake();

    $userWithNoSpins = User::factory()->create(['spins_balance' => 0]);
    $userWithExistingSpins = User::factory()->create(['spins_balance' => 3]);

    app(RewardsService::class)->grantDailySpins();

    $userWithNoSpins->refresh();
    $userWithExistingSpins->refresh();

    // User with 0 spins should be bumped to 1.
    // User already at 3 spins (> daily_grant of 1) should remain untouched.
    expect($userWithNoSpins->spins_balance)->toBe(1)
        ->and($userWithExistingSpins->spins_balance)->toBe(3);
});

it('weekly_leaderboard config has top_ranks of 10 with bonuses for all ranks', function () {
    $topRanks = config('rewards.weekly_leaderboard.top_ranks');
    $bonuses = config('rewards.weekly_leaderboard.bonuses');

    expect($topRanks)->toBe(10)
        ->and($bonuses)->toHaveCount(10)
        ->and($bonuses[1])->toBe(200)
        ->and($bonuses[10])->toBe(5);
});

it('rewards the previous Monday-to-Sunday top three at Monday midnight', function () {
    Notification::fake();

    Carbon::setTestNow(
        Carbon::create(2026, 9, 21, 0, 0, 0, 'Africa/Lagos')
    );

    $users = User::factory()
        ->count(4)
        ->create(['bonus_points' => 0]);

    foreach ([500, 300, 100, 50] as $index => $amount) {
        PointTransaction::create([
            'user_id' => $users[$index]->id,
            'type' => TransactionType::BID_DEBIT,
            'status' => TransactionStatus::COMPLETED,
            'amount' => $amount,
            'exchange_rate' => 1,
            'created_at' => Carbon::create(
                2026,
                9,
                20,
                23,
                59,
                0,
                'Africa/Lagos'
            )->utc(),
        ]);
    }

    $awarded = app(RewardsService::class)->processWeeklyLeaderboard();

    expect($awarded)->toBe(3);

    foreach ($users->take(3) as $user) {
        expect($user->fresh()->bonus_points)->toBe(1000);
    }

    expect($users[3]->fresh()->bonus_points)->toBe(0)
        ->and(LeaderboardSnapshot::count())->toBe(3)
        ->and(
            LeaderboardSnapshot::query()
                ->where('week_start', '2026-09-14')
                ->count()
        )
        ->toBe(3);

    Notification::assertSentTimes(WeeklyLeaderboardBonus::class, 3);

    Carbon::setTestNow();
});
