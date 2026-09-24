<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

test('non-admin users cannot access agent approval pages or actions', function () {
    $user = User::factory()->create();
    $user->assignRole('user');

    $agent = User::factory()->pendingAgent()->create();
    $agent->assignRole('agent');

    // Index
    $this->actingAs($user)->get(route('admin.agents.index'))->assertForbidden();

    // Approve
    $this->actingAs($user)->post(route('admin.agents.approve', $agent))->assertForbidden();

    // Reject
    $this->actingAs($user)->post(route('admin.agents.reject', $agent))->assertForbidden();
});

test('agents themselves cannot access agent approval pages or actions', function () {
    $approvedAgent = User::factory()->approvedAgent()->create();
    $approvedAgent->assignRole('agent');

    $agent = User::factory()->pendingAgent()->create();
    $agent->assignRole('agent');

    // Index
    $this->actingAs($approvedAgent)->get(route('admin.agents.index'))->assertForbidden();

    // Approve
    $this->actingAs($approvedAgent)->post(route('admin.agents.approve', $agent))->assertForbidden();

    // Reject
    $this->actingAs($approvedAgent)->post(route('admin.agents.reject', $agent))->assertForbidden();
});

test('admin can view agent approval index page', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $pendingAgent = User::factory()->pendingAgent()->create();
    $pendingAgent->assignRole('agent');

    $approvedAgent = User::factory()->approvedAgent()->create();
    $approvedAgent->assignRole('agent');

    $response = $this->actingAs($admin)->get(route('admin.agents.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Admin/Agents/Index')
        ->has('pending', 1)
        ->has('approved', 1)
    );
});

test('admin can approve a pending agent', function () {
    Notification::fake();

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $agent = User::factory()->pendingAgent()->create();
    $agent->assignRole('agent');

    $response = $this->actingAs($admin)->post(route('admin.agents.approve', $agent));

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $agent = $agent->fresh();
    expect($agent->agent_approved_at)->not->toBeNull()
        ->and($agent->approved_by)->toBe($admin->id)
        ->and($agent->isApprovedAgent())->toBeTrue();

    // Mail delivery for this notification is disabled.
    Notification::assertNothingSent();
});

test('admin can reject a pending agent and delete account', function () {
    Notification::fake();

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $agent = User::factory()->pendingAgent()->create();
    $agent->assignRole('agent');

    $response = $this->actingAs($admin)->post(route('admin.agents.reject', $agent));

    $response->assertRedirect();
    $response->assertSessionHas('success');

    // Agent account should be deleted from DB
    $this->assertDatabaseMissing('users', [
        'id' => $agent->id,
    ]);

    // Mail delivery for this notification is disabled.
    Notification::assertNothingSent();
});

test('pending agents count is shared correctly with admin users', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $agent1 = User::factory()->pendingAgent()->create();
    $agent1->assignRole('agent');

    $agent2 = User::factory()->pendingAgent()->create();
    $agent2->assignRole('agent');

    // Approved agent shouldn't count
    $agent3 = User::factory()->approvedAgent()->create();
    $agent3->assignRole('agent');

    $response = $this->actingAs($admin)->get(route('admin.users.index'));

    $response->assertInertia(fn ($page) => $page
        ->where('auth.pending_agents_count', 2)
    );
});
