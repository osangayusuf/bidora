<?php

use App\Enums\ActivityType;
use App\Enums\SubscriptionStatus;
use App\Models\PaystackPlan;
use App\Models\PointSubscription;
use App\Models\User;
use App\Models\UserActivity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function adminUser(): User
{
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    return $admin;
}

function subscriptionFor(User $user, SubscriptionStatus $status): PointSubscription
{
    $plan = PaystackPlan::create([
        'amount_kobo' => 500000,
        'interval' => 'monthly',
        'plan_code' => 'PLN_test_'.uniqid(),
        'name' => 'Test Plan',
    ]);

    return PointSubscription::create([
        'user_id' => $user->id,
        'paystack_plan_id' => $plan->id,
        'amount_naira' => 5000,
        'frequency' => 'monthly',
        'status' => $status->value,
    ]);
}

test('non-admins cannot view the daily reports page', function () {
    $user = User::factory()->create();
    $user->assignRole('user');

    $this->actingAs($user)->get(route('admin.reports.index'))->assertForbidden();
});

test('daily report counts visits and engagement activities within the selected day, excluding other days', function () {
    $admin = adminUser();
    $user = User::factory()->create();

    // Inside today (business timezone)
    UserActivity::factory()->for($user)->create([
        'type' => ActivityType::HOME_VIEWED->value,
        'created_at' => now(),
    ]);
    UserActivity::factory()->for($user)->create([
        'type' => ActivityType::BID_PLACED->value,
        'created_at' => now(),
    ]);

    // Outside the window — should not be counted
    UserActivity::factory()->for($user)->create([
        'type' => ActivityType::HOME_VIEWED->value,
        'created_at' => now()->subDays(3),
    ]);

    $response = $this->actingAs($admin)->get(route('admin.reports.index', ['period' => 'daily']));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Admin/Reports/Index')
        ->where('summary.visits', 1)
        ->where('summary.engagement', 2)
    );
});

test('a user with a currently-active subscription is counted as an active subscriber', function () {
    $admin = adminUser();
    $activeSubscriber = User::factory()->create();
    $cancelledSubscriber = User::factory()->create();
    $neverSubscribed = User::factory()->create();

    subscriptionFor($activeSubscriber, SubscriptionStatus::ACTIVE);
    subscriptionFor($cancelledSubscriber, SubscriptionStatus::CANCELLED);

    $response = $this->actingAs($admin)->get(route('admin.reports.index'));

    $response->assertInertia(fn (Assert $page) => $page
        ->where('activeUsers.data', fn ($users) => collect($users)->pluck('id')->contains($activeSubscriber->id))
        ->where('nonActiveUsers.data', fn ($users) => collect($users)->pluck('id')->contains($cancelledSubscriber->id)
            && ! collect($users)->pluck('id')->contains($neverSubscribed->id))
    );
});

test('a user with no subscription record at all is neither an active nor a non-active subscriber', function () {
    $admin = adminUser();
    $neverSubscribed = User::factory()->create();

    $response = $this->actingAs($admin)->get(route('admin.reports.index'));

    $response->assertInertia(fn (Assert $page) => $page
        ->where('activeUsers.data', fn ($users) => ! collect($users)->pluck('id')->contains($neverSubscribed->id))
        ->where('nonActiveUsers.data', fn ($users) => ! collect($users)->pluck('id')->contains($neverSubscribed->id))
    );
});

test('active and non-active subscriber counts do not change with the period filter, since subscription status has no history', function () {
    $admin = adminUser();
    $activeSubscriber = User::factory()->create();
    subscriptionFor($activeSubscriber, SubscriptionStatus::ACTIVE);

    $daily = $this->actingAs($admin)->get(route('admin.reports.index', ['period' => 'daily']));
    $monthly = $this->actingAs($admin)->get(route('admin.reports.index', ['period' => 'monthly']));

    $daily->assertInertia(fn (Assert $page) => $page->where('summary.active_count', 1));
    $monthly->assertInertia(fn (Assert $page) => $page->where('summary.active_count', 1));
});

test('the PDF export downloads with the correct content type', function () {
    $admin = adminUser();

    $response = $this->actingAs($admin)->get(route('admin.reports.download', ['period' => 'daily']));

    $response->assertOk();
    $response->assertHeader('content-type', 'application/pdf');
});
