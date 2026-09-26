<?php

use App\Enums\ActivityType;
use App\Models\Auction;
use App\Models\User;
use App\Models\UserActivity;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ─── Login activity ───────────────────────────────────────────────────────────

test('login_success activity is created on successful login', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertDatabaseHas('user_activities', [
        'user_id' => $user->id,
        'type' => ActivityType::LOGIN_SUCCESS->value,
    ]);
});

test('login_failed activity is created when credentials are wrong', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    expect(UserActivity::where('type', ActivityType::LOGIN_FAILED->value)->exists())->toBeTrue();
});

test('login_failed activity has null user_id when credentials are unknown', function () {
    $this->post(route('login.store'), [
        'email' => 'nobody@example.com',
        'password' => 'wrong-password',
    ]);

    $this->assertDatabaseHas('user_activities', [
        'user_id' => null,
        'type' => ActivityType::LOGIN_FAILED->value,
    ]);
});

// ─── Profile activity ─────────────────────────────────────────────────────────

test('profile_updated activity is created when user updates their profile', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->patch(route('profile.update'), [
        'name' => 'Updated Name',
        'email' => $user->email,
        'phone' => $user->phone,
    ]);

    $this->assertDatabaseHas('user_activities', [
        'user_id' => $user->id,
        'type' => ActivityType::PROFILE_UPDATED->value,
    ]);
});

// ─── Password activity ────────────────────────────────────────────────────────

test('password_changed activity is created when user changes their password', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->put(route('user-password.update'), [
        'current_password' => 'password',
        'password' => 'new-Password1!',
        'password_confirmation' => 'new-Password1!',
    ]);

    $this->assertDatabaseHas('user_activities', [
        'user_id' => $user->id,
        'type' => ActivityType::PASSWORD_CHANGED->value,
    ]);
});

// ─── Factory helpers ──────────────────────────────────────────────────────────

test('UserActivityFactory creates records with a valid type', function () {
    $activity = UserActivity::factory()->ofType(ActivityType::BID_PLACED)->create();

    expect($activity->type)->toBe(ActivityType::BID_PLACED);
});

test('UserActivityFactory asGuest creates a record with null user_id', function () {
    $activity = UserActivity::factory()->asGuest()->create();

    expect($activity->user_id)->toBeNull();
});

// ─── Guest page visit logging ──────────────────────────────────────────────────

test('visiting the home page as a guest logs a home_viewed activity with a null user_id', function () {
    $this->get(route('home'));

    $this->assertDatabaseHas('user_activities', [
        'user_id' => null,
        'type' => ActivityType::HOME_VIEWED->value,
    ]);
});

test('visiting open bids as a guest logs an open_bids_viewed activity', function () {
    $this->get(route('open-bids'));

    $this->assertDatabaseHas('user_activities', [
        'user_id' => null,
        'type' => ActivityType::OPEN_BIDS_VIEWED->value,
    ]);
});

test('visiting an auction page as a guest logs an auction_viewed activity', function () {
    $auction = Auction::factory()->create();

    $this->get(route('auctions.show', $auction));

    $this->assertDatabaseHas('user_activities', [
        'user_id' => null,
        'type' => ActivityType::AUCTION_VIEWED->value,
    ]);
});

test('logged-in users also generate a page-visit activity row, attributed to their account', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('home'));

    $this->assertDatabaseHas('user_activities', [
        'user_id' => $user->id,
        'type' => ActivityType::HOME_VIEWED->value,
    ]);
});

test('repeat guest visits to the same page within the dedupe window only log once', function () {
    $this->get(route('home'));
    $this->get(route('home'));
    $this->get(route('home'));

    expect(UserActivity::where('type', ActivityType::HOME_VIEWED->value)->count())->toBe(1);
});
