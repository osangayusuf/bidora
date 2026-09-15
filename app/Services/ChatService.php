<?php

namespace App\Services;

use App\Enums\AgentStatus;
use App\Enums\ChatSenderType;
use App\Enums\ChatSessionStatus;
use App\Events\Support\AgentClaimedSession;
use App\Events\Support\ChatMessageSent;
use App\Events\Support\ChatSessionClosed;
use App\Events\Support\NewChatSessionCreated;
use App\Exceptions\ChatSessionLimitExceededException;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\NewChatSessionNotification;
use App\Notifications\TicketConvertedFromChatNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class ChatService
{
    /**
     * Maximum number of concurrent open (waiting or active) sessions a single customer or
     * guest may have at once, to stop one visitor from flooding the queue.
     */
    public const MAX_OPEN_SESSIONS = 3;

    /**
     * Create a new ChatService instance.
     */
    public function __construct(
        private readonly TicketService $ticketService
    ) {}

    /**
     * Initiate a new chat session for a customer or guest.
     *
     * $message is the customer's opening message, required so that a session is never
     * created (and never shown to agents) with nothing in it — previously a visitor could
     * fill in the pre-chat name/email fields, hit "Start Live Chat", and abandon the widget
     * before typing anything, leaving agents an empty session in the queue that looked
     * exactly like a lost/blank message.
     *
     * $guestToken is a long-lived, cookie-backed identifier for anonymous visitors (see
     * CustomerChatController), used in place of a customer_id to track ownership and enforce
     * the open-session cap for guests.
     *
     * @throws ChatSessionLimitExceededException if the customer/guest already has
     *                                           MAX_OPEN_SESSIONS open sessions.
     */
    public function initiate(?User $customer, array $guestData, string $message, ?string $guestToken = null): ChatSession
    {
        $session = DB::transaction(function () use ($customer, $guestData, $guestToken): ChatSession {
            $openSessions = ChatSession::whereIn('status', [ChatSessionStatus::WAITING, ChatSessionStatus::ACTIVE])
                ->when($customer, fn ($q) => $q->where('customer_id', $customer->id))
                ->when(! $customer, fn ($q) => $q->where('guest_token', $guestToken))
                ->count();

            if ($openSessions >= self::MAX_OPEN_SESSIONS) {
                throw new ChatSessionLimitExceededException(
                    'You already have '.self::MAX_OPEN_SESSIONS.' open conversations. Please continue one of those, or wait for it to be resolved, before starting a new one.'
                );
            }

            return ChatSession::create([
                'uuid' => (string) Str::uuid(),
                'customer_id' => $customer?->id,
                'customer_name' => $customer ? $customer->name : ($guestData['name'] ?? 'Guest'),
                'customer_email' => $customer ? $customer->email : ($guestData['email'] ?? null),
                'guest_token' => $customer ? null : $guestToken,
                'status' => ChatSessionStatus::WAITING,
            ]);
        });

        // Record the customer's opening message before ever notifying/broadcasting the new
        // session, so agents always have something to go on the moment they see it.
        $this->addMessage($session, $customer, $message, ChatSenderType::CUSTOMER);

        // Broadcast and notify only once the session is safely committed, so a hiccup in
        // real-time delivery (the broadcast server being briefly unreachable, for example)
        // can never roll back — or fail — the chat session itself.
        $this->safeBroadcast(fn () => broadcast(new NewChatSessionCreated($session))->toOthers());

        // Send notification to all online agents/admins
        $onlineAgents = User::role(['agent', 'admin'])
            ->where(function ($query) {
                $query->where(function ($q) {
                    $q->whereHas('roles', fn ($r) => $r->where('name', 'agent'))
                        ->whereNotNull('agent_approved_at');
                })->orWhereHas('roles', fn ($r) => $r->where('name', 'admin'));
            })
            ->whereHas('agentStatus', function ($query) {
                $query->where('status', AgentStatus::ONLINE);
            })
            ->get();

        Notification::send($onlineAgents, new NewChatSessionNotification($session));

        return $session;
    }

    /**
     * Claim a waiting chat session by an agent.
     */
    public function claimSession(ChatSession $session, User $agent): void
    {
        if (! $session->isWaiting()) {
            throw new \Exception('This chat session is already claimed or closed.');
        }

        DB::transaction(function () use ($session, $agent): void {
            $session->update([
                'agent_id' => $agent->id,
                'status' => ChatSessionStatus::ACTIVE,
                'started_at' => now(),
            ]);
        });

        // Add a system message notifying that the agent joined (this safely broadcasts its
        // own ChatMessageSent event once committed — see addMessage()).
        $this->addMessage($session, null, "{$agent->agent_display_name} has joined the chat.", ChatSenderType::SYSTEM);

        // Broadcast that the session has been claimed, after the update is committed.
        $this->safeBroadcast(fn () => broadcast(new AgentClaimedSession($session))->toOthers());
    }

    /**
     * Add a message (from customer, agent, or system) to the chat session.
     */
    public function addMessage(ChatSession $session, ?User $sender, string $body, ChatSenderType $type): ChatMessage
    {
        $message = DB::transaction(function () use ($session, $sender, $body, $type): ChatMessage {
            return ChatMessage::create([
                'chat_session_id' => $session->id,
                'sender_id' => $sender?->id,
                'sender_type' => $type,
                'body' => $body,
            ]);
        });

        // Broadcast only after the message is safely committed, and never let a broadcast
        // failure (e.g. the Reverb/Pusher server being briefly unreachable) surface as an
        // error — the message is already saved either way.
        $this->safeBroadcast(fn () => broadcast(new ChatMessageSent($message))->toOthers());

        return $message;
    }

    /**
     * Run a broadcast, logging (rather than throwing) if it fails. Real-time delivery is a
     * best-effort convenience on top of already-persisted data — a broadcast server hiccup
     * should never roll back a database write or turn into a 500 for the caller.
     */
    private function safeBroadcast(callable $broadcast): void
    {
        try {
            $broadcast();
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Get a summary of every chat session belonging to a customer or guest, most recently
     * updated first, for the "your conversations" list in the chat widget.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function sessionsFor(?User $customer, ?string $guestToken): Collection
    {
        if (! $customer && ! $guestToken) {
            return collect();
        }

        return ChatSession::query()
            ->when($customer, fn ($q) => $q->where('customer_id', $customer->id))
            ->when(! $customer, fn ($q) => $q->where('guest_token', $guestToken))
            ->with(['agent:id,name', 'latestMessage'])
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (ChatSession $session): array => [
                'uuid' => $session->uuid,
                'status' => $session->status->value,
                'agent' => $session->agent ? [
                    'id' => $session->agent->id,
                    'name' => $session->agent->agent_display_name,
                ] : null,
                'last_message' => $session->latestMessage?->body,
                'last_message_at' => $session->latestMessage?->created_at?->toIso8601String(),
                'created_at' => $session->created_at->toIso8601String(),
                'updated_at' => $session->updated_at->toIso8601String(),
            ]);
    }

    /**
     * Close an active or waiting chat session.
     */
    public function closeSession(ChatSession $session): void
    {
        if ($session->isClosed()) {
            return;
        }

        DB::transaction(function () use ($session): void {
            $session->update([
                'status' => ChatSessionStatus::CLOSED,
                'closed_at' => now(),
            ]);
        });

        // Add a system message notifying that the chat is closed (safely broadcasts its own
        // ChatMessageSent — see addMessage()).
        $this->addMessage($session, null, 'The chat session has been closed.', ChatSenderType::SYSTEM);

        // Broadcast that the session has been closed, after the update is committed.
        $this->safeBroadcast(fn () => broadcast(new ChatSessionClosed($session))->toOthers());
    }

    /**
     * Convert the chat session history into a support ticket.
     */
    public function convertToTicket(ChatSession $session, array $ticketData): Ticket
    {
        if ($session->ticket_id !== null) {
            throw new \Exception('This chat session has already been converted to a ticket.');
        }

        return DB::transaction(function () use ($session, $ticketData): Ticket {
            // Tickets always belong to a real user account, but chat sessions allow guests
            // (no customer_id). Resolve or provision an account before creating the ticket.
            $customer = $this->resolveCustomerForConversion($session);

            // Use the ticket service to create the ticket and import messages
            $ticket = $this->ticketService->createFromChat($session, $customer, $ticketData);

            // Set the ticket ID on the chat session
            $session->update([
                'ticket_id' => $ticket->id,
            ]);

            // Add a system message to the chat session indicating conversion
            $this->addMessage(
                $session,
                null,
                "This chat session has been converted to a support ticket: {$ticket->subject}.",
                ChatSenderType::SYSTEM
            );

            // Email the customer, since the system chat message above is only visible if
            // they're still watching the live chat widget.
            $customer->notify(new TicketConvertedFromChatNotification($ticket));

            return $ticket;
        });
    }

    /**
     * Resolve the user a converted ticket should be attributed to.
     *
     * Authenticated sessions already have a customer_id. Guest sessions don't, so we
     * find-or-create an account by the guest's email — mirroring the guest flow used for
     * offline ticket submissions (see CustomerChatController::submitOfflineTicket) — and
     * link it back onto the session so future lookups/replies attribute correctly.
     *
     * @throws \Exception if the session is a guest session with no email on file, since
     *                    there is then no identity to attach the ticket to.
     */
    private function resolveCustomerForConversion(ChatSession $session): User
    {
        if ($session->customer_id !== null) {
            return $session->customer ?? User::findOrFail($session->customer_id);
        }

        if (empty($session->customer_email)) {
            throw new \Exception('Cannot convert to a ticket: this guest has not provided an email address. Ask for their email in the chat first.');
        }

        $customer = User::firstOrCreate(
            ['email' => $session->customer_email],
            [
                'name' => $session->customer_name ?: 'Guest',
                'password' => bcrypt(Str::random(16)),
            ]
        );

        $session->update(['customer_id' => $customer->id]);

        return $customer;
    }
}
