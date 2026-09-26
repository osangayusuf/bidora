<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import BidCard from '@/components/cards/BidCard.vue';
import FeaturedAuctionsCarousel from '@/components/home/FeaturedAuctionsCarousel.vue';
import HomeBidItemsSection from '@/components/home/HomeBidItemsSection.vue';
import HomeEventPopup from '@/components/home/HomeEventPopup.vue';
import HomeHeroSection from '@/components/home/HomeHeroSection.vue';
import HomeHowToPlaySection from '@/components/home/HomeHowToPlaySection.vue';
import HomeLaunchNoticeModal from '@/components/home/HomeLaunchNoticeModal.vue';
import HomeLeaderboardWidget from '@/components/home/HomeLeaderboardWidget.vue';
import HomePromoSection from '@/components/home/HomePromoSection.vue';
import HomeTestimonialsSection from '@/components/home/HomeTestimonialsSection.vue';
import HomeWinnerPopup from '@/components/home/HomeWinnerPopup.vue';
import HomeWinnersSection from '@/components/home/HomeWinnersSection.vue';
import YoutubeTestimonialsCta from '@/components/shared/YoutubeTestimonialsCta.vue';
import PublicLayout from '@/layouts/PublicLayout.vue';
import { formatPrice } from '@/lib/utils';
import {
    trending,
    openBids as openBidsRoute,
    tasks,
    howToPlay,
} from '@/routes/index';
import type { Bid } from '@/types/auction';

defineOptions({ layout: PublicLayout });

export type { Bid, Bidder } from '@/types/auction';

export type Winner = {
    id: number;
    msisdn: string;
    total_points: number;
    bidid: number;
    created_at: string;
    bid: {
        id: number;
        name: string;
        image: string | null;
        url: string;
        price: string;
    } | null;
};

export type Review = {
    id: number;
    user_id: string;
    rating: number;
    comment: string;
    social_platform?: string | null;
    social_handle?: string | null;
    bidid: number | null;
    created_at: string;
    bid: { id: number; name: string; image: string | null; url: string } | null;
};

type TopBidder = {
    rank: number;
    name: string;
    msisdn: string;
    total_bid_pts: number;
};

const props = defineProps<{
    search?: string | null;
    heroBid?: Bid | null;
    bids?: Bid[];
    trendingBids?: Bid[];
    recentlyAddedBids?: Bid[];
    openBids?: Bid[];
    luxuryBids?: Bid[];
    categoryBids?: Record<string, Bid[]>;
    categories?: string[];
    winners?: Winner[];
    userPoints?: number | null;
    reviews?: Review[];
    winnerPopup?: Winner | null;
    eventPopupBid?: Bid | null;
    featuredBids?: Bid[];
    homeTopBidders?: TopBidder[] | null;
}>();

const bidItemsSectionRef = ref<InstanceType<typeof HomeBidItemsSection> | null>(
    null,
);

function openBidModal(bid: Bid): void {
    bidItemsSectionRef.value?.openBidModal(bid);
}

const hasActiveSearch = computed(() => Boolean(props.search?.trim()));

const hasAnyBids = computed(() => {
    if ((props.bids?.length ?? 0) > 0) {
        return true;
    }

    if ((props.trendingBids?.length ?? 0) > 0) {
        return true;
    }

    if ((props.recentlyAddedBids?.length ?? 0) > 0) {
        return true;
    }

    if ((props.openBids?.length ?? 0) > 0) {
        return true;
    }

    if ((props.luxuryBids?.length ?? 0) > 0) {
        return true;
    }

    if (props.categoryBids) {
        for (const bids of Object.values(props.categoryBids)) {
            if (bids.length > 0) {
                return true;
            }
        }
    }

    return false;
});

const getCategoryIcon = (category: string): string => {
    const map: Record<string, string> = {
        Appliances: 'kitchen',
        Computing: 'computer',
        Electronics: 'devices',
        Fashion: 'checkroom',
        'Gadgets & Accessories': 'headphones',
        Gaming: 'sports_esports',
        'Health & Beauty': 'spa',
        'Home & Office': 'home_work',
        'Musical Instrument': 'piano',
        Supermarket: 'local_grocery_store',
        Bags: 'shopping_bag',
        Shoes: 'footprint',
        Watches: 'watch',
        Jewelry: 'diamond',
        Accessories: 'redeem',
        Beauty: 'spa',
        Sports: 'sports_soccer',
        Gadgets: 'smartphone',
    };

    return map[category] || 'category';
};
</script>

<template>
    <Head title="Home" />

    <HomeLaunchNoticeModal />
    <HomeWinnerPopup v-if="props.winnerPopup" :winner="props.winnerPopup" />
    <HomeEventPopup
        v-if="props.eventPopupBid"
        :bid="props.eventPopupBid"
        @open-bid-modal="openBidModal"
    />

    <HomeBidItemsSection
        ref="bidItemsSectionRef"
        :bids="props.bids ?? []"
        :user-points="props.userPoints ?? null"
        class="hidden"
    />

    <div class="overflow-x-hidden bg-sage-bg text-left font-sans text-ink">
        <HomeHeroSection :get-category-icon="getCategoryIcon" />

        <!-- FEATURED AUCTIONS CAROUSEL -->
        <FeaturedAuctionsCarousel
            v-if="props.featuredBids && props.featuredBids.length > 0"
            :bids="props.featuredBids"
            :user-points="props.userPoints ?? null"
        />

        <!-- HYPE MARQUEE BANNER -->
        <div
            class="my-2 overflow-hidden border-y-2 border-lemon bg-linear-to-r from-forest to-navy py-2"
        >
            <div class="flex animate-marquee items-center whitespace-nowrap">
                <span
                    class="inline-flex items-center gap-2 px-8 text-xs font-black tracking-widest text-lemon uppercase"
                >
                    🔥 50 people will win hot items this week!
                </span>
                <span
                    class="inline-flex items-center gap-2 px-8 text-xs font-black tracking-widest text-white uppercase"
                >
                    ⚡ Hot items are trending on the site - bid now!
                </span>
                <span
                    class="inline-flex items-center gap-2 px-8 text-xs font-black tracking-widest text-lemon uppercase"
                >
                    📱 Follow this month's jackpot winner on social media!
                </span>
                <span
                    class="inline-flex items-center gap-2 px-8 text-xs font-black tracking-widest text-white uppercase"
                >
                    👀 Check out the most visited item today!
                </span>
                <!-- Duplicate for seamless loop -->
                <span
                    class="inline-flex items-center gap-2 px-8 text-xs font-black tracking-widest text-lemon uppercase"
                >
                    🔥 50 people will win hot items this week!
                </span>
                <span
                    class="inline-flex items-center gap-2 px-8 text-xs font-black tracking-widest text-white uppercase"
                >
                    ⚡ Hot items are trending on the site - bid now!
                </span>
                <span
                    class="inline-flex items-center gap-2 px-8 text-xs font-black tracking-widest text-lemon uppercase"
                >
                    📱 Follow this month's jackpot winner on social media!
                </span>
                <span
                    class="inline-flex items-center gap-2 px-8 text-xs font-black tracking-widest text-white uppercase"
                >
                    👀 Check out the most visited item today!
                </span>
            </div>
        </div>

        <!-- MAIN CONTENT + LEADERBOARD SIDEBAR -->
        <div class="mx-auto mt-4 max-w-[1300px] px-4">
            <div
                class="flex flex-col gap-5 lg:flex-row lg:items-start lg:gap-6"
            >
                <!-- ── LEFT COLUMN: Bids ─────────────────────────────── -->
                <div class="min-w-0 flex-1">
                    <!-- LIVE OPPORTUNITIES -->
                    <div
                        v-if="props.openBids && props.openBids.length > 0"
                        class="mb-5"
                    >
                        <div class="mb-3.5 flex items-center justify-between">
                            <div
                                class="flex items-center font-condensed text-2xl font-extrabold text-ink"
                            >
                                <span
                                    class="relative mr-2.5 flex h-3 w-3 align-middle"
                                >
                                    <span
                                        class="absolute inline-flex h-full w-full animate-ping rounded-full bg-red-400 opacity-75"
                                    ></span>
                                    <span
                                        class="relative inline-flex h-3 w-3 rounded-full bg-red-500"
                                    ></span>
                                </span>
                                <span
                                    class="pi pi-bolt mr-1 text-lg text-lemon"
                                ></span>
                                Live Opportunities
                            </div>
                            <Link
                                :href="openBidsRoute.url()"
                                class="text-sm font-bold text-forest hover:underline"
                                >View All →</Link
                            >
                        </div>
                        <div
                            :class="
                                props.openBids.length > 1
                                    ? 'grid grid-cols-2 gap-2.5'
                                    : 'grid-cols-1'
                            "
                        >
                            <BidCard
                                v-for="bid in props.openBids.slice(0, 4)"
                                :key="bid.id"
                                :bid="bid"
                                :user-points="props.userPoints ?? null"
                            />
                        </div>
                    </div>

                    <!-- NO RESULTS -->
                    <div v-if="hasActiveSearch && !hasAnyBids" class="mb-5">
                        <div
                            class="rounded-xl border-2 border-sage-border-dark bg-white px-6 py-10 text-center shadow-sm"
                        >
                            <span
                                class="material-symbols-outlined mb-3 text-5xl text-muted-green"
                                >search_off</span
                            >
                            <p
                                class="font-condensed text-xl font-extrabold text-ink"
                            >
                                No auctions found
                            </p>
                            <p class="mt-2 text-sm text-muted-green">
                                Nothing matched "<span
                                    class="font-bold text-ink"
                                    >{{ props.search }}</span
                                >". Try a different keyword or browse all
                                categories below.
                            </p>
                        </div>
                    </div>

                    <!-- PRODUCT STRIP -->
                    <div v-if="props.bids && props.bids.length > 0">
                        <div
                            class="mt-8 mb-3.5 flex items-center font-condensed text-2xl font-extrabold text-ink"
                        >
                            <span
                                class="mr-2 inline-block h-[22px] w-1 rounded-sm bg-forest align-middle"
                            ></span>
                            <span
                                class="pi pi-fire mr-1 text-lg text-amber"
                            ></span>
                            For You
                        </div>
                        <div class="grid grid-cols-2 gap-2.5">
                            <BidCard
                                v-for="bid in props.bids.slice(0, 4)"
                                :key="bid.id"
                                :bid="bid"
                                :user-points="props.userPoints ?? null"
                            />
                        </div>
                    </div>
                </div>

                <!-- ── RIGHT COLUMN: Leaderboard Widget ──────────────── -->
                <div class="w-full shrink-0 lg:w-md">
                    <HomeLeaderboardWidget
                        :top-bidders="props.homeTopBidders"
                    />
                </div>
            </div>
        </div>

        <!-- LIVE TICKER -->
        <div
            class="my-3 overflow-hidden border-y-2 border-lemon bg-navy py-2"
            v-if="props.bids && props.bids.length > 0"
        >
            <div class="flex animate-marquee items-center whitespace-nowrap">
                <span
                    class="inline-flex items-center gap-2 px-7 text-sm font-bold text-lemon"
                    v-for="bid in props.bids"
                    :key="bid.id"
                >
                    <span
                        class="inline-block h-2 w-2 rounded-full bg-lemon"
                    ></span>
                    {{ bid.name }}
                    <span class="font-extrabold whitespace-nowrap text-white">{{
                        formatPrice(bid.price)
                    }}</span>
                    <span
                        class="ml-1 rounded-sm bg-forest px-2 py-0.5 text-xs font-extrabold text-lemon"
                        v-if="bid.status === 1"
                        >LIVE</span
                    >
                </span>
            </div>
        </div>

        <!-- HOW TO PLAY VIDEO & STEPS SECTION -->
        <HomeHowToPlaySection />

        <!-- CATEGORIES -->
        <div
            class="mx-auto mb-5 max-w-[1300px] px-4"
            v-if="props.categories && props.categories.length > 0"
        >
            <div class="mb-3.5 flex items-center justify-between">
                <div
                    class="flex items-center font-condensed text-2xl font-extrabold text-ink"
                >
                    <span
                        class="mr-2 inline-block h-[22px] w-1 rounded-sm bg-forest align-middle"
                    ></span>
                    Browse Categories
                </div>
                <Link
                    :href="trending.url()"
                    class="text-sm font-bold text-forest hover:underline"
                    >View All →</Link
                >
            </div>
            <div class="grid grid-cols-3 gap-2.5 sm:grid-cols-4 lg:grid-cols-8">
                <Link
                    :href="
                        trending.url() + '?category=' + encodeURIComponent(cat)
                    "
                    class="group cursor-pointer rounded-lg border-2 border-transparent bg-white p-3.5 px-2 text-center shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:border-lemon hover:bg-navy hover:text-lemon"
                    v-for="cat in props.categories"
                    :key="cat"
                    style="text-decoration: none"
                >
                    <div class="mb-1.5 text-3xl">
                        <span class="material-symbols-outlined">{{
                            getCategoryIcon(cat)
                        }}</span>
                    </div>
                    <div
                        class="text-xs font-extrabold text-ink group-hover:text-lemon"
                    >
                        {{ cat }}
                    </div>
                </Link>
            </div>
        </div>

        <!-- TRENDING BIDS -->
        <div
            class="mx-auto mb-5 max-w-[1300px] px-4"
            v-if="props.trendingBids && props.trendingBids.length > 0"
        >
            <div class="mb-3.5 flex items-center justify-between">
                <div
                    class="flex items-center font-condensed text-2xl font-extrabold text-ink"
                >
                    <span
                        class="mr-2 inline-block h-[22px] w-1 rounded-sm bg-forest align-middle"
                    ></span>
                    <span class="pi pi-fire mr-1 text-lg text-amber"></span>
                    Trending Bids
                </div>
                <Link
                    :href="trending.url()"
                    class="text-sm font-bold text-forest hover:underline"
                    >View More →</Link
                >
            </div>
            <div class="grid grid-cols-2 gap-2.5 md:grid-cols-4">
                <BidCard
                    v-for="bid in props.trendingBids.slice(0, 4)"
                    :key="bid.id"
                    :bid="bid"
                    :user-points="props.userPoints ?? null"
                />
            </div>
            <div class="mt-4 flex justify-center">
                <Link
                    :href="trending.url()"
                    class="inline-flex items-center gap-2 rounded-xl border-2 border-lemon bg-navy px-6 py-3 text-sm font-extrabold text-lemon transition-colors hover:bg-forest"
                >
                    <span class="pi pi-fire text-base"></span>
                    View More Trending Items
                    <span class="pi pi-arrow-right text-xs"></span>
                </Link>
            </div>
        </div>

        <!-- RECENTLY ADDED -->
        <div
            class="mx-auto mb-5 max-w-[1300px] px-4"
            v-if="props.recentlyAddedBids && props.recentlyAddedBids.length > 0"
        >
            <div class="mb-3.5 flex items-center justify-between">
                <div
                    class="flex items-center font-condensed text-2xl font-extrabold text-ink"
                >
                    <span
                        class="mr-2 inline-block h-[22px] w-1 rounded-sm bg-forest align-middle"
                    ></span>
                    <span class="pi pi-sparkles mr-1 text-lg text-amber"></span>
                    Recently Added
                </div>
                <Link
                    :href="trending.url() + '?sort=recent'"
                    class="text-sm font-bold text-forest hover:underline"
                    >View More →</Link
                >
            </div>
            <div class="grid grid-cols-2 gap-2.5 md:grid-cols-4">
                <BidCard
                    v-for="bid in props.recentlyAddedBids.slice(0, 4)"
                    :key="bid.id"
                    :bid="bid"
                    :user-points="props.userPoints ?? null"
                />
            </div>
        </div>

        <!-- FEATURE BANNERS -->
        <div class="mx-auto mb-5 max-w-[1300px] px-4">
            <div class="grid grid-cols-1 gap-3.5 lg:grid-cols-2">
                <div
                    class="relative flex items-center justify-between overflow-hidden rounded-xl bg-linear-to-br from-navy to-forest px-7 py-6 text-white"
                >
                    <div>
                        <div
                            class="mb-2 inline-block rounded-xl bg-lemon/25 px-2.5 py-1 text-[10px] font-extrabold tracking-wider text-lemon uppercase"
                        >
                            <span class="pi pi-bullseye mr-1"></span> Task
                            Center
                        </div>
                        <div
                            class="mb-1.5 font-condensed text-3xl leading-tight font-black text-white"
                        >
                            Win More<br />Points Free!
                        </div>
                        <div
                            class="mb-3.5 text-xs leading-snug text-[#cde] opacity-85"
                        >
                            Complete simple tasks to earn bidding points and
                            increase your chances of winning luxury items.
                        </div>
                        <Link
                            :href="tasks.url()"
                            class="rounded-md bg-lemon px-4 py-2 text-sm font-extrabold text-navy transition-colors hover:bg-amber"
                        >
                            Visit Task Center →</Link
                        >
                    </div>
                    <div
                        class="text-[90px] leading-none font-black opacity-[0.18]"
                    >
                        <span class="pi pi-trophy"></span>
                    </div>
                </div>
                <div
                    class="relative flex items-center justify-between overflow-hidden rounded-xl bg-linear-to-br from-ink to-forest px-7 py-6 text-white"
                >
                    <div>
                        <div
                            class="mb-2 inline-block rounded-xl bg-lemon/25 px-2.5 py-1 text-[10px] font-extrabold tracking-wider text-lemon uppercase"
                        >
                            <span class="pi pi-lock mr-1"></span> Verified
                            Integrity
                        </div>
                        <div
                            class="mb-1.5 font-condensed text-3xl leading-tight font-black text-white"
                        >
                            100% Fair<br />&amp; Secure!
                        </div>
                        <div
                            class="mb-3.5 text-xs leading-snug text-[#cde] opacity-85"
                        >
                            Every bid is recorded on our secure database. Fully
                            transparent, fully trustworthy auction process.
                        </div>
                        <Link
                            :href="howToPlay.url()"
                            class="rounded-md bg-lemon px-4 py-2 text-sm font-extrabold text-navy transition-colors hover:bg-amber"
                        >
                            Learn More →
                        </Link>
                    </div>
                    <div
                        class="text-[90px] leading-none font-black opacity-[0.18]"
                    >
                        <span class="pi pi-shield"></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- PREMIUM & LUXURY -->
        <div
            class="mx-auto mb-5 max-w-[1300px] px-4"
            v-if="props.luxuryBids && props.luxuryBids.length > 0"
        >
            <div class="mb-3.5 flex items-center justify-between">
                <div
                    class="flex items-center font-condensed text-2xl font-extrabold text-ink"
                >
                    <span
                        class="mr-2 inline-block h-[22px] w-1 rounded-sm bg-forest align-middle"
                    ></span>
                    <span class="pi pi-star mr-1 text-lg text-amber"></span>
                    Premium &amp; Luxury
                </div>
                <Link
                    :href="trending.url() + '?sort=price'"
                    class="text-sm font-bold text-forest hover:underline"
                    >View All →</Link
                >
            </div>
            <div class="grid grid-cols-2 gap-2.5 md:grid-cols-4">
                <BidCard
                    v-for="bid in props.luxuryBids.slice(0, 4)"
                    :key="bid.id"
                    :bid="bid"
                    :user-points="props.userPoints ?? null"
                />
            </div>
        </div>

        <!-- BIDDING PROCESS SCROLLING TICKER -->
        <div class="mb-5 overflow-hidden border-y-2 border-lemon bg-navy py-4">
            <div class="flex animate-marquee items-center whitespace-nowrap">
                <!-- Step 1 -->
                <div class="inline-flex items-center gap-3 px-10">
                    <span
                        class="flex h-9 w-9 items-center justify-center rounded-full border border-lemon/35 bg-lemon/20 text-lemon"
                    >
                        <span class="material-symbols-outlined text-lg"
                            >payments</span
                        >
                    </span>
                    <div class="text-left">
                        <div
                            class="text-xs font-black tracking-wider text-[#8aaa80] uppercase"
                        >
                            Step 1
                        </div>
                        <div class="text-sm font-extrabold text-white">
                            Buy Points
                        </div>
                    </div>
                </div>
                <!-- Arrow -->
                <i class="pi pi-chevron-right text-xs text-lemon/50"></i>

                <!-- Step 2 -->
                <div class="inline-flex items-center gap-3 px-10">
                    <span
                        class="flex h-9 w-9 items-center justify-center rounded-full border border-lemon/35 bg-lemon/20 text-lemon"
                    >
                        <span class="material-symbols-outlined text-lg"
                            >search</span
                        >
                    </span>
                    <div class="text-left">
                        <div
                            class="text-xs font-black tracking-wider text-[#8aaa80] uppercase"
                        >
                            Step 2
                        </div>
                        <div class="text-sm font-extrabold text-white">
                            Choose Item to Bid For
                        </div>
                    </div>
                </div>
                <!-- Arrow -->
                <i class="pi pi-chevron-right text-xs text-lemon/50"></i>

                <!-- Step 3 -->
                <div class="inline-flex items-center gap-3 px-10">
                    <span
                        class="flex h-9 w-9 items-center justify-center rounded-full border border-lemon/35 bg-lemon/20 text-lemon"
                    >
                        <span class="material-symbols-outlined text-lg"
                            >gavel</span
                        >
                    </span>
                    <div class="text-left">
                        <div
                            class="text-xs font-black tracking-wider text-[#8aaa80] uppercase"
                        >
                            Step 3
                        </div>
                        <div class="text-sm font-extrabold text-white">
                            Bid Points
                        </div>
                    </div>
                </div>
                <!-- Arrow -->
                <i class="pi pi-chevron-right text-xs text-lemon/50"></i>

                <!-- Step 4 -->
                <div class="inline-flex items-center gap-3 px-10">
                    <span
                        class="flex h-9 w-9 items-center justify-center rounded-full border border-lemon/35 bg-lemon/20 text-lemon"
                    >
                        <span class="material-symbols-outlined text-lg"
                            >emoji_events</span
                        >
                    </span>
                    <div class="text-left">
                        <div
                            class="text-xs font-black tracking-wider text-[#8aaa80] uppercase"
                        >
                            Step 4
                        </div>
                        <div class="text-sm font-extrabold text-white">
                            Highest Bidder Wins
                        </div>
                    </div>
                </div>
                <!-- Arrow -->
                <i class="pi pi-chevron-right text-xs text-lemon/50"></i>

                <!-- Step 5 -->
                <div class="inline-flex items-center gap-3 px-10">
                    <span
                        class="flex h-9 w-9 items-center justify-center rounded-full border border-lemon/35 bg-lemon/20 text-lemon"
                    >
                        <span class="material-symbols-outlined text-lg"
                            >local_shipping</span
                        >
                    </span>
                    <div class="text-left">
                        <div
                            class="text-xs font-black tracking-wider text-[#8aaa80] uppercase"
                        >
                            Step 5
                        </div>
                        <div class="text-sm font-extrabold text-white">
                            Items Delivered
                        </div>
                    </div>
                </div>
                <!-- Arrow -->
                <i class="pi pi-chevron-right text-xs text-lemon/50"></i>

                <!-- Step 6 -->
                <div class="inline-flex items-center gap-3 px-10">
                    <span
                        class="flex h-9 w-9 items-center justify-center rounded-full border border-lemon/35 bg-lemon/20 text-lemon"
                    >
                        <span class="material-symbols-outlined text-lg"
                            >price_check</span
                        >
                    </span>
                    <div class="text-left">
                        <div
                            class="text-xs font-black tracking-wider text-[#8aaa80] uppercase"
                        >
                            Step 6
                        </div>
                        <div class="text-sm font-extrabold text-white">
                            No Payment for Item Won
                        </div>
                    </div>
                </div>

                <!-- Duplicate steps for seamless loop -->
                <!-- Arrow -->
                <i class="pi pi-chevron-right text-xs text-lemon/50"></i>
                <!-- Step 1 -->
                <div class="inline-flex items-center gap-3 px-10">
                    <span
                        class="flex h-9 w-9 items-center justify-center rounded-full border border-lemon/35 bg-lemon/20 text-lemon"
                    >
                        <span class="material-symbols-outlined text-lg"
                            >payments</span
                        >
                    </span>
                    <div class="text-left">
                        <div
                            class="text-xs font-black tracking-wider text-[#8aaa80] uppercase"
                        >
                            Step 1
                        </div>
                        <div class="text-sm font-extrabold text-white">
                            Buy Points
                        </div>
                    </div>
                </div>
                <!-- Arrow -->
                <i class="pi pi-chevron-right text-xs text-lemon/50"></i>
                <!-- Step 2 -->
                <div class="inline-flex items-center gap-3 px-10">
                    <span
                        class="flex h-9 w-9 items-center justify-center rounded-full border border-lemon/35 bg-lemon/20 text-lemon"
                    >
                        <span class="material-symbols-outlined text-lg"
                            >search</span
                        >
                    </span>
                    <div class="text-left">
                        <div
                            class="text-xs font-black tracking-wider text-[#8aaa80] uppercase"
                        >
                            Step 2
                        </div>
                        <div class="text-sm font-extrabold text-white">
                            Choose Item to Bid For
                        </div>
                    </div>
                </div>
                <!-- Arrow -->
                <i class="pi pi-chevron-right text-xs text-lemon/50"></i>
                <!-- Step 3 -->
                <div class="inline-flex items-center gap-3 px-10">
                    <span
                        class="flex h-9 w-9 items-center justify-center rounded-full border border-lemon/35 bg-lemon/20 text-lemon"
                    >
                        <span class="material-symbols-outlined text-lg"
                            >gavel</span
                        >
                    </span>
                    <div class="text-left">
                        <div
                            class="text-xs font-black tracking-wider text-[#8aaa80] uppercase"
                        >
                            Step 3
                        </div>
                        <div class="text-sm font-extrabold text-white">
                            Bid Points
                        </div>
                    </div>
                </div>
                <!-- Arrow -->
                <i class="pi pi-chevron-right text-xs text-lemon/50"></i>
                <!-- Step 4 -->
                <div class="inline-flex items-center gap-3 px-10">
                    <span
                        class="flex h-9 w-9 items-center justify-center rounded-full border border-lemon/35 bg-lemon/20 text-lemon"
                    >
                        <span class="material-symbols-outlined text-lg"
                            >emoji_events</span
                        >
                    </span>
                    <div class="text-left">
                        <div
                            class="text-xs font-black tracking-wider text-[#8aaa80] uppercase"
                        >
                            Step 4
                        </div>
                        <div class="text-sm font-extrabold text-white">
                            Highest Bidder Wins
                        </div>
                    </div>
                </div>
                <!-- Arrow -->
                <i class="pi pi-chevron-right text-xs text-lemon/50"></i>
                <!-- Step 5 -->
                <div class="inline-flex items-center gap-3 px-10">
                    <span
                        class="flex h-9 w-9 items-center justify-center rounded-full border border-lemon/35 bg-lemon/20 text-lemon"
                    >
                        <span class="material-symbols-outlined text-lg"
                            >local_shipping</span
                        >
                    </span>
                    <div class="text-left">
                        <div
                            class="text-xs font-black tracking-wider text-[#8aaa80] uppercase"
                        >
                            Step 5
                        </div>
                        <div class="text-sm font-extrabold text-white">
                            Items Delivered
                        </div>
                    </div>
                </div>
                <!-- Arrow -->
                <i class="pi pi-chevron-right text-xs text-lemon/50"></i>
                <!-- Step 6 -->
                <div class="inline-flex items-center gap-3 px-10">
                    <span
                        class="flex h-9 w-9 items-center justify-center rounded-full border border-lemon/35 bg-lemon/20 text-lemon"
                    >
                        <span class="material-symbols-outlined text-lg"
                            >price_check</span
                        >
                    </span>
                    <div class="text-left">
                        <div
                            class="text-xs font-black tracking-wider text-[#8aaa80] uppercase"
                        >
                            Step 6
                        </div>
                        <div class="text-sm font-extrabold text-white">
                            No Payment for Item Won
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- CATEGORY BIDS -->
        <template v-if="props.categoryBids">
            <div
                v-for="(bids, categoryName) in props.categoryBids"
                :key="String(categoryName)"
                class="mx-auto mb-5 max-w-[1300px] px-4"
            >
                <template v-if="bids && bids.length > 0">
                    <div class="mb-3.5 flex items-center justify-between">
                        <div
                            class="flex items-center font-condensed text-2xl font-extrabold text-ink"
                        >
                            <span
                                class="mr-2 inline-block h-[22px] w-1 rounded-sm bg-forest align-middle"
                            ></span>
                            <span
                                class="material-symbols-outlined mr-1.5 text-[22px] leading-none text-forest"
                                >{{
                                    getCategoryIcon(String(categoryName))
                                }}</span
                            >
                            {{ categoryName }}
                        </div>
                        <Link
                            :href="
                                trending.url() +
                                '?category=' +
                                encodeURIComponent(String(categoryName))
                            "
                            class="text-sm font-bold text-forest hover:underline"
                            >View All →</Link
                        >
                    </div>
                    <div class="grid grid-cols-2 gap-2.5 md:grid-cols-4">
                        <BidCard
                            v-for="bid in bids.slice(0, 4)"
                            :key="bid.id"
                            :bid="bid"
                            :user-points="props.userPoints ?? null"
                        />
                    </div>
                </template>
            </div>
        </template>

        <!-- WINNERS MARQUEE (existing component) -->
        <HomeWinnersSection
            v-if="props.winners && props.winners.length > 0"
            :winners="props.winners"
        />

        <!-- TESTIMONIALS (existing Material Design component) -->
        <HomeTestimonialsSection
            v-if="props.reviews && props.reviews.length > 0"
            :reviews="props.reviews"
        />

         <!-- YOUTUBE TESTIMONIALS CTA -->
        <div class="mx-auto max-w-screen-2xl px-8 py-4">
            <YoutubeTestimonialsCta />
        </div>

        <HomePromoSection />
    </div>
</template>
