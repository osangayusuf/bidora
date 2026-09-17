<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import {
    Gavel,
    Users,
    Coins,
    CreditCard,
    Layers,
    Repeat,
    Star,
    Settings,
    LogOut,
    Menu,
    X,
    UserCheck,
    ListFilter,
    BarChart3,
    ShieldAlert,
    Trophy,
} from 'lucide-vue-next';
import { ref } from 'vue';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { logout as logoutRoute, home } from '@/routes';
import { index as activityLogIndex } from '@/routes/admin/activity-log';
import { index as agentsIndex } from '@/routes/admin/agents';
import { index as auctionsIndex } from '@/routes/admin/auctions';
import { index as bidsIndex } from '@/routes/admin/bids';
import { index as metricsIndex } from '@/routes/admin/metrics';
import { index as paystackTransactionsIndex } from '@/routes/admin/paystack-transactions';
import { index as pointSubscriptionsIndex } from '@/routes/admin/point-subscriptions';
import { index as pointTransactionsIndex } from '@/routes/admin/point-transactions';
import { index as reviewsIndex } from '@/routes/admin/reviews';
import { index as rewardsConfigIndex } from '@/routes/admin/rewards-config';
import { index as subscriptionPackagesIndex } from '@/routes/admin/subscription-packages';
import { index as usersIndex } from '@/routes/admin/users';
import { index as winnersIndex } from '@/routes/admin/winners';

type Breadcrumb = {
    title: string;
    href?: string;
};

defineProps<{
    breadcrumbs?: Breadcrumb[];
}>();

const page = usePage();
const user = ref(page.props.auth?.user);

const isMobileMenuOpen = ref(false);

const { isCurrentOrParentUrl } = useCurrentUrl();

const navItems = [
    { name: 'Metrics', href: metricsIndex.url(), icon: BarChart3 },
    { name: 'Activity Log', href: activityLogIndex.url(), icon: ShieldAlert },
    { name: 'Auctions', href: auctionsIndex.url(), icon: Gavel },
    { name: 'Winners', href: winnersIndex.url(), icon: Trophy },
    { name: 'Users', href: usersIndex.url(), icon: Users },
    { name: 'Agents', href: agentsIndex.url(), icon: UserCheck },
    { name: 'Bids Audit', href: bidsIndex.url(), icon: ListFilter },
    { name: 'Points Ledger', href: pointTransactionsIndex.url(), icon: Coins },
    {
        name: 'Paystack Logs',
        href: paystackTransactionsIndex.url(),
        icon: CreditCard,
    },
    {
        name: 'Subscriptions',
        href: pointSubscriptionsIndex.url(),
        icon: Repeat,
    },
    {
        name: 'Subscription Packages',
        href: subscriptionPackagesIndex.url(),
        icon: Layers,
    },
    { name: 'Reviews', href: reviewsIndex.url(), icon: Star },
    { name: 'Rewards Config', href: rewardsConfigIndex.url(), icon: Settings },
];

const logout = () => {
    router.post(logoutRoute.url());
};
</script>

<template>
    <div
        class="flex min-h-screen flex-col bg-background font-sans text-on-surface antialiased"
    >
        <!-- Top bar for Mobile -->
        <header
            class="sticky top-0 z-40 flex items-center justify-between border-b border-outline-variant bg-surface-container-low px-4 py-3 lg:hidden"
        >
            <div class="flex items-center gap-2">
                <span
                    class="text-sm font-black tracking-tight text-primary uppercase"
                    >BIDORA ADMIN</span
                >
            </div>
            <button
                @click="isMobileMenuOpen = !isMobileMenuOpen"
                class="rounded border border-outline-variant bg-surface-container-lowest p-1 text-on-surface-variant focus:outline-none"
            >
                <Menu v-if="!isMobileMenuOpen" class="h-5 w-5" />
                <X v-else class="h-5 w-5" />
            </button>
        </header>

        <div class="relative flex min-h-screen flex-1 lg:min-h-0">
            <!-- Sidebar Panel (Desktop & Mobile Drawer) -->
            <aside
                :class="[
                    'fixed inset-y-0 left-0 z-40 flex w-[280px] transform flex-col border-r border-outline-variant bg-surface-container-low transition-transform duration-300 lg:static lg:h-auto lg:translate-x-0',
                    isMobileMenuOpen ? 'translate-x-0' : '-translate-x-full',
                ]"
            >
                <!-- Brand logo Header -->
                <div
                    class="hidden flex-col border-b border-outline-variant p-6 lg:flex"
                >
                    <h1
                        class="text-xl font-black tracking-tight text-primary uppercase"
                    >
                        BIDORA ADMIN
                    </h1>
                    <p
                        class="mt-1 text-[9px] font-bold tracking-widest text-on-surface-variant uppercase"
                    >
                        Management Suite
                    </p>
                </div>

                <!-- Navigation List -->
                <nav class="flex-1 space-y-1 overflow-y-auto px-4 py-6">
                    <Link
                        v-for="item in navItems"
                        :key="item.name"
                        :href="item.href"
                        :class="[
                            'flex items-center gap-3 rounded-lg px-4 py-3 text-xs font-bold transition-all',
                            isCurrentOrParentUrl(item.href)
                                ? 'border border-secondary/20 bg-secondary-container text-on-secondary-container shadow-sm'
                                : 'text-on-surface-variant hover:bg-surface-variant/40',
                        ]"
                        @click="isMobileMenuOpen = false"
                    >
                        <component :is="item.icon" class="h-4 w-4" />
                        <span>{{ item.name }}</span>
                        <span
                            v-if="
                                item.name === 'Agents' &&
                                $page.props.auth?.pending_agents_count > 0
                            "
                            class="ml-auto rounded-full bg-error px-2 py-0.5 text-[10px] font-bold text-on-error"
                        >
                            {{ $page.props.auth.pending_agents_count }}
                        </span>
                    </Link>
                </nav>

                <!-- User Footer & Logout -->
                <div
                    class="mt-auto flex flex-col gap-2 border-t border-outline-variant bg-surface-container-low p-4"
                >
                    <div class="mb-2 flex items-center gap-3 px-2 py-1">
                        <div
                            class="flex h-10 w-10 items-center justify-center overflow-hidden rounded-full border border-outline-variant bg-surface-container-highest"
                        >
                            <span class="text-sm font-black text-primary">{{
                                user?.name ? user.name[0].toUpperCase() : 'A'
                            }}</span>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p
                                class="truncate text-xs font-bold text-on-surface"
                            >
                                {{ user?.name }}
                            </p>
                            <p
                                class="truncate text-[10px] text-on-surface-variant"
                            >
                                {{ user?.email }}
                            </p>
                        </div>
                    </div>

                    <button
                        @click="logout"
                        class="flex w-full items-center justify-center gap-2 rounded-lg border border-error/20 bg-transparent px-3 py-2.5 text-xs font-bold text-error transition-all hover:bg-error-container/20"
                    >
                        <LogOut class="h-4 w-4" />
                        <span>Exit Admin</span>
                    </button>
                </div>
            </aside>

            <!-- Main Scroll Area -->
            <main
                class="flex min-w-0 flex-1 flex-col overflow-y-auto bg-background"
            >
                <!-- Desktop Top Bar -->
                <header
                    class="sticky top-0 z-40 hidden h-16 items-center justify-between border-b border-outline-variant bg-surface px-8 lg:flex"
                >
                    <nav
                        class="flex items-center gap-2 text-[10px] font-bold tracking-wider text-on-surface-variant uppercase"
                    >
                        <Link
                            :href="home.url()"
                            class="transition-colors hover:text-primary"
                            >Home</Link
                        >
                        <span
                            class="text-[12px] font-normal text-on-surface-variant/30"
                            >/</span
                        >
                        <span class="transition-colors hover:text-primary"
                            >Admin</span
                        >
                        <template
                            v-for="crumb in breadcrumbs"
                            :key="crumb.title"
                        >
                            <span
                                class="text-[12px] font-normal text-on-surface-variant/30"
                                >/</span
                            >
                            <Link
                                v-if="crumb.href"
                                :href="crumb.href"
                                class="font-bold transition-colors hover:text-primary"
                                >{{ crumb.title }}</Link
                            >
                            <span v-else class="font-bold text-primary">{{
                                crumb.title
                            }}</span>
                        </template>
                    </nav>
                </header>

                <div class="flex-1 space-y-6 p-6 lg:p-8">
                    <!-- Mobile Breadcrumbs only -->
                    <div
                        class="mb-2 flex items-center gap-2 text-[10px] font-bold tracking-wider text-on-surface-variant uppercase lg:hidden"
                    >
                        <Link
                            :href="home.url()"
                            class="transition-colors hover:text-primary"
                            >Home</Link
                        >
                        <span
                            class="text-[12px] font-normal text-on-surface-variant/30"
                            >/</span
                        >
                        <span class="font-bold text-primary">Admin</span>
                        <template
                            v-for="crumb in breadcrumbs"
                            :key="crumb.title"
                        >
                            <span
                                class="text-[12px] font-normal text-on-surface-variant/30"
                                >/</span
                            >
                            <span class="font-bold text-primary">{{
                                crumb.title
                            }}</span>
                        </template>
                    </div>

                    <!-- Flash Notifications -->
                    <div
                        v-if="$page.props.flash?.success"
                        class="flex items-center justify-between rounded-lg border-l-4 border-secondary bg-secondary-container/40 p-4 text-xs font-bold text-on-secondary-container shadow-sm"
                    >
                        <span>{{ $page.props.flash.success }}</span>
                    </div>
                    <div
                        v-if="$page.props.errors?.error"
                        class="flex items-center justify-between rounded-lg border-l-4 border-error bg-error-container/30 p-4 text-xs font-bold text-on-error-container shadow-sm"
                    >
                        <span>{{ $page.props.errors.error }}</span>
                    </div>

                    <!-- Actual View Content slot -->
                    <slot />
                </div>
            </main>
        </div>
    </div>
</template>
