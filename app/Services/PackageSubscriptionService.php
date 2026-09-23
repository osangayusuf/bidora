<?php

namespace App\Services;

use App\Enums\SubscriptionFrequency;
use App\Enums\SubscriptionStatus;
use App\Models\PaystackPlan;
use App\Models\PointSubscription;
use App\Models\SubscriptionPackage;
use App\Models\User;
use InvalidArgumentException;

class PackageSubscriptionService
{
    public function __construct(
        private readonly PaystackService $paystackService,
    ) {}

    /**
     * Start a package purchase: creates a local pending record and
     * initializes the first charge.
     *
     * A recurring package runs on a native Paystack Plan (one per package):
     * the first charge is card-only, Paystack creates the Subscription and
     * bills every renewal itself, and `subscription.create` confirms it. A
     * one-off package is a plain charge open to every payment channel.
     *
     * @return array{init: array<string, mixed>, subscription: PointSubscription}|null
     */
    public function subscribe(User $user, SubscriptionPackage $package, ?string $callbackUrl = null): ?array
    {
        if (! $package->is_active) {
            throw new InvalidArgumentException('This package is not currently available.');
        }

        // One-off packages are repeatable purchases, not standing subscriptions,
        // so a user may buy one any number of times regardless of prior purchases.
        if ($package->renewal_cycle->isRecurring()) {
            $alreadyActive = PointSubscription::query()
                ->where('user_id', $user->id)
                ->where('subscription_package_id', $package->id)
                ->whereIn('status', [SubscriptionStatus::PENDING, SubscriptionStatus::ACTIVE, SubscriptionStatus::ATTENTION])
                ->exists();

            if ($alreadyActive) {
                throw new InvalidArgumentException('You already have an active subscription to this package.');
            }
        }

        $interval = $package->renewal_cycle->paystackInterval();

        if ($package->renewal_cycle->isRecurring() && $interval === null) {
            throw new InvalidArgumentException('This package is not currently available.');
        }

        $plan = $interval !== null ? $this->ensurePlan($package, $interval) : null;

        if ($interval !== null && $plan === null) {
            return null;
        }

        $subscription = PointSubscription::create([
            'user_id' => $user->id,
            'subscription_package_id' => $package->id,
            'paystack_plan_id' => $plan?->id,
            'amount_naira' => $package->price_naira,
            'frequency' => $interval,
            'status' => SubscriptionStatus::PENDING,
        ]);

        $init = $this->paystackService->initializeTransaction(
            $user,
            (float) $package->price_naira,
            $callbackUrl,
            $plan?->plan_code,
            ['point_subscription_id' => $subscription->id, 'purpose' => 'package_subscription'],
            // Paystack can only bill a reusable card; transfers can't back a subscription.
            $plan !== null ? ['card'] : null,
        );

        if ($init === null) {
            $subscription->delete();

            return null;
        }

        $subscription->update(['pending_reference' => $init['reference']]);

        return ['init' => $init, 'subscription' => $subscription];
    }

    /**
     * The Paystack Plan backing a package at its current price and interval,
     * created on first use. Plan amounts can't be edited on Paystack, so a
     * price change simply gets a fresh plan; existing subscribers stay on
     * the plan they signed up under.
     */
    private function ensurePlan(SubscriptionPackage $package, SubscriptionFrequency $interval): ?PaystackPlan
    {
        $amountKobo = $package->priceKobo();

        $existing = $package->plans()
            ->where('amount_kobo', $amountKobo)
            ->where('interval', $interval->value)
            ->latest('id')
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $name = sprintf('Bidora %s (%s)', $package->name, $interval->label());
        $data = $this->paystackService->createPlan($name, $amountKobo, $interval->value);

        if ($data === null) {
            return null;
        }

        return PaystackPlan::create([
            'subscription_package_id' => $package->id,
            'amount_kobo' => $amountKobo,
            'interval' => $interval->value,
            'plan_code' => $data['plan_code'],
            'name' => $name,
        ]);
    }
}
