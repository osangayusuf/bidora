<?php

namespace App\Http\Controllers;

use App\Http\Requests\Wallet\CreatePackageSubscriptionRequest;
use App\Services\PackageSubscriptionService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use InvalidArgumentException;

class WalletPackageSubscriptionController extends Controller
{
    public function __construct(
        private readonly PackageSubscriptionService $packageSubscriptionService,
    ) {}

    public function store(CreatePackageSubscriptionRequest $request): RedirectResponse
    {
        $user = $request->user();
        $package = $request->package();

        try {
            $result = $this->packageSubscriptionService->subscribe($user, $package, route('wallet.payment.callback'));
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['package' => $e->getMessage()]);
        }

        if ($result === null) {
            return back()->withErrors([
                'package' => 'Unable to start your subscription. Please try again.',
            ]);
        }

        Inertia::flash('paystack_init', [
            'reference' => $result['init']['reference'],
            'access_code' => $result['init']['access_code'] ?? null,
            'amount_kobo' => $package->priceKobo(),
            'email' => $user->email,
            'public_key' => config('services.paystack.public'),
        ]);

        return back();
    }
}
