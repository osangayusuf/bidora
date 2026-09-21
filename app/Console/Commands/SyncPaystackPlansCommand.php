<?php

namespace App\Console\Commands;

use App\Enums\SubscriptionFrequency;
use App\Models\PaystackPlan;
use App\Services\PaystackService;
use Illuminate\Console\Command;

class SyncPaystackPlansCommand extends Command
{
    protected $signature = 'paystack:sync-plans';

    protected $description = 'Create (idempotently) the Paystack Plan matrix backing recurring point subscriptions, for every deposit preset amount x enabled frequency.';

    public function handle(PaystackService $paystackService): int
    {
        $presets = config('points.deposit_presets', []);
        $frequencies = config('points.recurring.frequencies', []);

        if (empty($presets) || empty($frequencies)) {
            $this->warn('No deposit presets or recurring frequencies configured; nothing to sync.');

            return self::SUCCESS;
        }

        foreach ($frequencies as $frequency) {
            // Fail fast on a config typo rather than silently sending a bad interval to Paystack.
            $interval = SubscriptionFrequency::from($frequency);

            foreach ($presets as $amountNaira) {
                $amountKobo = (int) round($amountNaira * 100);

                $existing = PaystackPlan::query()
                    ->whereNull('subscription_package_id')
                    ->where('amount_kobo', $amountKobo)
                    ->where('interval', $interval->value)
                    ->first();

                if ($existing !== null) {
                    $this->line("Skipping existing plan: ₦{$amountNaira} / {$interval->value} ({$existing->plan_code})");

                    continue;
                }

                $name = sprintf('Bidora %s Top-up ₦%s', $interval->label(), number_format($amountNaira));

                $data = $paystackService->createPlan($name, $amountKobo, $interval->value);

                if ($data === null) {
                    $this->error("Failed to create plan: {$name}");

                    continue;
                }

                PaystackPlan::create([
                    'amount_kobo' => $amountKobo,
                    'interval' => $interval->value,
                    'plan_code' => $data['plan_code'],
                    'name' => $name,
                ]);

                $this->info("Created plan: {$name} ({$data['plan_code']})");
            }
        }

        return self::SUCCESS;
    }
}
