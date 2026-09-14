<?php

namespace App\Http\Controllers;

use App\Http\Requests\Wallet\CreateSubscriptionRequest;
use App\Models\PointSubscription;
use App\Services\RecurringPaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use InvalidArgumentException;

class WalletSubscriptionController extends Controller
{
    public function __construct(
        private readonly RecurringPaymentService $recurringPaymentService,
    ) {}

    public function store(CreateSubscriptionRequest $request): RedirectResponse
    {
        $user = $request->user();
        $plan = $request->plan();

        try {
            $result = $this->recurringPaymentService->subscribe($user, $plan, route('wallet.payment.callback'));
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['plan' => $e->getMessage()]);
        }

        if ($result === null) {
            return back()->withErrors([
                'plan' => 'Unable to start your subscription. Please try again.',
            ]);
        }

        Inertia::flash('paystack_init', [
            'reference' => $result['init']['reference'],
            'access_code' => $result['init']['access_code'] ?? null,
            'amount_kobo' => (int) ($plan->amount_kobo),
            'email' => $user->email,
            'public_key' => config('services.paystack.public'),
        ]);

        return back();
    }

    public function destroy(Request $request, PointSubscription $subscription): RedirectResponse
    {
        abort_unless($subscription->user_id === $request->user()->id, 403);

        $cancelled = $this->recurringPaymentService->cancel($subscription);

        if (! $cancelled) {
            return back()->withErrors([
                'subscription' => 'Unable to cancel this subscription right now. Please try again.',
            ]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Auto top-up cancelled. You will not be charged again.'),
        ]);

        return back();
    }

    public function manageCard(Request $request, PointSubscription $subscription): RedirectResponse
    {
        abort_unless($subscription->user_id === $request->user()->id, 403);

        $link = $this->recurringPaymentService->manageCardLink($subscription);

        if ($link === null) {
            return back()->withErrors([
                'subscription' => 'Unable to open the card management page right now. Please try again.',
            ]);
        }

        return redirect()->away($link);
    }
}
