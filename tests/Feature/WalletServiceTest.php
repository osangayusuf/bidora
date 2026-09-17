<?php

use App\Enums\PackageRenewalCycle;
use App\Enums\SubscriptionStatus;
use App\Models\LaunchPromotion;
use App\Models\PointSubscription;
use App\Models\SubscriptionPackage;
use App\Models\User;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('can process deposit and convert naira to points', function () {
    $user = User::factory()->create(['points_balance' => 0]);
    $service = new WalletService;

    // Default rate is 100 points per naira
    config(['points.points_per_naira' => 100]);

    $transaction = $service->processDeposit($user, 1000, 'ref_123');

    expect($transaction->user_id)->toBe($user->id)
        ->and($transaction->type->value)->toBe('deposit')
        ->and($transaction->naira_amount)->toEqual(1000)
        ->and($transaction->amount)->toEqual(100000) // 1000 * 100
        ->and($transaction->provider_reference)->toBe('ref_123')
        ->and($transaction->status->value)->toBe('completed');
});

it('credits a package subscription its fixed points_allocated, not the naira rate', function () {
    config(['points.points_per_naira' => 10]);

    $user = User::factory()->create(['points_balance' => 0]);
    $package = SubscriptionPackage::create([
        'name' => 'Awoof',
        'slug' => 'awoof',
        'renewal_cycle' => PackageRenewalCycle::WEEKLY,
        'points_allocated' => 6000,
        'price_naira' => 500,
        'is_active' => true,
        'sort_order' => 1,
    ]);
    $subscription = PointSubscription::create([
        'user_id' => $user->id,
        'subscription_package_id' => $package->id,
        'amount_naira' => 500,
        'status' => SubscriptionStatus::PENDING,
        'authorization_code' => 'AUTH_pkg',
    ]);

    $service = new WalletService;
    $transaction = $service->processDeposit($user, 500, 'ref_pkg_first', [], null, $subscription->id);

    // Standard rate would give 500 * 10 = 5000 — the package's bonus rate wins.
    expect($transaction->amount)->toEqual(6000);
    expect($user->fresh()->points_balance)->toEqual(6000);

    $subscription->refresh();
    expect($subscription->status)->toBe(SubscriptionStatus::ACTIVE)
        ->and($subscription->next_charge_at)->not->toBeNull();
});

it('marks a one-off package subscription completed with no next charge', function () {
    $user = User::factory()->create(['points_balance' => 0]);
    $package = SubscriptionPackage::create([
        'name' => 'Solo',
        'slug' => 'solo',
        'renewal_cycle' => PackageRenewalCycle::ONE_OFF,
        'points_allocated' => 5000,
        'price_naira' => 500,
        'is_active' => true,
        'sort_order' => 1,
    ]);
    $subscription = PointSubscription::create([
        'user_id' => $user->id,
        'subscription_package_id' => $package->id,
        'amount_naira' => 500,
        'status' => SubscriptionStatus::PENDING,
        'authorization_code' => 'AUTH_solo',
    ]);

    (new WalletService)->processDeposit($user, 500, 'ref_solo', [], null, $subscription->id);

    $subscription->refresh();
    expect($subscription->status)->toBe(SubscriptionStatus::COMPLETED)
        ->and($subscription->next_charge_at)->toBeNull()
        ->and($subscription->isCancellable())->toBeFalse();
});

it('can award bonus points', function () {
    $user = User::factory()->create(['bonus_points' => 0]);
    $service = new WalletService;

    $transaction = $service->awardBonusPoints($user, 200, ['reason' => 'signup']);

    expect($transaction->user_id)->toBe($user->id)
        ->and($transaction->type->value)->toBe('bonus_award')
        ->and($transaction->amount)->toEqual(200)
        ->and($transaction->status->value)->toBe('completed')
        ->and($transaction->metadata['reason'])->toBe('signup');

    // User balance should be updated
    expect($user->fresh()->bonus_points)->toEqual(200);
});

it('can claim bonus points to spendable points', function () {
    config(['points.bonus_conversion_rate' => 1.0]);
    $user = User::factory()->create(['bonus_points' => 500, 'points_balance' => 0]);
    $service = new WalletService;

    $transaction = $service->claimBonusPoints($user, 200);

    expect($transaction->user_id)->toBe($user->id)
        ->and($transaction->type->value)->toBe('bonus_claim')
        ->and($transaction->amount)->toEqual(200)
        ->and($transaction->status->value)->toBe('completed')
        ->and($transaction->metadata['bonus_claimed'])->toEqual(200);

    $user->refresh();
    expect($user->bonus_points)->toEqual(300)
        ->and($user->points_balance)->toEqual(200); // 0 + 200
});

it('throws exception when claiming more bonus points than available', function () {
    $user = User::factory()->create([
        'points_balance' => 0,
        'bonus_points' => 50,
    ]);

    $service = new WalletService;

    $service->claimBonusPoints($user, 100);
})->throws(InvalidArgumentException::class);

it('awards the launch bonus and consumes a slot', function () {
    $promo = LaunchPromotion::query()->updateOrCreate(['slug' => 'launch_first_100'], [
        'slots_total' => 100,
        'slots_claimed' => 0,
        'amount' => 5000,
        'is_active' => true,
    ]);
    $user = User::factory()->create(['points_balance' => 0]);
    $service = new WalletService;

    $transaction = $service->awardLaunchBonus($user);

    expect($transaction->type->value)->toBe('launch_bonus')
        ->and($transaction->amount)->toEqual(5000)
        ->and($transaction->metadata['slot'])->toBe(1);

    expect($user->fresh()->points_balance)->toEqual(5000)
        ->and($promo->fresh()->slots_claimed)->toBe(1);
});

it('does not award the launch bonus twice to the same user', function () {
    LaunchPromotion::query()->updateOrCreate(['slug' => 'launch_first_100'], [
        'slots_total' => 100,
        'slots_claimed' => 0,
        'amount' => 5000,
        'is_active' => true,
    ]);
    $user = User::factory()->create(['points_balance' => 0]);
    $service = new WalletService;

    $service->awardLaunchBonus($user);
    $second = $service->awardLaunchBonus($user);

    expect($second)->toBeNull()
        ->and($user->fresh()->points_balance)->toEqual(5000);
});

it('stops awarding the launch bonus once all slots are claimed', function () {
    $promo = LaunchPromotion::query()->updateOrCreate(['slug' => 'launch_first_100'], [
        'slots_total' => 2,
        'slots_claimed' => 2,
        'amount' => 5000,
        'is_active' => true,
    ]);
    $user = User::factory()->create(['points_balance' => 0]);
    $service = new WalletService;

    $transaction = $service->awardLaunchBonus($user);

    expect($transaction)->toBeNull()
        ->and($user->fresh()->points_balance)->toEqual(0)
        ->and($promo->fresh()->slots_claimed)->toBe(2);
});

it('does not award the launch bonus when the promotion is inactive', function () {
    LaunchPromotion::query()->updateOrCreate(['slug' => 'launch_first_100'], [
        'slots_total' => 100,
        'slots_claimed' => 0,
        'amount' => 5000,
        'is_active' => false,
    ]);
    $user = User::factory()->create(['points_balance' => 0]);
    $service = new WalletService;

    $transaction = $service->awardLaunchBonus($user);

    expect($transaction)->toBeNull()
        ->and($user->fresh()->points_balance)->toEqual(0);
});
