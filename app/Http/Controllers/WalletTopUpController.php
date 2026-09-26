<?php

namespace App\Http\Controllers;

use App\Http\Requests\Wallet\StoreTopUpRequest;
use App\Services\TopUpService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class WalletTopUpController extends Controller
{
    public function __construct(
        private readonly TopUpService $topUpService,
    ) {}

    public function store(StoreTopUpRequest $request): RedirectResponse
    {
        $user = $request->user();

        $result = $this->topUpService->start(
            $user,
            (float) $request->validated('amount'),
            route('wallet.payment.callback'),
        );

        if ($result === null) {
            return back()->withErrors([
                'amount' => 'Unable to initialize payment. Please try again.',
            ]);
        }

        Inertia::flash('paystack_init', [
            'reference' => $result['init']['reference'],
            'access_code' => $result['init']['access_code'] ?? null,
            'amount_kobo' => $result['top_up']->amountKobo(),
            'email' => $user->email,
            'public_key' => config('services.paystack.public'),
        ]);

        return back();
    }
}
