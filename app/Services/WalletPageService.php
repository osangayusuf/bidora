<?php

namespace App\Services;

use App\Models\PaystackPlan;
use App\Models\PointSubscription;
use App\Models\PointTransaction;
use App\Models\SubscriptionPackage;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class WalletPageService
{
    /**
     * @return array<string, mixed>
     */
    public function walletConfig(): array
    {
        return [
            'points_per_naira' => (float) config('points.points_per_naira'),
            'bonus_conversion_rate' => (float) config('points.bonus_conversion_rate'),
            'min_deposit_naira' => (int) config('points.min_deposit_naira'),
            'max_deposit_naira' => (int) config('points.max_deposit_naira'),
            'deposit_presets' => config('points.deposit_presets', [1000]),
            'paystack_public_key' => config('services.paystack.public'),
            'top_ups_enabled' => (bool) config('points.top_ups.enabled'),
            'recurring_enabled' => (bool) config('points.recurring.enabled'),
            'packages_enabled' => (bool) config('points.packages.enabled'),
        ];
    }

    /**
     * @return array{points_balance: float, bonus_points: float}
     */
    public function balances(User $user): array
    {
        return [
            'points_balance' => (float) $user->points_balance,
            'bonus_points' => (float) $user->bonus_points,
        ];
    }

    /**
     * @return LengthAwarePaginator<int, PointTransaction>
     */
    public function paginateTransactions(User $user, int $perPage = 15): LengthAwarePaginator
    {
        return PointTransaction::query()
            ->where('user_id', $user->id)
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * The plan matrix (amount x frequency) users can subscribe to, ordered
     * for a sensible UI grouping.
     *
     * @return Collection<int, PaystackPlan>
     */
    public function availablePlans(): Collection
    {
        return PaystackPlan::query()
            ->orderBy('amount_kobo')
            ->orderBy('interval')
            ->get();
    }

    /**
     * @return Collection<int, PointSubscription>
     */
    public function subscriptions(User $user): Collection
    {
        return PointSubscription::query()
            ->where('user_id', $user->id)
            ->with(['plan', 'subscriptionPackage'])
            ->latest()
            ->get();
    }

    /**
     * The active fixed-tier packages users can subscribe to.
     *
     * @return Collection<int, SubscriptionPackage>
     */
    public function packages(): Collection
    {
        return SubscriptionPackage::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }
}
