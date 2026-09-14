<?php

use App\Jobs\ProcessPaystackWebhookJob;
use App\Models\PaystackTransaction;
use App\Models\PaystackWebhookLog;
use App\Models\User;
use App\Services\PaystackService;
use App\Services\RecurringPaymentService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function processWebhookLog(ProcessPaystackWebhookJob $job): void
{
    $job->handle(new WalletService, new RecurringPaymentService(new PaystackService));
}

it('processes a pending webhook log and updates existing transaction', function () {
    $user = User::factory()->create(['points_balance' => 0]);
    config(['points.points_per_naira' => 100]);

    $transaction = PaystackTransaction::create([
        'user_id' => $user->id,
        'reference' => 'ref_123',
        'amount' => 500000,
        'status' => 'pending',
    ]);

    $log = PaystackWebhookLog::create([
        'event' => 'charge.success',
        'reference' => 'ref_123',
        'status' => 'pending',
        'payload' => [
            'data' => [
                'reference' => 'ref_123',
                'amount' => 500000,
            ],
        ],
    ]);

    $job = new ProcessPaystackWebhookJob($log->id);
    processWebhookLog($job);

    $log->refresh();
    $transaction->refresh();
    $user->refresh();

    expect($log->status)->toBe('processed')
        ->and($transaction->status)->toBe('success')
        ->and($user->points_balance)->toEqual(500000); // 5000 NGN * 100
});

it('creates transaction if missing but user email in payload', function () {
    $user = User::factory()->create(['points_balance' => 0]);
    config(['points.points_per_naira' => 100]);

    $log = PaystackWebhookLog::create([
        'event' => 'charge.success',
        'reference' => 'ref_abc',
        'status' => 'pending',
        'payload' => [
            'data' => [
                'reference' => 'ref_abc',
                'amount' => 100000,
                'customer' => [
                    'email' => $user->email,
                ],
            ],
        ],
    ]);

    $job = new ProcessPaystackWebhookJob($log->id);
    processWebhookLog($job);

    $transaction = PaystackTransaction::where('reference', 'ref_abc')->first();

    expect($transaction)->not->toBeNull()
        ->and($transaction->status)->toBe('success')
        ->and($transaction->user_id)->toBe($user->id)
        ->and($user->fresh()->points_balance)->toEqual(100000);
});

it('ignores already processed webhook logs', function () {
    $log = PaystackWebhookLog::create([
        'event' => 'charge.success',
        'status' => 'processed', // Already processed
        'payload' => [],
    ]);

    $job = new ProcessPaystackWebhookJob($log->id);
    processWebhookLog($job);

    // Should return early and not fail or change status
    expect($log->fresh()->status)->toBe('processed');
});
