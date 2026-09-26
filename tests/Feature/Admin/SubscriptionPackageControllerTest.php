<?php

use App\Enums\PackageRenewalCycle;
use App\Enums\SubscriptionStatus;
use App\Models\PointSubscription;
use App\Models\SubscriptionPackage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('non-admin users cannot access the subscription packages admin page', function () {
    $user = User::factory()->create();
    $user->assignRole('user');

    $this->actingAs($user)
        ->get(route('admin.subscription-packages.index'))
        ->assertRedirect(route('home'));
});

test('admin can view the packages list', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    SubscriptionPackage::create([
        'name' => 'Awoof',
        'slug' => 'awoof',
        'renewal_cycle' => PackageRenewalCycle::WEEKLY,
        'points_allocated' => 6000,
        'price_naira' => 500,
        'is_active' => true,
        'sort_order' => 1,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.subscription-packages.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/SubscriptionPackages/Index')
            ->has('packages', 1)
        );
});

test('admin can create a package', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->actingAs($admin)
        ->post(route('admin.subscription-packages.store'), [
            'name' => 'Jara',
            'slug' => 'jara',
            'renewal_cycle' => 'daily',
            'points_allocated' => 1500,
            'price_naira' => 100,
            'sort_order' => 2,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('subscription_packages', [
        'slug' => 'jara',
        'points_allocated' => 1500,
    ]);
});

test('admin cannot create a one-off package', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->actingAs($admin)
        ->post(route('admin.subscription-packages.store'), [
            'name' => 'Solo',
            'slug' => 'solo',
            'renewal_cycle' => 'one_off',
            'points_allocated' => 5000,
            'price_naira' => 500,
        ])
        ->assertSessionHasErrors('renewal_cycle');

    $this->assertDatabaseMissing('subscription_packages', ['slug' => 'solo']);
});

test('admin can update a package', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $package = SubscriptionPackage::create([
        'name' => 'Jara',
        'slug' => 'jara',
        'renewal_cycle' => PackageRenewalCycle::DAILY,
        'points_allocated' => 1500,
        'price_naira' => 100,
        'is_active' => true,
        'sort_order' => 2,
    ]);

    $this->actingAs($admin)
        ->patch(route('admin.subscription-packages.update', $package), [
            'name' => 'Jara',
            'slug' => 'jara',
            'renewal_cycle' => 'daily',
            'points_allocated' => 2000,
            'price_naira' => 150,
            'sort_order' => 2,
        ])
        ->assertRedirect();

    expect($package->fresh()->points_allocated)->toBe(2000);
});

test('admin can toggle a package active', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $package = SubscriptionPackage::create([
        'name' => 'Jara',
        'slug' => 'jara',
        'renewal_cycle' => PackageRenewalCycle::DAILY,
        'points_allocated' => 1500,
        'price_naira' => 100,
        'is_active' => true,
        'sort_order' => 2,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.subscription-packages.toggle-active', $package))
        ->assertRedirect();

    expect($package->fresh()->is_active)->toBeFalse();
});

test('admin cannot delete a package that has subscribers', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $package = SubscriptionPackage::create([
        'name' => 'Jara',
        'slug' => 'jara',
        'renewal_cycle' => PackageRenewalCycle::DAILY,
        'points_allocated' => 1500,
        'price_naira' => 100,
        'is_active' => true,
        'sort_order' => 2,
    ]);
    $user = User::factory()->create();
    PointSubscription::create([
        'user_id' => $user->id,
        'subscription_package_id' => $package->id,
        'amount_naira' => 100,
        'status' => SubscriptionStatus::ACTIVE,
    ]);

    $this->actingAs($admin)
        ->delete(route('admin.subscription-packages.destroy', $package))
        ->assertSessionHasErrors('error');

    $this->assertDatabaseHas('subscription_packages', ['id' => $package->id]);
});

test('admin can delete a package with no subscribers', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $package = SubscriptionPackage::create([
        'name' => 'Jara',
        'slug' => 'jara',
        'renewal_cycle' => PackageRenewalCycle::DAILY,
        'points_allocated' => 1500,
        'price_naira' => 100,
        'is_active' => true,
        'sort_order' => 2,
    ]);

    $this->actingAs($admin)
        ->delete(route('admin.subscription-packages.destroy', $package))
        ->assertRedirect();

    $this->assertDatabaseMissing('subscription_packages', ['id' => $package->id]);
});
