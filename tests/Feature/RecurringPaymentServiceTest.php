<?php

use App\Enums\SubscriptionStatus;
use App\Models\PaystackPlan;
use App\Models\PointSubscription;
use App\Models\User;
use App\Notifications\RecurringChargeFailed;
use App\Services\RecurringPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = app(RecurringPaymentService::class);
    $this->plan = PaystackPlan::create([
        'amount_kobo' => 100000,
        'interval' => 'weekly',
        'plan_code' => 'PLN_test_weekly_1000',
        'name' => 'Bidora Weekly Top-up ₦1,000',
    ]);
});

test('subscribe creates a pending subscription and initializes the first charge', function () {
    Http::fake([
        'api.paystack.co/transaction/initialize' => Http::response([
            'status' => true,
            'data' => [
                'authorization_url' => 'https://checkout.paystack.com/url',
                'access_code' => 'access_abc',
                'reference' => 'ref_sub_init',
            ],
        ], 200),
    ]);

    $user = User::factory()->create();

    $result = $this->service->subscribe($user, $this->plan, 'https://bidora.test/callback');

    expect($result)->not->toBeNull();
    expect($result['init']['reference'])->toBe('ref_sub_init');

    $subscription = $result['subscription'];
    expect($subscription->status)->toBe(SubscriptionStatus::PENDING);
    expect($subscription->pending_reference)->toBe('ref_sub_init');
    expect($subscription->user_id)->toBe($user->id);
    expect($subscription->paystack_plan_id)->toBe($this->plan->id);

    Http::assertSent(function ($request) use ($subscription) {
        return $request->url() === 'https://api.paystack.co/transaction/initialize'
            && $request['plan'] === 'PLN_test_weekly_1000'
            && $request['metadata']['point_subscription_id'] === $subscription->id;
    });
});

test('subscribe refuses a second subscription to the same plan while one is already active', function () {
    $user = User::factory()->create();

    PointSubscription::create([
        'user_id' => $user->id,
        'paystack_plan_id' => $this->plan->id,
        'amount_naira' => 1000,
        'frequency' => 'weekly',
        'status' => SubscriptionStatus::ACTIVE,
    ]);

    expect(fn () => $this->service->subscribe($user, $this->plan))
        ->toThrow(InvalidArgumentException::class);
});

test('cancel disables an active subscription on paystack and marks it cancelled locally', function () {
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
        'subscription_code' => 'SUB_123',
        'email_token' => 'tok_abc',
    ]);

    $cancelled = $this->service->cancel($subscription);

    expect($cancelled)->toBeTrue();
    expect($subscription->fresh()->status)->toBe(SubscriptionStatus::CANCELLED);

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.paystack.co/subscription/disable'
            && $request['code'] === 'SUB_123'
            && $request['token'] === 'tok_abc';
    });
});

test('cancel is local-only when the subscription was never confirmed by paystack', function () {
    $user = User::factory()->create();
    $subscription = PointSubscription::create([
        'user_id' => $user->id,
        'paystack_plan_id' => $this->plan->id,
        'amount_naira' => 1000,
        'frequency' => 'weekly',
        'status' => SubscriptionStatus::PENDING,
    ]);

    $cancelled = $this->service->cancel($subscription);

    expect($cancelled)->toBeTrue();
    expect($subscription->fresh()->status)->toBe(SubscriptionStatus::CANCELLED);
    Http::assertNothingSent();
});

test('handleSubscriptionCreate confirms a pending subscription and stamps customer metadata', function () {
    Http::fake([
        'api.paystack.co/customer/*' => Http::response(['status' => true], 200),
    ]);

    $user = User::factory()->create();
    $subscription = PointSubscription::create([
        'user_id' => $user->id,
        'paystack_plan_id' => $this->plan->id,
        'amount_naira' => 1000,
        'frequency' => 'weekly',
        'status' => SubscriptionStatus::PENDING,
        'pending_reference' => 'ref_sub_init',
    ]);

    $this->service->handleSubscriptionCreate([
        'subscription_code' => 'SUB_999',
        'email_token' => 'tok_xyz',
        'next_payment_date' => '2026-09-21T00:00:00.000Z',
        'plan' => ['plan_code' => 'PLN_test_weekly_1000'],
        'customer' => ['customer_code' => 'CUS_1', 'email' => $user->email],
        'authorization' => ['authorization_code' => 'AUTH_1', 'last4' => '4081', 'brand' => 'visa'],
    ]);

    $subscription->refresh();

    expect($subscription->status)->toBe(SubscriptionStatus::ACTIVE);
    expect($subscription->subscription_code)->toBe('SUB_999');
    expect($subscription->email_token)->toBe('tok_xyz');
    expect($subscription->customer_code)->toBe('CUS_1');
    expect($subscription->authorization_code)->toBe('AUTH_1');
    expect($subscription->authorization_last4)->toBe('4081');

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.paystack.co/customer/CUS_1'
            && $request['metadata']['app'] === 'bidora';
    });
});

test('resolveSubscriptionForCharge resolves the first charge via metadata', function () {
    $user = User::factory()->create();
    $subscription = PointSubscription::create([
        'user_id' => $user->id,
        'paystack_plan_id' => $this->plan->id,
        'amount_naira' => 1000,
        'frequency' => 'weekly',
        'status' => SubscriptionStatus::PENDING,
    ]);

    $resolved = $this->service->resolveSubscriptionForCharge([
        'metadata' => ['point_subscription_id' => $subscription->id],
        'customer' => ['customer_code' => 'CUS_1'],
    ]);

    expect($resolved?->id)->toBe($subscription->id);
    expect($resolved->fresh()->customer_code)->toBe('CUS_1');
});

test('resolveSubscriptionForCharge resolves a later recurring charge via plan and customer', function () {
    $user = User::factory()->create();
    $subscription = PointSubscription::create([
        'user_id' => $user->id,
        'paystack_plan_id' => $this->plan->id,
        'amount_naira' => 1000,
        'frequency' => 'weekly',
        'status' => SubscriptionStatus::ACTIVE,
        'subscription_code' => 'SUB_1',
        'customer_code' => 'CUS_1',
    ]);

    // A Paystack-initiated recurring charge carries no metadata of ours.
    $resolved = $this->service->resolveSubscriptionForCharge([
        'plan' => ['plan_code' => 'PLN_test_weekly_1000'],
        'customer' => ['customer_code' => 'CUS_1', 'email' => $user->email],
    ]);

    expect($resolved?->id)->toBe($subscription->id);
    expect($resolved->fresh()->last_charged_at)->not->toBeNull();
});

test('handleInvoicePaymentFailed increments failure count and notifies the user', function () {
    Notification::fake();

    $user = User::factory()->create();
    $subscription = PointSubscription::create([
        'user_id' => $user->id,
        'paystack_plan_id' => $this->plan->id,
        'amount_naira' => 1000,
        'frequency' => 'weekly',
        'status' => SubscriptionStatus::ACTIVE,
        'subscription_code' => 'SUB_1',
    ]);

    $this->service->handleInvoicePaymentFailed(['subscription_code' => 'SUB_1']);

    $subscription->refresh();
    expect($subscription->status)->toBe(SubscriptionStatus::ATTENTION);
    expect($subscription->failure_count)->toBe(1);

    Notification::assertSentTo($user, RecurringChargeFailed::class);
});

test('handleSubscriptionDisable marks the subscription cancelled', function () {
    $user = User::factory()->create();
    $subscription = PointSubscription::create([
        'user_id' => $user->id,
        'paystack_plan_id' => $this->plan->id,
        'amount_naira' => 1000,
        'frequency' => 'weekly',
        'status' => SubscriptionStatus::ACTIVE,
        'subscription_code' => 'SUB_1',
    ]);

    $this->service->handleSubscriptionDisable(['subscription_code' => 'SUB_1']);

    expect($subscription->fresh()->status)->toBe(SubscriptionStatus::CANCELLED);
});

test('handleSubscriptionNotRenew marks the subscription non renewing', function () {
    $user = User::factory()->create();
    $subscription = PointSubscription::create([
        'user_id' => $user->id,
        'paystack_plan_id' => $this->plan->id,
        'amount_naira' => 1000,
        'frequency' => 'weekly',
        'status' => SubscriptionStatus::ACTIVE,
        'subscription_code' => 'SUB_1',
    ]);

    $this->service->handleSubscriptionNotRenew(['subscription_code' => 'SUB_1']);

    expect($subscription->fresh()->status)->toBe(SubscriptionStatus::NON_RENEWING);
});
