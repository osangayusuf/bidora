<script setup lang="ts">
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import {
    MessageSquare,
    User as UserIcon,
    Clock,
    Inbox,
    Send,
    CheckCircle,
    UserCheck,
    FileText,
    AlertCircle,
    Lock,
} from 'lucide-vue-next';
import { ref, onMounted, onUnmounted, watch, nextTick, computed } from 'vue';
import SupportLayout from '@/layouts/SupportLayout.vue';
import {
    status as chatStatus,
    claim as chatClaim,
    close as chatClose,
    message as chatMessage,
    convert as chatConvert,
    show as chatShow,
} from '@/routes/support/chat';
import { show as ticketsShow } from '@/routes/support/tickets';

type User = {
    id: number;
    name: string;
    email: string;
};

type ChatMessage = {
    id: number;
    chat_session_id: number;
    sender_id: number | null;
    sender_type: string;
    body: string;
    created_at: string;
    sender: User | null;
};

type ChatSession = {
    id: number;
    uuid: string;
    customer_id: number | null;
    customer_name: string;
    customer_email: string | null;
    agent_id: number | null;
    ticket_id: number | null;
    status: string;
    started_at: string | null;
    closed_at: string | null;
    messages: ChatMessage[];
    agent: User | null;
};

type AgentStatus = {
    status: string;
};

const props = defineProps<{
    waitingSessions: ChatSession[];
    activeSessions: ChatSession[];
    closedSessions: ChatSession[];
    agentStatus: AgentStatus;
    selectedSession: ChatSession | null;
    categories: string[];
    priorities: string[];
}>();

const currentTab = ref('active'); // 'waiting' | 'active' | 'closed'
const messageForm = useForm({
    body: '',
});

const convertForm = useForm({
    subject: '',
    category: '',
    priority: 'normal',
});

const showConvertModal = ref(false);
const messageContainer = ref<HTMLDivElement | null>(null);

const waitingList = ref<ChatSession[]>([...props.waitingSessions]);
const activeList = ref<ChatSession[]>([...props.activeSessions]);
const closedList = ref<ChatSession[]>([...props.closedSessions]);

const messages = ref<ChatMessage[]>(
    props.selectedSession ? [...props.selectedSession.messages] : [],
);
const sessionStatus = ref<string>(
    props.selectedSession ? props.selectedSession.status : '',
);
const assignedAgent = ref<User | null>(
    props.selectedSession ? props.selectedSession.agent : null,
);

const currentStatus = ref(props.agentStatus?.status || 'offline');

const scrollToBottom = () => {
    nextTick(() => {
        if (messageContainer.value) {
            messageContainer.value.scrollTop =
                messageContainer.value.scrollHeight;
        }
    });
};

// Handle Echo events
onMounted(() => {
    scrollToBottom();

    // Register presence channel to get new incoming chats in real-time
    window.Echo?.join('support.agents').listen(
        '.NewChatSessionCreated',
        (event: any) => {
            const exists = waitingList.value.some((s) => s.uuid === event.uuid);

            if (!exists) {
                waitingList.value.unshift({
                    id: event.id,
                    uuid: event.uuid,
                    customer_id: event.customer_id,
                    customer_name: event.customer_name,
                    customer_email: event.customer_email,
                    status: event.status,
                    started_at: null,
                    closed_at: null,
                    messages: [],
                    agent: null,
                    ticket_id: null,
                });
            }
        },
    );

    // Register active session channel if selected
    if (props.selectedSession) {
        joinSessionChannel(props.selectedSession.uuid);
    }
});

const joinSessionChannel = (uuid: string) => {
    window.Echo?.channel(`chat.${uuid}`)
        .listen('.ChatMessageSent', (event: any) => {
            const exists = messages.value.some((m) => m.id === event.id);

            if (!exists) {
                messages.value.push({
                    id: event.id,
                    chat_session_id: event.chat_session_id,
                    sender_id: event.sender_id,
                    sender_type: event.sender_type,
                    body: event.body,
                    created_at: event.created_at,
                    sender: event.sender,
                });
                scrollToBottom();
            }
        })
        .listen('.AgentClaimedSession', (event: any) => {
            sessionStatus.value = event.status;
            assignedAgent.value = event.agent_name
                ? { id: event.agent_id, name: event.agent_name, email: '' }
                : null;
        })
        .listen('.ChatSessionClosed', (event: any) => {
            sessionStatus.value = event.status;
        });
};

onUnmounted(() => {
    window.Echo?.leave('support.agents');

    if (props.selectedSession) {
        window.Echo?.leave(`chat.${props.selectedSession.uuid}`);
    }
});

watch(
    () => props.selectedSession,
    (newSession, oldSession) => {
        // Only leave and rejoin the Echo channel if the session has actually changed
        if (oldSession && oldSession.uuid !== newSession?.uuid) {
            window.Echo?.leave(`chat.${oldSession.uuid}`);
        }

        messages.value = newSession ? [...newSession.messages] : [];
        sessionStatus.value = newSession ? newSession.status : '';
        assignedAgent.value = newSession ? newSession.agent : null;

        if (
            newSession &&
            (!oldSession || oldSession.uuid !== newSession.uuid)
        ) {
            joinSessionChannel(newSession.uuid);
            scrollToBottom();
        }
    },
    { deep: true },
);

watch(
    () => props.waitingSessions,
    (newVal) => {
        waitingList.value = [...newVal];
    },
);
watch(
    () => props.activeSessions,
    (newVal) => {
        activeList.value = [...newVal];
    },
);
watch(
    () => props.closedSessions,
    (newVal) => {
        closedList.value = [...newVal];
    },
);
watch(
    () => props.agentStatus,
    (newVal) => {
        if (newVal) {
            currentStatus.value = newVal.status;
        }
    },
);

const changePresenceStatus = () => {
    router.post(chatStatus.url(), {
        status: currentStatus.value,
    });
};

const claimChat = () => {
    if (!props.selectedSession) {
        return;
    }

    router.post(
        chatClaim.url(props.selectedSession.uuid),
        {},
        {
            onSuccess: () => {
                currentTab.value = 'active';
            },
        },
    );
};

const closeChat = () => {
    if (!props.selectedSession) {
        return;
    }

    if (confirm('Are you sure you want to close this chat session?')) {
        router.post(chatClose.url(props.selectedSession.uuid));
    }
};

const submitMessage = () => {
    if (!props.selectedSession || !messageForm.body.trim()) {
        return;
    }

    const bodyText = messageForm.body;
    messageForm.body = ''; // Clear locally for instant response feel

    const socketId = window.Echo?.socketId();
    const headers: Record<string, string> = {};

    if (socketId) {
        headers['X-Socket-ID'] = socketId;
    }

    router.post(
        chatMessage.url(props.selectedSession.uuid),
        {
            body: bodyText,
        },
        {
            preserveScroll: true,
            preserveState: true,
            headers,
        },
    );
};

const submitConvert = () => {
    if (!props.selectedSession) {
        return;
    }

    convertForm.post(chatConvert.url(props.selectedSession.uuid), {
        onSuccess: () => {
            showConvertModal.value = false;
            convertForm.reset();
        },
    });
};

const getStatusDotClass = (status: string) => {
    switch (status) {
        case 'online':
            return 'bg-surface-tint';
        case 'away':
            return 'bg-amber';
        default:
            return 'bg-error';
    }
};
</script>

<template>
    <Head title="Live Chat Support - Bidora Support" />

    <SupportLayout :breadcrumbs="[{ title: 'Live Chat' }]">
        <div
            class="flex h-[calc(100vh-12rem)] overflow-hidden rounded-xl border border-outline-variant/60 bg-surface-container-lowest font-sans text-xs shadow-sm"
        >
            <!-- Middle Panel: Chat Sessions List (300px wide) -->
            <div
                class="flex w-[320px] flex-col border-r border-outline-variant bg-surface"
            >
                <!-- Status Toggle -->
                <div
                    class="flex items-center justify-between border-b border-outline-variant bg-surface p-4"
                >
                    <div class="flex items-center gap-2">
                        <span
                            :class="[
                                'h-2.5 w-2.5 rounded-full',
                                getStatusDotClass(currentStatus),
                            ]"
                        ></span>
                        <span
                            class="text-[10px] font-bold tracking-wider text-on-surface-variant uppercase"
                            >Agent: {{ currentStatus }}</span
                        >
                    </div>
                    <select
                        v-model="currentStatus"
                        @change="changePresenceStatus"
                        class="cursor-pointer rounded border border-outline-variant bg-surface-container-lowest px-2.5 py-1.5 text-[10px] font-bold uppercase focus:ring-1 focus:ring-secondary focus:outline-none"
                    >
                        <option value="online">Online</option>
                        <option value="away">Away</option>
                        <option value="offline">Offline</option>
                    </select>
                </div>

                <!-- Tabs header -->
                <div class="flex border-b border-outline-variant/40">
                    <button
                        @click="currentTab = 'active'"
                        :class="[
                            'flex-1 border-b-2 py-3 text-center text-[10px] font-bold tracking-wider uppercase transition-all',
                            currentTab === 'active'
                                ? 'border-primary bg-surface-container-lowest text-primary'
                                : 'border-transparent text-on-surface-variant hover:text-on-surface',
                        ]"
                    >
                        Active ({{ activeList.length }})
                    </button>
                    <button
                        @click="currentTab = 'waiting'"
                        :class="[
                            'flex-1 border-b-2 py-3 text-center text-[10px] font-bold tracking-wider uppercase transition-all',
                            currentTab === 'waiting'
                                ? 'border-primary bg-surface-container-lowest text-primary'
                                : 'border-transparent text-on-surface-variant hover:text-on-surface',
                        ]"
                    >
                        Waiting ({{ waitingList.length }})
                    </button>
                    <button
                        @click="currentTab = 'closed'"
                        :class="[
                            'flex-1 border-b-2 py-3 text-center text-[10px] font-bold tracking-wider uppercase transition-all',
                            currentTab === 'closed'
                                ? 'border-primary bg-surface-container-lowest text-primary'
                                : 'border-transparent text-on-surface-variant hover:text-on-surface',
                        ]"
                    >
                        Closed
                    </button>
                </div>

                <!-- Sessions List -->
                <div
                    class="flex-1 divide-y divide-outline-variant/30 overflow-y-auto"
                >
                    <!-- Active List -->
                    <template v-if="currentTab === 'active'">
                        <Link
                            v-for="session in activeList"
                            :key="session.uuid"
                            :href="chatShow.url(session.uuid)"
                            :class="[
                                'block p-4 transition-all hover:bg-surface-container-low/30',
                                selectedSession?.uuid === session.uuid
                                    ? 'border-l-4 border-primary bg-surface-container'
                                    : '',
                            ]"
                        >
                            <div class="mb-1 flex items-start justify-between">
                                <span
                                    class="truncate pr-2 font-bold text-on-surface"
                                    >{{ session.customer_name }}</span
                                >
                                <span
                                    class="text-[9px] font-medium text-on-surface-variant/80"
                                >
                                    {{
                                        session.started_at
                                            ? new Date(
                                                  session.started_at,
                                              ).toLocaleTimeString([], {
                                                  hour: '2-digit',
                                                  minute: '2-digit',
                                              })
                                            : 'Active'
                                    }}
                                </span>
                            </div>
                            <p
                                class="truncate text-[10px] font-medium text-on-surface-variant"
                            >
                                {{
                                    session.customer_email ||
                                    'No email provided'
                                }}
                            </p>
                            <div
                                class="mt-2 flex items-center justify-between text-[9px] font-semibold text-on-surface-variant/75"
                            >
                                <span
                                    v-if="session.agent"
                                    class="rounded-sm border border-secondary/20 bg-secondary/15 px-2 py-0.5 tracking-wide text-primary uppercase"
                                >
                                    Agent: {{ session.agent.name }}
                                </span>
                                <span
                                    v-if="session.ticket_id"
                                    class="font-bold text-primary"
                                    >✓ Converted</span
                                >
                            </div>
                        </Link>
                        <div
                            v-if="activeList.length === 0"
                            class="py-12 text-center text-on-surface-variant/60"
                        >
                            <Inbox
                                class="mx-auto mb-2 h-8 w-8 text-outline-variant/60"
                            />
                            <p
                                class="text-[9px] font-bold tracking-widest uppercase"
                            >
                                No Active Chats
                            </p>
                        </div>
                    </template>

                    <!-- Waiting List -->
                    <template v-if="currentTab === 'waiting'">
                        <Link
                            v-for="session in waitingList"
                            :key="session.uuid"
                            :href="chatShow.url(session.uuid)"
                            :class="[
                                'block p-4 transition-all hover:bg-surface-container-low/30',
                                selectedSession?.uuid === session.uuid
                                    ? 'border-l-4 border-primary bg-surface-container'
                                    : '',
                            ]"
                        >
                            <div class="mb-1 flex items-start justify-between">
                                <span
                                    class="truncate pr-2 font-bold text-on-surface"
                                    >{{ session.customer_name }}</span
                                >
                                <span
                                    class="flex items-center gap-1 text-[9px] font-bold text-error"
                                >
                                    <Clock class="h-3 w-3 text-error" />
                                    <span>Waiting</span>
                                </span>
                            </div>
                            <p
                                class="truncate text-[10px] font-medium text-on-surface-variant"
                            >
                                {{
                                    session.customer_email ||
                                    'No email provided'
                                }}
                            </p>
                            <span
                                class="mt-2 inline-block text-[9px] font-bold text-on-surface-variant/80"
                            >
                                Requested:
                                {{
                                    new Date(
                                        session.created_at,
                                    ).toLocaleTimeString([], {
                                        hour: '2-digit',
                                        minute: '2-digit',
                                    })
                                }}
                            </span>
                        </Link>
                        <div
                            v-if="waitingList.length === 0"
                            class="py-12 text-center text-on-surface-variant/60"
                        >
                            <Inbox
                                class="mx-auto mb-2 h-8 w-8 text-outline-variant/60"
                            />
                            <p
                                class="text-[9px] font-bold tracking-widest uppercase"
                            >
                                Queue is Empty
                            </p>
                        </div>
                    </template>

                    <!-- Closed List -->
                    <template v-if="currentTab === 'closed'">
                        <Link
                            v-for="session in closedList"
                            :key="session.uuid"
                            :href="chatShow.url(session.uuid)"
                            :class="[
                                'block p-4 transition-all hover:bg-surface-container-low/30',
                                selectedSession?.uuid === session.uuid
                                    ? 'border-l-4 border-primary bg-surface-container'
                                    : '',
                            ]"
                        >
                            <div class="mb-1 flex items-start justify-between">
                                <span
                                    class="truncate pr-2 font-bold text-on-surface"
                                    >{{ session.customer_name }}</span
                                >
                                <span
                                    class="text-[9px] font-medium text-on-surface-variant/80"
                                >
                                    {{
                                        session.closed_at
                                            ? new Date(
                                                  session.closed_at,
                                              ).toLocaleDateString()
                                            : 'Closed'
                                    }}
                                </span>
                            </div>
                            <p
                                class="truncate text-[10px] font-medium text-on-surface-variant"
                            >
                                {{
                                    session.customer_email ||
                                    'No email provided'
                                }}
                            </p>
                            <div
                                class="mt-2 flex items-center justify-between text-[9px] font-semibold text-on-surface-variant/70"
                            >
                                <span>Closed support</span>
                                <span
                                    v-if="session.ticket_id"
                                    class="font-bold text-primary"
                                    >✓ Converted</span
                                >
                            </div>
                        </Link>
                        <div
                            v-if="closedList.length === 0"
                            class="py-12 text-center text-on-surface-variant/60"
                        >
                            <Inbox
                                class="mx-auto mb-2 h-8 w-8 text-outline-variant/60"
                            />
                            <p
                                class="text-[9px] font-bold tracking-widest uppercase"
                            >
                                No Closed Chats
                            </p>
                        </div>
                    </template>
                </div>
            </div>

            <!-- Right Panel: Conversation Thread -->
            <div class="flex flex-1 flex-col bg-surface-container-lowest">
                <!-- If a chat is selected -->
                <template v-if="selectedSession">
                    <!-- Session Header Bar -->
                    <div
                        class="flex items-center justify-between border-b border-outline-variant/60 bg-surface px-6 py-4"
                    >
                        <div>
                            <h2 class="text-sm font-bold text-primary">
                                {{ selectedSession.customer_name }}
                            </h2>
                            <p
                                class="mt-0.5 text-[10px] text-on-surface-variant"
                            >
                                {{
                                    selectedSession.customer_email ||
                                    'Guest customer'
                                }}
                                • Session #{{ selectedSession.id }}
                            </p>
                        </div>

                        <!-- Header Action buttons -->
                        <div class="flex items-center gap-2">
                            <!-- If status is Waiting -->
                            <button
                                v-if="sessionStatus === 'waiting'"
                                @click="claimChat"
                                class="flex cursor-pointer items-center gap-1.5 rounded-lg border border-secondary/25 bg-secondary-container px-4 py-2 font-bold text-on-secondary-container uppercase transition-all hover:bg-secondary hover:text-primary"
                            >
                                <UserCheck class="h-3.5 w-3.5" />
                                <span>Claim Chat Session</span>
                            </button>

                            <!-- If status is Active -->
                            <template v-else-if="sessionStatus === 'active'">
                                <button
                                    v-if="!selectedSession.ticket_id"
                                    @click="showConvertModal = true"
                                    class="flex cursor-pointer items-center gap-1.5 rounded-lg border border-outline-variant bg-surface-container-low px-3 py-2 font-bold text-on-surface uppercase transition-all hover:bg-surface-container"
                                >
                                    <FileText class="h-3.5 w-3.5" />
                                    <span>Convert to Ticket</span>
                                </button>
                                <button
                                    @click="closeChat"
                                    class="flex cursor-pointer items-center gap-1.5 rounded-lg border border-error/20 bg-error-container/10 px-3 py-2 font-bold text-error uppercase transition-all hover:bg-error hover:text-white"
                                >
                                    <CheckCircle class="h-3.5 w-3.5" />
                                    <span>Close Session</span>
                                </button>
                            </template>

                            <!-- If already converted to a ticket -->
                            <Link
                                v-if="selectedSession.ticket_id"
                                :href="
                                    ticketsShow.url(selectedSession.ticket_id)
                                "
                                class="flex items-center gap-1.5 rounded-lg border border-primary/20 bg-primary/5 px-3 py-2 font-bold text-primary uppercase transition-all hover:bg-primary hover:text-white"
                            >
                                <FileText class="h-3.5 w-3.5" />
                                <span
                                    >Open Ticket #{{
                                        selectedSession.ticket_id
                                    }}</span
                                >
                            </Link>
                        </div>
                    </div>

                    <!-- Conversation Message Stream -->
                    <div
                        ref="messageContainer"
                        class="flex flex-1 flex-col gap-4 overflow-y-auto bg-[#fcfdfd] p-6"
                    >
                        <div
                            v-if="messages.length === 0"
                            class="flex flex-1 flex-col items-center justify-center gap-2 text-on-surface-variant/60"
                        >
                            <Inbox class="h-10 w-10" />
                            <p
                                class="text-[10px] font-bold tracking-wider uppercase"
                            >
                                No messages yet
                            </p>
                        </div>
                        <div
                            v-for="msg in messages"
                            :key="msg.id"
                            :class="[
                                'flex max-w-[70%] flex-col',
                                msg.sender_type === 'system'
                                    ? 'mx-auto my-2 w-full max-w-none text-center'
                                    : msg.sender_type === 'agent'
                                      ? 'items-end self-end'
                                      : 'items-start self-start',
                            ]"
                        >
                            <!-- System messages style -->
                            <template v-if="msg.sender_type === 'system'">
                                <div
                                    class="mx-auto inline-flex items-center gap-1.5 rounded-full border border-outline-variant bg-surface-container-low px-4 py-1.5 text-[9px] font-extrabold tracking-wider text-on-surface-variant uppercase"
                                >
                                    <AlertCircle class="h-3 w-3 text-primary" />
                                    <span>{{ msg.body }}</span>
                                </div>
                            </template>

                            <!-- Chat bubbles style -->
                            <template v-else>
                                <div
                                    class="mb-1 flex items-center gap-1 text-[9px] font-bold text-on-surface-variant uppercase"
                                >
                                    <span>{{
                                        msg.sender_type === 'agent'
                                            ? msg.sender?.name || 'Agent'
                                            : selectedSession.customer_name
                                    }}</span>
                                </div>
                                <div
                                    :class="[
                                        'rounded-2xl p-3.5 text-[11px] leading-relaxed whitespace-pre-wrap shadow-sm',
                                        msg.sender_type === 'agent'
                                            ? 'rounded-tr-none border border-navy/10 bg-navy text-lemon'
                                            : 'rounded-tl-none border border-outline-variant/35 bg-surface-container-low text-on-surface',
                                    ]"
                                >
                                    {{ msg.body }}
                                </div>
                                <span
                                    class="mt-1 text-[8px] font-semibold text-on-surface-variant/70"
                                >
                                    {{
                                        new Date(
                                            msg.created_at,
                                        ).toLocaleTimeString([], {
                                            hour: '2-digit',
                                            minute: '2-digit',
                                        })
                                    }}
                                </span>
                            </template>
                        </div>
                    </div>

                    <!-- Input Reply Box -->
                    <div
                        v-if="sessionStatus === 'active'"
                        class="border-t border-outline-variant/60 bg-surface p-4"
                    >
                        <form
                            @submit.prevent="submitMessage"
                            class="flex gap-3"
                        >
                            <textarea
                                v-model="messageForm.body"
                                rows="1"
                                placeholder="TYPE A MESSAGE DIRECTLY TO THE CUSTOMER (PRESS ENTER TO SEND)..."
                                @keydown.enter.exact.prevent="submitMessage"
                                class="flex-1 resize-none rounded-lg border border-outline-variant bg-surface-container-low px-4 py-3 text-[11px] font-medium text-on-surface focus:ring-1 focus:ring-secondary focus:outline-none"
                            ></textarea>
                            <button
                                type="submit"
                                :disabled="
                                    messageForm.processing ||
                                    !messageForm.body.trim()
                                "
                                class="flex cursor-pointer items-center justify-center rounded-lg bg-navy px-5 text-lemon transition-all hover:bg-forest disabled:opacity-50"
                            >
                                <Send class="h-4 w-4" />
                            </button>
                        </form>
                    </div>
                </template>

                <!-- If no chat is selected -->
                <div
                    v-else
                    class="flex flex-1 flex-col items-center justify-center gap-3 text-on-surface-variant/60"
                >
                    <MessageSquare
                        class="h-16 w-16 animate-bounce-slow text-outline-variant/60"
                    />
                    <p class="text-[10px] font-black tracking-widest uppercase">
                        No Session Selected
                    </p>
                    <p
                        class="max-w-xs text-center text-xs text-on-surface-variant/75"
                    >
                        Select an active or waiting chat session from the queue
                        left sidebar to start communicating with customers in
                        real-time.
                    </p>
                </div>
            </div>
        </div>

        <!-- Convert to Ticket Modal -->
        <div
            v-if="showConvertModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-on-background/40 p-4 backdrop-blur-sm"
        >
            <div
                class="flex w-full max-w-md flex-col gap-4 rounded-xl border border-outline-variant bg-surface-container-lowest p-6 shadow-xl"
            >
                <div
                    class="flex items-center justify-between border-b border-outline-variant/30 pb-4"
                >
                    <h2
                        class="flex items-center gap-1.5 text-sm font-black tracking-widest text-primary uppercase"
                    >
                        <FileText class="h-4 w-4 text-primary" />
                        <span>Convert Chat to Ticket</span>
                    </h2>
                    <button
                        @click="showConvertModal = false"
                        class="cursor-pointer font-bold text-on-surface-variant hover:text-primary"
                    >
                        ✕
                    </button>
                </div>

                <form @submit.prevent="submitConvert" class="space-y-4 text-xs">
                    <!-- Subject -->
                    <div class="flex flex-col gap-1.5">
                        <label
                            for="subject"
                            class="text-[10px] font-bold tracking-wider text-on-surface-variant uppercase"
                            >Ticket Subject</label
                        >
                        <input
                            id="subject"
                            v-model="convertForm.subject"
                            type="text"
                            required
                            placeholder="E.G. BILLING DISPUTE DISCOVERED DURING CHAT"
                            class="rounded-lg border border-outline-variant bg-surface-container-low px-4 py-3 font-bold text-on-surface uppercase focus:ring-1 focus:ring-secondary focus:outline-none"
                        />
                        <p
                            v-if="convertForm.errors.subject"
                            class="mt-1 text-error"
                        >
                            {{ convertForm.errors.subject }}
                        </p>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <!-- Category -->
                        <div class="flex flex-col gap-1.5">
                            <label
                                class="text-[10px] font-bold tracking-wider text-on-surface-variant uppercase"
                                >Category</label
                            >
                            <select
                                v-model="convertForm.category"
                                required
                                class="rounded-lg border border-outline-variant bg-surface-container-low px-4 py-3 font-bold text-on-surface uppercase focus:ring-1 focus:ring-secondary focus:outline-none"
                            >
                                <option value="">-- CHOOSE --</option>
                                <option
                                    v-for="cat in categories"
                                    :key="cat"
                                    :value="cat"
                                >
                                    {{ cat }}
                                </option>
                            </select>
                            <p
                                v-if="convertForm.errors.category"
                                class="mt-1 text-error"
                            >
                                {{ convertForm.errors.category }}
                            </p>
                        </div>

                        <!-- Priority -->
                        <div class="flex flex-col gap-1.5">
                            <label
                                class="text-[10px] font-bold tracking-wider text-on-surface-variant uppercase"
                                >Priority</label
                            >
                            <select
                                v-model="convertForm.priority"
                                required
                                class="rounded-lg border border-outline-variant bg-surface-container-low px-4 py-3 font-bold text-on-surface uppercase focus:ring-1 focus:ring-secondary focus:outline-none"
                            >
                                <option
                                    v-for="prio in priorities"
                                    :key="prio"
                                    :value="prio"
                                >
                                    {{ prio }}
                                </option>
                            </select>
                            <p
                                v-if="convertForm.errors.priority"
                                class="mt-1 text-error"
                            >
                                {{ convertForm.errors.priority }}
                            </p>
                        </div>
                    </div>

                    <!-- Modal Actions -->
                    <div
                        class="flex justify-end gap-3 border-t border-outline-variant/30 pt-4"
                    >
                        <button
                            type="button"
                            @click="showConvertModal = false"
                            class="cursor-pointer rounded-lg border border-outline-variant px-4 py-2.5 font-bold text-on-surface-variant uppercase transition-colors hover:text-primary"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="convertForm.processing"
                            class="cursor-pointer rounded-lg bg-navy px-5 py-2.5 font-black text-lemon uppercase transition-all disabled:opacity-50"
                        >
                            Convert & Open
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </SupportLayout>
</template>
