<?php

use App\Enums\PackageRenewalCycle;
use App\Enums\SubscriptionFrequency;
use App\Enums\SubscriptionStatus;
use App\Models\PaystackPlan;
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

test('subscribe on a recurring package creates a package plan and a card-only plan charge', function () {
    Http::fake([
        'api.paystack.co/plan' => Http::response(['status' => true, 'data' => ['plan_code' => 'PLN_jara']], 200),
        'api.paystack.co/transaction/initialize' => Http::response([
            'status' => true,
            'data' => ['access_code' => 'access_abc', 'reference' => 'ref_pkg_sub'],
        ], 200),
    ]);

    $user = User::factory()->create();

    $result = app(PackageSubscriptionService::class)->subscribe($user, $this->package);

    $plan = PaystackPlan::where('subscription_package_id', $this->package->id)->first();

    expect($result)->not->toBeNull()
        ->and($plan->plan_code)->toBe('PLN_jara')
        ->and($plan->interval)->toBe(SubscriptionFrequency::DAILY)
        ->and($plan->amount_kobo)->toBe(10000)
        ->and($result['subscription']->status)->toBe(SubscriptionStatus::PENDING)
        ->and($result['subscription']->paystack_plan_id)->toBe($plan->id)
        ->and($result['subscription']->frequency)->toBe(SubscriptionFrequency::DAILY)
        ->and($result['subscription']->pending_reference)->toBe('ref_pkg_sub');

    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/transaction/initialize')
        && $request['plan'] === 'PLN_jara'
        && $request['channels'] === ['card']);
});

test('subscribe reuses the package plan while its price is unchanged and makes a new one when it changes', function () {
    Http::fake([
        'api.paystack.co/plan' => Http::sequence()
            ->push(['status' => true, 'data' => ['plan_code' => 'PLN_one']], 200)
            ->push(['status' => true, 'data' => ['plan_code' => 'PLN_two']], 200),
        'api.paystack.co/transaction/initialize' => Http::sequence()
            ->push(['status' => true, 'data' => ['access_code' => 'a', 'reference' => 'r1']], 200)
            ->push(['status' => true, 'data' => ['access_code' => 'a', 'reference' => 'r2']], 200)
            ->push(['status' => true, 'data' => ['access_code' => 'a', 'reference' => 'r3']], 200),
    ]);

    $service = app(PackageSubscriptionService::class);
    $service->subscribe(User::factory()->create(), $this->package);
    $service->subscribe(User::factory()->create(), $this->package);

    expect(PaystackPlan::count())->toBe(1);

    $this->package->update(['price_naira' => 150]);
    $service->subscribe(User::factory()->create(), $this->package->fresh());

    expect(PaystackPlan::pluck('plan_code')->all())->toBe(['PLN_one', 'PLN_two']);
});

test('subscribe on a one-off package is a plain charge with no plan and no channel restriction', function () {
    Http::fake([
        'api.paystack.co/transaction/initialize' => Http::response([
            'status' => true,
            'data' => ['access_code' => 'a', 'reference' => 'ref_solo'],
        ], 200),
    ]);

    $solo = SubscriptionPackage::create([
        'name' => 'Solo', 'slug' => 'solo', 'renewal_cycle' => PackageRenewalCycle::ONE_OFF,
        'points_allocated' => 5000, 'price_naira' => 500, 'is_active' => true, 'sort_order' => 0,
    ]);

    $result = app(PackageSubscriptionService::class)->subscribe(User::factory()->create(), $solo);

    expect($result['subscription']->paystack_plan_id)->toBeNull();

    Http::assertSent(fn ($request) => ! isset($request['plan']) && ! isset($request['channels']));
    expect(PaystackPlan::count())->toBe(0);
});

test('subscribe rejects the legacy bi-weekly package', function () {
    $this->package->update(['renewal_cycle' => PackageRenewalCycle::BI_WEEKLY]);

    app(PackageSubscriptionService::class)->subscribe(User::factory()->create(), $this->package->fresh());
})->throws(InvalidArgumentException::class, 'not currently available');

test('subscribe rejects a second active subscription to the same recurring package', function () {
    $user = User::factory()->create();
    PointSubscription::create([
        'user_id' => $user->id,
        'subscription_package_id' => $this->package->id,
        'amount_naira' => 100,
        'status' => SubscriptionStatus::ACTIVE,
    ]);

    app(PackageSubscriptionService::class)->subscribe($user, $this->package);
})->throws(InvalidArgumentException::class);

test('subscribe allows repeat purchases of a one-off package even with an active purchase already', function () {
    Http::fake([
        'api.paystack.co/transaction/initialize' => Http::response([
            'status' => true,
            'data' => ['access_code' => 'a', 'reference' => 'ref_solo_2'],
        ], 200),
    ]);

    $solo = SubscriptionPackage::create([
        'name' => 'Solo', 'slug' => 'solo', 'renewal_cycle' => PackageRenewalCycle::ONE_OFF,
        'points_allocated' => 5000, 'price_naira' => 500, 'is_active' => true, 'sort_order' => 0,
    ]);

    $user = User::factory()->create();
    PointSubscription::create([
        'user_id' => $user->id,
        'subscription_package_id' => $solo->id,
        'amount_naira' => 500,
        'status' => SubscriptionStatus::ACTIVE,
    ]);

    $result = app(PackageSubscriptionService::class)->subscribe($user, $solo);

    expect($result)->not->toBeNull()
        ->and(PointSubscription::where('subscription_package_id', $solo->id)->count())->toBe(2);
});

test('subscribe rejects an inactive package', function () {
    $this->package->update(['is_active' => false]);
    $user = User::factory()->create();

    app(PackageSubscriptionService::class)->subscribe($user, $this->package);
})->throws(InvalidArgumentException::class);
