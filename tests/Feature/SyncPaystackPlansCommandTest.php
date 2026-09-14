<?php

use App\Models\PaystackPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

test('it creates a plan for every preset amount and enabled frequency', function () {
    config([
        'points.deposit_presets' => [100, 500],
        'points.recurring.frequencies' => ['weekly', 'monthly'],
    ]);

    Http::fake([
        'api.paystack.co/plan' => Http::sequence()
            ->push(['status' => true, 'data' => ['plan_code' => 'PLN_1']])
            ->push(['status' => true, 'data' => ['plan_code' => 'PLN_2']])
            ->push(['status' => true, 'data' => ['plan_code' => 'PLN_3']])
            ->push(['status' => true, 'data' => ['plan_code' => 'PLN_4']]),
    ]);

    $this->artisan('paystack:sync-plans')->assertSuccessful();

    expect(PaystackPlan::count())->toBe(4);
    expect(PaystackPlan::where('amount_kobo', 10000)->where('interval', 'weekly')->exists())->toBeTrue();
    expect(PaystackPlan::where('amount_kobo', 50000)->where('interval', 'monthly')->exists())->toBeTrue();
});

test('it does not recreate an already-synced plan', function () {
    config([
        'points.deposit_presets' => [100],
        'points.recurring.frequencies' => ['weekly'],
    ]);

    PaystackPlan::create([
        'amount_kobo' => 10000,
        'interval' => 'weekly',
        'plan_code' => 'PLN_existing',
        'name' => 'Bidora Weekly Top-up ₦100',
    ]);

    Http::fake();

    $this->artisan('paystack:sync-plans')->assertSuccessful();

    expect(PaystackPlan::count())->toBe(1);
    Http::assertNothingSent();
});
