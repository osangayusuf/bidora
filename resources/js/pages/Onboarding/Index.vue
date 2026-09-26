<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import OnboardingController from '@/actions/App/Http/Controllers/OnboardingController';
import WalletBalanceCards from '@/components/wallet/WalletBalanceCards.vue';
import WalletTopUpForm from '@/components/wallet/WalletTopUpForm.vue';
import PublicLayout from '@/layouts/PublicLayout.vue';
import { profile } from '@/routes';
import type { WalletBalances, WalletConfig } from '@/types/wallet';

defineOptions({ layout: PublicLayout });

defineProps<{
    user: {
        name: string;
        email: string;
        phone: string | null;
        points_balance: number;
    };
    balances: WalletBalances;
    walletConfig: WalletConfig;
    pointsPerNaira: number;
    minBidIncrement: number;
}>();
</script>

<template>
    <Head title="Welcome" />

    <div class="min-h-screen bg-background px-4 py-8 sm:px-6 lg:px-8 lg:py-12">
        <div class="mx-auto max-w-2xl">
            <div class="mb-8 text-center">
                <div
                    class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full border-4 border-primary bg-primary/10"
                >
                    <span
                        class="material-symbols-outlined text-3xl! text-primary"
                        >mark_email_read</span
                    >
                </div>
                <h1
                    class="font-headline text-3xl font-black tracking-tight text-on-surface"
                >
                    Email verified
                </h1>
                <p class="mt-2 text-on-surface-variant">
                    Fund your wallet with points to place bids. Minimum bid
                    increment:
                    <span class="font-bold text-on-surface"
                        >{{ minBidIncrement }} pts</span
                    >
                </p>
            </div>

            <!-- Form Card for Social Signups missing a Phone Number -->
            <Form
                v-if="user.phone === null"
                :action="OnboardingController.complete.url()"
                method="post"
                v-slot="{ errors, processing }"
                class="mb-6 rounded-2xl border-2 border-primary bg-surface p-6 shadow-md"
            >
                <h2 class="font-headline text-lg font-bold text-on-surface">
                    Complete your profile
                </h2>
                <p class="mt-1 mb-4 text-xs text-on-surface-variant">
                    Before you can start bidding on Bidora, please provide a
                    valid phone number.
                </p>

                <div class="space-y-4">
                    <div
                        class="flex justify-between gap-4 border-b border-outline-variant/30 pb-3 text-sm"
                    >
                        <dt class="font-semibold text-on-surface-variant">
                            Name
                        </dt>
                        <dd class="font-bold text-on-surface">
                            {{ user.name }}
                        </dd>
                    </div>
                    <div
                        class="flex justify-between gap-4 border-b border-outline-variant/30 pb-3 text-sm"
                    >
                        <dt class="font-semibold text-on-surface-variant">
                            Email
                        </dt>
                        <dd class="font-bold text-on-surface">
                            {{ user.email }}
                        </dd>
                    </div>

                    <!-- Phone Number Input -->
                    <div class="space-y-1.5 pt-2">
                        <label
                            for="phone"
                            class="block text-xs font-bold tracking-widest text-on-surface-variant uppercase"
                        >
                            Phone Number (MSISDN)
                        </label>
                        <input
                            id="phone"
                            name="phone"
                            type="tel"
                            inputmode="tel"
                            autocomplete="tel"
                            required
                            class="block w-full rounded-lg border-2 border-outline-variant/50 bg-background px-4 py-3 text-sm text-on-surface transition-all focus:border-primary focus:ring-2 focus:ring-primary/20 focus:outline-none"
                            placeholder="e.g. 08031234567"
                        />
                        <p
                            v-if="errors && errors.phone"
                            class="mt-1 text-xs font-medium text-error"
                        >
                            {{ errors.phone }}
                        </p>
                    </div>

                    <!-- Submit action inside the card -->
                    <div class="pt-3">
                        <button
                            type="submit"
                            :disabled="processing"
                            class="w-full rounded-xl border-4 border-primary bg-primary px-8 py-3.5 text-center font-headline text-sm font-black tracking-wide text-on-primary uppercase shadow-md transition hover:opacity-95 active:scale-[0.98] disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            {{
                                processing
                                    ? 'Saving details…'
                                    : 'Save Details & Continue'
                            }}
                        </button>
                    </div>
                </div>
            </Form>

            <!-- Standard Details Card (if Phone is already set) -->
            <div
                v-else
                class="mb-6 rounded-2xl border-2 border-outline-variant/50 bg-surface p-6 shadow-sm"
            >
                <h2 class="font-headline text-lg font-bold text-on-surface">
                    Your details
                </h2>
                <dl class="mt-4 space-y-3 text-sm">
                    <div
                        class="flex justify-between gap-4 border-b border-outline-variant/30 pb-3"
                    >
                        <dt class="font-semibold text-on-surface-variant">
                            Name
                        </dt>
                        <dd class="font-bold text-on-surface">
                            {{ user.name }}
                        </dd>
                    </div>
                    <div
                        class="flex justify-between gap-4 border-b border-outline-variant/30 pb-3"
                    >
                        <dt class="font-semibold text-on-surface-variant">
                            Email
                        </dt>
                        <dd class="font-bold text-on-surface">
                            {{ user.email }}
                        </dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="font-semibold text-on-surface-variant">
                            Phone
                        </dt>
                        <dd class="font-bold text-on-surface">
                            {{ user.phone }}
                        </dd>
                    </div>
                </dl>
                <Link
                    :href="profile.url()"
                    class="mt-4 inline-flex text-sm font-bold text-primary hover:underline"
                >
                    Edit profile
                </Link>
            </div>

            <div
                class="mb-8 rounded-2xl border-4 border-primary bg-surface p-6 shadow-lg sm:p-8"
            >
                <h2 class="font-headline text-xl font-black text-on-surface">
                    Fund your wallet
                </h2>
                <p class="mt-1 text-sm text-on-surface-variant">
                    Buy points with Paystack. ₦1 =
                    <span class="font-bold text-on-surface"
                        >{{ pointsPerNaira.toLocaleString() }} pts</span
                    >
                </p>

                <div class="mt-6 space-y-6">
                    <WalletBalanceCards
                        :balances="balances"
                        :wallet-config="walletConfig"
                    />
                    <WalletTopUpForm :wallet-config="walletConfig" />
                </div>
            </div>

            <div
                v-if="user.phone !== null"
                class="flex flex-col gap-3 sm:flex-row sm:justify-center"
            >
                <Form
                    :action="OnboardingController.complete.url()"
                    method="post"
                    class="w-full sm:w-auto"
                >
                    <button
                        type="submit"
                        class="w-full rounded-xl border-4 border-primary bg-primary px-8 py-4 text-center font-headline text-sm font-black tracking-wide text-on-primary uppercase shadow-md transition hover:opacity-95 active:scale-[0.98]"
                    >
                        Continue to bidding
                    </button>
                </Form>
                <Form
                    :action="OnboardingController.complete.url()"
                    method="post"
                    class="w-full sm:w-auto"
                >
                    <button
                        type="submit"
                        class="w-full rounded-xl border-2 border-outline-variant bg-surface px-8 py-4 text-center text-sm font-bold text-on-surface-variant transition hover:border-on-surface-variant hover:text-on-surface"
                    >
                        Skip for now
                    </button>
                </Form>
            </div>
        </div>
    </div>
</template>
