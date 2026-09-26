<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
});

it('renders task center for authenticated users', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('tasks'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('TaskCenter/Index')
            ->has('checkin')
            ->has('achievements')
            ->has('spin')
            ->has('leaderboard')
            ->has('wallet')
            ->where('wallet.unclaimed_points', (int) $user->bonus_points)
            ->where('user_points', (int) $user->points_balance)
        );
});

it('requires authentication for task center', function () {
    $this->get(route('tasks'))->assertRedirect();
});

it('shows the configured referral reward amounts on the task center page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('tasks'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->where('referral.signup_reward_points', (int) config('points.referral.referrer_signup_points'))
            ->where('referral.deposit_reward_points', (int) config('points.referral.referrer_first_deposit_points'))
        );
});

it('can check in via post', function () {
    $user = User::factory()->create(['last_checkin_date' => null]);

    $this->actingAs($user)
        ->post(route('checkin'))
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($user->fresh()->last_checkin_date?->toDateString())->toBe(now()->toDateString());
});

it('can claim rewards from task center', function () {
    $user = User::factory()->create(['bonus_points' => 40, 'points_balance' => 0]);

    $this->actingAs($user)
        ->post(route('rewards.claim'))
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($user->fresh()->bonus_points)->toBe(0)
        ->and($user->fresh()->points_balance)->toBe(40);
});

it('returns json for spin endpoint', function () {
    $user = User::factory()->create(['spins_balance' => 1]);

    $this->actingAs($user)
        ->postJson(route('spin'))
        ->assertSuccessful()
        ->assertJsonStructure(['points_won', 'segment_index', 'message']);
});
