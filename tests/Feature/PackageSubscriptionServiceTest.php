<?php

use App\Enums\PackageRenewalCycle;
use App\Enums\SubscriptionStatus;
use App\Models\PointSubscription;
use App\Models\SubscriptionPackage;
use App\Models\User;
use App\Services\PackageSubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->package = SubscriptionPackage::create([
        'name' => 'Jara',
        'slug' => 'jara',
        'renewal_cycle' => PackageRenewalCycle::DAILY,
        'points_allocated' => 1500,
        'price_naira' => 100,
        'is_active' => true,
        'sort_order' => 1,
    ]);
});

test('subscribe creates a pending subscription and initializes the first charge', function () {
    Http::fake([
        'api.paystack.co/transaction/initialize' => Http::response([
            'status' => true,
            'data' => ['access_code' => 'access_abc', 'reference' => 'ref_pkg_sub'],
        ], 200),
    ]);

    $user = User::factory()->create();
    $service = app(PackageSubscriptionService::class);

    $result = $service->subscribe($user, $this->package);

    expect($result)->not->toBeNull()
        ->and($result['subscription']->status)->toBe(SubscriptionStatus::PENDING)
        ->and($result['subscription']->subscription_package_id)->toBe($this->package->id)
        ->and($result['subscription']->pending_reference)->toBe('ref_pkg_sub');
});

test('subscribe rejects a second active subscription to the same package', function () {
    $user = User::factory()->create();
    PointSubscription::create([
        'user_id' => $user->id,
        'subscription_package_id' => $this->package->id,
        'amount_naira' => 100,
        'status' => SubscriptionStatus::ACTIVE,
    ]);

    app(PackageSubscriptionService::class)->subscribe($user, $this->package);
})->throws(InvalidArgumentException::class);

test('subscribe rejects an inactive package', function () {
    $this->package->update(['is_active' => false]);
    $user = User::factory()->create();

    app(PackageSubscriptionService::class)->subscribe($user, $this->package);
})->throws(InvalidArgumentException::class);

test('chargeRenewal credits points and advances next_charge_at on success', function () {
    $user = User::factory()->create(['points_balance' => 0]);
    $subscription = PointSubscription::create([
        'user_id' => $user->id,
        'subscription_package_id' => $this->package->id,
        'amount_naira' => 100,
        'status' => SubscriptionStatus::ACTIVE,
        'authorization_code' => 'AUTH_jara',
        'next_charge_at' => now()->subMinute(),
    ]);

    Http::fake([
        'api.paystack.co/transaction/charge_authorization' => Http::response([
            'status' => true,
            'data' => [
                'status' => 'success',
                'reference' => 'renewal_ref',
                'authorization' => ['authorization_code' => 'AUTH_jara', 'last4' => '1111', 'brand' => 'visa'],
            ],
        ], 200),
    ]);

    app(PackageSubscriptionService::class)->chargeRenewal($subscription);

    $subscription->refresh();
    expect($user->fresh()->points_balance)->toEqual(1500)
        ->and($subscription->status)->toBe(SubscriptionStatus::ACTIVE)
        ->and($subscription->failure_count)->toBe(0)
        ->and($subscription->next_charge_at)->not->toBeNull()
        ->and($subscription->next_charge_at->isFuture())->toBeTrue();
});

test('chargeRenewal marks attention and increments failure_count on a declined charge', function () {
    $user = User::factory()->create(['points_balance' => 0]);
    $subscription = PointSubscription::create([
        'user_id' => $user->id,
        'subscription_package_id' => $this->package->id,
        'amount_naira' => 100,
        'status' => SubscriptionStatus::ACTIVE,
        'authorization_code' => 'AUTH_jara',
        'next_charge_at' => now()->subMinute(),
    ]);

    Http::fake([
        'api.paystack.co/transaction/charge_authorization' => Http::response([
            'status' => true,
            'data' => ['status' => 'failed', 'gateway_response' => 'Declined'],
        ], 200),
    ]);

    app(PackageSubscriptionService::class)->chargeRenewal($subscription);

    $subscription->refresh();
    expect($user->fresh()->points_balance)->toEqual(0)
        ->and($subscription->status)->toBe(SubscriptionStatus::ATTENTION)
        ->and($subscription->failure_count)->toBe(1);
});

test('chargeRenewal cancels the subscription once max failures is exhausted', function () {
    config(['points.packages.max_charge_failures' => 3]);

    $user = User::factory()->create(['points_balance' => 0]);
    $subscription = PointSubscription::create([
        'user_id' => $user->id,
        'subscription_package_id' => $this->package->id,
        'amount_naira' => 100,
        'status' => SubscriptionStatus::ATTENTION,
        'authorization_code' => 'AUTH_jara',
        'failure_count' => 2,
        'next_charge_at' => now()->subMinute(),
    ]);

    Http::fake([
        'api.paystack.co/transaction/charge_authorization' => Http::response([
            'status' => true,
            'data' => ['status' => 'failed'],
        ], 200),
    ]);

    app(PackageSubscriptionService::class)->chargeRenewal($subscription);

    $subscription->refresh();
    expect($subscription->status)->toBe(SubscriptionStatus::CANCELLED)
        ->and($subscription->failure_count)->toBe(3)
        ->and($subscription->next_charge_at)->toBeNull();
});
