<?php

namespace App\Http\Controllers;

use App\Enums\ActivityType;
use App\Http\Requests\Wallet\ClaimBonusRequest;
use App\Http\Requests\Wallet\DepositRequest;
use App\Http\Resources\PaystackPlanResource;
use App\Http\Resources\PointSubscriptionResource;
use App\Http\Resources\PointTransactionResource;
use App\Services\ActivityService;
use App\Services\PaystackService;
use App\Services\RecurringPaymentService;
use App\Services\WalletPageService;
use App\Services\WalletService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WalletController extends Controller
{
    public function __construct(
        private readonly WalletPageService $walletPageService,
        private readonly WalletService $walletService,
        private readonly PaystackService $paystackService,
        private readonly ActivityService $activityService,
        private readonly RecurringPaymentService $recurringPaymentService,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Wallet/Index', [
            'balances' => $this->walletPageService->balances($user),
            'walletConfig' => $this->walletPageService->walletConfig(),
            'transactions' => PointTransactionResource::collection(
                $this->walletPageService->paginateTransactions($user),
            ),
            'availablePlans' => PaystackPlanResource::collection(
                $this->walletPageService->availablePlans(),
            ),
            'subscriptions' => PointSubscriptionResource::collection(
                $this->walletPageService->subscriptions($user),
            ),
            'paymentStatus' => $request->query('payment'),
            'paymentReference' => $request->query('reference'),
        ]);
    }

    public function deposit(DepositRequest $request): RedirectResponse
    {
        $user = $request->user();
        $amount = (float) $request->validated('amount');

        $callbackUrl = route('wallet.payment.callback');

        $data = $this->paystackService->initializeTransaction($user, $amount, $callbackUrl);

        if ($data === null) {
            return back()->withErrors([
                'amount' => 'Unable to initialize payment. Please try again.',
            ]);
        }

        Inertia::flash('paystack_init', [
            'reference' => $data['reference'],
            'access_code' => $data['access_code'] ?? null,
            'amount_kobo' => (int) ($amount * 100),
            'email' => $user->email,
            'public_key' => config('services.paystack.public'),
        ]);

        return back();
    }

    public function paymentCallback(Request $request): RedirectResponse
    {
        $reference = $request->string('reference')->toString();

        if ($reference === '') {
            return redirect()->route('wallet', ['payment' => 'failed']);
        }

        $user = $request->user();
        $paystackData = $this->paystackService->verifyTransaction($reference);

        if ($paystackData === null || ($paystackData['status'] ?? null) !== 'success') {
            return redirect()->route('wallet', [
                'payment' => 'failed',
                'reference' => $reference,
            ]);
        }

        $metadataUserId = $paystackData['metadata']['user_id'] ?? null;

        if ($metadataUserId !== null && (int) $metadataUserId !== $user->id) {
            abort(403);
        }

        $subscription = $this->recurringPaymentService->resolveSubscriptionForCharge($paystackData);

        $transaction = $this->walletService->finalizePaystackDeposit($user, $reference, $paystackData, $subscription?->id);

        if ($transaction !== null && $transaction->wasRecentlyCreated) {
            $this->activityService->log(ActivityType::POINTS_DEPOSITED, $user, $transaction);
        }

        return redirect()->route('wallet', [
            'payment' => 'success',
            'reference' => $reference,
        ]);
    }

    public function claimBonus(ClaimBonusRequest $request): RedirectResponse
    {
        $user = $request->user();
        $transaction = $this->walletService->claimAllBonusPoints($user);

        $this->activityService->log(ActivityType::BONUS_POINTS_CLAIMED, $user, $transaction, [
            'bonus_claimed' => (int) ($transaction->metadata['bonus_claimed'] ?? 0),
            'spendable' => (int) $transaction->amount,
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Bonus points claimed successfully.'),
        ]);

        return redirect()->route('wallet');
    }
}
