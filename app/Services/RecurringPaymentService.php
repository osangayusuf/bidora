<?php

namespace App\Services;

use App\Enums\SubscriptionStatus;
use App\Models\PaystackPlan;
use App\Models\PointSubscription;
use App\Models\User;
use App\Notifications\RecurringChargeFailed;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class RecurringPaymentService
{
    public function __construct(
        private readonly PaystackService $paystackService,
    ) {}

    /**
     * Start (or resume) a recurring subscription: creates a local pending
     * record and initializes the first charge against the plan. The
     * subscription is confirmed active once the `subscription.create`
     * webhook arrives.
     *
     * @return array{init: array<string, mixed>, subscription: PointSubscription}|null
     */
    public function subscribe(User $user, PaystackPlan $plan, ?string $callbackUrl = null): ?array
    {
        $alreadyActive = PointSubscription::query()
            ->where('user_id', $user->id)
            ->where('paystack_plan_id', $plan->id)
            ->whereIn('status', [SubscriptionStatus::PENDING, SubscriptionStatus::ACTIVE, SubscriptionStatus::ATTENTION])
            ->exists();

        if ($alreadyActive) {
            throw new InvalidArgumentException('You already have an active subscription at this amount and frequency.');
        }

        $subscription = PointSubscription::create([
            'user_id' => $user->id,
            'paystack_plan_id' => $plan->id,
            'amount_naira' => $plan->amountNaira(),
            'frequency' => $plan->interval,
            'status' => SubscriptionStatus::PENDING,
        ]);

        $init = $this->paystackService->initializeTransaction(
            $user,
            $plan->amountNaira(),
            $callbackUrl,
            $plan->plan_code,
            ['point_subscription_id' => $subscription->id, 'purpose' => 'recurring_subscription'],
        );

        if ($init === null) {
            $subscription->delete();

            return null;
        }

        $subscription->update(['pending_reference' => $init['reference']]);

        return ['init' => $init, 'subscription' => $subscription];
    }

    /**
     * Cancel a subscription. Takes effect immediately on Paystack's side —
     * no further charges, including any already-scheduled one, will fire.
     */
    public function cancel(PointSubscription $subscription): bool
    {
        if (! $subscription->isCancellable()) {
            return false;
        }

        if ($subscription->subscription_code === null || $subscription->email_token === null) {
            // Never confirmed by Paystack (e.g. the first charge failed before
            // subscription.create arrived) — safe to cancel locally only.
            $subscription->update(['status' => SubscriptionStatus::CANCELLED]);

            return true;
        }

        $disabled = $this->paystackService->disableSubscription(
            $subscription->subscription_code,
            $subscription->email_token,
        );

        if ($disabled) {
            $subscription->update(['status' => SubscriptionStatus::CANCELLED]);
        }

        return $disabled;
    }

    /**
     * Pull the current state from Paystack and reconcile it locally —
     * used when a webhook may have been missed.
     */
    public function resync(PointSubscription $subscription): PointSubscription
    {
        if ($subscription->subscription_code === null) {
            return $subscription;
        }

        $data = $this->paystackService->fetchSubscription($subscription->subscription_code);

        if ($data === null) {
            return $subscription;
        }

        $subscription->update([
            'status' => $this->mapPaystackStatus($data['status'] ?? null) ?? $subscription->status,
            'next_payment_date' => $data['next_payment_date'] ?? $subscription->next_payment_date,
        ]);

        return $subscription->fresh();
    }

    public function manageCardLink(PointSubscription $subscription): ?string
    {
        if ($subscription->subscription_code === null) {
            return null;
        }

        return $this->paystackService->subscriptionManageLink($subscription->subscription_code);
    }

    /**
     * Handle the `subscription.create` webhook event: confirms a pending
     * subscription with the details Paystack assigned to it.
     *
     * @param  array<string, mixed>  $data
     */
    public function handleSubscriptionCreate(array $data): void
    {
        $planCode = $data['plan']['plan_code'] ?? null;
        $email = $data['customer']['email'] ?? null;
        $subscriptionCode = $data['subscription_code'] ?? null;

        if ($planCode === null || $email === null || $subscriptionCode === null) {
            Log::warning('subscription.create webhook missing plan/customer/subscription_code.', ['data' => $data]);

            return;
        }

        $plan = PaystackPlan::where('plan_code', $planCode)->first();
        $user = User::where('email', $email)->first();

        if ($plan === null || $user === null) {
            Log::warning('subscription.create webhook could not resolve plan or user.', [
                'plan_code' => $planCode,
                'email' => $email,
            ]);

            return;
        }

        $subscription = PointSubscription::query()
            ->where('user_id', $user->id)
            ->where('paystack_plan_id', $plan->id)
            ->whereIn('status', [SubscriptionStatus::PENDING, SubscriptionStatus::ACTIVE])
            ->latest('id')
            ->first();

        if ($subscription === null) {
            // No local row found (e.g. this event outraced its creation, or
            // was created directly on Paystack) — create a best-effort record.
            $subscription = PointSubscription::create([
                'user_id' => $user->id,
                'paystack_plan_id' => $plan->id,
                'amount_naira' => $plan->amountNaira(),
                'frequency' => $plan->interval,
                'status' => SubscriptionStatus::PENDING,
            ]);
        }

        $customerCode = $data['customer']['customer_code'] ?? $subscription->customer_code;

        $subscription->update([
            'subscription_code' => $subscriptionCode,
            'email_token' => $data['email_token'] ?? $subscription->email_token,
            'customer_code' => $customerCode,
            'authorization_code' => $data['authorization']['authorization_code'] ?? $subscription->authorization_code,
            'authorization_last4' => $data['authorization']['last4'] ?? $subscription->authorization_last4,
            'authorization_brand' => $data['authorization']['brand'] ?? $subscription->authorization_brand,
            'next_payment_date' => $data['next_payment_date'] ?? $subscription->next_payment_date,
            'status' => SubscriptionStatus::ACTIVE,
        ]);

        // Stamp the app tag on the Customer itself. Paystack-initiated recurring
        // charges don't reliably inherit metadata from the original initializing
        // transaction, and this account is shared with Fanscorner — the shared
        // webhook endpoint relies on metadata.app to route events correctly.
        if ($customerCode !== null) {
            $this->paystackService->updateCustomerMetadata($customerCode, ['app' => 'bidora']);
        }
    }

    /**
     * Resolve which subscription a `charge.success` event belongs to, so the
     * caller can credit points against it. Handles both the very first
     * charge (correlated via metadata we set ourselves) and later
     * Paystack-initiated recurring charges (correlated via plan + customer).
     *
     * @param  array<string, mixed>  $chargeData  The webhook's `data` payload.
     */
    public function resolveSubscriptionForCharge(array $chargeData): ?PointSubscription
    {
        $subscriptionId = $chargeData['metadata']['point_subscription_id'] ?? null;

        if ($subscriptionId !== null) {
            $subscription = PointSubscription::find($subscriptionId);

            if ($subscription !== null) {
                $this->markCharged($subscription, $chargeData);

                return $subscription;
            }
        }

        $planCode = is_array($chargeData['plan'] ?? null)
            ? ($chargeData['plan']['plan_code'] ?? null)
            : ($chargeData['plan'] ?? null);
        $customerCode = $chargeData['customer']['customer_code'] ?? null;

        if ($planCode === null) {
            return null;
        }

        $plan = PaystackPlan::where('plan_code', $planCode)->first();

        if ($plan === null) {
            return null;
        }

        $query = PointSubscription::query()->where('paystack_plan_id', $plan->id);

        $subscription = $customerCode !== null
            ? (clone $query)->where('customer_code', $customerCode)->latest('id')->first()
            : null;

        if ($subscription === null) {
            $email = $chargeData['customer']['email'] ?? null;
            $user = $email !== null ? User::where('email', $email)->first() : null;

            if ($user !== null) {
                $subscription = (clone $query)->where('user_id', $user->id)->latest('id')->first();
            }
        }

        if ($subscription !== null) {
            $this->markCharged($subscription, $chargeData);
        }

        return $subscription;
    }

    private function markCharged(PointSubscription $subscription, array $chargeData): void
    {
        $subscription->update([
            'customer_code' => $chargeData['customer']['customer_code'] ?? $subscription->customer_code,
            'authorization_code' => $chargeData['authorization']['authorization_code'] ?? $subscription->authorization_code,
            'authorization_last4' => $chargeData['authorization']['last4'] ?? $subscription->authorization_last4,
            'authorization_brand' => $chargeData['authorization']['brand'] ?? $subscription->authorization_brand,
            'last_charged_at' => now(),
            'failure_count' => 0,
            'status' => $subscription->status === SubscriptionStatus::ATTENTION
                ? SubscriptionStatus::ACTIVE
                : $subscription->status,
        ]);
    }

    /**
     * Handle `invoice.payment_failed`: a scheduled recurring charge failed.
     *
     * @param  array<string, mixed>  $data
     */
    public function handleInvoicePaymentFailed(array $data): void
    {
        $subscriptionCode = $data['subscription']['subscription_code'] ?? $data['subscription_code'] ?? null;

        $subscription = $subscriptionCode !== null
            ? PointSubscription::where('subscription_code', $subscriptionCode)->first()
            : null;

        if ($subscription === null) {
            Log::warning('invoice.payment_failed webhook could not resolve a subscription.', ['data' => $data]);

            return;
        }

        $subscription->increment('failure_count');
        $subscription->update(['status' => SubscriptionStatus::ATTENTION]);

        $subscription->user?->notify(new RecurringChargeFailed($subscription));
    }

    /**
     * Handle `subscription.not_renew`.
     *
     * @param  array<string, mixed>  $data
     */
    public function handleSubscriptionNotRenew(array $data): void
    {
        $this->updateStatusByCode($data, SubscriptionStatus::NON_RENEWING);
    }

    /**
     * Handle `subscription.disable`.
     *
     * @param  array<string, mixed>  $data
     */
    public function handleSubscriptionDisable(array $data): void
    {
        $this->updateStatusByCode($data, SubscriptionStatus::CANCELLED);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function updateStatusByCode(array $data, SubscriptionStatus $status): void
    {
        $subscriptionCode = $data['subscription_code'] ?? null;

        if ($subscriptionCode === null) {
            return;
        }

        PointSubscription::where('subscription_code', $subscriptionCode)
            ->where('status', '!=', SubscriptionStatus::CANCELLED)
            ->update(['status' => $status]);
    }

    private function mapPaystackStatus(?string $paystackStatus): ?SubscriptionStatus
    {
        return match ($paystackStatus) {
            'active' => SubscriptionStatus::ACTIVE,
            'non-renewing' => SubscriptionStatus::NON_RENEWING,
            'attention' => SubscriptionStatus::ATTENTION,
            'completed' => SubscriptionStatus::COMPLETED,
            'cancelled' => SubscriptionStatus::CANCELLED,
            default => null,
        };
    }
}
