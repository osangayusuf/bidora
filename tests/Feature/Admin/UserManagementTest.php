<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin']);
    Role::firstOrCreate(['name' => 'user']);
});

test('admin can view user list', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $this->actingAs($admin);

    // Create a few regular users
    User::factory()->count(3)->create();

    $response = $this->get(route('admin.users.index'));
    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Admin/Users/Index')
        ->has('users.data')
    );
});

test('admin can toggle is_active status of other users', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $this->actingAs($admin);

    $user = User::factory()->create(['is_active' => true]);

    $response = $this->post(route('admin.users.toggle-active', $user));
    $response->assertRedirect();

    $user->refresh();
    expect($user->is_active)->toBeFalse();

    $response = $this->post(route('admin.users.toggle-active', $user));
    $response->assertRedirect();

    $user->refresh();
    expect($user->is_active)->toBeTrue();
});

test('admin cannot toggle is_active status of their own account', function () {
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('admin');
    $this->actingAs($admin);

    $response = $this->post(route('admin.users.toggle-active', $admin));
    $response->assertSessionHasErrors('error');

    $admin->refresh();
    expect($admin->is_active)->toBeTrue();
});

test('admin can bulk disable multiple users', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $this->actingAs($admin);

    $users = User::factory()->count(3)->create(['is_active' => true]);

    $response = $this->post(route('admin.users.bulk-disable'), [
        'user_ids' => $users->pluck('id')->all(),
    ]);
    $response->assertRedirect();

    $users->each(function (User $user) {
        $user->refresh();
        expect($user->is_active)->toBeFalse();
    });
});

test('admin bulk disable skips their own account without failing the batch', function () {
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('admin');
    $this->actingAs($admin);

    $user = User::factory()->create(['is_active' => true]);

    $response = $this->post(route('admin.users.bulk-disable'), [
        'user_ids' => [$admin->id, $user->id],
    ]);
    $response->assertRedirect();

    $admin->refresh();
    $user->refresh();

    expect($admin->is_active)->toBeTrue();
    expect($user->is_active)->toBeFalse();
});

test('admin bulk disable requires at least one user id', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $this->actingAs($admin);

    $response = $this->post(route('admin.users.bulk-disable'), [
        'user_ids' => [],
    ]);
    $response->assertSessionHasErrors('user_ids');
});

test('admin can change role of other users', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $this->actingAs($admin);

    $user = User::factory()->create();
    $user->assignRole('user');

    $response = $this->post(route('admin.users.role', $user), [
        'role' => 'admin',
    ]);
    $response->assertRedirect();

    expect($user->hasRole('admin'))->toBeTrue();
});

test('admin cannot demote their own administrative role', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $this->actingAs($admin);

    $response = $this->post(route('admin.users.role', $admin), [
        'role' => 'user',
    ]);
    $response->assertSessionHasErrors('error');

    expect($admin->hasRole('admin'))->toBeTrue();
});
