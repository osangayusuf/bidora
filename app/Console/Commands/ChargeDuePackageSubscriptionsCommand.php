<?php

namespace App\Console\Commands;

use App\Enums\SubscriptionStatus;
use App\Models\PointSubscription;
use App\Services\PackageSubscriptionService;
use Illuminate\Console\Command;

class ChargeDuePackageSubscriptionsCommand extends Command
{
    protected $signature = 'packages:charge-due';

    protected $description = 'Charge the saved card for every package subscription whose renewal is due.';

    public function handle(PackageSubscriptionService $service): int
    {
        $due = PointSubscription::query()
            ->whereNotNull('subscription_package_id')
            ->whereIn('status', [SubscriptionStatus::ACTIVE, SubscriptionStatus::ATTENTION])
            ->whereNotNull('next_charge_at')
            ->where('next_charge_at', '<=', now())
            ->with(['user', 'subscriptionPackage'])
            ->get();

        foreach ($due as $subscription) {
            $service->chargeRenewal($subscription);
        }

        $this->info("Processed {$due->count()} due package subscription(s).");

        return self::SUCCESS;
    }
}
