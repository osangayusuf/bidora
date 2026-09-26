<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import WalletTopUpController from '@/actions/App/Http/Controllers/WalletTopUpController';
import { usePaystackInline } from '@/composables/usePaystackInline';
import { appToast } from '@/lib/appToast';
import type { PaystackInit, WalletConfig } from '@/types/wallet';

const props = defineProps<{
    walletConfig: WalletConfig;
}>();

const { openPayment } = usePaystackInline();

const amount = ref<string>(
    String(props.walletConfig.deposit_presets[0] ?? 1000),
);
const isProcessing = ref(false);

const amountNumber = computed(() => {
    const parsed = Number.parseFloat(amount.value);

    return Number.isFinite(parsed) ? parsed : 0;
});

const pointsPreview = computed(() =>
    Math.floor(amountNumber.value * props.walletConfig.points_per_naira),
);

const isValidAmount = computed(
    () =>
        amountNumber.value >= props.walletConfig.min_deposit_naira &&
        amountNumber.value <= props.walletConfig.max_deposit_naira,
);

function selectPreset(preset: number): void {
    amount.value = String(preset);
}

async function startTopUp(): Promise<void> {
    if (!isValidAmount.value || isProcessing.value) {
        return;
    }

    isProcessing.value = true;

    router.post(
        WalletTopUpController.store.url(),
        { amount: amountNumber.value },
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
            onError: () => {
                appToast.show({
                    type: 'error',
                    message: 'Unable to start payment. Please try again.',
                });
            },
            onFinish: () => {
                isProcessing.value = false;
            },
        },
    );
}
</script>

<template>
    <div
        class="rounded-xl border border-outline-variant bg-surface-container-lowest p-5 md:p-6"
    >
        <h3 class="text-lg font-semibold text-on-surface">Top up</h3>
        <p class="mt-1 text-sm text-on-surface-variant">
            Enter any amount and get
            {{ walletConfig.points_per_naira.toLocaleString() }} points per ₦1.
            Min ₦{{ walletConfig.min_deposit_naira.toLocaleString() }}, max ₦{{
                walletConfig.max_deposit_naira.toLocaleString()
            }}.
        </p>

        <div class="mt-4 flex flex-wrap gap-2">
            <button
                v-for="preset in walletConfig.deposit_presets"
                :key="preset"
                type="button"
                class="rounded-lg border-2 px-4 py-2 text-xs font-bold transition-colors"
                :class="
                    Number(amount) === preset
                        ? 'border-secondary bg-secondary-container text-on-secondary-container'
                        : 'border-outline-variant text-on-surface-variant hover:bg-surface-container'
                "
                @click="selectPreset(preset)"
            >
                ₦{{ preset.toLocaleString() }}
            </button>
        </div>

        <div class="mt-4 flex flex-col gap-2">
            <label
                class="text-xs font-semibold text-on-surface"
                for="top-up-amount"
                >Amount (₦)</label
            >
            <input
                id="top-up-amount"
                v-model="amount"
                type="number"
                :min="walletConfig.min_deposit_naira"
                :max="walletConfig.max_deposit_naira"
                step="1"
                class="w-full max-w-xs rounded-lg border border-outline-variant bg-surface px-4 py-3 text-sm text-on-surface focus:border-secondary focus:ring-2 focus:ring-secondary focus:outline-none"
            />
            <p class="text-sm text-on-surface-variant" v-if="isValidAmount">
                You will receive
                <span class="font-bold text-on-surface"
                    >{{ pointsPreview.toLocaleString() }} pts</span
                >
            </p>
            <p class="text-xs text-error italic" v-if="!isValidAmount">
                Please enter an amount between ₦{{
                    walletConfig.min_deposit_naira.toLocaleString()
                }}
                and ₦{{ walletConfig.max_deposit_naira.toLocaleString() }}.
            </p>
        </div>

        <button
            type="button"
            :disabled="!isValidAmount || isProcessing"
            data-test="pay-with-paystack-button"
            class="mt-6 flex items-center gap-2 rounded-lg bg-primary px-8 py-3 text-xs font-bold text-on-primary shadow-md transition-all hover:bg-tertiary-container hover:shadow-lg disabled:opacity-60"
            @click="startTopUp"
        >
            <span class="material-symbols-outlined text-sm">payments</span>
            {{ isProcessing ? 'Initializing…' : 'Pay with Paystack' }}
        </button>

        <p class="mt-4 text-xs text-on-surface-variant">
            <span class="material-symbols-outlined align-middle text-[14px]"
                >info</span
            >
            Top up as often as you like, paid by card or bank transfer. Points
            bidden cannot be refunded and wallet credits are non-withdrawable.
        </p>
    </div>
</template>
