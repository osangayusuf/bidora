<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';
import WalletPackageSubscriptionController from '@/actions/App/Http/Controllers/WalletPackageSubscriptionController';
import WalletSubscriptionController from '@/actions/App/Http/Controllers/WalletSubscriptionController';
import { usePaystackInline } from '@/composables/usePaystackInline';
import { appToast } from '@/lib/appToast';
import type {
    PaystackInit,
    PointSubscriptionListing,
    SubscriptionPackageListing,
} from '@/types/wallet';

defineProps<{
    packages: SubscriptionPackageListing[];
    subscriptions: PointSubscriptionListing[];
}>();

const { openPayment } = usePaystackInline();

const subscribingId = ref<number | null>(null);

function subscribe(pkg: SubscriptionPackageListing): void {
    if (subscribingId.value !== null) {
        return;
    }

    subscribingId.value = pkg.id;

    router.post(
        WalletPackageSubscriptionController.store.url(),
        { subscription_package_id: pkg.id },
        {
            preserveScroll: true,
            onFlash: async (flash) => {
                const init = flash.paystack_init as PaystackInit | undefined;

                if (!init) {
                    return;
                }

                try {
                    await openPayment(init);
                } catch {
                    appToast.show({
                        type: 'error',
                        message:
                            'Paystack could not be loaded. Please try again.',
                    });
                }
            },
            onError: (errors) => {
                appToast.show({
                    type: 'error',
                    message:
                        (errors.package as string | undefined) ??
                        'Unable to start your subscription. Please try again.',
                });
            },
            onFinish: () => {
                subscribingId.value = null;
            },
        },
    );
}

const cancellingId = ref<number | null>(null);

function cancelSubscription(subscription: PointSubscriptionListing): void {
    if (
        cancellingId.value !== null ||
        !confirm(
            `Cancel your ${subscription.package_name} subscription? You will not be charged again.`,
        )
    ) {
        return;
    }

    cancellingId.value = subscription.id;

    router.delete(WalletSubscriptionController.destroy.url(subscription.id), {
        preserveScroll: true,
        onError: () => {
            appToast.show({
                type: 'error',
                message:
                    'Unable to cancel this subscription right now. Please try again.',
            });
        },
        onFinish: () => {
            cancellingId.value = null;
        },
    });
}

function statusBadgeClass(status: PointSubscriptionListing['status']): string {
    switch (status) {
        case 'active':
        case 'completed':
            return 'border-secondary/20 bg-secondary-container text-on-secondary-container';
        case 'pending':
            return 'border-amber-200 bg-amber-50 text-amber-800 animate-pulse';
        case 'attention':
            return 'border-amber-200 bg-amber-50 text-amber-800';
        case 'non_renewing':
            return 'border-outline-variant bg-surface-container text-on-surface-variant';
        case 'cancelled':
        default:
            return 'border-error/20 bg-error-container/30 text-error';
    }
}

function formatDate(value: string | null): string {
    return value ? new Date(value).toLocaleDateString() : '—';
}
</script>

<template>
    <div
        class="rounded-xl border border-outline-variant bg-surface-container-lowest p-5 md:p-6"
    >
        <h3 class="text-lg font-semibold text-on-surface">
            Subscription packages
        </h3>
        <p class="mt-1 text-sm text-on-surface-variant">
            Pick a package for a fixed points bundle, renewed automatically on
            its cycle.
        </p>

        <div
            v-if="packages.length > 0"
            class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3"
        >
            <div
                v-for="pkg in packages"
                :key="pkg.id"
                class="flex flex-col justify-between rounded-lg border-2 border-outline-variant p-4 transition-colors hover:border-primary"
            >
                <div>
                    <p class="text-sm font-black text-on-surface uppercase">
                        {{ pkg.name }}
                    </p>
                    <p
                        class="mt-0.5 text-[10px] font-bold tracking-widest text-on-surface-variant uppercase"
                    >
                        {{ pkg.renewal_cycle_label }}
                    </p>
                    <p class="mt-3 text-xl font-black text-primary">
                        ₦{{ pkg.price_naira.toLocaleString() }}
                    </p>
                    <p class="mt-1 text-xs text-on-surface-variant">
                        {{ pkg.points_allocated.toLocaleString() }} points
                    </p>
                    <p class="mt-2 text-[10px] text-on-surface-variant">
                        One active subscription at a time · card only
                    </p>
                </div>

                <button
                    type="button"
                    :disabled="subscribingId !== null"
                    class="mt-4 flex items-center justify-center gap-2 rounded-lg bg-primary px-4 py-2.5 text-xs font-bold text-on-primary shadow-sm transition-all hover:bg-tertiary-container disabled:opacity-60"
                    @click="subscribe(pkg)"
                >
                    <span class="material-symbols-outlined text-sm">bolt</span>
                    {{ subscribingId === pkg.id ? 'Starting…' : 'Subscribe' }}
                </button>
            </div>
        </div>

        <p v-else class="mt-4 text-xs text-on-surface-variant">
            No packages are available right now.
        </p>

        <p class="mt-4 text-xs text-on-surface-variant">
            <span class="material-symbols-outlined align-middle text-[14px]"
                >info</span
            >
            Packages authorize Paystack to charge your saved card automatically
            on the package's cycle until you cancel, and only allow one active
            subscription at a time. Points bidden cannot be refunded and wallet
            credits are non-withdrawable.
        </p>

        <div
            v-if="subscriptions.length > 0"
            class="mt-6 flex flex-col gap-3 border-t border-outline-variant pt-4"
        >
            <h4
                class="text-xs font-semibold tracking-wider text-on-surface-variant uppercase"
            >
                Your package subscriptions
            </h4>

            <div
                v-for="subscription in subscriptions"
                :key="subscription.id"
                class="flex flex-col gap-2 rounded-lg border border-outline-variant p-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <div>
                    <p class="text-sm font-bold text-on-surface">
                        {{ subscription.package_name }} · ₦{{
                            subscription.amount_naira.toLocaleString()
                        }}
                        · {{ subscription.frequency_label }}
                    </p>
                    <p class="mt-1 text-xs text-on-surface-variant">
                        <span
                            v-if="
                                subscription.status === 'active' ||
                                subscription.status === 'attention'
                            "
                        >
                            <span v-if="subscription.next_charge_at">
                                Next charge:
                                {{ formatDate(subscription.next_charge_at) }}
                            </span>
                            <span v-else>Active</span>
                        </span>
                        <span v-else-if="subscription.status === 'pending'">
                            Confirming your first charge…
                        </span>
                        <span v-else>
                            Last charged:
                            {{ formatDate(subscription.last_charged_at) }}
                        </span>
                        <span
                            v-if="subscription.authorization_last4"
                            class="ml-1"
                        >
                            · {{ subscription.authorization_brand }} ····
                            {{ subscription.authorization_last4 }}
                        </span>
                    </p>
                </div>

                <div class="flex items-center gap-2">
                    <span
                        class="rounded-full border px-2.5 py-1 text-[9px] font-black tracking-widest uppercase"
                        :class="statusBadgeClass(subscription.status)"
                    >
                        {{ subscription.status_label }}
                    </span>

                    <button
                        v-if="subscription.is_cancellable"
                        type="button"
                        :disabled="cancellingId === subscription.id"
                        class="rounded-lg border border-error/30 px-3 py-1.5 text-[10px] font-bold text-error uppercase transition-colors hover:bg-error-container/30 disabled:opacity-60"
                        @click="cancelSubscription(subscription)"
                    >
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
