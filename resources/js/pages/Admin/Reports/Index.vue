<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    ClipboardList,
    Download,
    Eye,
    MousePointerClick,
    UserCheck,
    UserX,
} from 'lucide-vue-next';
import { ref } from 'vue';
import AppPaginator from '@/components/layout/AppPaginator.vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { index as reportsIndex, download as reportsDownload } from '@/routes/admin/reports';

type ReportUser = {
    id: number;
    name: string;
    email: string;
};

type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
};

type Period = 'daily' | 'weekly' | 'monthly';

const props = defineProps<{
    summary: {
        period: Period;
        range: { start: string; end: string };
        visits: number;
        engagement: number;
        active_count: number;
        non_active_count: number;
    };
    activeUsers: Paginated<ReportUser>;
    nonActiveUsers: Paginated<ReportUser>;
    filters: {
        period: Period;
        date: string | null;
    };
}>();

const period = ref<Period>(props.filters.period);
const date = ref<string>(props.filters.date ?? '');

function applyFilters(extra: Record<string, string | number> = {}): void {
    router.get(
        reportsIndex.url({
            query: {
                period: period.value,
                ...(date.value ? { date: date.value } : {}),
                ...extra,
            },
        }),
        {},
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function goToActivePage(page: number): void {
    applyFilters({ active_page: page });
}

function goToInactivePage(page: number): void {
    applyFilters({ inactive_page: page });
}

function downloadUrl(): string {
    return reportsDownload.url({
        query: {
            period: period.value,
            ...(date.value ? { date: date.value } : {}),
        },
    });
}

function formatCount(value: number): string {
    if (value >= 1_000_000) {
        return `${(value / 1_000_000).toFixed(1).replace(/\.0$/, '')}m`;
    }
    if (value >= 1_000) {
        return `${(value / 1_000).toFixed(1).replace(/\.0$/, '')}k`;
    }
    return String(value);
}
</script>

<template>
    <Head title="Admin - Daily Reports" />

    <AdminLayout :breadcrumbs="[{ title: 'Daily Reports' }]">
        <div class="flex flex-col gap-6 font-sans text-xs">
            <!-- Header -->
            <div
                class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center"
            >
                <div>
                    <h1
                        class="flex items-center gap-2 text-2xl font-black tracking-tight text-primary uppercase"
                    >
                        <ClipboardList class="h-6 w-6 text-primary" />
                        Daily Reports
                    </h1>
                    <p class="mt-1 text-on-surface-variant">
                        {{ summary.range.start }}
                        <template v-if="summary.range.start !== summary.range.end">
                            to {{ summary.range.end }}
                        </template>
                    </p>
                </div>

                <a                
                    :href="downloadUrl()"
                    class="inline-flex items-center gap-2 rounded-xl bg-primary px-5 py-2.5 font-bold text-on-primary transition-opacity hover:opacity-90"
                >
                    <Download class="h-4 w-4" />
                    Download PDF
                </a>
            </div>

            <!-- Filters -->
            <div
                class="flex flex-wrap items-center gap-3 rounded-2xl border border-outline-variant/40 bg-surface-container p-4"
            >
                <select
                    v-model="period"
                    class="rounded-xl border border-outline-variant/40 bg-background px-4 py-2 font-bold uppercase"
                    @change="applyFilters({ active_page: 1, inactive_page: 1 })"
                >
                    <option value="daily">Daily</option>
                    <option value="weekly">Weekly</option>
                    <option value="monthly">Monthly</option>
                </select>

                <input
                    v-model="date"
                    type="date"
                    class="rounded-xl border border-outline-variant/40 bg-background px-4 py-2"
                    @change="applyFilters({ active_page: 1, inactive_page: 1 })"
                />
            </div>

            <!-- Stat cards -->
            <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                <div
                    class="rounded-2xl border border-outline-variant/40 bg-surface-container p-5"
                >
                    <div class="flex items-center gap-2 text-on-surface-variant">
                        <Eye class="h-4 w-4" />
                        <span class="font-bold uppercase">Visits</span>
                    </div>
                    <p class="mt-2 text-3xl font-black text-on-surface">
                        {{ formatCount(summary.visits) }}
                    </p>
                </div>

                <div
                    class="rounded-2xl border border-outline-variant/40 bg-surface-container p-5"
                >
                    <div class="flex items-center gap-2 text-on-surface-variant">
                        <MousePointerClick class="h-4 w-4" />
                        <span class="font-bold uppercase">Engagement</span>
                    </div>
                    <p class="mt-2 text-3xl font-black text-on-surface">
                        {{ formatCount(summary.engagement) }}
                    </p>
                </div>

                <div
                    class="rounded-2xl border border-outline-variant/40 bg-surface-container p-5"
                >
                    <div class="flex items-center gap-2 text-on-surface-variant">
                        <UserCheck class="h-4 w-4" />
                        <span class="font-bold uppercase">Active Subscribers</span>
                    </div>
                    <p class="mt-2 text-3xl font-black text-on-surface">
                        {{ formatCount(summary.active_count) }}
                    </p>
                    <p class="mt-1 text-[10px] text-on-surface-variant">
                        Current status — not affected by the period filter
                    </p>
                </div>

                <div
                    class="rounded-2xl border border-outline-variant/40 bg-surface-container p-5"
                >
                    <div class="flex items-center gap-2 text-on-surface-variant">
                        <UserX class="h-4 w-4" />
                        <span class="font-bold uppercase">Non-Active Subscribers</span>
                    </div>
                    <p class="mt-2 text-3xl font-black text-on-surface">
                        {{ formatCount(summary.non_active_count) }}
                    </p>
                    <p class="mt-1 text-[10px] text-on-surface-variant">
                        Current status — not affected by the period filter
                    </p>
                </div>
            </div>

            <!-- Active / Non-active user lists -->
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                <div
                    class="rounded-2xl border border-outline-variant/40 bg-surface-container p-5"
                >
                    <h2 class="mb-3 font-bold text-on-surface uppercase">
                        Active Subscribers ({{ summary.active_count }})
                    </h2>
                    <table class="w-full text-left">
                        <thead>
                            <tr class="text-on-surface-variant">
                                <th class="pb-2">Name</th>
                                <th class="pb-2">Email</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="user in activeUsers.data"
                                :key="user.id"
                                class="border-t border-outline-variant/30"
                            >
                                <td class="py-2 font-bold text-on-surface">
                                    {{ user.name }}
                                </td>
                                <td class="py-2 text-on-surface-variant">
                                    {{ user.email }}
                                </td>
                            </tr>
                            <tr v-if="activeUsers.data.length === 0">
                                <td
                                    colspan="2"
                                    class="py-6 text-center text-on-surface-variant italic"
                                >
                                    No active users in this period.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <AppPaginator
                        v-if="activeUsers.last_page > 1"
                        class="mt-3"
                        :current-page="activeUsers.current_page"
                        :last-page="activeUsers.last_page"
                        @page-change="goToActivePage"
                    />
                </div>

                <div
                    class="rounded-2xl border border-outline-variant/40 bg-surface-container p-5"
                >
                    <h2 class="mb-3 font-bold text-on-surface uppercase">
                        Non-Active Subscribers ({{ summary.non_active_count }})
                    </h2>
                    <table class="w-full text-left">
                        <thead>
                            <tr class="text-on-surface-variant">
                                <th class="pb-2">Name</th>
                                <th class="pb-2">Email</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="user in nonActiveUsers.data"
                                :key="user.id"
                                class="border-t border-outline-variant/30"
                            >
                                <td class="py-2 font-bold text-on-surface">
                                    {{ user.name }}
                                </td>
                                <td class="py-2 text-on-surface-variant">
                                    {{ user.email }}
                                </td>
                            </tr>
                            <tr v-if="nonActiveUsers.data.length === 0">
                                <td
                                    colspan="2"
                                    class="py-6 text-center text-on-surface-variant italic"
                                >
                                    No non-active users in this period.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <AppPaginator
                        v-if="nonActiveUsers.last_page > 1"
                        class="mt-3"
                        :current-page="nonActiveUsers.current_page"
                        :last-page="nonActiveUsers.last_page"
                        @page-change="goToInactivePage"
                    />
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
