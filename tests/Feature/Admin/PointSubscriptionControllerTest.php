<?php

use App\Enums\SubscriptionStatus;
use App\Models\PaystackPlan;
use App\Models\PointSubscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->plan = PaystackPlan::create([
        'amount_kobo' => 100000,
        'interval' => 'weekly',
        'plan_code' => 'PLN_test_weekly_1000',
        'name' => 'Bidora Weekly Top-up ₦1,000',
    ]);
});

test('non-admin users cannot access the subscriptions admin page', function () {
    $user = User::factory()->create();
    $user->assignRole('user');

    // The app's UnauthorizedException handler redirects non-admins home
    // rather than returning a 403 (see bootstrap/app.php).
    $this->actingAs($user)
        ->get(route('admin.point-subscriptions.index'))
        ->assertRedirect(route('home'));
});

test('admin can view the subscriptions list', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $user = User::factory()->create();
    PointSubscription::create([
        'user_id' => $user->id,
        'paystack_plan_id' => $this->plan->id,
        'amount_naira' => 1000,
        'frequency' => 'weekly',
        'status' => SubscriptionStatus::ACTIVE,
        'subscription_code' => 'SUB_1',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.point-subscriptions.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/PointSubscriptions/Index')
            ->has('subscriptions.data', 1)
        );
});

test('admin can cancel a subscription', function () {
    Http::fake([
        'api.paystack.co/subscription/disable' => Http::response(['status' => true], 200),
    ]);

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $user = User::factory()->create();
    $subscription = PointSubscription::create([
        'user_id' => $user->id,
        'paystack_plan_id' => $this->plan->id,
        'amount_naira' => 1000,
        'frequency' => 'weekly',
        'status' => SubscriptionStatus::ACTIVE,
        'subscription_code' => 'SUB_1',
        'email_token' => 'tok_1',
    ]);

    $this->actingAs($admin)
        ->post(route('admin.point-subscriptions.cancel', $subscription))
        ->assertRedirect();

    expect($subscription->fresh()->status)->toBe(SubscriptionStatus::CANCELLED);
});

test('admin can resync a confirmed subscription from paystack', function () {
    Http::fake([
        'api.paystack.co/subscription/SUB_1' => Http::response([
            'status' => true,
            'data' => [
                'status' => 'non-renewing',
                'next_payment_date' => '2026-10-01T00:00:00.000Z',
            ],
        ], 200),
    ]);

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $user = User::factory()->create();
    $subscription = PointSubscription::create([
        'user_id' => $user->id,
        'paystack_plan_id' => $this->plan->id,
        'amount_naira' => 1000,
        'frequency' => 'weekly',
        'status' => SubscriptionStatus::ACTIVE,
        'subscription_code' => 'SUB_1',
    ]);

    $this->actingAs($admin)
        ->post(route('admin.point-subscriptions.resync', $subscription))
        ->assertRedirect();

    expect($subscription->fresh()->status)->toBe(SubscriptionStatus::NON_RENEWING);
});
