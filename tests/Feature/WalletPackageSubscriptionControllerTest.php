<?php

use App\Enums\PackageRenewalCycle;
use App\Models\SubscriptionPackage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->package = SubscriptionPackage::create([
        'name' => 'Awoof',
        'slug' => 'awoof',
        'renewal_cycle' => PackageRenewalCycle::WEEKLY,
        'points_allocated' => 6000,
        'price_naira' => 500,
        'is_active' => true,
        'sort_order' => 1,
    ]);
});

test('store initializes the first charge and flashes inline payment data', function () {
    Http::fake([
        'api.paystack.co/transaction/initialize' => Http::response([
            'status' => true,
            'data' => ['access_code' => 'access_abc', 'reference' => 'ref_pkg_store'],
        ], 200),
    ]);

    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->from(route('wallet'))
        ->post(route('wallet.packages.subscribe'), ['subscription_package_id' => $this->package->id]);

    $response->assertRedirect(route('wallet'))
        ->assertInertiaFlash('paystack_init.reference', 'ref_pkg_store')
        ->assertInertiaFlash('paystack_init.access_code', 'access_abc');

    $this->assertDatabaseHas('point_subscriptions', [
        'user_id' => $user->id,
        'subscription_package_id' => $this->package->id,
        'status' => 'pending',
        'pending_reference' => 'ref_pkg_store',
    ]);
});

test('store rejects an unknown package id', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('wallet.packages.subscribe'), ['subscription_package_id' => 999999])
        ->assertSessionHasErrors('subscription_package_id');
});

test('store rejects an inactive package', function () {
    $this->package->update(['is_active' => false]);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('wallet.packages.subscribe'), ['subscription_package_id' => $this->package->id])
        ->assertSessionHasErrors('subscription_package_id');
});
