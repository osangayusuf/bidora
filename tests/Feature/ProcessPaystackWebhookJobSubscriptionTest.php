<?php

use App\Enums\SubscriptionStatus;
use App\Jobs\ProcessPaystackWebhookJob;
use App\Models\PaystackPlan;
use App\Models\PaystackWebhookLog;
use App\Models\PointSubscription;
use App\Models\PointTransaction;
use App\Models\User;
use App\Services\PaystackService;
use App\Services\RecurringPaymentService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function handleWebhookLog(PaystackWebhookLog $log): void
{
    (new ProcessPaystackWebhookJob($log->id))->handle(
        new WalletService,
        new RecurringPaymentService(new PaystackService),
    );
}

beforeEach(function () {
    $this->plan = PaystackPlan::create([
        'amount_kobo' => 100000,
        'interval' => 'weekly',
        'plan_code' => 'PLN_test_weekly_1000',
        'name' => 'Bidora Weekly Top-up ₦1,000',
    ]);
});

test('subscription.create confirms the pending subscription', function () {
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

    $log = PaystackWebhookLog::create([
        'event' => 'subscription.create',
        'status' => 'pending',
        'payload' => [
            'data' => [
                'subscription_code' => 'SUB_1',
                'email_token' => 'tok_1',
                'next_payment_date' => '2026-09-21T00:00:00.000Z',
                'plan' => ['plan_code' => 'PLN_test_weekly_1000'],
                'customer' => ['customer_code' => 'CUS_1', 'email' => $user->email],
                'authorization' => ['authorization_code' => 'AUTH_1', 'last4' => '4081', 'brand' => 'visa'],
            ],
        ],
    ]);

    handleWebhookLog($log);

    expect($log->fresh()->status)->toBe('processed');
    expect($subscription->fresh()->status)->toBe(SubscriptionStatus::ACTIVE);
    expect($subscription->fresh()->subscription_code)->toBe('SUB_1');
});

test('a recurring charge.success credits points and links the subscription without a PaystackTransaction', function () {
    $user = User::factory()->create(['points_balance' => 0]);
    config(['points.points_per_naira' => 10]);

    $subscription = PointSubscription::create([
        'user_id' => $user->id,
        'paystack_plan_id' => $this->plan->id,
        'amount_naira' => 1000,
        'frequency' => 'weekly',
        'status' => SubscriptionStatus::ACTIVE,
        'subscription_code' => 'SUB_1',
        'customer_code' => 'CUS_1',
    ]);

    $log = PaystackWebhookLog::create([
        'event' => 'charge.success',
        'reference' => 'ref_recurring_1',
        'status' => 'pending',
        'payload' => [
            'data' => [
                'reference' => 'ref_recurring_1',
                'amount' => 100000,
                'plan' => ['plan_code' => 'PLN_test_weekly_1000'],
                'customer' => ['customer_code' => 'CUS_1', 'email' => $user->email],
            ],
        ],
    ]);

    handleWebhookLog($log);

    expect($user->fresh()->points_balance)->toEqual(10000);

    $transaction = PointTransaction::where('provider_reference', 'ref_recurring_1')->first();
    expect($transaction)->not->toBeNull();
    expect($transaction->point_subscription_id)->toBe($subscription->id);

    expect($subscription->fresh()->last_charged_at)->not->toBeNull();
});

test('invoice.payment_failed marks the subscription for attention', function () {
    $user = User::factory()->create();
    $subscription = PointSubscription::create([
        'user_id' => $user->id,
        'paystack_plan_id' => $this->plan->id,
        'amount_naira' => 1000,
        'frequency' => 'weekly',
        'status' => SubscriptionStatus::ACTIVE,
        'subscription_code' => 'SUB_1',
    ]);

    $log = PaystackWebhookLog::create([
        'event' => 'invoice.payment_failed',
        'status' => 'pending',
        'payload' => [
            'data' => ['subscription_code' => 'SUB_1'],
        ],
    ]);

    handleWebhookLog($log);

    expect($subscription->fresh()->status)->toBe(SubscriptionStatus::ATTENTION);
    expect($subscription->fresh()->failure_count)->toBe(1);
});

test('subscription.disable cancels the subscription', function () {
    $user = User::factory()->create();
    $subscription = PointSubscription::create([
        'user_id' => $user->id,
        'paystack_plan_id' => $this->plan->id,
        'amount_naira' => 1000,
        'frequency' => 'weekly',
        'status' => SubscriptionStatus::ACTIVE,
        'subscription_code' => 'SUB_1',
    ]);

    $log = PaystackWebhookLog::create([
        'event' => 'subscription.disable',
        'status' => 'pending',
        'payload' => [
            'data' => ['subscription_code' => 'SUB_1'],
        ],
    ]);

    handleWebhookLog($log);

    expect($subscription->fresh()->status)->toBe(SubscriptionStatus::CANCELLED);
});
