<script setup lang="ts">
import { Head, useForm, router, Link } from '@inertiajs/vue3';
import { CreditCard, Search, RefreshCw } from 'lucide-vue-next';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { formatDateTime } from '@/lib/utils';
import {
    index as paystackIndex,
    requery as paystackRequery,
} from '@/routes/admin/paystack-transactions';

type PaystackTransaction = {
    id: number;
    user_id: number;
    reference: string;
    access_code: string | null;
    amount: number; // In kobo
    status: 'pending' | 'success' | 'failed';
    channel: string | null;
    currency: string;
    paid_at: string | null;
    created_at: string;
    user: { name: string; email: string; phone: string } | null;
};

const props = defineProps<{
    transactions: {
        data: PaystackTransaction[];
        links: any[];
        current_page: number;
        last_page: number;
    };
    filters: {
        search: string | null;
        status: string | null;
    };
    availableStatuses: string[];
}>();

const searchForm = useForm({
    search: props.filters.search || '',
    status: props.filters.status || '',
});

const handleSearch = () => {
    searchForm.get(paystackIndex.url(), {
        preserveState: true,
    });
};

const clearSearch = () => {
    searchForm.search = '';
    searchForm.status = '';
    handleSearch();
};

const requeryTransaction = (id: number) => {
    router.post(paystackRequery.url(id));
};
</script>

<template>
    <Head title="Admin - Paystack Logs" />

    <AdminLayout :breadcrumbs="[{ title: 'Paystack Logs' }]">
        <div class="flex flex-col gap-6 font-sans text-xs">
            <!-- Header bar -->
            <div
                class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center"
            >
                <div>
                    <h1
                        class="flex items-center gap-2 text-2xl font-black tracking-tight text-primary uppercase"
                    >
                        <CreditCard class="h-6 w-6 text-primary" />
                        <span>Paystack Gateway Log Audit</span>
                    </h1>
                    <p class="mt-1 text-xs text-on-surface-variant">
                        Audit all online cash transactions, check gateway
                        invoices, and resolve pending deposits via requeries.
                    </p>
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
                            placeholder="SEARCH BY USER EMAIL OR TRANSACTION REFERENCE..."
                            class="w-full rounded-lg border border-outline-variant bg-surface-container-low py-3 pr-4 pl-10 text-xs font-bold text-on-surface focus:ring-1 focus:ring-secondary focus:outline-none"
                        />
                        <Search
                            class="absolute top-3.5 left-3.5 h-4 w-4 text-on-surface-variant"
                        />
                    </div>

                    <div class="w-full md:w-48">
                        <select
                            v-model="searchForm.status"
                            class="w-full rounded-lg border border-outline-variant bg-surface-container-low px-4 py-3 text-xs font-bold text-on-surface uppercase focus:ring-1 focus:ring-secondary focus:outline-none"
                        >
                            <option value="">ALL STATUSES</option>
                            <option
                                v-for="s in availableStatuses"
                                :key="s"
                                :value="s"
                            >
                                {{ s }}
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

            <!-- Paystack Transactions List Table -->
            <div
                class="flex flex-col overflow-hidden rounded-xl border border-outline-variant bg-surface-container-lowest shadow-sm"
            >
                <div class="overflow-x-auto">
                    <table
                        class="w-full min-w-[900px] border-collapse text-left"
                    >
                        <thead
                            class="border-b border-outline-variant bg-surface-container-low"
                        >
                            <tr
                                class="text-[10px] font-bold tracking-widest text-on-surface-variant uppercase"
                            >
                                <th class="px-6 py-4">Reference ID</th>
                                <th class="px-6 py-4">User Info</th>
                                <th class="px-6 py-4 text-right">
                                    Naira Amount
                                </th>
                                <th class="px-6 py-4">Gateway Details</th>
                                <th class="px-6 py-4">Status</th>
                                <th class="px-6 py-4">Paid At / Created At</th>
                                <th class="px-6 py-4 text-right">Operations</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-outline-variant">
                            <tr
                                v-for="tx in transactions.data"
                                :key="tx.id"
                                class="group transition-colors hover:bg-surface-container-low/20"
                            >
                                <td
                                    class="px-6 py-4 font-bold text-on-surface-variant select-all"
                                >
                                    {{ tx.reference }}
                                </td>
                                <td class="px-6 py-4">
                                    <div v-if="tx.user" class="flex flex-col">
                                        <span class="font-bold text-primary">{{
                                            tx.user.name
                                        }}</span>
                                        <span
                                            class="mt-0.5 text-[10px] text-on-surface-variant"
                                            >{{ tx.user.phone }}</span
                                        >
                                    </div>
                                    <span
                                        v-else
                                        class="text-on-surface-variant italic"
                                        >Unknown Depositor</span
                                    >
                                </td>
                                <td
                                    class="px-6 py-4 text-right font-sans text-xs font-bold text-primary"
                                >
                                    ₦{{ (tx.amount / 100).toLocaleString() }}
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex flex-col">
                                        <span
                                            class="font-semibold text-on-surface"
                                            >CODE:
                                            {{ tx.access_code || 'N/A' }}</span
                                        >
                                        <span
                                            class="mt-0.5 text-[9px] text-on-surface-variant uppercase"
                                            >VIA:
                                            {{ tx.channel || 'pending' }}</span
                                        >
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <span
                                        :class="[
                                            'rounded-full border px-2.5 py-1 text-[9px] font-black tracking-widest uppercase',
                                            tx.status === 'success' &&
                                                'border-secondary/20 bg-secondary-container text-on-secondary-container',
                                            tx.status === 'pending' &&
                                                'text-amber-850 animate-pulse border-amber-200 bg-amber-50',
                                            tx.status === 'failed' &&
                                                'border-error/20 bg-error-container/30 text-error',
                                        ]"
                                    >
                                        {{
                                            tx.status === 'success'
                                                ? 'SUCCESS'
                                                : tx.status.toUpperCase()
                                        }}
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex flex-col text-[10px]">
                                        <span
                                            class="font-bold text-on-surface"
                                            v-if="tx.paid_at"
                                            >PAID:
                                            {{
                                                formatDateTime(tx.paid_at)
                                            }}</span
                                        >
                                        <span
                                            class="text-on-surface-variant"
                                            :class="[
                                                !tx.paid_at && 'font-bold',
                                            ]"
                                            >INIT:
                                            {{
                                                formatDateTime(tx.created_at)
                                            }}</span
                                        >
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <button
                                        v-if="tx.status === 'pending'"
                                        @click="requeryTransaction(tx.id)"
                                        class="text-amber-850 inline-flex items-center gap-1.5 rounded-lg border border-amber-300 px-2.5 py-1.5 text-[10px] font-black uppercase transition-colors hover:bg-amber-50"
                                        title="Requery paystack reference status"
                                    >
                                        <RefreshCw class="h-3.5 w-3.5" />
                                        <span>Requery</span>
                                    </button>
                                    <span
                                        v-else
                                        class="text-[10px] font-bold tracking-widest text-on-surface-variant uppercase italic"
                                        >Resolved</span
                                    >
                                </td>
                            </tr>
                            <tr v-if="transactions.data.length === 0">
                                <td
                                    colspan="7"
                                    class="px-6 py-12 text-center font-bold tracking-widest text-on-surface-variant uppercase"
                                >
                                    No Paystack payment sessions found.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination footer links -->
                <div
                    v-if="transactions.last_page > 1"
                    class="flex items-center justify-between border-t border-outline-variant bg-surface-container-low px-6 py-4"
                >
                    <p
                        class="text-[10px] font-bold tracking-widest text-on-surface-variant uppercase"
                    >
                        Page {{ transactions.current_page }} of
                        {{ transactions.last_page }}
                    </p>
                    <div class="flex gap-1">
                        <Link
                            v-for="link in transactions.links"
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
