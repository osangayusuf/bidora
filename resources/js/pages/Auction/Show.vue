<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { store as storeReview } from '@/actions/App/Http/Controllers/AuctionReviewController';
import { store as storeBid } from '@/actions/App/Http/Controllers/BidController';
import AuctionLeaderboardSidebar from '@/components/auction/AuctionLeaderboardSidebar.vue';
import AuctionHistoryFeed from '@/components/modals/AuctionHistoryFeed.vue';
import ShareModal from '@/components/modals/ShareModal.vue';
import { useAuctionCountdown } from '@/composables/useAuctionCountdown';
import PublicLayout from '@/layouts/PublicLayout.vue';
import {
    calcProgress,
    formatPrice,
    formatSlashedPrice,
    getRemainingTime,
} from '@/lib/utils';
import { home, login } from '@/routes/index';
import type { Bid, Bidder } from '@/types/auction';

defineOptions({ layout: PublicLayout });

type ReviewItem = {
    id: number;
    rating: number;
    comment: string;
    social_platform: string | null;
    social_handle: string | null;
    photos: string[] | null;
    video: string | null;
    created_at: string;
    user_name: string;
};

type UserReviewState = {
    canReview: boolean;
    isWinner: boolean;
    hasReviewed: boolean;
};

const props = defineProps<{
    auction: Bid;
    topBidders: Bidder[];
    userPoints: number | null;
    reviews: ReviewItem[];
    userReviewState: UserReviewState;
}>();

const page = usePage();

const { expiresAt } = useAuctionCountdown(
    computed(() => props.auction.id ?? null),
    computed(() => props.auction.expires_at),
);

const expandedImage = ref<string | null>(null);
const isShareOpen = ref(false);
const shareMessageOverride = ref<string | undefined>(undefined);

function openAuctionShareModal(): void {
    shareMessageOverride.value = undefined;
    isShareOpen.value = true;
}

function openReviewShareModal(reviewComment: string): void {
    const isWinner = props.userReviewState.isWinner;

    if (isWinner) {
        shareMessageOverride.value = `I won ${props.auction.name} on Bidora! Check out my review: "${reviewComment}" - Bid here:`;
    } else {
        shareMessageOverride.value = `A verified bidder won ${props.auction.name} on Bidora! What they said: "${reviewComment}" - Join me to bid:`;
    }

    isShareOpen.value = true;
}

const shareUrl = computed(() => {
    if (typeof window !== 'undefined') {
        return window.location.href;
    }

    return '';
});

const canBid = computed(
    () => props.auction.status === 0 || props.auction.status === 1,
);

const authUserPoints = computed(
    () =>
        props.userPoints ??
        (page.props.auth as { user?: { points_balance?: number | string } })
            ?.user?.points_balance ??
        null,
);

const form = useForm({
    points: Number(authUserPoints.value ?? 0) || 0,
});

const reviewForm = useForm({
    rating: 5,
    comment: '',
    social_platform: '',
    social_handle: '',
    photos: [] as File[],
    video: null as File | null,
});

const photoPreviews = ref<string[]>([]);
const videoPreview = ref<string | null>(null);

function handlePhotosChange(e: Event): void {
    const files = (e.target as HTMLInputElement).files;

    if (!files) {
        return;
    }

    const newFiles = Array.from(files).slice(0, 3 - reviewForm.photos.length);
    reviewForm.photos = [...reviewForm.photos, ...newFiles];

    newFiles.forEach((file) => {
        const reader = new FileReader();
        reader.onload = (event) => {
            if (event.target?.result) {
                photoPreviews.value.push(event.target.result as string);
            }
        };
        reader.readAsDataURL(file);
    });
}

function removePhoto(index: number): void {
    reviewForm.photos.splice(index, 1);
    photoPreviews.value.splice(index, 1);
}

function handleVideoChange(e: Event): void {
    const files = (e.target as HTMLInputElement).files;

    if (!files || files.length === 0) {
        return;
    }

    const file = files[0];
    reviewForm.video = file;

    const reader = new FileReader();
    reader.onload = (event) => {
        if (event.target?.result) {
            videoPreview.value = event.target.result as string;
        }
    };
    reader.readAsDataURL(file);
}

function removeVideo(): void {
    reviewForm.video = null;
    videoPreview.value = null;
}

function submitReview(): void {
    reviewForm.post(storeReview.url(props.auction.id), {
        preserveScroll: true,
        onSuccess: () => {
            reviewForm.reset();
            photoPreviews.value = [];
            videoPreview.value = null;
        },
    });
}

function statusLabel(): string {
    if (props.auction.status === 2) {
        return 'Closed';
    }

    if (props.auction.status === 1) {
        return 'Open';
    }

    return 'Live';
}

function submitBid(): void {
    if (!canBid.value) {
        return;
    }

    if (!page.props.auth?.user) {
        sessionStorage.setItem('pendingBidId', props.auction.id.toString());
        router.get(login.url());

        return;
    }

    form.post(storeBid.url(props.auction.id), {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head :title="auction.name" />

    <div class="mx-auto max-w-7xl px-4 py-8">
        <Link
            :href="home.url()"
            class="mb-6 inline-flex items-center gap-1.5 text-sm font-bold text-forest no-underline hover:text-forest-dark"
        >
            <i class="pi pi-arrow-left text-xs"></i>
            Back to home
        </Link>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3 lg:gap-8">
            <div class="flex flex-col gap-6 lg:col-span-2">
                <article
                    class="overflow-hidden rounded-xl border-2 border-sage-border bg-white"
                >
                    <div
                        class="relative h-48 cursor-pointer overflow-hidden bg-sage-light sm:h-64"
                        @click="expandedImage = auction.image"
                    >
                        <img
                            v-if="auction.image"
                            :src="auction.image"
                            :alt="auction.name"
                            class="block h-full w-full object-cover"
                        />
                        <div
                            v-else
                            class="flex h-full w-full items-center justify-center text-muted-green"
                        >
                            <span class="pi pi-image text-5xl"></span>
                        </div>
                        <div class="absolute top-3 right-3">
                            <span
                                v-if="auction.status === 0"
                                class="rounded-lg bg-navy px-3 py-1 text-[10px] font-black tracking-widest text-lemon uppercase shadow-lg"
                            >
                                Live
                            </span>
                            <span
                                v-else-if="auction.status === 1"
                                class="rounded-lg bg-navy px-3 py-1 text-[10px] font-black tracking-widest text-lemon uppercase shadow-lg"
                            >
                                Open
                            </span>
                            <span
                                v-else
                                class="rounded-lg bg-error px-3 py-1 text-[10px] font-black tracking-widest text-white uppercase shadow-lg"
                            >
                                Closed
                            </span>
                        </div>
                    </div>

                    <div class="p-4 sm:p-6">
                        <p
                            class="mb-1 text-[10px] font-black tracking-widest text-muted-green uppercase"
                        >
                            {{ statusLabel() }} auction
                        </p>
                        <div
                            class="mb-2 flex items-start justify-between gap-4"
                        >
                            <h1
                                class="m-0 flex flex-wrap items-center gap-2 text-xl font-extrabold text-ink sm:text-2xl"
                            >
                                <span>{{ auction.name }}</span>
                                <span
                                    v-if="auction.status === 1"
                                    class="inline-flex animate-pulse items-center gap-1 rounded bg-red-600 px-2 py-0.5 text-xs font-black text-white uppercase"
                                >
                                    🚨 Closing Soon
                                </span>
                                <span
                                    v-else-if="auction.bid_count >= 20"
                                    class="inline-flex items-center gap-1 rounded bg-red-500 px-2 py-0.5 text-xs font-black text-white uppercase"
                                >
                                    🔥 Hot Bid
                                </span>
                            </h1>
                            <button
                                type="button"
                                class="flex shrink-0 cursor-pointer items-center justify-center gap-1.5 rounded-lg border-2 border-sage-border bg-white px-3 py-1.5 text-xs font-bold text-ink transition-colors hover:border-lemon hover:text-forest"
                                @click="openAuctionShareModal"
                            >
                                <i class="pi pi-share-alt"></i> Share
                            </button>
                        </div>
                        <p class="mb-4 flex items-baseline gap-2">
                            <span
                                class="text-2xl font-black whitespace-nowrap text-forest"
                            >
                                {{ formatPrice(auction.price) }}
                            </span>
                            <span
                                class="text-sm font-bold whitespace-nowrap text-muted-green/70 line-through"
                            >
                                {{ formatSlashedPrice(auction.price) }}
                            </span>
                        </p>

                        <div
                            v-if="auction.description"
                            class="mb-6 rounded-xl border-2 border-sage-border bg-sage-bg/25 p-4"
                        >
                            <h3
                                class="mb-2 text-xs font-black tracking-widest text-muted-green uppercase"
                            >
                                Item Description
                            </h3>
                            <p
                                class="text-sm leading-relaxed font-semibold whitespace-pre-line text-ink"
                            >
                                {{ auction.description }}
                            </p>
                        </div>

                        <div
                            v-if="auction.external_url"
                            class="mb-6 flex flex-col justify-between gap-4 rounded-xl border-2 border-sage-border bg-sage-bg/25 p-4 sm:flex-row sm:items-center"
                        >
                            <div>
                                <h3
                                    class="mb-1 text-xs font-black tracking-widest text-muted-green uppercase"
                                >
                                    External Reference
                                </h3>
                                <p
                                    class="text-xs leading-normal font-semibold text-ink"
                                >
                                    This item is sourced externally. Visit the
                                    partner page for more details.
                                </p>
                            </div>
                            <a
                                :href="auction.external_url"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="inline-flex shrink-0 items-center justify-center gap-1.5 rounded-lg bg-navy px-4 py-2 text-xs font-extrabold text-lemon no-underline transition-colors hover:bg-forest"
                            >
                                Visit Website
                                <i class="pi pi-external-link text-[10px]"></i>
                            </a>
                        </div>

                        <div
                            class="mb-2 h-1.5 overflow-hidden rounded-sm bg-sage-mid"
                        >
                            <div
                                class="h-full rounded-sm bg-linear-to-r from-forest to-lemon"
                                :style="{ width: calcProgress(auction) + '%' }"
                            ></div>
                        </div>
                        <div
                            class="mb-4 flex items-center justify-between text-xs font-bold"
                        >
                            <span class="text-forest">
                                Progress: {{ auction.current_points ?? 0 }}/{{
                                    auction.opening_points
                                }}
                                pts
                            </span>
                            <span
                                :class="
                                    calcProgress(auction) >= 100
                                        ? 'text-error'
                                        : 'text-primary'
                                "
                            >
                                {{ calcProgress(auction) }}%
                            </span>
                        </div>

                        <p
                            v-if="
                                (auction.status === 1 ||
                                    calcProgress(auction) >= 100) &&
                                expiresAt
                            "
                            class="mb-0 text-center text-xs font-bold text-outline"
                        >
                            <span
                                class="material-symbols-outlined mr-1 align-middle text-sm"
                                >schedule</span
                            >
                            <span class="align-middle"
                                >{{ getRemainingTime(expiresAt) }} left</span
                            >
                        </p>
                    </div>
                </article>

                <section
                    class="rounded-xl border-2 border-sage-border bg-white p-4 sm:p-6"
                >
                    <h2
                        class="mb-4 font-headline text-lg font-extrabold text-ink"
                    >
                        {{ canBid ? 'Place your bid' : 'Auction history' }}
                    </h2>

                    <AuctionHistoryFeed :auction-id="auction.id" class="mb-4" />

                    <form
                        v-if="canBid"
                        class="border-t-2 border-sage-border pt-4"
                        @submit.prevent="submitBid"
                    >
                        <div class="mb-4">
                            <label
                                class="mb-2 block text-xs font-bold tracking-widest text-muted-green uppercase"
                            >
                                Bid points
                            </label>
                            <input
                                v-model="form.points"
                                type="number"
                                required
                                min="1"
                                class="w-full rounded-xl border-2 border-sage-border bg-sage-bg px-4 py-3 text-lg font-bold text-ink focus:border-lemon focus:ring-2 focus:ring-lemon/30 disabled:opacity-50"
                                :class="
                                    form.errors.points ? 'border-error' : ''
                                "
                                :disabled="form.processing"
                            />
                            <p
                                v-if="form.errors.points"
                                class="mt-2 text-xs font-bold text-error"
                            >
                                {{ form.errors.points }}
                            </p>
                        </div>

                        <div
                            class="mb-6 rounded-xl border-2 border-sage-border bg-sage-bg/50 p-4"
                        >
                            <div class="mb-2 flex items-center justify-between">
                                <span
                                    class="text-sm font-semibold text-muted-green"
                                    >Your active points</span
                                >
                                <span class="text-sm font-black text-ink">{{
                                    authUserPoints ?? 0
                                }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span
                                    class="text-sm font-semibold text-muted-green"
                                    >Points threshold</span
                                >
                                <span class="text-sm font-black text-ink">{{
                                    auction.opening_points
                                }}</span>
                            </div>
                        </div>

                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="flex w-full items-center justify-center rounded-xl bg-navy py-3 text-sm font-extrabold text-lemon transition-colors hover:bg-forest disabled:opacity-50"
                        >
                            <span
                                v-if="form.processing"
                                class="material-symbols-outlined mr-2 animate-spin"
                            >
                                progress_activity
                            </span>
                            Confirm bid
                        </button>
                    </form>

                    <p
                        v-else
                        class="border-t-2 border-sage-border pt-4 text-center text-sm font-semibold text-muted-green"
                    >
                        This auction has ended. Bidding is no longer available.
                    </p>
                </section>

                <!-- Reviews & Feedback Section -->
                <section
                    class="mt-6 rounded-xl border-2 border-sage-border bg-white p-4 sm:p-6"
                >
                    <h2
                        class="mb-6 flex items-center gap-2 font-headline text-lg font-extrabold text-ink"
                    >
                        <span class="material-symbols-outlined text-forest"
                            >rate_review</span
                        >
                        Feedback & Reviews
                    </h2>

                    <div
                        v-if="userReviewState.canReview"
                        class="mb-8 rounded-xl border border-sage-border bg-sage-bg/25 p-4 sm:p-5"
                    >
                        <h3
                            class="mb-4 text-xs font-black tracking-wider text-ink uppercase"
                        >
                            Write a Review
                        </h3>
                        <form @submit.prevent="submitReview" class="space-y-4">
                            <div>
                                <label
                                    class="mb-1 block text-[10px] font-black tracking-widest text-muted-green uppercase"
                                >
                                    Your Rating
                                </label>
                                <div class="flex items-center gap-1">
                                    <button
                                        v-for="star in 5"
                                        :key="star"
                                        type="button"
                                        class="material-symbols-outlined text-2xl transition-colors focus:outline-none"
                                        :class="
                                            star <= reviewForm.rating
                                                ? 'fill-1 text-lemon'
                                                : 'text-sage-mid'
                                        "
                                        @click="reviewForm.rating = star"
                                    >
                                        star
                                    </button>
                                </div>
                                <p
                                    v-if="reviewForm.errors.rating"
                                    class="mt-1 text-xs font-bold text-error"
                                >
                                    {{ reviewForm.errors.rating }}
                                </p>
                            </div>

                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <label
                                        for="social_platform"
                                        class="mb-1 block text-[10px] font-black tracking-widest text-muted-green uppercase"
                                    >
                                        Social Platform (Optional)
                                    </label>
                                    <select
                                        id="social_platform"
                                        v-model="reviewForm.social_platform"
                                        class="w-full rounded-xl border-2 border-sage-border bg-white px-4 py-3 text-sm font-semibold text-ink focus:border-lemon focus:ring-2 focus:ring-lemon/30"
                                        :disabled="reviewForm.processing"
                                    >
                                        <option value="">
                                            Select Platform
                                        </option>
                                        <option value="Instagram">
                                            Instagram
                                        </option>
                                        <option value="Twitter">Twitter</option>
                                        <option value="TikTok">TikTok</option>
                                        <option value="Facebook">
                                            Facebook
                                        </option>
                                    </select>
                                    <p
                                        v-if="reviewForm.errors.social_platform"
                                        class="mt-1 text-xs font-bold text-error"
                                    >
                                        {{ reviewForm.errors.social_platform }}
                                    </p>
                                </div>
                                <div>
                                    <label
                                        for="social_handle"
                                        class="mb-1 block text-[10px] font-black tracking-widest text-muted-green uppercase"
                                    >
                                        Social Handle (Optional)
                                    </label>
                                    <input
                                        id="social_handle"
                                        v-model="reviewForm.social_handle"
                                        type="text"
                                        placeholder="@username"
                                        class="w-full rounded-xl border-2 border-sage-border bg-white px-4 py-3 text-sm font-semibold text-ink focus:border-lemon focus:ring-2 focus:ring-lemon/30"
                                        :disabled="reviewForm.processing"
                                    />
                                    <p
                                        v-if="reviewForm.errors.social_handle"
                                        class="mt-1 text-xs font-bold text-error"
                                    >
                                        {{ reviewForm.errors.social_handle }}
                                    </p>
                                </div>
                            </div>

                            <div>
                                <label
                                    for="comment"
                                    class="mb-1 block text-[10px] font-black tracking-widest text-muted-green uppercase"
                                >
                                    Comment
                                </label>
                                <textarea
                                    id="comment"
                                    v-model="reviewForm.comment"
                                    rows="4"
                                    placeholder="Write your review here (minimum 5 characters)..."
                                    required
                                    class="w-full rounded-xl border-2 border-sage-border bg-white px-4 py-3 text-sm font-semibold text-ink focus:border-lemon focus:ring-2 focus:ring-lemon/30 disabled:opacity-50"
                                    :class="
                                        reviewForm.errors.comment
                                            ? 'border-error'
                                            : ''
                                    "
                                    :disabled="reviewForm.processing"
                                ></textarea>
                                <p
                                    v-if="reviewForm.errors.comment"
                                    class="mt-1 text-xs font-bold text-error"
                                >
                                    {{ reviewForm.errors.comment }}
                                </p>
                            </div>

                            <div
                                v-if="userReviewState.isWinner"
                                class="space-y-4 border-t border-sage-border pt-4"
                            >
                                <h4
                                    class="flex items-center gap-1.5 text-xs font-black tracking-widest text-forest uppercase"
                                >
                                    <span
                                        class="material-symbols-outlined text-sm"
                                        >local_shipping</span
                                    >
                                    Winner Delivery Proof (Photos & Video)
                                </h4>

                                <div
                                    class="grid grid-cols-1 gap-4 sm:grid-cols-2"
                                >
                                    <div>
                                        <label
                                            class="mb-1.5 block text-[10px] font-black tracking-widest text-muted-green uppercase"
                                        >
                                            Photos (Max 3, JPEG/PNG, max 5MB
                                            each)
                                        </label>
                                        <div
                                            class="relative flex flex-col items-center justify-center rounded-xl border-2 border-dashed border-sage-border bg-white p-4 text-center transition-colors hover:border-lemon"
                                        >
                                            <input
                                                type="file"
                                                multiple
                                                accept="image/jpeg,image/png,image/jpg"
                                                class="absolute inset-0 cursor-pointer opacity-0"
                                                :disabled="
                                                    reviewForm.photos.length >=
                                                        3 ||
                                                    reviewForm.processing
                                                "
                                                @change="handlePhotosChange"
                                            />
                                            <span
                                                class="material-symbols-outlined mb-1 text-2xl text-muted-green"
                                                >add_a_photo</span
                                            >
                                            <span
                                                class="text-xs font-extrabold text-ink"
                                                >Upload photos</span
                                            >
                                            <span
                                                class="mt-0.5 text-[10px] text-muted-green"
                                                >{{
                                                    3 - reviewForm.photos.length
                                                }}
                                                slots remaining</span
                                            >
                                        </div>

                                        <div
                                            v-if="photoPreviews.length > 0"
                                            class="mt-3 flex flex-wrap gap-2"
                                        >
                                            <div
                                                v-for="(
                                                    src, idx
                                                ) in photoPreviews"
                                                :key="idx"
                                                class="relative h-14 w-14 overflow-hidden rounded-lg border border-sage-border"
                                            >
                                                <img
                                                    :src="src"
                                                    class="h-full w-full object-cover"
                                                />
                                                <button
                                                    type="button"
                                                    class="absolute top-0.5 right-0.5 flex h-4 w-4 items-center justify-center rounded-full bg-black/75 text-white hover:bg-black"
                                                    @click="removePhoto(idx)"
                                                >
                                                    <span
                                                        class="material-symbols-outlined text-[10px]"
                                                        >close</span
                                                    >
                                                </button>
                                            </div>
                                        </div>
                                        <p
                                            v-if="reviewForm.errors.photos"
                                            class="mt-1 text-xs font-bold text-error"
                                        >
                                            {{ reviewForm.errors.photos }}
                                        </p>
                                    </div>

                                    <div>
                                        <label
                                            class="mb-1.5 block text-[10px] font-black tracking-widest text-muted-green uppercase"
                                        >
                                            Video Proof (Max 1, MP4/MOV, max
                                            25MB)
                                        </label>
                                        <div
                                            v-if="!videoPreview"
                                            class="relative flex flex-col items-center justify-center rounded-xl border-2 border-dashed border-sage-border bg-white p-4 text-center transition-colors hover:border-lemon"
                                        >
                                            <input
                                                type="file"
                                                accept="video/mp4,video/quicktime"
                                                class="absolute inset-0 cursor-pointer opacity-0"
                                                :disabled="
                                                    reviewForm.processing
                                                "
                                                @change="handleVideoChange"
                                            />
                                            <span
                                                class="material-symbols-outlined mb-1 text-2xl text-muted-green"
                                                >video_call</span
                                            >
                                            <span
                                                class="text-xs font-extrabold text-ink"
                                                >Upload video</span
                                            >
                                            <span
                                                class="mt-0.5 text-[10px] text-muted-green"
                                                >MP4 or MOV format</span
                                            >
                                        </div>

                                        <div
                                            v-else
                                            class="relative mt-3 flex aspect-video max-h-24 items-center justify-center overflow-hidden rounded-lg border border-sage-border bg-black"
                                        >
                                            <video
                                                :src="videoPreview"
                                                controls
                                                class="max-h-full max-w-full"
                                            ></video>
                                            <button
                                                type="button"
                                                class="absolute top-0.5 right-0.5 z-10 flex h-4 w-4 items-center justify-center rounded-full bg-black/75 text-white hover:bg-black"
                                                @click="removeVideo"
                                            >
                                                <span
                                                    class="material-symbols-outlined text-[10px]"
                                                    >close</span
                                                >
                                            </button>
                                        </div>
                                        <p
                                            v-if="reviewForm.errors.video"
                                            class="mt-1 text-xs font-bold text-error"
                                        >
                                            {{ reviewForm.errors.video }}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div
                                class="flex justify-end border-t border-sage-border pt-2"
                            >
                                <button
                                    type="submit"
                                    :disabled="reviewForm.processing"
                                    class="flex items-center justify-center rounded-xl bg-navy px-6 py-2.5 text-xs font-extrabold text-lemon transition-colors hover:bg-forest disabled:opacity-50"
                                >
                                    <span
                                        v-if="reviewForm.processing"
                                        class="material-symbols-outlined mr-2 animate-spin text-sm"
                                    >
                                        progress_activity
                                    </span>
                                    Submit Review
                                </button>
                            </div>
                        </form>
                    </div>

                    <div v-if="reviews.length > 0" class="space-y-6">
                        <div
                            v-for="review in reviews"
                            :key="review.id"
                            class="border-b border-sage-border pb-6 last:border-0 last:pb-0"
                        >
                            <div class="flex items-start justify-between">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <p class="font-extrabold text-ink">
                                            {{ review.user_name }}
                                        </p>
                                        <span
                                            v-if="review.social_handle"
                                            class="inline-flex items-center gap-1 rounded bg-sage-bg px-2 py-0.5 text-[10px] font-bold text-forest"
                                        >
                                            {{ review.social_platform }}:
                                            {{ review.social_handle }}
                                        </span>
                                    </div>
                                    <div class="mt-1 flex items-center gap-0.5">
                                        <span
                                            v-for="star in 5"
                                            :key="star"
                                            class="material-symbols-outlined text-sm"
                                            :class="
                                                star <=
                                                Math.round(review.rating)
                                                    ? 'fill-1 text-lemon'
                                                    : 'text-sage-mid'
                                            "
                                        >
                                            star
                                        </span>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2.5">
                                    <span
                                        class="text-xs font-bold text-muted-green"
                                    >
                                        {{
                                            new Date(
                                                review.created_at,
                                            ).toLocaleDateString(undefined, {
                                                year: 'numeric',
                                                month: 'short',
                                                day: 'numeric',
                                            })
                                        }}
                                    </span>
                                    <button
                                        type="button"
                                        class="flex h-7 w-7 cursor-pointer items-center justify-center rounded-full bg-surface-container text-on-surface-variant transition-colors hover:bg-surface-container-high"
                                        title="Share review"
                                        @click="
                                            openReviewShareModal(review.comment)
                                        "
                                    >
                                        <span
                                            class="pi pi-share-alt text-[14px]"
                                        ></span>
                                    </button>
                                </div>
                            </div>

                            <p
                                class="mt-3 font-sans text-sm leading-relaxed font-semibold whitespace-pre-line text-ink"
                            >
                                {{ review.comment }}
                            </p>

                            <div
                                v-if="review.photos?.length || review.video"
                                class="mt-4 flex flex-wrap gap-3"
                            >
                                <div
                                    v-for="(photo, pIdx) in review.photos"
                                    :key="pIdx"
                                    class="relative h-20 w-20 cursor-pointer overflow-hidden rounded-xl border-2 border-sage-border bg-sage-bg transition-transform hover:scale-105"
                                    @click="expandedImage = photo"
                                >
                                    <img
                                        :src="photo"
                                        class="h-full w-full object-cover"
                                    />
                                </div>

                                <div
                                    v-if="review.video"
                                    class="relative flex aspect-video h-20 w-36 items-center justify-center overflow-hidden rounded-xl border-2 border-sage-border bg-black"
                                >
                                    <video
                                        :src="review.video"
                                        controls
                                        class="h-full w-full object-cover"
                                    ></video>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-else class="py-12 text-center">
                        <span
                            class="material-symbols-outlined mb-2 text-4xl text-sage-mid"
                            >reviews</span
                        >
                        <p class="font-extrabold text-ink">No reviews yet</p>
                        <p class="mt-1 text-xs text-muted-green">
                            {{
                                userReviewState.canReview
                                    ? 'Be the first to share your experience with this item!'
                                    : 'Reviews will appear here once participants submit their feedback.'
                            }}
                        </p>
                    </div>
                </section>
            </div>

            <div class="lg:col-span-1">
                <AuctionLeaderboardSidebar :bidders="topBidders" />
            </div>
        </div>
    </div>

    <Teleport to="body">
        <div
            v-if="expandedImage"
            class="fixed inset-0 z-100 flex cursor-pointer items-center justify-center bg-black/90 p-4 backdrop-blur-sm"
            @click="expandedImage = null"
        >
            <img
                :src="expandedImage"
                class="max-h-full max-w-full rounded-2xl object-contain shadow-2xl"
                alt="Expanded auction image"
            />
        </div>
    </Teleport>

    <!-- Share Modal -->
    <ShareModal
        :is-open="isShareOpen"
        :url="shareUrl"
        :name="auction.name"
        :message="shareMessageOverride"
        @close="isShareOpen = false"
    />
</template>
