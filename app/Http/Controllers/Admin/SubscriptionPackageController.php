<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PackageRenewalCycle;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSubscriptionPackageRequest;
use App\Http\Requests\Admin\UpdateSubscriptionPackageRequest;
use App\Http\Resources\SubscriptionPackageResource;
use App\Models\SubscriptionPackage;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SubscriptionPackageController extends Controller
{
    public function index(): Response
    {
        $packages = SubscriptionPackage::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return Inertia::render('Admin/SubscriptionPackages/Index', [
            'packages' => $packages
                ->map(fn (SubscriptionPackage $package) => (new SubscriptionPackageResource($package))->resolve())
                ->values(),
            'renewalCycles' => array_map(
                fn (PackageRenewalCycle $cycle) => ['value' => $cycle->value, 'label' => $cycle->label()],
                PackageRenewalCycle::sellable(),
            ),
        ]);
    }

    public function store(StoreSubscriptionPackageRequest $request): RedirectResponse
    {
        SubscriptionPackage::create($request->validated());

        return back()->with('success', __('Package created.'));
    }

    public function update(UpdateSubscriptionPackageRequest $request, SubscriptionPackage $package): RedirectResponse
    {
        $package->update($request->validated());

        return back()->with('success', __('Package updated.'));
    }

    public function toggleActive(SubscriptionPackage $package): RedirectResponse
    {
        $package->update(['is_active' => ! $package->is_active]);

        return back()->with('success', __('Package status updated.'));
    }

    public function destroy(SubscriptionPackage $package): RedirectResponse
    {
        if ($package->subscriptions()->exists()) {
            return back()->withErrors([
                'error' => __('This package has subscribers and cannot be deleted. Deactivate it instead.'),
            ]);
        }

        $package->delete();

        return back()->with('success', __('Package deleted.'));
    }
}
