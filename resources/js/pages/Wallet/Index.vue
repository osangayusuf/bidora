<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, onMounted, watch } from 'vue';
import SettingsPanel from '@/components/settings/SettingsPanel.vue';
import SettingsShell from '@/components/settings/SettingsShell.vue';
import WalletBalanceCards from '@/components/wallet/WalletBalanceCards.vue';
import WalletClaimBonus from '@/components/wallet/WalletClaimBonus.vue';
import WalletPackagesSection from '@/components/wallet/WalletPackagesSection.vue';
import WalletRecurringSection from '@/components/wallet/WalletRecurringSection.vue';
import WalletTopUpForm from '@/components/wallet/WalletTopUpForm.vue';
import WalletTransactionsSection from '@/components/wallet/WalletTransactionsSection.vue';
import PublicLayout from '@/layouts/PublicLayout.vue';
import { appToast } from '@/lib/appToast';
import { wallet as walletRoute } from '@/routes/index';
import type {
    PaystackPlanListing,
    PointSubscriptionListing,
    SubscriptionPackageListing,
    WalletBalances,
    WalletConfig,
    WalletTransactionsPaginator,
} from '@/types/wallet';

defineOptions({ layout: PublicLayout });

const props = defineProps<{
    balances: WalletBalances;
    walletConfig: WalletConfig;
    transactions: WalletTransactionsPaginator;
    availablePlans: PaystackPlanListing[];
    subscriptions: PointSubscriptionListing[];
    packages: SubscriptionPackageListing[];
    paymentStatus?: string | null;
    paymentReference?: string | null;
}>();

const planSubscriptions = computed(() =>
    props.subscriptions.filter((s) => s.kind === 'plan'),
);
const packageSubscriptions = computed(() =>
    props.subscriptions.filter((s) => s.kind === 'package'),
);

function showPaymentToast(): void {
    if (props.paymentStatus === 'success') {
        appToast.show({
            type: 'success',
            message: 'Payment successful. Your points have been credited.',
        });
    } else if (props.paymentStatus === 'failed') {
        appToast.show({
            type: 'error',
            message:
                'Payment could not be verified. Please try again or contact support.',
        });
    }

    if (props.paymentStatus) {
        router.get(
            walletRoute.url(),
            {},
            { replace: true, preserveScroll: true },
        );
    }
}

onMounted(showPaymentToast);

watch(
    () => props.paymentStatus,
    () => showPaymentToast(),
);
</script>

<template>
    <Head title="Wallet" />

    <SettingsShell>
        <SettingsPanel
            title="Wallet"
            description="View your points balance, buy points, and review transaction history."
        >
            <div class="space-y-6">
                <WalletBalanceCards
                    :balances="balances"
                    :wallet-config="walletConfig"
                />
                <WalletTopUpForm
                    v-if="walletConfig.top_ups_enabled"
                    :wallet-config="walletConfig"
                />
                <WalletPackagesSection
                    v-if="walletConfig.packages_enabled"
                    :packages="packages"
                    :subscriptions="packageSubscriptions"
                />
                <WalletRecurringSection
                    v-if="walletConfig.recurring_enabled"
                    :available-plans="availablePlans"
                    :subscriptions="planSubscriptions"
                />
                <WalletClaimBonus :bonus-points="balances.bonus_points" />
                <WalletTransactionsSection :transactions="transactions" />
            </div>
        </SettingsPanel>
    </SettingsShell>
</template>
