<?php

use App\Enums\ChatSenderType;
use App\Enums\ChatSessionStatus;
use App\Enums\TicketCategory;
use App\Enums\TicketPriority;
use App\Models\AgentStatus;
use App\Models\ChatSession;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\NewChatSessionNotification;
use App\Notifications\NewTicketCreatedNotification;
use App\Notifications\TicketConvertedFromChatNotification;
use App\Notifications\TicketMessageReceivedNotification;
use App\Notifications\TicketReplyNotification;
use App\Services\ChatService;
use App\Services\TicketService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

test('agents can retrieve their notifications index page', function () {
    $agent = User::factory()->approvedAgent()->create();
    $agent->assignRole('agent');

    // Create a dummy notification
    $agent->notify(new NewChatSessionNotification(
        ChatSession::factory()->create(['customer_name' => 'John Doe'])
    ));

    $response = $this->actingAs($agent)
        ->get(route('support.notifications.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Support/Notifications/Index')
        ->has('notificationsList', 1)
        ->where('notificationsList.0.title', 'New Chat Session')
    );
});

test('agent can mark a notification as read', function () {
    $agent = User::factory()->approvedAgent()->create();
    $agent->assignRole('agent');

    $agent->notify(new NewChatSessionNotification(
        ChatSession::factory()->create(['customer_name' => 'John Doe'])
    ));

    $notification = $agent->unreadNotifications->first();
    expect($notification)->not->toBeNull();

    $response = $this->actingAs($agent)
        ->patch(route('support.notifications.read', $notification->id));

    $response->assertOk();
    expect($notification->fresh()->read())->toBeTrue();
});

test('agent can mark all notifications as read', function () {
    $agent = User::factory()->approvedAgent()->create();
    $agent->assignRole('agent');

    $agent->notify(new NewChatSessionNotification(
        ChatSession::factory()->create(['customer_name' => 'John Doe'])
    ));
    $agent->notify(new NewChatSessionNotification(
        ChatSession::factory()->create(['customer_name' => 'Jane Doe'])
    ));

    expect($agent->unreadNotifications->count())->toBe(2);

    $response = $this->actingAs($agent)
        ->patch(route('support.notifications.read-all'));

    $response->assertOk();
    expect($agent->fresh()->unreadNotifications->count())->toBe(0);
});

test('new chat session sends notification to online agents and admins', function () {
    Notification::fake();

    // Create online agent
    $agent = User::factory()->approvedAgent()->create();
    $agent->assignRole('agent');
    AgentStatus::factory()->online()->create(['user_id' => $agent->id]);

    // Create offline agent (should not be notified)
    $offlineAgent = User::factory()->approvedAgent()->create();
    $offlineAgent->assignRole('agent');
    AgentStatus::factory()->offline()->create(['user_id' => $offlineAgent->id]);

    // Initiate chat
    $customer = User::factory()->create();
    app(ChatService::class)->initiate($customer, [], 'Hello, I need help.');

    Notification::assertSentTo(
        [$agent],
        NewChatSessionNotification::class
    );

    Notification::assertNotSentTo(
        [$offlineAgent],
        NewChatSessionNotification::class
    );
});

test('new ticket sends notification to online agents and admins', function () {
    Notification::fake();

    // Create online agent
    $agent = User::factory()->approvedAgent()->create();
    $agent->assignRole('agent');
    AgentStatus::factory()->online()->create(['user_id' => $agent->id]);

    // Create offline agent
    $offlineAgent = User::factory()->approvedAgent()->create();
    $offlineAgent->assignRole('agent');
    AgentStatus::factory()->offline()->create(['user_id' => $offlineAgent->id]);

    $customer = User::factory()->create();

    // Create ticket manually
    app(TicketService::class)->createManually($agent, [
        'customer_id' => $customer->id,
        'subject' => 'Manual Ticket',
        'category' => TicketCategory::GENERAL->value,
        'priority' => TicketPriority::NORMAL->value,
        'body' => 'Body text',
    ]);

    Notification::assertSentTo(
        [$agent],
        NewTicketCreatedNotification::class
    );

    Notification::assertNotSentTo(
        [$offlineAgent],
        NewTicketCreatedNotification::class
    );
});

test('customer reply on ticket sends notification to assigned agent', function () {
    Notification::fake();

    $agent = User::factory()->approvedAgent()->create();
    $agent->assignRole('agent');

    $customer = User::factory()->create();

    $ticket = Ticket::factory()->inProgress($agent)->create([
        'customer_id' => $customer->id,
    ]);

    // Reply from customer
    app(TicketService::class)->addReply($ticket, $customer, 'Hello agent', false);

    Notification::assertSentTo(
        $agent,
        TicketMessageReceivedNotification::class
    );
});

test('staff reply on a ticket emails the customer', function () {
    Notification::fake();

    $agent = User::factory()->approvedAgent()->create();
    $agent->assignRole('agent');

    $customer = User::factory()->create();

    $ticket = Ticket::factory()->inProgress($agent)->create([
        'customer_id' => $customer->id,
    ]);

    app(TicketService::class)->addReply($ticket, $agent, 'We are looking into it', false);

    Notification::assertSentTo(
        $customer,
        TicketReplyNotification::class
    );
});

test('internal notes do not email the customer', function () {
    Notification::fake();

    $agent = User::factory()->approvedAgent()->create();
    $agent->assignRole('agent');

    $customer = User::factory()->create();

    $ticket = Ticket::factory()->inProgress($agent)->create([
        'customer_id' => $customer->id,
    ]);

    app(TicketService::class)->addReply($ticket, $agent, 'Internal note, not for customer', true);

    Notification::assertNotSentTo(
        $customer,
        TicketReplyNotification::class
    );
});

test('a customer replying to their own ticket does not email themselves', function () {
    Notification::fake();

    $agent = User::factory()->approvedAgent()->create();
    $agent->assignRole('agent');

    $customer = User::factory()->create();

    $ticket = Ticket::factory()->inProgress($agent)->create([
        'customer_id' => $customer->id,
    ]);

    app(TicketService::class)->addReply($ticket, $customer, 'Any update?', false);

    Notification::assertNotSentTo(
        $customer,
        TicketReplyNotification::class
    );
});

test('converting a chat session to a ticket does not email either party about replayed history', function () {
    Notification::fake();

    $agent = User::factory()->approvedAgent()->create();
    $agent->assignRole('agent');

    $customer = User::factory()->create();

    $session = ChatSession::factory()->create([
        'customer_id' => $customer->id,
        'customer_name' => $customer->name,
        'customer_email' => $customer->email,
        'agent_id' => $agent->id,
        'status' => ChatSessionStatus::ACTIVE,
    ]);

    app(ChatService::class)->addMessage($session, $customer, 'Hi, I need help', ChatSenderType::CUSTOMER);
    app(ChatService::class)->addMessage($session, $agent, 'Sure, what is wrong?', ChatSenderType::AGENT);

    app(ChatService::class)->convertToTicket($session, [
        'subject' => 'Chat converted',
        'category' => TicketCategory::GENERAL->value,
        'priority' => TicketPriority::NORMAL->value,
    ]);

    Notification::assertNotSentTo($agent, TicketMessageReceivedNotification::class);
    Notification::assertNotSentTo($customer, TicketReplyNotification::class);

    // The conversion itself is still communicated, just via a dedicated notification.
    Notification::assertSentTo($customer, TicketConvertedFromChatNotification::class);
});

test('converting a chat session to a ticket emails the customer', function () {
    Notification::fake();

    $agent = User::factory()->approvedAgent()->create();
    $agent->assignRole('agent');

    $customer = User::factory()->create();

    $session = ChatSession::factory()->create([
        'customer_id' => $customer->id,
        'customer_name' => $customer->name,
        'customer_email' => $customer->email,
        'agent_id' => $agent->id,
        'status' => ChatSessionStatus::ACTIVE,
    ]);

    $ticket = app(ChatService::class)->convertToTicket($session, [
        'subject' => 'Chat converted',
        'category' => TicketCategory::GENERAL->value,
        'priority' => TicketPriority::NORMAL->value,
    ]);

    Notification::assertSentTo(
        $customer,
        TicketConvertedFromChatNotification::class,
        fn ($notification) => $notification->ticket->is($ticket)
    );
});

test('converting a guest chat session to a ticket emails the newly provisioned customer', function () {
    Notification::fake();

    $agent = User::factory()->approvedAgent()->create();
    $agent->assignRole('agent');

    $session = ChatSession::factory()->guest()->create([
        'customer_name' => 'Guest User',
        'customer_email' => 'guest_notify@example.com',
        'agent_id' => $agent->id,
        'status' => ChatSessionStatus::ACTIVE,
    ]);

    app(ChatService::class)->convertToTicket($session, [
        'subject' => 'Guest chat converted',
        'category' => TicketCategory::GENERAL->value,
        'priority' => TicketPriority::NORMAL->value,
    ]);

    $guestUser = User::where('email', 'guest_notify@example.com')->firstOrFail();

    Notification::assertSentTo($guestUser, TicketConvertedFromChatNotification::class);
});
