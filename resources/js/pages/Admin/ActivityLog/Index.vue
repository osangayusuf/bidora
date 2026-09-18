<script setup lang="ts">
import { Head, useForm, router, Link } from '@inertiajs/vue3';
import { reactive } from 'vue';
import {
    ShieldAlert,
    Search,
    Lock,
    Unlock,
    Globe,
    Activity,
    AlertTriangle,
    CheckCircle,
    XCircle,
    UserMinus,
    ShieldOff,
} from 'lucide-vue-next';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { index as activityLogIndex } from '@/routes/admin/activity-log';
import {
    toggleActive as usersToggleActive,
    bulkDisable as usersBulkDisable,
} from '@/routes/admin/users';

type User = {
    id: number;
    name: string;
    email: string;
    is_active: boolean;
};

type UserActivity = {
    id: number;
    user_id: number | null;
    type: string;
    subject_type: string | null;
    subject_id: number | null;
    metadata: any;
    ip_address: string | null;
    created_at: string;
    user: User | null;
};

type SharedIpAlert = {
    ip: string;
    users: User[];
    count: number;
};

type HighBidAlert = {
    user: User;
    minute: string;
    bid_count: number;
};

const props = defineProps<{
    activities: {
        data: UserActivity[];
        links: any[];
        current_page: number;
        last_page: number;
    };
    filters: {
        search: string | null;
        type: string | null;
        ip: string | null;
    };
    availableTypes: string[];
    alerts: {
        sharedIps: SharedIpAlert[];
        highBidRate: HighBidAlert[];
    };
}>();

const searchForm = useForm({
    search: props.filters.search || '',
    type: props.filters.type || '',
    ip: props.filters.ip || '',
});

const handleSearch = () => {
    searchForm.get(activityLogIndex.url(), {
        preserveState: true,
    });
};

const clearSearch = () => {
    searchForm.search = '';
    searchForm.type = '';
    searchForm.ip = '';
    handleSearch();
};

const toggleUserActive = (user: User) => {
    const action = user.is_active ? 'disable' : 'enable';

    if (confirm(`Are you sure you want to ${action} this user's account?`)) {
        router.post(
            usersToggleActive.url(user.id),
            {},
            {
                preserveScroll: true,
            },
        );
    }
};

// Per-IP-group selection state for bulk disable, keyed by IP address.
const selectedByIp = reactive<Record<string, number[]>>({});

const selectedForIp = (ip: string) => selectedByIp[ip] ?? [];

const isSelected = (ip: string, userId: number) =>
    selectedForIp(ip).includes(userId);

const toggleSelection = (ip: string, userId: number) => {
    const current = selectedByIp[ip] ?? [];
    selectedByIp[ip] = current.includes(userId)
        ? current.filter((id) => id !== userId)
        : [...current, userId];
};

const bulkDisableUsers = (userIds: number[], ip?: string) => {
    if (userIds.length === 0) {
        return;
    }

    const label =
        userIds.length === 1 ? '1 account' : `${userIds.length} accounts`;

    if (
        confirm(
            `Are you sure you want to disable ${label}${ip ? ` from IP ${ip}` : ''}? This cannot be undone from here.`,
        )
    ) {
        router.post(
            usersBulkDisable.url(),
            { user_ids: userIds },
            {
                preserveScroll: true,
                onSuccess: () => {
                    if (ip) {
                        selectedByIp[ip] = [];
                    }
                },
            },
        );
    }
};

const disableSelectedForIp = (ip: string) => {
    bulkDisableUsers(selectedForIp(ip), ip);
};

const disableAllForIp = (alert: SharedIpAlert) => {
    bulkDisableUsers(
        alert.users.filter((u) => u.is_active).map((u) => u.id),
        alert.ip,
    );
};

const formatMetadata = (metadata: any) => {
    if (!metadata) {
        return 'N/A';
    }

    let data = metadata;

    if (typeof metadata === 'string') {
        try {
            data = JSON.parse(metadata);
        } catch (e) {
            return metadata;
        }
    }

    if (typeof data !== 'object') {
        return String(data);
    }

    return Object.entries(data)
        .map(
            ([k, v]) =>
                `${k.replace(/_/g, ' ')}: ${typeof v === 'object' ? JSON.stringify(v) : v}`,
        )
        .join(' | ');
};

const getBadgeStyle = (type: string) => {
    const lower = type.toLowerCase();

    if (lower.includes('failed') || lower.includes('alert')) {
        return 'bg-error-container/30 border-error/20 text-error';
    }

    if (
        lower.includes('success') ||
        lower.includes('deposit') ||
        lower.includes('claimed') ||
        lower.includes('win')
    ) {
        return 'bg-emerald-500/10 text-emerald-500 border-emerald-500/20';
    }

    if (lower.includes('bid')) {
        return 'bg-secondary-container text-on-secondary-container border-secondary/20';
    }

    return 'bg-surface-container-high text-primary border-outline-variant/50';
};
</script>

<template>
    <Head title="Admin - User Activity Audit & Spam Protection" />

    <AdminLayout :breadcrumbs="[{ title: 'Spam Protection & Activity Logs' }]">
        <div class="flex flex-col gap-6 font-sans text-xs">
            <!-- Header bar -->
            <div
                class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center"
            >
                <div>
                    <h1
                        class="flex items-center gap-2 text-2xl font-black tracking-tight text-primary uppercase"
                    >
                        <ShieldAlert class="h-6 w-6 text-primary" />
                        <span>Security & Spam Protection Audit</span>
                    </h1>
                    <p class="mt-1 text-xs text-on-surface-variant">
                        Audit real-time user activities, identify potential bot
                        biddings, and handle multiple registration threats from
                        shared IP addresses.
                    </p>
                </div>
            </div>

            <!-- Alerts Panel (Visible only when threats are found) -->
            <div
                v-if="
                    alerts.sharedIps.length > 0 || alerts.highBidRate.length > 0
                "
                class="flex flex-col gap-4"
            >
                <h2
                    class="flex items-center gap-1 text-xs font-black tracking-widest text-error uppercase"
                >
                    <AlertTriangle class="h-4 w-4 text-error" />
                    <span>Suspicious Activity Security Alerts</span>
                </h2>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <!-- Shared IP Threat Card -->
                    <div
                        v-if="alerts.sharedIps.length > 0"
                        class="space-y-4 rounded-xl border-2 border-error bg-surface-container-lowest p-5 shadow-md"
                    >
                        <div
                            class="flex items-start gap-3 border-b border-outline-variant/30 pb-3"
                        >
                            <div
                                class="rounded-lg bg-error-container/20 p-2 text-error"
                            >
                                <Globe class="h-5 w-5" />
                            </div>
                            <div>
                                <h3
                                    class="text-sm font-black text-primary uppercase"
                                >
                                    IP Sharing Alert
                                </h3>
                                <p
                                    class="mt-0.5 text-[10px] text-on-surface-variant"
                                >
                                    IP addresses with 3+ unique
                                    active/registered user accounts in 24 hours.
                                </p>
                            </div>
                        </div>

                        <div class="space-y-3">
                            <div
                                v-for="alert in alerts.sharedIps"
                                :key="alert.ip"
                                class="rounded-lg border border-outline-variant bg-surface-container-low/40 p-3.5"
                            >
                                <div
                                    class="mb-2 flex items-center justify-between gap-2"
                                >
                                    <span
                                        class="font-mono text-xs font-bold text-primary select-all"
                                        >IP: {{ alert.ip }}</span
                                    >
                                    <span
                                        class="rounded-full bg-error px-2 py-0.5 text-[9px] font-black tracking-wider text-on-error uppercase"
                                        >{{ alert.count }} accounts</span
                                    >
                                </div>
                                <div
                                    class="mb-2 flex items-center justify-end gap-2"
                                >
                                    <button
                                        v-if="selectedForIp(alert.ip).length > 0"
                                        @click="disableSelectedForIp(alert.ip)"
                                        class="flex items-center gap-1 rounded border border-error/20 bg-error-container/10 px-2 py-1 text-[9px] font-black text-error uppercase transition-colors hover:bg-error-container/25"
                                    >
                                        <Lock class="h-3 w-3" />
                                        <span
                                            >Disable Selected ({{
                                                selectedForIp(alert.ip).length
                                            }})</span
                                        >
                                    </button>
                                    <button
                                        v-if="
                                            alert.users.some(
                                                (u) => u.is_active,
                                            )
                                        "
                                        @click="disableAllForIp(alert)"
                                        class="flex items-center gap-1 rounded bg-error px-2 py-1 text-[9px] font-black text-on-error uppercase transition-colors hover:bg-error/90"
                                    >
                                        <ShieldOff class="h-3 w-3" />
                                        <span>Disable All</span>
                                    </button>
                                </div>
                                <div class="space-y-2">
                                    <div
                                        v-for="user in alert.users"
                                        :key="user.id"
                                        class="flex items-center justify-between rounded border border-outline-variant/40 bg-surface-container-lowest p-2"
                                    >
                                        <div
                                            class="flex min-w-0 flex-1 items-center gap-2"
                                        >
                                            <input
                                                v-if="user.is_active"
                                                type="checkbox"
                                                :checked="
                                                    isSelected(
                                                        alert.ip,
                                                        user.id,
                                                    )
                                                "
                                                @change="
                                                    toggleSelection(
                                                        alert.ip,
                                                        user.id,
                                                    )
                                                "
                                                class="h-3.5 w-3.5 shrink-0 rounded border-outline-variant text-error focus:ring-error"
                                            />
                                            <div class="min-w-0">
                                                <span
                                                    class="block truncate font-bold text-primary"
                                                    >{{ user.name }}</span
                                                >
                                                <span
                                                    class="mt-0.5 block truncate font-mono text-[9px] text-on-surface-variant"
                                                    >{{ user.email }}</span
                                                >
                                            </div>
                                        </div>
                                        <div class="pl-2">
                                            <button
                                                @click="toggleUserActive(user)"
                                                :class="[
                                                    'flex items-center gap-1 rounded border px-2 py-1 text-[9px] font-black uppercase transition-colors',
                                                    user.is_active
                                                        ? 'border-error/20 text-error hover:bg-error-container/20'
                                                        : 'border-secondary/20 text-on-secondary-container hover:bg-secondary-container/40',
                                                ]"
                                            >
                                                <component
                                                    :is="
                                                        user.is_active
                                                            ? Lock
                                                            : Unlock
                                                    "
                                                    class="h-3 w-3"
                                                />
                                                <span>{{
                                                    user.is_active
                                                        ? 'Disable'
                                                        : 'Enable'
                                                }}</span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- High Bid Frequency / Potential Bot Card -->
                    <div
                        v-if="alerts.highBidRate.length > 0"
                        class="space-y-4 rounded-xl border-2 border-error bg-surface-container-lowest p-5 shadow-md"
                    >
                        <div
                            class="flex items-start gap-3 border-b border-outline-variant/30 pb-3"
                        >
                            <div
                                class="rounded-lg bg-error-container/20 p-2 text-error"
                            >
                                <Activity class="h-5 w-5" />
                            </div>
                            <div>
                                <h3
                                    class="text-sm font-black text-primary uppercase"
                                >
                                    Bidding Speed Limit Trigger
                                </h3>
                                <p
                                    class="mt-0.5 text-[10px] text-on-surface-variant"
                                >
                                    Users placing 20+ bids within a single
                                    minute window (potential bots).
                                </p>
                            </div>
                        </div>

                        <div class="space-y-3">
                            <div
                                v-for="alert in alerts.highBidRate"
                                :key="alert.user.id + alert.minute"
                                class="flex items-center justify-between rounded-lg border border-outline-variant bg-surface-container-low/40 p-3.5"
                            >
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2">
                                        <span
                                            class="rounded bg-error px-1.5 py-0.5 text-[8px] font-black tracking-wider text-on-error uppercase"
                                            >{{
                                                alert.bid_count
                                            }}
                                            BIDS/MIN</span
                                        >
                                        <span
                                            class="block truncate font-black text-primary"
                                            >{{ alert.user.name }}</span
                                        >
                                    </div>
                                    <span
                                        class="mt-1 block truncate pl-0 font-mono text-[9px] font-bold tracking-wider text-on-surface-variant uppercase"
                                        >Trigger: {{ alert.minute }}</span
                                    >
                                    <span
                                        class="mt-0.5 block truncate font-mono text-[9px] text-on-surface-variant"
                                        >{{ alert.user.email }}</span
                                    >
                                </div>
                                <div class="pl-3">
                                    <button
                                        @click="toggleUserActive(alert.user)"
                                        :class="[
                                            'flex items-center gap-1 rounded border px-3 py-1.5 text-[9px] font-black uppercase transition-colors',
                                            alert.user.is_active
                                                ? 'border-error/20 text-error hover:bg-error-container/20'
                                                : 'border-secondary/20 text-on-secondary-container hover:bg-secondary-container/40',
                                        ]"
                                    >
                                        <component
                                            :is="
                                                alert.user.is_active
                                                    ? Lock
                                                    : Unlock
                                            "
                                            class="h-3 w-3"
                                        />
                                        <span>{{
                                            alert.user.is_active
                                                ? 'Disable'
                                                : 'Enable'
                                        }}</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Search and Filter Panel -->
            <div
                class="rounded-xl border border-outline-variant bg-surface-container-lowest p-4 shadow-sm"
            >
                <form
                    @submit.prevent="handleSearch"
                    class="flex flex-col gap-3 md:flex-row"
                >
                    <div class="relative flex-1">
                        <input
                            v-model="searchForm.search"
                            type="text"
                            placeholder="SEARCH BY USER NAME OR EMAIL..."
                            class="w-full rounded-lg border border-outline-variant bg-surface-container-low py-3 pr-4 pl-10 text-xs font-bold text-on-surface focus:ring-1 focus:ring-secondary focus:outline-none"
                        />
                        <Search
                            class="absolute top-3.5 left-3.5 h-4 w-4 text-on-surface-variant"
                        />
                    </div>

                    <div class="w-full md:w-48">
                        <input
                            v-model="searchForm.ip"
                            type="text"
                            placeholder="FILTER BY IP ADDRESS..."
                            class="w-full rounded-lg border border-outline-variant bg-surface-container-low px-4 py-3 text-xs font-bold text-on-surface focus:ring-1 focus:ring-secondary focus:outline-none"
                        />
                    </div>

                    <div class="w-full md:w-56">
                        <select
                            v-model="searchForm.type"
                            class="w-full rounded-lg border border-outline-variant bg-surface-container-low px-4 py-3 text-xs font-bold text-on-surface uppercase focus:ring-1 focus:ring-secondary focus:outline-none"
                        >
                            <option value="">ALL ACTIVITY TYPES</option>
                            <option
                                v-for="type in availableTypes"
                                :key="type"
                                :value="type"
                            >
                                {{ type.replace(/_/g, ' ') }}
                            </option>
                        </select>
                    </div>

                    <div class="flex gap-2">
                        <button
                            type="submit"
                            class="rounded-lg bg-secondary px-5 py-3 font-black text-on-secondary-fixed uppercase transition-all"
                        >
                            Filter
                        </button>
                        <button
                            type="button"
                            @click="clearSearch"
                            class="rounded-lg border border-outline-variant bg-surface-container-lowest px-5 py-3 font-bold text-on-surface-variant uppercase transition-all hover:text-primary"
                        >
                            Reset
                        </button>
                    </div>
                </form>
            </div>

            <!-- Activity Logs Table -->
            <div
                class="flex flex-col overflow-hidden rounded-xl border border-outline-variant bg-surface-container-lowest shadow-sm"
            >
                <div class="overflow-x-auto">
                    <table
                        class="w-full min-w-[1000px] border-collapse text-left"
                    >
                        <thead
                            class="border-b border-outline-variant bg-surface-container-low"
                        >
                            <tr
                                class="text-[10px] font-bold tracking-widest text-on-surface-variant uppercase"
                            >
                                <th class="px-6 py-4">Log ID</th>
                                <th class="px-6 py-4">User</th>
                                <th class="px-6 py-4">Action Type</th>
                                <th class="px-6 py-4 font-mono">IP Address</th>
                                <th class="px-6 py-4">Details / Context</th>
                                <th class="px-6 py-4">Date</th>
                                <th class="px-6 py-4">Moderate</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-outline-variant">
                            <tr
                                v-for="act in activities.data"
                                :key="act.id"
                                class="group transition-colors hover:bg-surface-container-low/20"
                            >
                                <td
                                    class="px-6 py-4 font-bold text-on-surface-variant"
                                >
                                    #{{ act.id }}
                                </td>
                                <td class="px-6 py-4">
                                    <div v-if="act.user" class="flex flex-col">
                                        <span class="font-bold text-primary">{{
                                            act.user.name
                                        }}</span>
                                        <span
                                            class="mt-0.5 text-[10px] text-on-surface-variant"
                                            >{{ act.user.email }}</span
                                        >
                                    </div>
                                    <span
                                        v-else
                                        class="text-on-surface-variant italic"
                                        >Guest Session</span
                                    >
                                </td>
                                <td class="px-6 py-4">
                                    <span
                                        :class="[
                                            'rounded-full border px-2.5 py-1 text-[8px] font-black tracking-widest whitespace-nowrap uppercase',
                                            getBadgeStyle(act.type),
                                        ]"
                                    >
                                        {{ act.type.replace(/_/g, ' ') }}
                                    </span>
                                </td>
                                <td
                                    class="px-6 py-4 font-mono font-bold text-on-surface select-all"
                                >
                                    {{ act.ip_address || 'N/A' }}
                                </td>
                                <td
                                    class="max-w-sm truncate px-6 py-4 text-[10px] font-semibold text-on-surface select-all"
                                    :title="formatMetadata(act.metadata)"
                                >
                                    {{ formatMetadata(act.metadata) }}
                                </td>
                                <td
                                    class="px-6 py-4 text-[10px] font-semibold text-on-surface-variant"
                                >
                                    {{
                                        new Date(
                                            act.created_at,
                                        ).toLocaleString()
                                    }}
                                </td>
                                <td class="px-6 py-4">
                                    <div
                                        v-if="act.user"
                                        class="flex items-center"
                                    >
                                        <button
                                            @click="toggleUserActive(act.user)"
                                            :class="[
                                                'flex items-center gap-1 rounded-lg border px-2 py-1.5 text-[9px] font-bold uppercase transition-colors',
                                                act.user.is_active
                                                    ? 'border-error/20 text-error hover:bg-error-container/20'
                                                    : 'border-secondary/25 text-on-secondary-container hover:bg-secondary-container/45',
                                            ]"
                                        >
                                            <component
                                                :is="
                                                    act.user.is_active
                                                        ? Lock
                                                        : Unlock
                                                "
                                                class="h-3.5 w-3.5"
                                            />
                                            <span>{{
                                                act.user.is_active
                                                    ? 'Disable'
                                                    : 'Enable'
                                            }}</span>
                                        </button>
                                    </div>
                                    <span
                                        v-else
                                        class="text-on-surface-variant italic"
                                        >-</span
                                    >
                                </td>
                            </tr>
                            <tr v-if="activities.data.length === 0">
                                <td
                                    colspan="7"
                                    class="px-6 py-12 text-center font-bold tracking-widest text-on-surface-variant uppercase"
                                >
                                    No user activity logs found.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination footer links -->
                <div
                    v-if="activities.last_page > 1"
                    class="flex items-center justify-between border-t border-outline-variant bg-surface-container-low px-6 py-4"
                >
                    <p
                        class="text-[10px] font-bold tracking-widest text-on-surface-variant uppercase"
                    >
                        Page {{ activities.current_page }} of
                        {{ activities.last_page }}
                    </p>
                    <div class="flex gap-1">
                        <Link
                            v-for="link in activities.links"
                            :key="link.label"
                            :href="link.url || '#'"
                            v-html="link.label"
                            :class="[
                                'rounded-lg border px-3 py-1.5 text-[10px] font-bold transition-all',
                                link.active
                                    ? 'border-primary bg-primary text-on-primary'
                                    : 'border-outline-variant bg-surface-container-lowest text-on-surface-variant hover:bg-surface-container-low',
                                !link.url && 'cursor-not-allowed opacity-40',
                            ]"
                        />
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
