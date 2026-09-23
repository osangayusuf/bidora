<?php

namespace App\Console\Commands;

use App\Enums\SubscriptionStatus;
use App\Models\PointSubscription;
use Illuminate\Console\Command;

class CancelStalePendingSubscriptionsCommand extends Command
{
    /**
     * A pending subscription this old never had its first charge confirmed
     * by a webhook — the user abandoned the Paystack checkout — so it's
     * safe to cancel and free up the package/plan for another attempt.
     */
    private const STALE_AFTER_MINUTES = 30;

    protected $signature = 'subscriptions:cancel-stale-pending {--dry-run : List what would be cancelled without changing anything}';

    protected $description = 'Cancel pending subscriptions whose first charge was abandoned, so users are not blocked from subscribing again.';

    public function handle(): int
    {
        $stale = PointSubscription::query()
            ->where('status', SubscriptionStatus::PENDING)
            ->where('created_at', '<=', now()->subMinutes(self::STALE_AFTER_MINUTES))
            ->with(['user', 'subscriptionPackage'])
            ->get();

        foreach ($stale as $subscription) {
            $this->line(sprintf(
                '#%d user %d %s',
                $subscription->id,
                $subscription->user_id,
                $subscription->subscriptionPackage?->name ?? 'plan subscription',
            ));

            if ($this->option('dry-run')) {
                continue;
            }

            $subscription->update(['status' => SubscriptionStatus::CANCELLED]);
        }

        $verb = $this->option('dry-run') ? 'Would cancel' : 'Cancelled';
        $this->info("{$verb} {$stale->count()} stale pending subscription(s).");

        return self::SUCCESS;
    }
}
