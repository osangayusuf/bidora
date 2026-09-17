<?php

namespace App\Services;

use App\Enums\SubscriptionStatus;
use App\Models\PointSubscription;
use App\Models\SubscriptionPackage;
use App\Models\User;
use App\Notifications\RecurringChargeFailed;
use InvalidArgumentException;

class PackageSubscriptionService
{
    public function __construct(
        private readonly PaystackService $paystackService,
        private readonly WalletService $walletService,
    ) {}

    /**
     * Start a package subscription: creates a local pending record and
     * initializes the first charge. Unlike a Paystack-Plan subscription,
     * this never creates a native Paystack Subscription object — the
     * resulting card authorization is later re-charged directly by
     * ChargeDuePackageSubscriptionsCommand on the package's renewal cycle.
     *
     * @return array{init: array<string, mixed>, subscription: PointSubscription}|null
     */
    public function subscribe(User $user, SubscriptionPackage $package, ?string $callbackUrl = null): ?array
    {
        if (! $package->is_active) {
            throw new InvalidArgumentException('This package is not currently available.');
        }

        $alreadyActive = PointSubscription::query()
            ->where('user_id', $user->id)
            ->where('subscription_package_id', $package->id)
            ->whereIn('status', [SubscriptionStatus::PENDING, SubscriptionStatus::ACTIVE, SubscriptionStatus::ATTENTION])
            ->exists();

        if ($alreadyActive) {
            throw new InvalidArgumentException('You already have an active subscription to this package.');
        }

        $subscription = PointSubscription::create([
            'user_id' => $user->id,
            'subscription_package_id' => $package->id,
            'amount_naira' => $package->price_naira,
            'status' => SubscriptionStatus::PENDING,
        ]);

        $init = $this->paystackService->initializeTransaction(
            $user,
            (float) $package->price_naira,
            $callbackUrl,
            null,
            ['point_subscription_id' => $subscription->id, 'purpose' => 'package_subscription'],
        );

        if ($init === null) {
            $subscription->delete();

            return null;
        }

        $subscription->update(['pending_reference' => $init['reference']]);

        return ['init' => $init, 'subscription' => $subscription];
    }

    /**
     * Charge the saved card authorization for a due package renewal.
     */
    public function chargeRenewal(PointSubscription $subscription): void
    {
        $package = $subscription->subscriptionPackage;

        if ($package === null || $subscription->authorization_code === null) {
            return;
        }

        $reference = sprintf('pkg_renewal_%d_%s', $subscription->id, now()->format('YmdHisv'));

        $result = $this->paystackService->chargeAuthorization(
            $subscription->authorization_code,
            $subscription->user->email,
            $package->priceKobo(),
            ['point_subscription_id' => $subscription->id, 'purpose' => 'package_subscription_renewal'],
            $reference,
        );

        if ($result !== null && ($result['status'] ?? null) === 'success') {
            $this->walletService->processDeposit(
                $subscription->user,
                (float) $package->price_naira,
                $result['reference'] ?? $reference,
                $result,
                null,
                $subscription->id,
            );

            $subscription->update([
                'authorization_code' => $result['authorization']['authorization_code'] ?? $subscription->authorization_code,
                'authorization_last4' => $result['authorization']['last4'] ?? $subscription->authorization_last4,
                'authorization_brand' => $result['authorization']['brand'] ?? $subscription->authorization_brand,
                'failure_count' => 0,
                'last_charged_at' => now(),
            ]);

            return;
        }

        $subscription->increment('failure_count');
        $maxFailures = (int) config('points.packages.max_charge_failures', 3);
        $hasExhaustedRetries = $subscription->failure_count >= $maxFailures;

        $subscription->update([
            'status' => $hasExhaustedRetries ? SubscriptionStatus::CANCELLED : SubscriptionStatus::ATTENTION,
            'next_charge_at' => $hasExhaustedRetries ? null : now()->addDay(),
        ]);

        $subscription->user?->notify(new RecurringChargeFailed($subscription));
    }
}
