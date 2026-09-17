<script setup lang="ts">
import { Head, useForm, router, Link } from '@inertiajs/vue3';
import { RefreshCw, RotateCw, Repeat } from 'lucide-vue-next';
import AdminLayout from '@/layouts/AdminLayout.vue';
import pointSubscriptions, {
    index as pointSubscriptionsIndex,
} from '@/routes/admin/point-subscriptions';

type SubscriptionStatus =
    | 'pending'
    | 'active'
    | 'attention'
    | 'non_renewing'
    | 'completed'
    | 'cancelled';

type PointSubscription = {
    id: number;
    user_id: number;
    subscription_code: string | null;
    amount_naira: string | number;
    frequency: 'weekly' | 'monthly' | null;
    status: SubscriptionStatus;
    authorization_last4: string | null;
    authorization_brand: string | null;
    next_payment_date: string | null;
    next_charge_at: string | null;
    last_charged_at: string | null;
    failure_count: number;
    created_at: string;
    user: { name: string; email: string; phone: string } | null;
    plan: { amount_kobo: number; interval: string } | null;
    subscriptionPackage: { name: string; renewal_cycle: string } | null;
};

function cycleLabel(sub: PointSubscription): string {
    return sub.frequency ?? sub.subscriptionPackage?.renewal_cycle ?? '—';
}

const props = defineProps<{
    subscriptions: {
        data: PointSubscription[];
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
    searchForm.get(pointSubscriptionsIndex.url(), {
        preserveState: true,
    });
};

const clearSearch = () => {
    searchForm.search = '';
    searchForm.status = '';
    handleSearch();
};

const cancelSubscription = (subscription: PointSubscription) => {
    if (
        !confirm(
            `Cancel ${subscription.user?.name ?? 'this user'}'s ₦${Number(subscription.amount_naira).toLocaleString()} ${cycleLabel(subscription)} auto top-up?`,
        )
    ) {
        return;
    }

    router.post(
        pointSubscriptions.cancel.url(subscription.id),
        {},
        {
            preserveScroll: true,
        },
    );
};

const resyncSubscription = (subscription: PointSubscription) => {
    router.post(
        pointSubscriptions.resync.url(subscription.id),
        {},
        {
            preserveScroll: true,
        },
    );
};

function statusBadgeClass(status: SubscriptionStatus): string {
    switch (status) {
        case 'active':
            return 'border-secondary/20 bg-secondary-container text-on-secondary-container';
        case 'pending':
        case 'attention':
            return 'text-amber-850 animate-pulse border-amber-200 bg-amber-50';
        case 'non_renewing':
            return 'border-outline-variant bg-surface-container text-on-surface-variant';
        default:
            return 'border-error/20 bg-error-container/30 text-error';
    }
}

function formatDate(value: string | null): string {
    return value ? new Date(value).toLocaleString() : '—';
}
</script>

<template>
    <Head title="Admin - Point Subscriptions" />

    <AdminLayout :breadcrumbs="[{ title: 'Point Subscriptions' }]">
        <div class="flex flex-col gap-6 font-sans text-xs">
            <div
                class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center"
            >
                <div>
                    <h1
                        class="flex items-center gap-2 text-2xl font-black tracking-tight text-primary uppercase"
                    >
                        <Repeat class="h-6 w-6 text-primary" />
                        <span>Recurring Point Subscriptions</span>
                    </h1>
                    <p class="mt-1 text-xs text-on-surface-variant">
                        View, cancel, and resync auto top-up subscriptions
                        without touching the Paystack dashboard.
                    </p>
                </div>
            </div>

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
                            placeholder="SEARCH BY USER NAME, EMAIL OR PHONE..."
                            class="w-full rounded-lg border border-outline-variant bg-surface-container-low py-3 pr-4 pl-4 text-xs font-bold text-on-surface focus:ring-1 focus:ring-secondary focus:outline-none"
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
                                <th class="px-6 py-4">User</th>
                                <th class="px-6 py-4 text-right">Plan</th>
                                <th class="px-6 py-4">Card</th>
                                <th class="px-6 py-4">Status</th>
                                <th class="px-6 py-4">Next / Last Charge</th>
                                <th class="px-6 py-4 text-right">Operations</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-outline-variant">
                            <tr
                                v-for="sub in subscriptions.data"
                                :key="sub.id"
                                class="group transition-colors hover:bg-surface-container-low/20"
                            >
                                <td class="px-6 py-4">
                                    <div v-if="sub.user" class="flex flex-col">
                                        <span class="font-bold text-primary">{{
                                            sub.user.name
                                        }}</span>
                                        <span
                                            class="mt-0.5 text-[10px] text-on-surface-variant"
                                            >{{ sub.user.email }}</span
                                        >
                                    </div>
                                    <span
                                        v-else
                                        class="text-on-surface-variant italic"
                                        >Unknown user</span
                                    >
                                    <span
                                        v-if="sub.subscriptionPackage"
                                        class="mt-0.5 block text-[9px] font-bold text-secondary uppercase"
                                        >{{
                                            sub.subscriptionPackage.name
                                        }}
                                        package</span
                                    >
                                </td>
                                <td
                                    class="px-6 py-4 text-right font-sans text-xs font-bold text-primary"
                                >
                                    ₦{{
                                        Number(
                                            sub.amount_naira,
                                        ).toLocaleString()
                                    }}
                                    <span
                                        class="block text-[9px] font-normal text-on-surface-variant uppercase"
                                        >{{ cycleLabel(sub) }}</span
                                    >
                                </td>
                                <td class="px-6 py-4">
                                    <span
                                        v-if="sub.authorization_last4"
                                        class="font-semibold text-on-surface"
                                        >{{ sub.authorization_brand }} ····
                                        {{ sub.authorization_last4 }}</span
                                    >
                                    <span
                                        v-else
                                        class="text-on-surface-variant italic"
                                        >Not yet confirmed</span
                                    >
                                    <span
                                        v-if="sub.failure_count > 0"
                                        class="mt-0.5 block text-[9px] text-error uppercase"
                                        >{{ sub.failure_count }} failed
                                        charge(s)</span
                                    >
                                </td>
                                <td class="px-6 py-4">
                                    <span
                                        :class="[
                                            'rounded-full border px-2.5 py-1 text-[9px] font-black tracking-widest uppercase',
                                            statusBadgeClass(sub.status),
                                        ]"
                                    >
                                        {{ sub.status.replace('_', ' ') }}
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex flex-col text-[10px]">
                                        <span class="font-bold text-on-surface"
                                            >NEXT:
                                            {{
                                                formatDate(
                                                    sub.next_payment_date,
                                                )
                                            }}</span
                                        >
                                        <span class="text-on-surface-variant"
                                            >LAST:
                                            {{
                                                formatDate(sub.last_charged_at)
                                            }}</span
                                        >
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex justify-end gap-2">
                                        <button
                                            v-if="sub.subscription_code"
                                            @click="resyncSubscription(sub)"
                                            class="inline-flex items-center gap-1.5 rounded-lg border border-outline-variant px-2.5 py-1.5 text-[10px] font-black text-on-surface-variant uppercase transition-colors hover:bg-surface-container"
                                            title="Pull the latest status from Paystack"
                                        >
                                            <RotateCw class="h-3.5 w-3.5" />
                                            <span>Resync</span>
                                        </button>
                                        <button
                                            v-if="
                                                sub.status !== 'cancelled' &&
                                                sub.status !== 'completed'
                                            "
                                            @click="cancelSubscription(sub)"
                                            class="inline-flex items-center gap-1.5 rounded-lg border border-error/30 px-2.5 py-1.5 text-[10px] font-black text-error uppercase transition-colors hover:bg-error-container/30"
                                            title="Cancel this subscription"
                                        >
                                            <RefreshCw class="h-3.5 w-3.5" />
                                            <span>Cancel</span>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="subscriptions.data.length === 0">
                                <td
                                    colspan="6"
                                    class="px-6 py-12 text-center font-bold tracking-widest text-on-surface-variant uppercase"
                                >
                                    No recurring subscriptions found.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div
                    v-if="subscriptions.last_page > 1"
                    class="flex items-center justify-between border-t border-outline-variant bg-surface-container-low px-6 py-4"
                >
                    <p
                        class="text-[10px] font-bold tracking-widest text-on-surface-variant uppercase"
                    >
                        Page {{ subscriptions.current_page }} of
                        {{ subscriptions.last_page }}
                    </p>
                    <div class="flex gap-1">
                        <Link
                            v-for="link in subscriptions.links"
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
