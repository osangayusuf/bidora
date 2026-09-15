<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import {
    MessageSquare,
    X,
    Send,
    ChevronDown,
    ChevronLeft,
    Clock,
    User,
    AlertCircle,
    CheckCircle2,
    Loader2,
} from 'lucide-vue-next';
import { ref, onMounted, onUnmounted, nextTick, computed, watch } from 'vue';
import {
    status as chatStatus,
    sessions as chatSessions,
    initiate as chatInitiate,
    messages as chatMessages,
    send as chatSend,
    offlineTicket as chatOfflineTicket,
} from '@/routes/support/chat/api';

type ChatMessage = {
    id: number;
    sender_type: string;
    body: string;
    created_at: string;
};

type ChatConversation = {
    uuid: string;
    status: string;
    agent: { id: number; name: string } | null;
    last_message: string | null;
    last_message_at: string | null;
    created_at: string;
    updated_at: string;
};

// Keep this in sync with ChatService::MAX_OPEN_SESSIONS on the backend.
const MAX_OPEN_CONVERSATIONS = 3;

const page = usePage();
const currentUser = computed(() => page.props.auth?.user);

const isOpen = ref(false);
const isOnline = ref(false);
const isSubmitting = ref(false);

// 'loading' while we work out what to show; 'list' shows past conversations; 'chat' is an
// open conversation thread; 'prechat'/'offline' are the existing start-a-chat forms.
const view = ref<'loading' | 'list' | 'chat' | 'prechat' | 'offline'>(
    'loading',
);
const conversations = ref<ChatConversation[]>([]);

const sessionUuid = ref<string | null>(null);
const sessionStatus = ref('waiting');
const agentName = ref<string | null>(null);
const messages = ref<ChatMessage[]>([]);

const prechatForm = ref({
    name: '',
    email: '',
    message: '',
});

const offlineForm = ref({
    name: '',
    email: '',
    body: '',
});

const messageText = ref('');
const messageContainer = ref<HTMLDivElement | null>(null);

const openConversationsCount = computed(
    () => conversations.value.filter((c) => c.status !== 'closed').length,
);
const atOpenConversationsCap = computed(
    () => openConversationsCount.value >= MAX_OPEN_CONVERSATIONS,
);
const canGoBack = computed(
    () => view.value !== 'list' && conversations.value.length > 0,
);

const headerTitle = computed(() => {
    if (view.value === 'chat') {
        return agentName.value
            ? `Chat with ${agentName.value}`
            : 'Support queue';
    }

    if (view.value === 'list') {
        return 'Your Conversations';
    }

    return 'Bidora Support';
});

const csrfToken = () =>
    (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)
        ?.content || '';

const scrollToBottom = () => {
    nextTick(() => {
        if (messageContainer.value) {
            messageContainer.value.scrollTop =
                messageContainer.value.scrollHeight;
        }
    });
};

const formatConversationTime = (iso: string | null) => {
    if (!iso) {
        return '';
    }

    const date = new Date(iso);
    const sameDay = date.toDateString() === new Date().toDateString();

    return sameDay
        ? date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
        : date.toLocaleDateString([], { month: 'short', day: 'numeric' });
};

const checkStatus = async () => {
    try {
        const res = await fetch(chatStatus.url());
        const data = await res.json();
        isOnline.value = data.online;
    } catch (e) {
        console.error('Failed to check support status', e);
        isOnline.value = false;
    }
};

// Loads (or refreshes) the visitor's list of past/ongoing conversations. This works for both
// logged-in customers (matched server-side by their account) and guests (matched by a
// long-lived cookie), so it survives closing the browser, not just reloading the page.
// Pass autoRoute = false for a background refresh that shouldn't change what's on screen.
const loadConversations = async (autoRoute = true) => {
    try {
        const res = await fetch(chatSessions.url());

        if (res.ok) {
            const data = await res.json();
            conversations.value = data.sessions || [];
        }
    } catch (e) {
        console.error('Failed to load conversations', e);
    }

    if (!autoRoute) {
        return;
    }

    if (conversations.value.length > 0) {
        view.value = 'list';
    } else {
        await checkStatus();
        view.value = isOnline.value ? 'prechat' : 'offline';
    }
};

const connectEcho = (uuid: string) => {
    window.Echo?.channel(`chat.${uuid}`)
        .listen('.ChatMessageSent', (event: any) => {
            const exists = messages.value.some((m) => m.id === event.id);

            if (!exists) {
                messages.value.push({
                    id: event.id,
                    sender_type: event.sender_type,
                    body: event.body,
                    created_at: event.created_at,
                });
                scrollToBottom();
            }
        })
        .listen('.AgentClaimedSession', (event: any) => {
            sessionStatus.value = event.status;
            agentName.value = event.agent_name;
        })
        .listen('.ChatSessionClosed', (event: any) => {
            sessionStatus.value = event.status;
        });
};

const loadHistory = async (uuid: string) => {
    try {
        const res = await fetch(chatMessages.url(uuid));

        if (res.ok) {
            const data = await res.json();
            messages.value = data.messages || [];
            sessionStatus.value = data.status || 'waiting';
            agentName.value = data.agent ? data.agent.name : null;
            sessionUuid.value = uuid;
            connectEcho(uuid);
            view.value = 'chat';
            scrollToBottom();
        } else {
            view.value = 'list';
            await loadConversations(false);
        }
    } catch (e) {
        console.error('Failed to load chat history', e);
        view.value = 'list';
    }
};

const openConversation = (uuid: string) => {
    view.value = 'loading';
    loadHistory(uuid);
};

const startNewConversation = async () => {
    view.value = 'loading';
    await checkStatus();
    view.value = isOnline.value ? 'prechat' : 'offline';
};

const goBack = () => {
    if (sessionUuid.value) {
        window.Echo?.leave(`chat.${sessionUuid.value}`);
    }

    sessionUuid.value = null;
    messages.value = [];
    view.value = 'loading';
    loadConversations();
};

const startChat = async () => {
    isSubmitting.value = true;

    try {
        const res = await fetch(chatInitiate.url(), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: JSON.stringify({
                name: prechatForm.value.name,
                email: prechatForm.value.email,
                message: prechatForm.value.message,
            }),
        });

        if (res.ok) {
            const data = await res.json();
            sessionUuid.value = data.uuid;
            sessionStatus.value = data.status;
            agentName.value = null;
            connectEcho(data.uuid);

            // Show the customer's own opening message (returned by the server, so it's
            // never lost even if this tab closes right after) plus a local welcome note.
            messages.value = [
                ...(data.messages || []),
                {
                    id: -1,
                    sender_type: 'system',
                    body: 'Connecting to Bidora support. Please wait for an agent to claim the session...',
                    created_at: new Date().toISOString(),
                },
            ];
            prechatForm.value.message = '';
            view.value = 'chat';
            scrollToBottom();

            // Refresh the conversation list in the background so it's up to date next time
            // they go back to it.
            loadConversations(false);
        } else if (res.status === 422) {
            const err = await res.json().catch(() => null);
            alert(
                err?.message ||
                    'You already have too many open conversations. Please continue one of those first.',
            );
            await loadConversations();
        }
    } catch (e) {
        console.error('Failed to initiate support chat', e);
    } finally {
        isSubmitting.value = false;
    }
};

const sendChatMessage = async () => {
    if (!sessionUuid.value || !messageText.value.trim() || isSubmitting.value) {
        return;
    }

    const body = messageText.value;
    messageText.value = ''; // Instant UI response

    try {
        const socketId = window.Echo?.socketId();
        const headers: Record<string, string> = {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
        };

        if (socketId) {
            headers['X-Socket-ID'] = socketId;
        }

        const res = await fetch(chatSend.url(sessionUuid.value), {
            method: 'POST',
            headers,
            body: JSON.stringify({ body }),
        });

        if (res.ok) {
            const data = await res.json();
            const exists = messages.value.some((m) => m.id === data.id);

            if (!exists) {
                messages.value.push({
                    id: data.id,
                    sender_type: data.sender_type,
                    body: data.body,
                    created_at: data.created_at,
                });
                scrollToBottom();
            }
        } else {
            // Send failed server-side (e.g. session closed, or a transient error) — restore
            // the text so it isn't silently lost.
            messageText.value = body;
        }
    } catch (e) {
        console.error('Failed to send message', e);
        // Put text back if failed
        messageText.value = body;
    }
};

const submitOfflineForm = async () => {
    isSubmitting.value = true;

    try {
        const res = await fetch(chatOfflineTicket.url(), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: JSON.stringify({
                name: offlineForm.value.name,
                email: offlineForm.value.email,
                body: offlineForm.value.body,
            }),
        });

        if (res.ok) {
            alert(
                'Your request has been filed successfully. A support ticket has been created and our team will get back to you via email.',
            );
            offlineForm.value.body = '';
            isOpen.value = false;
        }
    } catch (e) {
        console.error('Failed to submit offline ticket', e);
        alert('Something went wrong. Please try again.');
    } finally {
        isSubmitting.value = false;
    }
};

const handleOpenSupportChat = (): void => {
    isOpen.value = true;
};

onMounted(() => {
    window.addEventListener('open-support-chat', handleOpenSupportChat);

    // Check if logged-in user to prefill
    if (currentUser.value) {
        prechatForm.value.name = currentUser.value.name;
        prechatForm.value.email = currentUser.value.email;
        offlineForm.value.name = currentUser.value.name;
        offlineForm.value.email = currentUser.value.email;
    }

    // Ask the server for this visitor's past/ongoing conversations (matched by account for
    // logged-in customers, or by a persistent cookie for guests) so they persist across
    // reloads and even a full browser restart.
    loadConversations();
});

// Watch current user to prefill if they log in dynamically
watch(currentUser, (newUser) => {
    if (newUser) {
        prechatForm.value.name = newUser.name;
        prechatForm.value.email = newUser.email;
        offlineForm.value.name = newUser.name;
        offlineForm.value.email = newUser.email;
    }
});

onUnmounted(() => {
    window.removeEventListener('open-support-chat', handleOpenSupportChat);
});
</script>

<template>
    <div
        class="fixed bottom-20 z-50 font-sans text-xs md:bottom-6"
        :class="
            isOpen
                ? 'left-1/2 -translate-x-1/2 md:right-6 md:left-auto md:translate-x-0'
                : 'right-6'
        "
    >
        <!-- Minimized Floating Button -->
        <button
            v-if="!isOpen"
            @click="isOpen = true"
            class="flex h-14 w-14 cursor-pointer items-center justify-center rounded-full bg-navy text-lemon shadow-2xl transition-all hover:scale-105 hover:bg-forest"
        >
            <MessageSquare class="h-6 w-6" />
        </button>

        <!-- Chat Box -->
        <div
            v-else
            class="animate-fade-in flex h-[500px] w-[320px] flex-col overflow-hidden rounded-2xl border border-outline-variant bg-surface-container-lowest shadow-2xl md:w-[360px]"
        >
            <!-- Header -->
            <div
                class="flex items-center justify-between bg-navy px-5 py-4 text-white shadow-sm"
            >
                <div class="flex items-center gap-2">
                    <button
                        v-if="canGoBack"
                        @click="goBack"
                        class="-ml-1 cursor-pointer p-0.5 text-white/80 hover:text-white"
                    >
                        <ChevronLeft class="h-4 w-4" />
                    </button>
                    <span
                        :class="[
                            'h-2 w-2 rounded-full',
                            isOnline ? 'bg-surface-tint' : 'bg-gray-400',
                        ]"
                    ></span>
                    <span
                        class="text-[10px] font-extrabold tracking-wide uppercase"
                    >
                        {{ headerTitle }}
                    </span>
                </div>
                <button
                    @click="isOpen = false"
                    class="cursor-pointer p-0.5 text-white/80 hover:text-white"
                >
                    <ChevronDown class="h-5 w-5" />
                </button>
            </div>

            <!-- Body -->
            <div class="flex min-h-0 flex-1 flex-col bg-background">
                <!-- 1. Loading State -->
                <div
                    v-if="view === 'loading'"
                    class="flex flex-1 items-center justify-center"
                >
                    <Loader2 class="h-8 w-8 animate-spin text-primary" />
                </div>

                <!-- 2. Conversations List -->
                <template v-else-if="view === 'list'">
                    <div
                        class="flex flex-1 flex-col gap-2.5 overflow-y-auto bg-[#fcfdfd] p-4"
                    >
                        <button
                            v-for="conv in conversations"
                            :key="conv.uuid"
                            @click="openConversation(conv.uuid)"
                            class="flex cursor-pointer flex-col gap-1 rounded-xl border border-outline-variant/40 bg-surface-container-lowest p-3 text-left transition-colors hover:border-navy/30 hover:bg-surface-container-low"
                        >
                            <div
                                class="flex items-center justify-between gap-2"
                            >
                                <span
                                    class="truncate text-[10px] font-black text-on-surface uppercase"
                                >
                                    {{
                                        conv.agent
                                            ? conv.agent.name
                                            : conv.status === 'waiting'
                                              ? 'Waiting for agent'
                                              : 'Support Chat'
                                    }}
                                </span>
                                <span
                                    :class="[
                                        'shrink-0 rounded-full px-2 py-0.5 text-[8px] font-extrabold tracking-wide uppercase',
                                        conv.status === 'waiting'
                                            ? 'bg-amber-100 text-amber-700'
                                            : conv.status === 'active'
                                              ? 'bg-green-100 text-green-700'
                                              : 'bg-surface-container text-on-surface-variant',
                                    ]"
                                >
                                    {{ conv.status }}
                                </span>
                            </div>
                            <p
                                class="truncate text-[10px] text-on-surface-variant"
                            >
                                {{ conv.last_message || 'No messages yet' }}
                            </p>
                            <span
                                class="text-[8px] font-semibold text-on-surface-variant/60"
                            >
                                {{
                                    formatConversationTime(
                                        conv.last_message_at || conv.created_at,
                                    )
                                }}
                            </span>
                        </button>
                    </div>

                    <div
                        class="border-t border-outline-variant/50 bg-surface p-3"
                    >
                        <button
                            @click="startNewConversation"
                            :disabled="atOpenConversationsCap"
                            class="flex w-full cursor-pointer items-center justify-center gap-1.5 rounded-lg bg-navy py-3 text-[10px] font-black tracking-wider text-lemon uppercase transition-colors hover:bg-forest disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            <MessageSquare class="h-3.5 w-3.5" />
                            <span>Start New Conversation</span>
                        </button>
                        <p
                            v-if="atOpenConversationsCap"
                            class="mt-2 text-center text-[9px] text-on-surface-variant"
                        >
                            You have {{ openConversationsCount }} open
                            conversations. Finish or wait on one of those before
                            starting another.
                        </p>
                    </div>
                </template>

                <!-- 3. Active Chat Stream -->
                <template v-else-if="view === 'chat'">
                    <div
                        ref="messageContainer"
                        class="flex flex-1 flex-col gap-3 overflow-y-auto bg-[#fcfdfd] p-5"
                    >
                        <div
                            v-for="msg in messages"
                            :key="msg.id"
                            :class="[
                                'flex max-w-[75%] flex-col',
                                msg.sender_type === 'system'
                                    ? 'mx-auto my-1.5 w-full max-w-none text-center'
                                    : msg.sender_type === 'customer'
                                      ? 'items-end self-end'
                                      : 'items-start self-start',
                            ]"
                        >
                            <!-- System notification -->
                            <template v-if="msg.sender_type === 'system'">
                                <div
                                    class="inline-block rounded border border-outline-variant/30 bg-surface-container px-3 py-1 text-[8.5px] font-extrabold tracking-wider text-on-surface-variant/80 uppercase"
                                >
                                    {{ msg.body }}
                                </div>
                            </template>

                            <!-- User message bubbles -->
                            <template v-else>
                                <div
                                    :class="[
                                        'rounded-2xl p-3 text-[10.5px] leading-relaxed whitespace-pre-wrap shadow-sm',
                                        msg.sender_type === 'customer'
                                            ? 'rounded-tr-none border border-navy/10 bg-navy text-lemon'
                                            : 'rounded-tl-none border border-outline-variant/35 bg-surface-container-low text-on-surface',
                                    ]"
                                >
                                    {{ msg.body }}
                                </div>
                            </template>
                        </div>
                    </div>

                    <!-- Input / Ending Banner -->
                    <div
                        class="border-t border-outline-variant/50 bg-surface p-3"
                    >
                        <div
                            v-if="sessionStatus === 'closed'"
                            class="space-y-2 py-2 text-center"
                        >
                            <p
                                class="text-[10px] font-bold text-on-surface-variant uppercase"
                            >
                                This chat has been closed.
                            </p>
                            <button
                                @click="goBack"
                                class="cursor-pointer rounded-lg bg-navy px-4 py-2 text-[9px] font-black tracking-widest text-lemon uppercase"
                            >
                                View Conversations
                            </button>
                        </div>
                        <form
                            v-else
                            @submit.prevent="sendChatMessage"
                            class="flex gap-2"
                        >
                            <input
                                v-model="messageText"
                                placeholder="Type a message..."
                                class="flex-1 rounded-lg border border-outline-variant bg-surface-container-low px-4 py-2.5 text-[11px] font-medium text-on-surface focus:ring-1 focus:ring-secondary focus:outline-none"
                            />
                            <button
                                type="submit"
                                :disabled="!messageText.trim()"
                                class="flex cursor-pointer items-center justify-center rounded-lg bg-navy px-4 text-lemon transition-colors hover:bg-forest disabled:opacity-50"
                            >
                                <Send class="h-3.5 w-3.5" />
                            </button>
                        </form>
                    </div>
                </template>

                <!-- 4. Pre-Chat Form (Online) -->
                <div
                    v-else-if="view === 'prechat'"
                    class="flex flex-1 flex-col justify-center overflow-y-auto p-6"
                >
                    <div class="mb-6 text-center">
                        <MessageSquare
                            class="mx-auto mb-2 h-12 w-12 text-primary"
                        />
                        <h3
                            class="text-xs font-black tracking-wider text-primary uppercase"
                        >
                            Chat with Bidora Support
                        </h3>
                        <p class="mt-1 text-[10px] text-on-surface-variant">
                            Our customer care agents are online and ready to
                            assist you.
                        </p>
                    </div>

                    <form @submit.prevent="startChat" class="space-y-4">
                        <div class="flex flex-col gap-1">
                            <label
                                class="text-[9px] font-bold tracking-wider text-on-surface-variant uppercase"
                                >Your Name</label
                            >
                            <input
                                v-model="prechatForm.name"
                                required
                                type="text"
                                placeholder="Enter name"
                                class="rounded-lg border border-outline-variant bg-surface-container-low px-4 py-2.5 font-bold text-on-surface capitalize focus:ring-1 focus:ring-secondary focus:outline-none"
                            />
                        </div>

                        <div class="flex flex-col gap-1">
                            <label
                                class="text-[9px] font-bold tracking-wider text-on-surface-variant uppercase"
                                >Email Address</label
                            >
                            <input
                                v-model="prechatForm.email"
                                required
                                type="email"
                                placeholder="Enter email"
                                class="rounded-lg border border-outline-variant bg-surface-container-low px-4 py-2.5 font-bold text-on-surface lowercase focus:ring-1 focus:ring-secondary focus:outline-none"
                            />
                        </div>

                        <div class="flex flex-col gap-1">
                            <label
                                class="text-[9px] font-bold tracking-wider text-on-surface-variant uppercase"
                                >How can we help?</label
                            >
                            <textarea
                                v-model="prechatForm.message"
                                required
                                rows="3"
                                placeholder="Tell us what you need help with..."
                                class="rounded-lg border border-outline-variant bg-surface-container-low p-4 text-[11px] font-medium text-on-surface focus:ring-1 focus:ring-secondary focus:outline-none"
                            ></textarea>
                        </div>

                        <button
                            type="submit"
                            :disabled="isSubmitting"
                            class="flex w-full cursor-pointer items-center justify-center gap-1.5 rounded-lg bg-navy py-3 font-black tracking-wider text-lemon uppercase transition-colors disabled:opacity-50"
                        >
                            <Loader2
                                v-if="isSubmitting"
                                class="h-4 w-4 animate-spin"
                            />
                            <span>Start Live Chat</span>
                        </button>
                    </form>
                </div>

                <!-- 5. Offline Contact Form (Offline) -->
                <div
                    v-else
                    class="flex flex-1 flex-col justify-center overflow-y-auto p-6"
                >
                    <div class="mb-6 text-center">
                        <Clock
                            class="mx-auto mb-2 h-12 w-12 text-on-surface-variant/80"
                        />
                        <h3
                            class="text-xs font-black tracking-wider text-primary uppercase"
                        >
                            Support is Offline
                        </h3>
                        <p class="mt-1 text-[10px] text-on-surface-variant">
                            Our team is currently offline. Drop us a message
                            below and we will automatically create a support
                            ticket.
                        </p>
                    </div>

                    <form @submit.prevent="submitOfflineForm" class="space-y-4">
                        <div class="flex flex-col gap-1">
                            <label
                                class="text-[9px] font-bold tracking-wider text-on-surface-variant uppercase"
                                >Your Name</label
                            >
                            <input
                                v-model="offlineForm.name"
                                required
                                type="text"
                                placeholder="Enter name"
                                class="rounded-lg border border-outline-variant bg-surface-container-low px-4 py-2.5 font-bold text-on-surface uppercase focus:ring-1 focus:ring-secondary focus:outline-none"
                            />
                        </div>

                        <div class="flex flex-col gap-1">
                            <label
                                class="text-[9px] font-bold tracking-wider text-on-surface-variant uppercase"
                                >Email Address</label
                            >
                            <input
                                v-model="offlineForm.email"
                                required
                                type="email"
                                placeholder="Enter email"
                                class="rounded-lg border border-outline-variant bg-surface-container-low px-4 py-2.5 font-bold text-on-surface uppercase focus:ring-1 focus:ring-secondary focus:outline-none"
                            />
                        </div>

                        <div class="flex flex-col gap-1">
                            <label
                                class="text-[9px] font-bold tracking-wider text-on-surface-variant uppercase"
                                >Inquiry Details</label
                            >
                            <textarea
                                v-model="offlineForm.body"
                                required
                                rows="3"
                                placeholder="Provide description..."
                                class="rounded-lg border border-outline-variant bg-surface-container-low p-4 text-[11px] font-medium text-on-surface focus:ring-1 focus:ring-secondary focus:outline-none"
                            ></textarea>
                        </div>

                        <button
                            type="submit"
                            :disabled="isSubmitting"
                            class="flex w-full cursor-pointer items-center justify-center gap-1.5 rounded-lg bg-navy py-3 font-black tracking-wider text-lemon uppercase transition-colors disabled:opacity-50"
                        >
                            <Loader2
                                v-if="isSubmitting"
                                class="h-4 w-4 animate-spin"
                            />
                            <span>Submit Request</span>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</template>
