<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import WalletSubscriptionController from '@/actions/App/Http/Controllers/WalletSubscriptionController';
import { usePaystackInline } from '@/composables/usePaystackInline';
import { appToast } from '@/lib/appToast';
import type {
    PaystackInit,
    PaystackPlanListing,
    PointSubscriptionListing,
    SubscriptionFrequency,
} from '@/types/wallet';

const props = defineProps<{
    availablePlans: PaystackPlanListing[];
    subscriptions: PointSubscriptionListing[];
}>();

const { openPayment } = usePaystackInline();

const frequencies = computed<SubscriptionFrequency[]>(() => {
    const seen = new Set<SubscriptionFrequency>();

    for (const plan of props.availablePlans) {
        seen.add(plan.frequency);
    }

    return Array.from(seen);
});

const selectedFrequency = ref<SubscriptionFrequency | null>(
    frequencies.value[0] ?? null,
);

const plansForFrequency = computed(() =>
    props.availablePlans.filter(
        (plan) => plan.frequency === selectedFrequency.value,
    ),
);

const selectedPlanId = ref<number | null>(
    plansForFrequency.value[0]?.id ?? null,
);

function selectFrequency(frequency: SubscriptionFrequency): void {
    selectedFrequency.value = frequency;
    selectedPlanId.value =
        props.availablePlans.find((plan) => plan.frequency === frequency)
            ?.id ?? null;
}

const isSubscribing = ref(false);

function subscribe(): void {
    if (selectedPlanId.value === null || isSubscribing.value) {
        return;
    }

    isSubscribing.value = true;

    router.post(
        WalletSubscriptionController.store.url(),
        { paystack_plan_id: selectedPlanId.value },
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
                        (errors.plan as string | undefined) ??
                        'Unable to start your subscription. Please try again.',
                });
            },
            onFinish: () => {
                isSubscribing.value = false;
            },
        },
    );
}

const cancellingId = ref<number | null>(null);

function cancelSubscription(subscription: PointSubscriptionListing): void {
    if (
        cancellingId.value !== null ||
        !confirm(
            `Cancel your ₦${subscription.amount_naira.toLocaleString()} ${subscription.frequency_label.toLowerCase()} auto top-up? You will not be charged again.`,
        )
    ) {
        return;
    }

    cancellingId.value = subscription.id;

    router.delete(
        WalletSubscriptionController.destroy.url(subscription.id),
        {
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
        },
    );
}

function manageCard(subscription: PointSubscriptionListing): void {
    window.location.href = WalletSubscriptionController.manageCard.url(
        subscription.id,
    );
}

function statusBadgeClass(status: PointSubscriptionListing['status']): string {
    switch (status) {
        case 'active':
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
        <h3 class="text-lg font-semibold text-on-surface">Auto top-up</h3>
        <p class="mt-1 text-sm text-on-surface-variant">
            Set up a recurring charge with Paystack and never run out of
            points.
        </p>

        <div v-if="availablePlans.length > 0" class="mt-4">
            <div class="flex gap-2">
                <button
                    v-for="frequency in frequencies"
                    :key="frequency"
                    type="button"
                    class="rounded-lg border-2 px-4 py-2 text-xs font-bold uppercase transition-colors"
                    :class="
                        selectedFrequency === frequency
                            ? 'border-primary bg-primary-container text-on-primary-container'
                            : 'border-outline-variant text-on-surface-variant hover:bg-surface-container'
                    "
                    @click="selectFrequency(frequency)"
                >
                    {{
                        availablePlans.find((p) => p.frequency === frequency)
                            ?.frequency_label ?? frequency
                    }}
                </button>
            </div>

            <div class="mt-4 flex flex-wrap gap-2">
                <button
                    v-for="plan in plansForFrequency"
                    :key="plan.id"
                    type="button"
                    class="rounded-lg border-2 px-4 py-2 text-xs font-bold transition-colors"
                    :class="
                        selectedPlanId === plan.id
                            ? 'border-secondary bg-secondary-container text-on-secondary-container'
                            : 'border-outline-variant text-on-surface-variant hover:bg-surface-container'
                    "
                    @click="selectedPlanId = plan.id"
                >
                    ₦{{ plan.amount_naira.toLocaleString() }}
                </button>
            </div>

            <button
                type="button"
                :disabled="selectedPlanId === null || isSubscribing"
                data-test="subscribe-button"
                class="mt-6 flex items-center gap-2 rounded-lg bg-primary px-8 py-3 text-xs font-bold text-on-primary shadow-md transition-all hover:bg-tertiary-container hover:shadow-lg disabled:opacity-60"
                @click="subscribe"
            >
                <span class="material-symbols-outlined text-sm">autorenew</span>
                {{ isSubscribing ? 'Starting…' : 'Start auto top-up' }}
            </button>

            <p class="mt-4 text-xs text-on-surface-variant">
                <span
                    class="material-symbols-outlined align-middle text-[14px]"
                    >info</span
                >
                By starting an auto top-up you authorize Paystack to charge
                your saved card automatically on this schedule until you
                cancel. Points bidden cannot be refunded and wallet credits
                are non-withdrawable.
            </p>
        </div>

        <div
            v-if="subscriptions.length > 0"
            class="mt-6 flex flex-col gap-3 border-t border-outline-variant pt-4"
        >
            <h4
                class="text-xs font-semibold tracking-wider text-on-surface-variant uppercase"
            >
                Your subscriptions
            </h4>

            <div
                v-for="subscription in subscriptions"
                :key="subscription.id"
                class="flex flex-col gap-2 rounded-lg border border-outline-variant p-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <div>
                    <p class="text-sm font-bold text-on-surface">
                        ₦{{ subscription.amount_naira.toLocaleString() }} ·
                        {{ subscription.frequency_label }}
                    </p>
                    <p class="mt-1 text-xs text-on-surface-variant">
                        <span
                            v-if="
                                subscription.status === 'active' ||
                                subscription.status === 'attention'
                            "
                        >
                            Next charge:
                            {{ formatDate(subscription.next_payment_date) }}
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
                        v-if="subscription.status !== 'pending' && subscription.status !== 'cancelled'"
                        type="button"
                        class="rounded-lg border border-outline-variant px-3 py-1.5 text-[10px] font-bold text-on-surface-variant uppercase transition-colors hover:bg-surface-container"
                        @click="manageCard(subscription)"
                    >
                        Update card
                    </button>

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
