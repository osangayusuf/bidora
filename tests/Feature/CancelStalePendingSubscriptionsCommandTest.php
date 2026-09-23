<?php

use App\Enums\PackageRenewalCycle;
use App\Enums\SubscriptionStatus;
use App\Models\PointSubscription;
use App\Models\SubscriptionPackage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('it cancels pending subscriptions abandoned more than 30 minutes ago', function () {
    $package = SubscriptionPackage::create([
        'name' => 'Jara', 'slug' => 'jara', 'renewal_cycle' => PackageRenewalCycle::DAILY,
        'points_allocated' => 1500, 'price_naira' => 100, 'is_active' => true, 'sort_order' => 1,
    ]);

    $stale = PointSubscription::create([
        'user_id' => User::factory()->create()->id,
        'subscription_package_id' => $package->id,
        'amount_naira' => 100,
        'status' => SubscriptionStatus::PENDING,
    ]);
    $stale->forceFill(['created_at' => now()->subMinutes(45)])->save();

    $this->artisan('subscriptions:cancel-stale-pending')->assertSuccessful();

    expect($stale->fresh()->status)->toBe(SubscriptionStatus::CANCELLED);
});

test('it leaves recent pending subscriptions and other statuses alone', function () {
    $package = SubscriptionPackage::create([
        'name' => 'Jara', 'slug' => 'jara', 'renewal_cycle' => PackageRenewalCycle::DAILY,
        'points_allocated' => 1500, 'price_naira' => 100, 'is_active' => true, 'sort_order' => 1,
    ]);

    $recentPending = PointSubscription::create([
        'user_id' => User::factory()->create()->id,
        'subscription_package_id' => $package->id,
        'amount_naira' => 100,
        'status' => SubscriptionStatus::PENDING,
    ]);

    $active = PointSubscription::create([
        'user_id' => User::factory()->create()->id,
        'subscription_package_id' => $package->id,
        'amount_naira' => 100,
        'status' => SubscriptionStatus::ACTIVE,
    ]);
    $active->forceFill(['created_at' => now()->subMinutes(45)])->save();

    $this->artisan('subscriptions:cancel-stale-pending')->assertSuccessful();

    expect($recentPending->fresh()->status)->toBe(SubscriptionStatus::PENDING)
        ->and($active->fresh()->status)->toBe(SubscriptionStatus::ACTIVE);
});

test('dry run changes nothing', function () {
    $package = SubscriptionPackage::create([
        'name' => 'Jara', 'slug' => 'jara', 'renewal_cycle' => PackageRenewalCycle::DAILY,
        'points_allocated' => 1500, 'price_naira' => 100, 'is_active' => true, 'sort_order' => 1,
    ]);

    $stale = PointSubscription::create([
        'user_id' => User::factory()->create()->id,
        'subscription_package_id' => $package->id,
        'amount_naira' => 100,
        'status' => SubscriptionStatus::PENDING,
    ]);
    $stale->forceFill(['created_at' => now()->subMinutes(45)])->save();

    $this->artisan('subscriptions:cancel-stale-pending --dry-run')->assertSuccessful();

    expect($stale->fresh()->status)->toBe(SubscriptionStatus::PENDING);
});
