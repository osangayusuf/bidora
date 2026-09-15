<?php

namespace App\Http\Controllers\Support;

use App\Enums\ChatSenderType;
use App\Exceptions\ChatSessionLimitExceededException;
use App\Http\Controllers\Controller;
use App\Models\AgentStatus;
use App\Models\ChatSession;
use App\Models\Ticket;
use App\Models\User;
use App\Services\ChatService;
use App\Services\TicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

class CustomerChatController extends Controller
{
    /**
     * Cookie used to give an anonymous guest a stable, long-lived identity so they can be
     * shown all of their past chat sessions on return visits, not just the most recent one.
     */
    private const GUEST_TOKEN_COOKIE = 'bidora_guest_token';

    /** Keep the guest identity around for a year. */
    private const GUEST_TOKEN_TTL_MINUTES = 60 * 24 * 365;

    /**
     * Create a new CustomerChatController instance.
     */
    public function __construct(
        private readonly ChatService $chatService,
        private readonly TicketService $ticketService
    ) {}

    /**
     * Check if any support agent is currently online.
     */
    public function status(): JsonResponse
    {
        $anyOnline = AgentStatus::where('status', \App\Enums\AgentStatus::ONLINE)->exists();

        return response()->json([
            'online' => $anyOnline,
        ]);
    }

    /**
     * Initiate a new support chat session.
     */
    public function initiate(Request $request): JsonResponse
    {
        $user = Auth::user();

        if (! $user) {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|email|max:255',
                'message' => 'required|string',
            ]);
            $guestData = [
                'name' => $validated['name'],
                'email' => $validated['email'],
            ];
        } else {
            $validated = $request->validate([
                'message' => 'required|string',
            ]);
            $guestData = [];
        }

        $guestToken = $user ? null : $this->currentOrNewGuestToken($request);

        try {
            $session = $this->chatService->initiate($user, $guestData, $validated['message'], $guestToken);
        } catch (ChatSessionLimitExceededException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        if (! $user) {
            // Keep track of the active session UUID in the PHP session for guest authorization
            session(['active_chat_session' => $session->uuid]);
        }

        $session->load('messages');

        return response()->json([
            'uuid' => $session->uuid,
            'customer_name' => $session->customer_name,
            'customer_email' => $session->customer_email,
            'status' => $session->status->value,
            'messages' => $session->messages,
        ]);
    }

    /**
     * List every chat session belonging to the current customer or guest, most recent first,
     * so the widget can show "your conversations" and let them resume any of them.
     */
    public function sessions(Request $request): JsonResponse
    {
        $user = Auth::user();
        $guestToken = $user ? null : $request->cookie(self::GUEST_TOKEN_COOKIE);

        return response()->json([
            'sessions' => $this->chatService->sessionsFor($user, $guestToken),
        ]);
    }

    /**
     * Get all messages for a given chat session (customer-facing).
     */
    public function getMessages(string $uuid, Request $request): JsonResponse
    {
        $session = ChatSession::where('uuid', $uuid)->firstOrFail();

        $this->authorizeSessionAccess($session);

        $messages = $session->messages()
            ->with('sender:id,name')
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function ($message) {
                if ($message->sender_type === ChatSenderType::AGENT && $message->sender) {
                    $message->sender->name = $message->sender->agent_display_name;
                }

                return $message;
            });

        return response()->json([
            'status' => $session->status->value,
            'agent' => $session->agent ? [
                'id' => $session->agent->id,
                'name' => $session->agent->agent_display_name,
            ] : null,
            'messages' => $messages,
        ]);
    }

    /**
     * Send a new message to the chat session (customer-facing).
     */
    public function sendMessage(string $uuid, Request $request): JsonResponse
    {
        $session = ChatSession::where('uuid', $uuid)->firstOrFail();

        $this->authorizeSessionAccess($session);

        $validated = $request->validate([
            'body' => 'required|string',
        ]);

        $message = $this->chatService->addMessage(
            $session,
            Auth::user(),
            $validated['body'],
            ChatSenderType::CUSTOMER
        );

        $message->load('sender:id,name');

        if ($message->sender_type === ChatSenderType::AGENT && $message->sender) {
            $message->sender->name = $message->sender->agent_display_name;
        }

        return response()->json($message);
    }

    /**
     * Validate that the current user or guest session is authorized to access the chat session.
     */
    private function authorizeSessionAccess(ChatSession $session): void
    {
        $user = Auth::user();

        // Agents/admins can access any session
        if ($user && ($user->isAdmin() || $user->isApprovedAgent())) {
            return;
        }

        // Authenticated customer must match the session owner
        if ($session->customer_id !== null) {
            if ($user && (int) $user->id === (int) $session->customer_id) {
                return;
            }
            abort(403, 'Unauthorized access to this chat session.');
        }

        // Guest session UUID must match the one stored in PHP session (covers the session
        // just created this visit, even before a guest token cookie round-trips back to us)
        if (session('active_chat_session') === $session->uuid) {
            return;
        }

        // ...or the guest's persistent token must match the one the session was created
        // with, so returning guests can reach any of their older sessions too, not just the
        // single most recent one tracked above.
        if ($session->guest_token !== null && request()->cookie(self::GUEST_TOKEN_COOKIE) === $session->guest_token) {
            return;
        }

        abort(403, 'Unauthorized access to this chat session.');
    }

    /**
     * Read the guest's persistent identity cookie, minting and queueing a new one if it's
     * missing (first-ever visit, or cookies were previously cleared).
     */
    private function currentOrNewGuestToken(Request $request): string
    {
        $token = $request->cookie(self::GUEST_TOKEN_COOKIE);

        if ($token) {
            return $token;
        }

        $token = (string) Str::uuid();
        Cookie::queue(self::GUEST_TOKEN_COOKIE, $token, self::GUEST_TOKEN_TTL_MINUTES);

        return $token;
    }

    /**
     * Submit a support ticket when all agents are offline.
     */
    public function submitOfflineTicket(Request $request): JsonResponse
    {
        $user = Auth::user();

        if (! $user) {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|email|max:255',
                'body' => 'required|string',
            ]);

            // Find or create guest user
            $customer = User::firstOrCreate(
                ['email' => $validated['email']],
                [
                    'name' => $validated['name'],
                    'password' => bcrypt(Str::random(16)),
                ]
            );
            $body = $validated['body'];
        } else {
            $validated = $request->validate([
                'body' => 'required|string',
            ]);
            $customer = $user;
            $body = $validated['body'];
        }

        $this->ticketService->createFromOffline($customer, $body);

        return response()->json([
            'success' => true,
            'message' => 'Your support ticket has been created successfully.',
        ]);
    }
}
