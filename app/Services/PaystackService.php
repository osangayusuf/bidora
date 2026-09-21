<?php

namespace App\Services;

use App\Models\PaystackTransaction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PaystackService
{
    /**
     * Verify the Paystack webhook signature.
     */
    public function verifyWebhookSignature(Request $request): bool
    {
        $signature = $request->header('x-paystack-signature');

        if (! $signature) {
            return false;
        }

        $secret = config('services.paystack.secret');
        $computedSignature = hash_hmac('sha512', $request->getContent(), $secret);

        return hash_equals($computedSignature, $signature);
    }

    /**
     * Initialize a Paystack transaction.
     *
     * @param  string|null  $planCode  When set, this charge subscribes the customer to the
     *                                 plan on success (the initial charge of a recurring subscription).
     * @param  array<string, mixed>  $metadata  Merged into the standard app/user_id metadata.
     * @param  array<int, string>|null  $channels  Restrict the payment methods offered (e.g. ['card']).
     */
    public function initializeTransaction(
        User $user,
        float $amount,
        ?string $callbackUrl = null,
        ?string $planCode = null,
        array $metadata = [],
        ?array $channels = null,
    ): ?array {
        $secret = config('services.paystack.secret');

        $payload = [
            'email' => $user->email,
            'amount' => (int) ($amount * 100), // Convert Naira to Kobo
            'callback_url' => $callbackUrl,
            'metadata' => array_merge([
                'app' => 'bidora',
                'user_id' => $user->id,
            ], $metadata),
        ];

        if ($planCode !== null) {
            $payload['plan'] = $planCode;
        }

        if ($channels !== null) {
            $payload['channels'] = $channels;
        }

        $response = Http::withToken($secret)
            ->post('https://api.paystack.co/transaction/initialize', $payload);

        if ($response->successful()) {
            $data = $response->json('data');

            PaystackTransaction::create([
                'user_id' => $user->id,
                'reference' => $data['reference'],
                'access_code' => $data['access_code'] ?? null,
                'amount' => (int) ($amount * 100),
                'status' => 'pending',
                'currency' => 'NGN',
            ]);

            return $data;
        }

        Log::error('Paystack initialize transaction failed', [
            'status' => $response->status(),
            'body' => $response->body(),
            'user_id' => $user->id,
        ]);

        return null;
    }

    /**
     * Create a Paystack Plan. Returns the plan's data payload (including `plan_code`).
     */
    public function createPlan(string $name, int $amountKobo, string $interval): ?array
    {
        $secret = config('services.paystack.secret');

        $response = Http::withToken($secret)
            ->post('https://api.paystack.co/plan', [
                'name' => $name,
                'amount' => $amountKobo,
                'interval' => $interval,
            ]);

        if ($response->successful()) {
            return $response->json('data');
        }

        Log::error('Paystack create plan failed', [
            'status' => $response->status(),
            'body' => $response->body(),
            'name' => $name,
        ]);

        return null;
    }

    /**
     * Set metadata on a Customer. Paystack-initiated recurring charges don't
     * reliably carry the metadata from the original initializing transaction,
     * so we stamp `app` on the customer itself — this account is shared with
     * Fanscorner, and every event must still be routable by `metadata.app`.
     */
    public function updateCustomerMetadata(string $customerCode, array $metadata): bool
    {
        $secret = config('services.paystack.secret');

        $response = Http::withToken($secret)
            ->put("https://api.paystack.co/customer/{$customerCode}", [
                'metadata' => $metadata,
            ]);

        if (! $response->successful()) {
            Log::error('Paystack update customer metadata failed', [
                'status' => $response->status(),
                'body' => $response->body(),
                'customer_code' => $customerCode,
            ]);
        }

        return $response->successful();
    }

    /**
     * Disable (cancel) a subscription. Requires the subscription_code and the
     * email_token issued when the subscription was created.
     */
    public function disableSubscription(string $subscriptionCode, string $emailToken): bool
    {
        $secret = config('services.paystack.secret');

        $response = Http::withToken($secret)
            ->post('https://api.paystack.co/subscription/disable', [
                'code' => $subscriptionCode,
                'token' => $emailToken,
            ]);

        if (! $response->successful()) {
            Log::error('Paystack disable subscription failed', [
                'status' => $response->status(),
                'body' => $response->body(),
                'subscription_code' => $subscriptionCode,
            ]);
        }

        return $response->successful();
    }

    /**
     * Fetch a subscription's current state from Paystack.
     */
    public function fetchSubscription(string $subscriptionCode): ?array
    {
        $secret = config('services.paystack.secret');

        $response = Http::withToken($secret)
            ->get("https://api.paystack.co/subscription/{$subscriptionCode}");

        if ($response->successful()) {
            return $response->json('data');
        }

        Log::error('Paystack fetch subscription failed', [
            'status' => $response->status(),
            'body' => $response->body(),
            'subscription_code' => $subscriptionCode,
        ]);

        return null;
    }

    /**
     * Get a hosted link the customer can use to update the card backing a subscription.
     */
    public function subscriptionManageLink(string $subscriptionCode): ?string
    {
        $secret = config('services.paystack.secret');

        $response = Http::withToken($secret)
            ->get("https://api.paystack.co/subscription/{$subscriptionCode}/manage/link");

        if ($response->successful()) {
            return $response->json('data.link');
        }

        Log::error('Paystack subscription manage link failed', [
            'status' => $response->status(),
            'body' => $response->body(),
            'subscription_code' => $subscriptionCode,
        ]);

        return null;
    }

    /**
     * Charge a previously-saved card authorization directly (no hosted
     * checkout). Used to renew package subscriptions on our own schedule
     * instead of relying on a native Paystack Subscription. Paystack
     * responds 200 with `data.status` of `success` or `failed` even for a
     * declined card, so callers must check that field, not just the HTTP
     * status.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function chargeAuthorization(
        string $authorizationCode,
        string $email,
        int $amountKobo,
        array $metadata = [],
        ?string $reference = null,
    ): ?array {
        $secret = config('services.paystack.secret');

        $payload = [
            'authorization_code' => $authorizationCode,
            'email' => $email,
            'amount' => $amountKobo,
            'metadata' => array_merge(['app' => 'bidora'], $metadata),
        ];

        if ($reference !== null) {
            $payload['reference'] = $reference;
        }

        $response = Http::withToken($secret)
            ->post('https://api.paystack.co/transaction/charge_authorization', $payload);

        if ($response->successful()) {
            return $response->json('data');
        }

        Log::error('Paystack charge authorization failed', [
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        return null;
    }

    /**
     * Verify a transaction manually via Paystack API.
     */
    public function verifyTransaction(string $reference): ?array
    {
        $secret = config('services.paystack.secret');

        $response = Http::withToken($secret)
            ->get("https://api.paystack.co/transaction/verify/{$reference}");

        if ($response->successful()) {
            return $response->json('data');
        }

        Log::error('Paystack verify transaction failed', [
            'status' => $response->status(),
            'body' => $response->body(),
            'reference' => $reference,
        ]);

        return null;
    }
}
