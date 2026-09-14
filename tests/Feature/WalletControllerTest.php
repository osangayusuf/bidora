<?php

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\PaystackTransaction;
use App\Models\PointTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('guests are redirected when visiting wallet', function () {
    $this->get(route('wallet'))
        ->assertRedirect(route('login'));
});

test('authenticated users can view wallet page', function () {
    $user = User::factory()->create([
        'points_balance' => 500,
        'bonus_points' => 100,
    ]);

    PointTransaction::create([
        'user_id' => $user->id,
        'type' => TransactionType::DEPOSIT,
        'amount' => 1000,
        'exchange_rate' => 100,
        'status' => TransactionStatus::COMPLETED,
    ]);

    $this->actingAs($user)
        ->get(route('wallet'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Wallet/Index')
            ->has('balances')
            ->has('walletConfig')
            ->has('availablePlans')
            ->has('subscriptions')
            ->has('transactions.data', 1)
            ->where('balances.points_balance', 500)
            ->where('balances.bonus_points', 100)
            ->where('walletConfig.one_off_deposits_enabled', false)
            ->where('walletConfig.recurring_enabled', true)
        );
});

test('deposit initializes paystack and flashes inline payment data', function () {
    Http::fake([
        'api.paystack.co/transaction/initialize' => Http::response([
            'status' => true,
            'data' => [
                'authorization_url' => 'https://checkout.paystack.com/url',
                'access_code' => 'access_abc',
                'reference' => 'ref_wallet_test',
            ],
        ], 200),
    ]);

    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->from(route('wallet'))
        ->post(route('wallet.deposit'), ['amount' => 1000]);

    $response->assertRedirect(route('wallet'))
        ->assertInertiaFlash('paystack_init.reference', 'ref_wallet_test')
        ->assertInertiaFlash('paystack_init.access_code', 'access_abc')
        ->assertInertiaFlash('paystack_init.amount_kobo', 100000);

    $this->assertDatabaseHas('paystack_transactions', [
        'user_id' => $user->id,
        'reference' => 'ref_wallet_test',
        'status' => 'pending',
    ]);
});

test('payment callback verifies paystack and credits points once', function () {
    $user = User::factory()->create(['points_balance' => 0]);

    PaystackTransaction::create([
        'user_id' => $user->id,
        'reference' => 'ref_callback',
        'amount' => 100000,
        'status' => 'pending',
        'currency' => 'NGN',
    ]);

    Http::fake([
        'api.paystack.co/transaction/verify/ref_callback' => Http::response([
            'status' => true,
            'data' => [
                'status' => 'success',
                'reference' => 'ref_callback',
                'amount' => 100000,
                'metadata' => ['user_id' => $user->id],
            ],
        ], 200),
    ]);

    $this->actingAs($user)
        ->get(route('wallet.payment.callback', ['reference' => 'ref_callback']))
        ->assertRedirect(route('wallet', ['payment' => 'success', 'reference' => 'ref_callback']));

    expect((float) $user->fresh()->points_balance)->toEqual(10000.0);
    expect(PointTransaction::where('provider_reference', 'ref_callback')->count())->toBe(1);

    $this->actingAs($user)
        ->get(route('wallet.payment.callback', ['reference' => 'ref_callback']))
        ->assertRedirect();

    expect((float) $user->fresh()->points_balance)->toEqual(10000.0);
    expect(PointTransaction::where('provider_reference', 'ref_callback')->count())->toBe(1);
});

test('claim bonus converts all bonus points to spendable balance', function () {
    $user = User::factory()->create([
        'points_balance' => 0,
        'bonus_points' => 250,
    ]);

    $this->actingAs($user)
        ->post(route('wallet.claim-bonus'))
        ->assertRedirect(route('wallet'));

    $user->refresh();

    expect($user->bonus_points)->toEqual(0)
        ->and($user->points_balance)->toEqual(250);

    $this->assertDatabaseHas('point_transactions', [
        'user_id' => $user->id,
        'type' => 'bonus_claim',
        'amount' => 250,
    ]);
});

test('claim bonus fails when user has no bonus points', function () {
    $user = User::factory()->create(['bonus_points' => 0]);

    $this->actingAs($user)
        ->from(route('wallet'))
        ->post(route('wallet.claim-bonus'))
        ->assertSessionHasErrors('bonus');
});

test('deposit rejects amounts below minimum', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('wallet'))
        ->post(route('wallet.deposit'), ['amount' => 50])
        ->assertSessionHasErrors('amount');
});

test('wallet includes auction id for bid transactions', function () {
    $user = User::factory()->create();

    PointTransaction::create([
        'user_id' => $user->id,
        'type' => TransactionType::BID_DEBIT,
        'amount' => 50,
        'exchange_rate' => 1,
        'status' => TransactionStatus::COMPLETED,
        'metadata' => ['auction_id' => 42, 'bid_id' => 7],
    ]);

    $this->actingAs($user)
        ->get(route('wallet'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('transactions.data', 1)
            ->where('transactions.data.0.type', 'bid_debit')
            ->where('transactions.data.0.auction_id', 42)
        );
});

test('wallet transactions only show authenticated user records', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    PointTransaction::create([
        'user_id' => $user->id,
        'type' => TransactionType::DEPOSIT,
        'amount' => 100,
        'exchange_rate' => 100,
        'status' => TransactionStatus::COMPLETED,
    ]);
    PointTransaction::create([
        'user_id' => $other->id,
        'type' => TransactionType::DEPOSIT,
        'amount' => 999,
        'exchange_rate' => 100,
        'status' => TransactionStatus::COMPLETED,
    ]);

    $this->actingAs($user)
        ->get(route('wallet'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('transactions.data', 1)
            ->where('transactions.data.0.amount', 100)
        );
});

test('wallet renders all transaction types without error', function () {
    $user = User::factory()->create();

    foreach (TransactionType::cases() as $case) {
        PointTransaction::create([
            'user_id' => $user->id,
            'type' => $case,
            'amount' => 100,
            'exchange_rate' => 1,
            'status' => TransactionStatus::COMPLETED,
        ]);
    }

    $this->actingAs($user)
        ->get(route('wallet'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Wallet/Index')
            ->has('transactions.data', count(TransactionType::cases()))
        );
});
