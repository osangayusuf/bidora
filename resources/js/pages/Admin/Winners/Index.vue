<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    Trophy,
    Search,
    Coins,
    Package,
    Star,
    Eye,
    X,
    ExternalLink,
    Image as ImageIcon,
    Film,
    Phone,
    Mail,
    ListFilter,
} from 'lucide-vue-next';
import { ref } from 'vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { index as bidsIndex } from '@/routes/admin/bids';
import { index as winnersIndex } from '@/routes/admin/winners';

type WinnerReview = {
    id: number;
    rating: number;
    comment: string;
    social_platform: string | null;
    social_handle: string | null;
    is_visible: boolean;
    photos: string[] | null;
    video: string | null;
    created_at: string;
};

type WinnerRecord = {
    id: number;
    auction_name: string;
    category: string | null;
    price: number;
    image: string | null;
    enabled: boolean;
    event: boolean;
    bid_count: number;
    winning_pts: number;
    total_pts_bid: number;
    closed_at: string;
    winner: {
        id: number;
        name: string;
        email: string;
        phone: string | null;
    } | null;
    review: WinnerReview | null;
};

type PaginatedWinners = {
    data: WinnerRecord[];
    links: {
        url: string | null;
        label: string;
        active: boolean;
    }[];
    current_page: number;
    last_page: number;
    total: number;
    from: number | null;
    to: number | null;
};

type Stats = {
    total_winners_count: number;
    total_winning_points: number;
    total_retail_value: number;
    total_reviews_count: number;
};

const props = defineProps<{
    winners: PaginatedWinners;
    stats: Stats;
    categories: string[];
    filters: {
        search: string | null;
        category: string | null;
        review_status: string | null;
    };
}>();

const searchForm = useForm({
    search: props.filters.search || '',
    category: props.filters.category || '',
    review_status: props.filters.review_status || '',
});

const handleSearch = () => {
    searchForm.get(winnersIndex.url(), {
        preserveState: true,
    });
};

const clearSearch = () => {
    searchForm.search = '';
    searchForm.category = '';
    searchForm.review_status = '';
    handleSearch();
};

const selectedReviewWinner = ref<WinnerRecord | null>(null);
const activeExpandedPhoto = ref<string | null>(null);

const openProofModal = (winner: WinnerRecord) => {
    selectedReviewWinner.value = winner;
    activeExpandedPhoto.value = null;
};

const closeProofModal = () => {
    selectedReviewWinner.value = null;
    activeExpandedPhoto.value = null;
};

const formatCurrency = (val: number) => {
    return new Intl.NumberFormat('en-NG', {
        style: 'currency',
        currency: 'NGN',
        minimumFractionDigits: 0,
    }).format(val);
};

const formatPoints = (val: number) => {
    return new Intl.NumberFormat('en-US').format(val);
};

const formatDate = (isoString: string) => {
    if (!isoString) {
        return '—';
    }

    return new Date(isoString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};
</script>

<template>
    <Head title="Admin - Previous Auction Winners" />

    <AdminLayout :breadcrumbs="[{ title: 'Previous Winners' }]">
        <div class="flex flex-col gap-6 font-sans text-xs">
            <!-- Header bar -->
            <div
                class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center"
            >
                <div>
                    <h1
                        class="flex items-center gap-2.5 text-2xl font-black tracking-tight text-primary uppercase"
                    >
                        <Trophy class="h-6 w-6 text-primary" />
                        <span>Previous Auction Winners</span>
                    </h1>
                    <p class="mt-1 text-xs text-on-surface-variant">
                        Audit winning bids, cumulative points, winner contact
                        records, and customer delivery reviews.
                    </p>
                </div>
            </div>

            <!-- KPI Summary Cards -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div
                    class="flex items-center gap-4 rounded-xl border border-outline-variant bg-surface-container-lowest p-4 shadow-sm"
                >
                    <div
                        class="flex h-12 w-12 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary"
                    >
                        <Trophy class="h-6 w-6" />
                    </div>
                    <div>
                        <p
                            class="text-[10px] font-bold tracking-wider text-on-surface-variant uppercase"
                        >
                            Total Auctions Won
                        </p>
                        <p class="mt-0.5 text-xl font-black text-primary">
                            {{ formatPoints(stats.total_winners_count) }}
                        </p>
                    </div>
                </div>

                <div
                    class="flex items-center gap-4 rounded-xl border border-outline-variant bg-surface-container-lowest p-4 shadow-sm"
                >
                    <div
                        class="flex h-12 w-12 shrink-0 items-center justify-center rounded-lg bg-secondary/10 text-secondary"
                    >
                        <Coins class="h-6 w-6" />
                    </div>
                    <div>
                        <p
                            class="text-[10px] font-bold tracking-wider text-on-surface-variant uppercase"
                        >
                            Total Winning Points
                        </p>
                        <p class="mt-0.5 text-xl font-black text-secondary">
                            {{ formatPoints(stats.total_winning_points) }} PTS
                        </p>
                    </div>
                </div>

                <div
                    class="flex items-center gap-4 rounded-xl border border-outline-variant bg-surface-container-lowest p-4 shadow-sm"
                >
                    <div
                        class="flex h-12 w-12 shrink-0 items-center justify-center rounded-lg bg-tertiary/10 text-tertiary"
                    >
                        <Package class="h-6 w-6" />
                    </div>
                    <div>
                        <p
                            class="text-[10px] font-bold tracking-wider text-on-surface-variant uppercase"
                        >
                            Total Retail Value
                        </p>
                        <p class="mt-0.5 text-xl font-black text-primary">
                            {{ formatCurrency(stats.total_retail_value) }}
                        </p>
                    </div>
                </div>

                <div
                    class="flex items-center gap-4 rounded-xl border border-outline-variant bg-surface-container-lowest p-4 shadow-sm"
                >
                    <div
                        class="flex h-12 w-12 shrink-0 items-center justify-center rounded-lg bg-amber-500/10 text-amber-500"
                    >
                        <Star class="h-6 w-6" />
                    </div>
                    <div>
                        <p
                            class="text-[10px] font-bold tracking-wider text-on-surface-variant uppercase"
                        >
                            Reviews & Proofs
                        </p>
                        <p class="mt-0.5 text-xl font-black text-amber-500">
                            {{ formatPoints(stats.total_reviews_count) }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Filter and Search Bar -->
            <div
                class="rounded-xl border border-outline-variant bg-surface-container-lowest p-4 shadow-sm"
            >
                <form
                    @submit.prevent="handleSearch"
                    class="grid grid-cols-1 gap-3 md:grid-cols-12"
                >
                    <!-- Search input -->
                    <div class="relative md:col-span-5">
                        <input
                            v-model="searchForm.search"
                            type="text"
                            placeholder="SEARCH BY WINNER NAME, EMAIL, PHONE OR AUCTION..."
                            class="w-full rounded-lg border border-outline-variant bg-surface-container-low py-3 pr-4 pl-10 text-xs font-bold text-on-surface focus:ring-1 focus:ring-secondary focus:outline-none"
                        />
                        <Search
                            class="absolute top-3.5 left-3.5 h-4 w-4 text-on-surface-variant"
                        />
                    </div>

                    <!-- Category dropdown -->
                    <div class="md:col-span-3">
                        <select
                            v-model="searchForm.category"
                            class="w-full rounded-lg border border-outline-variant bg-surface-container-low px-3 py-3 text-xs font-bold text-on-surface focus:ring-1 focus:ring-secondary focus:outline-none"
                        >
                            <option value="">ALL CATEGORIES</option>
                            <option
                                v-for="cat in categories"
                                :key="cat"
                                :value="cat"
                            >
                                {{ cat.toUpperCase() }}
                            </option>
                        </select>
                    </div>

                    <!-- Review status dropdown -->
                    <div class="md:col-span-2">
                        <select
                            v-model="searchForm.review_status"
                            class="w-full rounded-lg border border-outline-variant bg-surface-container-low px-3 py-3 text-xs font-bold text-on-surface focus:ring-1 focus:ring-secondary focus:outline-none"
                        >
                            <option value="">ALL REVIEWS</option>
                            <option value="with_review">HAS REVIEW</option>
                            <option value="with_media">HAS MEDIA PROOF</option>
                            <option value="without_review">NO REVIEW</option>
                        </select>
                    </div>

                    <!-- Action buttons -->
                    <div class="flex gap-2 md:col-span-2">
                        <button
                            type="submit"
                            class="flex-1 rounded-lg bg-secondary px-4 py-3 font-black text-on-secondary-fixed uppercase transition-all hover:bg-secondary/90"
                        >
                            Filter
                        </button>
                        <button
                            type="button"
                            @click="clearSearch"
                            class="rounded-lg border border-outline-variant bg-surface-container-lowest px-4 py-3 font-bold text-on-surface-variant uppercase transition-all hover:text-primary"
                        >
                            Reset
                        </button>
                    </div>
                </form>
            </div>

            <!-- Winners Table -->
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
                                <th class="px-6 py-4">Auction Item</th>
                                <th class="px-6 py-4">Winner Details</th>
                                <th class="px-6 py-4">Winning Bid / Total</th>
                                <th class="px-6 py-4">Delivery Proof</th>
                                <th class="px-6 py-4">Closed Date</th>
                                <th class="px-6 py-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-outline-variant">
                            <tr
                                v-for="item in winners.data"
                                :key="item.id"
                                class="group transition-colors hover:bg-surface-container-low/20"
                            >
                                <!-- Auction Item -->
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <div
                                            class="h-12 w-12 shrink-0 overflow-hidden rounded-lg border border-outline-variant bg-surface-container-low"
                                        >
                                            <img
                                                v-if="item.image"
                                                :src="item.image"
                                                :alt="item.auction_name"
                                                class="h-full w-full object-cover"
                                            />
                                            <div
                                                v-else
                                                class="flex h-full w-full items-center justify-center text-on-surface-variant"
                                            >
                                                <Package class="h-5 w-5" />
                                            </div>
                                        </div>
                                        <div class="flex flex-col">
                                            <span
                                                class="font-bold text-primary"
                                                >{{ item.auction_name }}</span
                                            >
                                            <div
                                                class="mt-1 flex items-center gap-2 text-[10px]"
                                            >
                                                <span
                                                    v-if="item.category"
                                                    class="rounded bg-surface-container-low px-1.5 py-0.5 font-semibold text-on-surface-variant uppercase"
                                                >
                                                    {{ item.category }}
                                                </span>
                                                <span
                                                    class="font-semibold text-primary"
                                                >
                                                    {{
                                                        formatCurrency(
                                                            item.price,
                                                        )
                                                    }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </td>

                                <!-- Winner Info -->
                                <td class="px-6 py-4">
                                    <div
                                        v-if="item.winner"
                                        class="flex flex-col gap-1"
                                    >
                                        <div
                                            class="flex items-center gap-1.5 font-bold text-primary"
                                        >
                                            <span>{{ item.winner.name }}</span>
                                            <span
                                                class="text-[9px] font-semibold text-on-surface-variant"
                                                >#{{ item.winner.id }}</span
                                            >
                                        </div>
                                        <div
                                            class="flex items-center gap-1 text-[10px] text-on-surface-variant"
                                        >
                                            <Mail class="h-3 w-3 shrink-0" />
                                            <span>{{ item.winner.email }}</span>
                                        </div>
                                        <div
                                            v-if="item.winner.phone"
                                            class="flex items-center gap-1 text-[10px] text-on-surface-variant"
                                        >
                                            <Phone class="h-3 w-3 shrink-0" />
                                            <span>{{ item.winner.phone }}</span>
                                        </div>
                                    </div>
                                    <span
                                        v-else
                                        class="text-on-surface-variant italic"
                                        >No Winner Assigned</span
                                    >
                                </td>

                                <!-- Winning Bid & Stats -->
                                <td class="px-6 py-4">
                                    <div class="flex flex-col gap-1">
                                        <div
                                            class="flex items-center gap-1.5 font-black text-secondary"
                                        >
                                            <Coins class="h-3.5 w-3.5" />
                                            <span
                                                >{{
                                                    formatPoints(
                                                        item.winning_pts,
                                                    )
                                                }}
                                                PTS</span
                                            >
                                        </div>
                                        <span
                                            class="text-[10px] text-on-surface-variant"
                                        >
                                            Pool:
                                            {{
                                                formatPoints(item.total_pts_bid)
                                            }}
                                            PTS ({{ item.bid_count }} bids)
                                        </span>
                                    </div>
                                </td>

                                <!-- Delivery Proof / Review -->
                                <td class="px-6 py-4">
                                    <div
                                        v-if="item.review"
                                        class="flex flex-col items-start gap-1.5"
                                    >
                                        <div class="flex items-center gap-1">
                                            <div
                                                class="flex items-center gap-0.5 text-amber-500"
                                            >
                                                <Star
                                                    class="h-3 w-3 fill-current"
                                                />
                                                <span class="font-bold">{{
                                                    item.review.rating
                                                }}</span>
                                            </div>
                                            <span
                                                v-if="
                                                    (item.review.photos &&
                                                        item.review.photos
                                                            .length > 0) ||
                                                    item.review.video
                                                "
                                                class="flex items-center gap-1 rounded bg-secondary-container/50 px-1.5 py-0.5 text-[9px] font-bold text-on-secondary-container"
                                            >
                                                <ImageIcon
                                                    v-if="
                                                        item.review.photos &&
                                                        item.review.photos
                                                            .length > 0
                                                    "
                                                    class="h-2.5 w-2.5"
                                                />
                                                <Film
                                                    v-if="item.review.video"
                                                    class="h-2.5 w-2.5"
                                                />
                                                <span>Proof</span>
                                            </span>
                                        </div>

                                        <button
                                            type="button"
                                            @click="openProofModal(item)"
                                            class="flex items-center gap-1 text-[10px] font-bold text-secondary transition-colors hover:text-secondary/80"
                                        >
                                            <Eye class="h-3 w-3" />
                                            <span>Inspect Review</span>
                                        </button>
                                    </div>

                                    <div
                                        v-else
                                        class="flex items-center gap-1 text-[10px] font-semibold text-on-surface-variant/70"
                                    >
                                        <Clock class="h-3 w-3" />
                                        <span>Pending Review</span>
                                    </div>
                                </td>

                                <!-- Closed Date -->
                                <td
                                    class="px-6 py-4 text-[10px] font-semibold text-on-surface-variant"
                                >
                                    {{ formatDate(item.closed_at) }}
                                </td>

                                <!-- Actions -->
                                <td class="px-6 py-4 text-right">
                                    <div
                                        class="flex items-center justify-end gap-2"
                                    >
                                        <Link
                                            :href="
                                                bidsIndex.url({
                                                    query: {
                                                        search: item.auction_name,
                                                    },
                                                })
                                            "
                                            title="Audit Auction Bids"
                                            class="flex items-center gap-1 rounded-lg border border-outline-variant bg-surface-container-low px-2.5 py-1.5 text-[10px] font-bold text-on-surface-variant transition-colors hover:bg-surface-container-lowest hover:text-primary"
                                        >
                                            <ListFilter class="h-3 w-3" />
                                            <span>Bids</span>
                                        </Link>

                                        <a
                                            :href="`/auctions/${item.id}/timeline`"
                                            target="_blank"
                                            title="View Public Timeline"
                                            class="flex items-center gap-1 rounded-lg border border-outline-variant bg-surface-container-low px-2.5 py-1.5 text-[10px] font-bold text-on-surface-variant transition-colors hover:bg-surface-container-lowest hover:text-primary"
                                        >
                                            <ExternalLink class="h-3 w-3" />
                                        </a>
                                    </div>
                                </td>
                            </tr>

                            <tr v-if="winners.data.length === 0">
                                <td
                                    colspan="6"
                                    class="px-6 py-12 text-center font-bold tracking-widest text-on-surface-variant uppercase"
                                >
                                    No previous auction winners found matching
                                    your search criteria.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination footer links -->
                <div
                    v-if="winners.last_page > 1"
                    class="flex items-center justify-between border-t border-outline-variant bg-surface-container-low px-6 py-4"
                >
                    <p
                        class="text-[10px] font-bold tracking-widest text-on-surface-variant uppercase"
                    >
                        Showing {{ winners.from || 0 }} to
                        {{ winners.to || 0 }} of {{ winners.total }} winners
                        (Page {{ winners.current_page }} of
                        {{ winners.last_page }})
                    </p>
                    <div class="flex gap-1">
                        <Link
                            v-for="link in winners.links"
                            :key="link.label"
                            :href="link.url || '#'"
                            :class="[
                                'rounded-lg border px-3 py-1.5 text-[10px] font-bold transition-all',
                                link.active
                                    ? 'border-primary bg-primary text-on-primary'
                                    : 'border-outline-variant bg-surface-container-lowest text-on-surface-variant hover:bg-surface-container-low',
                                !link.url && 'cursor-not-allowed opacity-40',
                            ]"
                        >
                            <span v-html="link.label" />
                        </Link>
                    </div>
                </div>
            </div>
        </div>

        <!-- Delivery Proof / Review Inspection Modal -->
        <div
            v-if="selectedReviewWinner"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4 backdrop-blur-xs"
            @click.self="closeProofModal"
        >
            <div
                class="flex max-h-[90vh] w-full max-w-2xl flex-col overflow-hidden rounded-2xl border border-outline-variant bg-surface-container-lowest shadow-2xl"
            >
                <!-- Modal Header -->
                <div
                    class="flex items-center justify-between border-b border-outline-variant bg-surface-container-low px-6 py-4"
                >
                    <div class="flex items-center gap-2.5">
                        <Trophy class="h-5 w-5 text-secondary" />
                        <div>
                            <h2
                                class="text-sm font-black tracking-tight text-primary uppercase"
                            >
                                Winner Delivery Proof & Review
                            </h2>
                            <p class="text-[10px] text-on-surface-variant">
                                {{ selectedReviewWinner.auction_name }}
                            </p>
                        </div>
                    </div>
                    <button
                        type="button"
                        @click="closeProofModal"
                        class="rounded-lg p-1.5 text-on-surface-variant transition-colors hover:bg-surface-container-lowest hover:text-primary"
                    >
                        <X class="h-5 w-5" />
                    </button>
                </div>

                <!-- Modal Body -->
                <div class="flex-1 space-y-5 overflow-y-auto p-6">
                    <!-- Winner Bio Info -->
                    <div
                        class="grid grid-cols-1 gap-3 rounded-xl border border-outline-variant bg-surface-container-low/50 p-4 sm:grid-cols-2"
                    >
                        <div>
                            <p
                                class="text-[10px] font-bold tracking-wider text-on-surface-variant uppercase"
                            >
                                Winner
                            </p>
                            <p class="mt-0.5 text-xs font-bold text-primary">
                                {{ selectedReviewWinner.winner?.name || '—' }}
                            </p>
                            <p class="text-[10px] text-on-surface-variant">
                                {{ selectedReviewWinner.winner?.email }}
                            </p>
                        </div>

                        <div>
                            <p
                                class="text-[10px] font-bold tracking-wider text-on-surface-variant uppercase"
                            >
                                Winning Bid & Points
                            </p>
                            <p class="mt-0.5 text-xs font-bold text-secondary">
                                {{
                                    formatPoints(
                                        selectedReviewWinner.winning_pts,
                                    )
                                }}
                                PTS
                            </p>
                            <p class="text-[10px] text-on-surface-variant">
                                Retail:
                                {{ formatCurrency(selectedReviewWinner.price) }}
                            </p>
                        </div>
                    </div>

                    <!-- Review Details -->
                    <div
                        v-if="selectedReviewWinner.review"
                        class="space-y-4 rounded-xl border border-outline-variant bg-surface-container-lowest p-4"
                    >
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-1 text-amber-500">
                                <Star
                                    v-for="i in 5"
                                    :key="i"
                                    :class="[
                                        'h-4 w-4',
                                        i <=
                                        Math.round(
                                            selectedReviewWinner.review.rating,
                                        )
                                            ? 'fill-current'
                                            : 'opacity-30',
                                    ]"
                                />
                                <span
                                    class="ml-1 text-xs font-bold text-primary"
                                    >{{ selectedReviewWinner.review.rating }} /
                                    5</span
                                >
                            </div>

                            <div
                                v-if="
                                    selectedReviewWinner.review.social_platform
                                "
                                class="rounded bg-surface-container-low px-2 py-0.5 text-[10px] font-bold text-on-surface-variant"
                            >
                                {{
                                    selectedReviewWinner.review.social_platform
                                }}:
                                {{ selectedReviewWinner.review.social_handle }}
                            </div>
                        </div>

                        <div>
                            <p
                                class="text-[10px] font-bold tracking-wider text-on-surface-variant uppercase"
                            >
                                Testimonial Comment
                            </p>
                            <p
                                class="mt-1 text-xs leading-relaxed text-on-surface"
                            >
                                "{{ selectedReviewWinner.review.comment }}"
                            </p>
                        </div>

                        <!-- Photo Proofs -->
                        <div
                            v-if="
                                selectedReviewWinner.review.photos &&
                                selectedReviewWinner.review.photos.length > 0
                            "
                        >
                            <p
                                class="mb-2 text-[10px] font-bold tracking-wider text-on-surface-variant uppercase"
                            >
                                Attached Proof Photos ({{
                                    selectedReviewWinner.review.photos.length
                                }})
                            </p>
                            <div class="grid grid-cols-3 gap-2">
                                <button
                                    v-for="(
                                        photo, index
                                    ) in selectedReviewWinner.review.photos"
                                    :key="index"
                                    type="button"
                                    @click="activeExpandedPhoto = photo"
                                    class="relative aspect-square overflow-hidden rounded-lg border border-outline-variant bg-surface-container-low transition-transform hover:scale-102"
                                >
                                    <img
                                        :src="photo"
                                        alt="Delivery Proof"
                                        class="h-full w-full object-cover"
                                    />
                                </button>
                            </div>
                        </div>

                        <!-- Video Proof -->
                        <div v-if="selectedReviewWinner.review.video">
                            <p
                                class="mb-2 text-[10px] font-bold tracking-wider text-on-surface-variant uppercase"
                            >
                                Attached Video Proof
                            </p>
                            <div
                                class="overflow-hidden rounded-lg border border-outline-variant bg-surface-container-low"
                            >
                                <video
                                    :src="selectedReviewWinner.review.video"
                                    controls
                                    class="max-h-64 w-full object-contain"
                                ></video>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Modal Footer -->
                <div
                    class="flex items-center justify-end border-t border-outline-variant bg-surface-container-low px-6 py-4"
                >
                    <button
                        type="button"
                        @click="closeProofModal"
                        class="rounded-lg bg-surface-container-lowest px-4 py-2 font-bold text-on-surface uppercase transition-colors hover:bg-surface-container-low"
                    >
                        Close
                    </button>
                </div>
            </div>
        </div>

        <!-- Expanded Photo Lightbox -->
        <div
            v-if="activeExpandedPhoto"
            class="fixed inset-0 z-60 flex items-center justify-center bg-black/90 p-4"
            @click="activeExpandedPhoto = null"
        >
            <div class="relative max-h-[90vh] max-w-[90vw]">
                <img
                    :src="activeExpandedPhoto"
                    alt="Proof Expanded"
                    class="max-h-[85vh] max-w-full rounded-xl object-contain shadow-2xl"
                />
                <button
                    type="button"
                    @click="activeExpandedPhoto = null"
                    class="absolute -top-3 -right-3 rounded-full bg-surface-container-lowest p-2 text-primary shadow-lg"
                >
                    <X class="h-5 w-5" />
                </button>
            </div>
        </div>
    </AdminLayout>
</template>
