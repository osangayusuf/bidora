<?php

use App\Enums\RewardSource;
use App\Enums\TransactionType;
use App\Models\PointTransaction;
use App\Models\User;
use App\Services\WalletService;
use Illuminate\Auth\Events\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('new user automatically generates a unique referral code on creation', function () {
    $user = User::factory()->create();

    expect($user->referral_code)->not->toBeNull()
        ->and(strlen($user->referral_code))->toBe(8);
});

test('new users can register with a valid referral code and award points to both', function () {
    $referrer = User::factory()->create([
        'referral_code' => 'TESTCODE',
        'bonus_points' => 0,
    ]);

    $response = $this->post(route('register.store'), [
        'name' => 'Referree User',
        'email' => 'referee@example.com',
        'phone' => '08031234568',
        'password' => 'password',
        'password_confirmation' => 'password',
        'referral_code' => 'TESTCODE',
    ]);

    $this->assertAuthenticated();

    // Verify referee was created and referred_by was set
    $referee = User::where('email', 'referee@example.com')->first();
    expect($referee)->not->toBeNull()
        ->and($referee->referred_by)->toBe($referrer->id);

    // Verify points were awarded to referee
    expect((float) $referee->bonus_points)->toBe(10.00);

    // Verify points were awarded to referrer  (referrer_signup_points)
    expect((float) $referrer->refresh()->bonus_points)->toBe(1250.00);

    // Check transactions
    $refereeTx = PointTransaction::where('user_id', $referee->id)
        ->where('type', TransactionType::BONUS_AWARD)
        ->first();
    expect($refereeTx)->not->toBeNull()
        ->and($refereeTx->metadata['source'])->toBe(RewardSource::ReferralSignup->value)
        ->and($refereeTx->metadata['referrer_id'])->toBe($referrer->id);

    $referrerTx = PointTransaction::where('user_id', $referrer->id)
        ->where('type', TransactionType::BONUS_AWARD)
        ->first();
    expect($referrerTx)->not->toBeNull()
        ->and($referrerTx->metadata['source'])->toBe(RewardSource::ReferralSignup->value)
        ->and($referrerTx->metadata['referred_user_id'])->toBe($referee->id);
});

test('registration validation fails if referral code is invalid', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'Referree User',
        'email' => 'referee@example.com',
        'phone' => '08031234568',
        'password' => 'password',
        'password_confirmation' => 'password',
        'referral_code' => 'NONEXISTENT',
    ]);

    $response->assertSessionHasErrors(['referral_code']);
    $this->assertGuest();
});

test('referrer is awarded bonus points on referee first deposit only', function () {
    // Create referrer and referee
    $referrer = User::factory()->create(['referral_code' => 'REFCODE', 'bonus_points' => 0]);
    $referee = User::factory()->create([
        'referred_by' => $referrer->id,
        'points_balance' => 0,
    ]);

    // Perform first deposit via WalletService
    $walletService = app(WalletService::class);
    $walletService->processDeposit($referee, 1000.00, 'ref_first_dep');

    // Referrer should get 750 bonus points (referrer_first_deposit_points)
    expect((float) $referrer->refresh()->bonus_points)->toBe(750.00);

    $referrerDepositTx = PointTransaction::where('user_id', $referrer->id)
        ->where('type', TransactionType::BONUS_AWARD)
        ->where('metadata->source', RewardSource::ReferralDeposit->value)
        ->first();
    expect($referrerDepositTx)->not->toBeNull()
        ->and($referrerDepositTx->metadata['referred_user_id'])->toBe($referee->id);

    // Reset referrer bonus points to 0 to test second deposit
    $referrer->update(['bonus_points' => 0]);

    // Perform second deposit
    $walletService->processDeposit($referee, 500.00, 'ref_second_dep');

    // Referrer should not get any more points
    expect((float) $referrer->refresh()->bonus_points)->toBe(0.00);
});

test('existing users without a referral code get one generated on login', function () {
    $user = User::factory()->create();
    $user->updateQuietly(['referral_code' => null]);
    expect($user->referral_code)->toBeNull();

    event(new Login('web', $user, false));

    expect($user->refresh()->referral_code)->not->toBeNull()
        ->and(strlen($user->referral_code))->toBe(8);
});
