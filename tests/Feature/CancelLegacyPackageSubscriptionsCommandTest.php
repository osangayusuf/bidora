<?php

use App\Enums\PackageRenewalCycle;
use App\Enums\SubscriptionStatus;
use App\Models\PaystackPlan;
use App\Models\PointSubscription;
use App\Models\SubscriptionPackage;
use App\Models\User;
use App\Notifications\AutoTopUpEnded;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

function legacyPackage(string $slug, PackageRenewalCycle $cycle): SubscriptionPackage
{
    return SubscriptionPackage::create([
        'name' => ucfirst($slug), 'slug' => $slug, 'renewal_cycle' => $cycle,
        'points_allocated' => 1000, 'price_naira' => 100, 'is_active' => true, 'sort_order' => 1,
    ]);
}

test('it cancels legacy recurring package subscriptions and notifies paying users only', function () {
    Notification::fake();

    $daily = legacyPackage('jara', PackageRenewalCycle::DAILY);
    $active = User::factory()->create();
    $pending = User::factory()->create();

    $activeSub = PointSubscription::create(['user_id' => $active->id, 'subscription_package_id' => $daily->id, 'amount_naira' => 100, 'status' => SubscriptionStatus::ACTIVE, 'next_charge_at' => now()]);
    $pendingSub = PointSubscription::create(['user_id' => $pending->id, 'subscription_package_id' => $daily->id, 'amount_naira' => 100, 'status' => SubscriptionStatus::PENDING]);

    $this->artisan('packages:cancel-legacy')->assertSuccessful();

    expect($activeSub->fresh()->status)->toBe(SubscriptionStatus::CANCELLED)
        ->and($activeSub->fresh()->next_charge_at)->toBeNull()
        ->and($pendingSub->fresh()->status)->toBe(SubscriptionStatus::CANCELLED);

    Notification::assertSentTo($active, AutoTopUpEnded::class);
    Notification::assertNotSentTo($pending, AutoTopUpEnded::class);
});

test('it leaves plan-backed, one-off and already-cancelled subscriptions alone', function () {
    Notification::fake();

    $daily = legacyPackage('jara', PackageRenewalCycle::DAILY);
    $solo = legacyPackage('solo', PackageRenewalCycle::ONE_OFF);
    $plan = PaystackPlan::create(['subscription_package_id' => $daily->id, 'amount_kobo' => 10000, 'interval' => 'daily', 'plan_code' => 'PLN_x', 'name' => 'x']);
    $user = User::factory()->create();

    $planned = PointSubscription::create(['user_id' => $user->id, 'subscription_package_id' => $daily->id, 'paystack_plan_id' => $plan->id, 'amount_naira' => 100, 'status' => SubscriptionStatus::ACTIVE]);
    $oneOff = PointSubscription::create(['user_id' => $user->id, 'subscription_package_id' => $solo->id, 'amount_naira' => 100, 'status' => SubscriptionStatus::ACTIVE]);

    $this->artisan('packages:cancel-legacy')->assertSuccessful();

    expect($planned->fresh()->status)->toBe(SubscriptionStatus::ACTIVE)
        ->and($oneOff->fresh()->status)->toBe(SubscriptionStatus::ACTIVE);
    Notification::assertNothingSent();
});

test('dry run changes nothing', function () {
    Notification::fake();

    $daily = legacyPackage('jara', PackageRenewalCycle::DAILY);
    $sub = PointSubscription::create(['user_id' => User::factory()->create()->id, 'subscription_package_id' => $daily->id, 'amount_naira' => 100, 'status' => SubscriptionStatus::ACTIVE]);

    $this->artisan('packages:cancel-legacy --dry-run')->assertSuccessful();

    expect($sub->fresh()->status)->toBe(SubscriptionStatus::ACTIVE);
    Notification::assertNothingSent();
});
