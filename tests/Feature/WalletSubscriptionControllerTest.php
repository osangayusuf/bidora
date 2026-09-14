<?php

use App\Enums\SubscriptionStatus;
use App\Models\PaystackPlan;
use App\Models\PointSubscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->plan = PaystackPlan::create([
        'amount_kobo' => 100000,
        'interval' => 'weekly',
        'plan_code' => 'PLN_test_weekly_1000',
        'name' => 'Bidora Weekly Top-up ₦1,000',
    ]);
});

test('store initializes the first charge and flashes inline payment data', function () {
    Http::fake([
        'api.paystack.co/transaction/initialize' => Http::response([
            'status' => true,
            'data' => [
                'access_code' => 'access_abc',
                'reference' => 'ref_sub_store',
            ],
        ], 200),
    ]);

    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->from(route('wallet'))
        ->post(route('wallet.subscriptions.store'), ['paystack_plan_id' => $this->plan->id]);

    $response->assertRedirect(route('wallet'))
        ->assertInertiaFlash('paystack_init.reference', 'ref_sub_store')
        ->assertInertiaFlash('paystack_init.access_code', 'access_abc');

    $this->assertDatabaseHas('point_subscriptions', [
        'user_id' => $user->id,
        'paystack_plan_id' => $this->plan->id,
        'status' => SubscriptionStatus::PENDING->value,
        'pending_reference' => 'ref_sub_store',
    ]);
});

test('store rejects an unknown plan id', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('wallet.subscriptions.store'), ['paystack_plan_id' => 999999])
        ->assertSessionHasErrors('paystack_plan_id');
});

test('destroy cancels the owning users subscription', function () {
    Http::fake([
        'api.paystack.co/subscription/disable' => Http::response(['status' => true], 200),
    ]);

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

    $this->actingAs($user)
        ->delete(route('wallet.subscriptions.destroy', $subscription))
        ->assertRedirect();

    expect($subscription->fresh()->status)->toBe(SubscriptionStatus::CANCELLED);
});

test('destroy forbids cancelling another users subscription', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();

    $subscription = PointSubscription::create([
        'user_id' => $owner->id,
        'paystack_plan_id' => $this->plan->id,
        'amount_naira' => 1000,
        'frequency' => 'weekly',
        'status' => SubscriptionStatus::ACTIVE,
        'subscription_code' => 'SUB_1',
        'email_token' => 'tok_1',
    ]);

    $this->actingAs($intruder)
        ->delete(route('wallet.subscriptions.destroy', $subscription))
        ->assertForbidden();

    expect($subscription->fresh()->status)->toBe(SubscriptionStatus::ACTIVE);
});

test('manage card redirects to the paystack hosted link for the owner', function () {
    Http::fake([
        'api.paystack.co/subscription/*/manage/link' => Http::response([
            'status' => true,
            'data' => ['link' => 'https://paystack.com/manage/xyz'],
        ], 200),
    ]);

    $user = User::factory()->create();
    $subscription = PointSubscription::create([
        'user_id' => $user->id,
        'paystack_plan_id' => $this->plan->id,
        'amount_naira' => 1000,
        'frequency' => 'weekly',
        'status' => SubscriptionStatus::ACTIVE,
        'subscription_code' => 'SUB_1',
    ]);

    $this->actingAs($user)
        ->get(route('wallet.subscriptions.manage-card', $subscription))
        ->assertRedirect('https://paystack.com/manage/xyz');
});

test('manage card forbids access for another user', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();

    $subscription = PointSubscription::create([
        'user_id' => $owner->id,
        'paystack_plan_id' => $this->plan->id,
        'amount_naira' => 1000,
        'frequency' => 'weekly',
        'status' => SubscriptionStatus::ACTIVE,
        'subscription_code' => 'SUB_1',
    ]);

    $this->actingAs($intruder)
        ->get(route('wallet.subscriptions.manage-card', $subscription))
        ->assertForbidden();
});
