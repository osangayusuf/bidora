<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SubscriptionStatus;
use App\Http\Controllers\Controller;
use App\Models\PointSubscription;
use App\Services\RecurringPaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PointSubscriptionController extends Controller
{
    public function __construct(
        private readonly RecurringPaymentService $recurringPaymentService,
    ) {}

    public function index(Request $request): Response
    {
        $search = $request->filled('search')
            ? trim($request->string('search')->toString())
            : null;

        $statusFilter = $request->filled('status')
            ? $request->string('status')->toString()
            : null;

        $query = PointSubscription::query()
            ->with(['user', 'plan'])
            ->orderBy('id', 'desc');

        if ($search) {
            $query->whereHas('user', function ($uq) use ($search) {
                $uq->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($statusFilter) {
            $query->where('status', $statusFilter);
        }

        $subscriptions = $query->paginate(20)->withQueryString();

        return Inertia::render('Admin/PointSubscriptions/Index', [
            'subscriptions' => $subscriptions,
            'filters' => [
                'search' => $search,
                'status' => $statusFilter,
            ],
            'availableStatuses' => array_map(fn (SubscriptionStatus $s) => $s->value, SubscriptionStatus::cases()),
        ]);
    }

    public function cancel(PointSubscription $subscription): RedirectResponse
    {
        $cancelled = $this->recurringPaymentService->cancel($subscription);

        if (! $cancelled) {
            return back()->withErrors([
                'error' => __('Unable to cancel this subscription. It may already be inactive.'),
            ]);
        }

        return back()->with('success', __('Subscription cancelled.'));
    }

    public function resync(PointSubscription $subscription): RedirectResponse
    {
        if ($subscription->subscription_code === null) {
            return back()->withErrors([
                'error' => __('This subscription has not been confirmed by Paystack yet, nothing to resync.'),
            ]);
        }

        $this->recurringPaymentService->resync($subscription);

        return back()->with('success', __('Subscription status resynced from Paystack.'));
    }
}
