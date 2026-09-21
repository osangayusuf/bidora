<?php

namespace App\Console\Commands;

use App\Enums\SubscriptionStatus;
use App\Models\PointSubscription;
use App\Notifications\AutoTopUpEnded;
use Illuminate\Console\Command;

class CancelLegacyPackageSubscriptionsCommand extends Command
{
    protected $signature = 'packages:cancel-legacy {--dry-run : List what would be cancelled without changing anything}';

    protected $description = 'Cancel package subscriptions created before packages moved onto Paystack Plans (no plan, renewed by a local job that no longer exists), and tell the user.';

    public function handle(): int
    {
        $legacy = PointSubscription::query()
            ->whereNotNull('subscription_package_id')
            ->whereNull('paystack_plan_id')
            ->whereIn('status', [SubscriptionStatus::PENDING, SubscriptionStatus::ACTIVE, SubscriptionStatus::ATTENTION])
            ->whereHas('subscriptionPackage', fn ($query) => $query->where('renewal_cycle', '!=', 'one_off'))
            ->with(['user', 'subscriptionPackage'])
            ->get();

        foreach ($legacy as $subscription) {
            $this->line(sprintf('#%d user %d %s (%s)', $subscription->id, $subscription->user_id, $subscription->subscriptionPackage?->name, $subscription->status->value));

            if ($this->option('dry-run')) {
                continue;
            }

            $wasPaying = $subscription->status !== SubscriptionStatus::PENDING;

            $subscription->update(['status' => SubscriptionStatus::CANCELLED, 'next_charge_at' => null]);

            // A pending row never took a payment, so there's nothing to tell the user.
            if ($wasPaying) {
                $subscription->user?->notify(new AutoTopUpEnded($subscription));
            }
        }

        $verb = $this->option('dry-run') ? 'Would cancel' : 'Cancelled';
        $this->info("{$verb} {$legacy->count()} legacy package subscription(s).");

        return self::SUCCESS;
    }
}
