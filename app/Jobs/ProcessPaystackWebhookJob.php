<?php

namespace App\Jobs;

use App\Models\PaystackTransaction;
use App\Models\PaystackWebhookLog;
use App\Models\User;
use App\Services\RecurringPaymentService;
use App\Services\WalletService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProcessPaystackWebhookJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $paystackWebhookLogId
    ) {}

    /**
     * Execute the job.
     */
    public function handle(WalletService $walletService, RecurringPaymentService $recurringPaymentService): void
    {
        $log = PaystackWebhookLog::find($this->paystackWebhookLogId);

        if (! $log || $log->status !== 'pending') {
            return;
        }

        try {
            $payload = $log->payload;
            $event = $log->event;
            $data = $payload['data'] ?? [];

            $status = 'processed';

            switch ($event) {
                case 'charge.success':
                    $status = $this->handleChargeSuccess($data, $walletService, $recurringPaymentService) ?? $status;
                    break;
                case 'subscription.create':
                    $recurringPaymentService->handleSubscriptionCreate($data);
                    break;
                case 'subscription.not_renew':
                    $recurringPaymentService->handleSubscriptionNotRenew($data);
                    break;
                case 'subscription.disable':
                    $recurringPaymentService->handleSubscriptionDisable($data);
                    break;
                case 'invoice.payment_failed':
                    $recurringPaymentService->handleInvoicePaymentFailed($data);
                    break;
            }

            $log->update(['status' => $status]);
        } catch (\Exception $e) {
            $log->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            Log::error("ProcessPaystackWebhookJob failed for log {$this->paystackWebhookLogId}: {$e->getMessage()}");
            throw $e; // Re-throw to fail the job in queue
        }
    }

    /**
     * Handles both a user-initiated single deposit and the recurring charges
     * Paystack generates on its own schedule for an active subscription —
     * both arrive as `charge.success` and are credited identically.
     *
     * @param  array<string, mixed>  $data
     * @return string|null A log status override (e.g. 'ignored'), or null to record 'processed'.
     */
    private function handleChargeSuccess(array $data, WalletService $walletService, RecurringPaymentService $recurringPaymentService): ?string
    {
        $reference = $data['reference'] ?? null;
        $amountInKobo = $data['amount'] ?? 0;
        $nairaAmount = $amountInKobo / 100;

        // Find PaystackTransaction
        $paystackTransaction = PaystackTransaction::where('reference', $reference)->first();

        if ($paystackTransaction) {
            if ($paystackTransaction->status === 'success') {
                return 'ignored';
            }

            // Attempt to find user from transaction or payload
            $user = $paystackTransaction->user;
        } else {
            // Fallback to finding user via email or metadata if transaction is not recorded.
            // This is also the normal path for a Paystack-initiated recurring charge, which
            // never goes through our own transaction/initialize call.
            $userId = $data['metadata']['user_id'] ?? null;
            $email = $data['customer']['email'] ?? null;

            if ($userId) {
                $user = User::find($userId);
            } elseif ($email) {
                $user = User::where('email', $email)->first();
            }

            if (! $user) {
                throw new \Exception("User not found for reference {$reference}");
            }

            // Create transaction retrospectively
            $paystackTransaction = PaystackTransaction::create([
                'user_id' => $user->id,
                'reference' => $reference,
                'amount' => $amountInKobo,
                'currency' => $data['currency'] ?? 'NGN',
                'channel' => $data['channel'] ?? null,
                'status' => 'pending',
            ]);
        }

        $subscription = $recurringPaymentService->resolveSubscriptionForCharge($data);

        // Credit the user
        $walletService->processDeposit(
            $user,
            $nairaAmount,
            $reference,
            $data,
            $paystackTransaction->id,
            $subscription?->id,
        );

        // Update PaystackTransaction
        $paystackTransaction->update([
            'status' => 'success',
            'paid_at' => now(),
        ]);

        return null;
    }
}
