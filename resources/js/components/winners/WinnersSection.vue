<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppPaginator from '@/components/layout/AppPaginator.vue';
import YoutubeTestimonialsCta from '@/components/shared/YoutubeTestimonialsCta.vue';
import { getDaysAgo, maskedMsisdnParts } from '@/lib/utils';
import { show as auctionShow } from '@/routes/auctions';
import { winners as winnersRoute } from '@/routes/index';
import type { LengthAwarePaginator, WinnerListing } from '@/types/auction';

const expandedImage = ref<string | null>(null);
const expandedVideo = ref<string | null>(null);

const props = defineProps<{
    winners: LengthAwarePaginator<WinnerListing>;
    search?: string | null;
}>();

const activeFilters = computed(() => {
    if (!props.search?.trim()) {
        return [];
    }

    return [{ key: 'search', label: `Search: ${props.search.trim()}` }];
});

function visit(extra: Record<string, string | number> = {}): void {
    const query: Record<string, string | number> = {
        ...(props.search?.trim() ? { search: props.search.trim() } : {}),
        ...extra,
    };

    router.get(
        winnersRoute.url({ query }),
        {},
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function removeFilter(key: string): void {
    if (key === 'search') {
        router.get(
            winnersRoute.url({ query: { page: 1 } }),
            {},
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }
}

function goToPage(page: number): void {
    visit({ page });
}

function winnerMsisdn(msisdn: string): { prefix: string; suffix: string } {
    return maskedMsisdnParts(msisdn);
}
</script>

<template>
    <section class="mx-auto max-w-[1300px] px-4 pt-8 pb-20">
        <header class="mb-10">
            <div class="space-y-2">
                <span
                    class="text-xs font-bold tracking-widest text-muted-green uppercase"
                    >Success Stories</span
                >
                <h1
                    class="font-condensed text-4xl font-extrabold tracking-tight text-ink md:text-5xl"
                >
                    Winners
                </h1>
                <p class="text-sm text-muted-green">
                    Verified winners from completed auctions on Bidora.
                </p>
            </div>

            <div
                v-if="activeFilters.length > 0 || winners.total > 0"
                class="mt-5 flex flex-wrap items-center gap-3"
            >
                <div
                    v-for="filter in activeFilters"
                    :key="filter.key"
                    class="flex items-center gap-2 rounded-full border-2 border-sage-border bg-sage-bg px-4 py-2 text-xs font-bold text-ink"
                >
                    <span>{{ filter.label }}</span>
                    <button
                        type="button"
                        class="material-symbols-outlined cursor-pointer text-sm text-muted-green hover:text-navy"
                        @click="removeFilter(filter.key)"
                    >
                        close
                    </button>
                </div>

                <span class="ml-auto text-xs font-bold text-muted-green">
                    {{ winners.total }} winner{{
                        winners.total !== 1 ? 's' : ''
                    }}
                </span>
            </div>
        </header>
        <YoutubeTestimonialsCta
            heading="See Our Winners Celebrate"
            subheading="Watch real winners react to landing their prize — new videos posted every week."
            compact
            class="mb-10"
        />

        <div
            v-if="winners.data.length === 0"
            class="flex flex-col items-center justify-center py-24 text-center"
        >
            <span
                class="material-symbols-outlined mb-4 text-5xl text-muted-green"
                >emoji_events</span
            >
            <p class="font-condensed text-xl font-extrabold text-ink">
                No winners yet
            </p>
            <p class="mt-2 text-sm text-muted-green">
                Check back after the next auctions close.
            </p>
        </div>

        <div
            v-else
            class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
        >
            <article
                v-for="winner in winners.data"
                :key="winner.id"
                class="flex flex-col overflow-hidden rounded-xl border-2 border-sage-border bg-white shadow-sm transition-all hover:-translate-y-0.5 hover:border-lemon"
            >
                <div class="relative h-44 bg-sage-bg p-3">
                    <img
                        v-if="winner.bid?.image"
                        :src="winner.bid.image"
                        :alt="winner.bid.name"
                        class="h-full w-full rounded-lg object-cover"
                    />
                    <div
                        v-else
                        class="flex h-full w-full items-center justify-center rounded-lg bg-sage-bg text-muted-green"
                    >
                        <span class="pi pi-image text-4xl"></span>
                    </div>
                    <span
                        class="absolute top-5 left-5 rounded-md bg-lemon px-2 py-1 text-[10px] font-black tracking-wider text-navy uppercase shadow"
                    >
                        Won Bid
                    </span>
                </div>

                <div class="flex flex-1 flex-col p-4">
                    <h2 class="mb-1 line-clamp-2 font-extrabold text-ink">
                        {{ winner.bid?.name ?? 'Luxury Item' }}
                    </h2>
                    <p class="mb-3 text-sm font-bold text-forest">
                        {{ winner.winner_name }}
                    </p>
                    <p
                        v-if="winnerMsisdn(winner.msisdn).suffix"
                        class="mb-4 text-xs font-bold text-ink"
                    >
                        {{ winnerMsisdn(winner.msisdn).prefix }}
                        <span class="text-muted-green">***</span>
                        {{ winnerMsisdn(winner.msisdn).suffix }}
                    </p>

                    <div
                        class="mb-4 grid grid-cols-3 gap-2 rounded-lg border border-sage-border bg-sage-bg p-3 text-center"
                    >
                        <div>
                            <p
                                class="text-[10px] font-bold tracking-wide text-muted-green uppercase"
                            >
                                Winning
                            </p>
                            <p class="text-sm font-black text-ink">
                                {{ winner.winning_pts.toLocaleString() }} pts
                            </p>
                        </div>
                        <div>
                            <p
                                class="text-[10px] font-bold tracking-wide text-muted-green uppercase"
                            >
                                Total Bid
                            </p>
                            <p class="text-sm font-black text-ink">
                                {{ winner.total_pts_bid.toLocaleString() }} pts
                            </p>
                        </div>
                        <div>
                            <p
                                class="text-[10px] font-bold tracking-wide text-muted-green uppercase"
                            >
                                Bids
                            </p>
                            <p class="text-sm font-black text-ink">
                                {{ winner.bid_count }}
                            </p>
                        </div>
                    </div>

                    <div
                        v-if="winner.review"
                        class="mb-4 border-t border-sage-border pt-3"
                    >
                        <div
                            class="mb-1.5 flex items-center justify-between gap-1"
                        >
                            <div class="flex items-center gap-0.5">
                                <span
                                    v-for="star in 5"
                                    :key="star"
                                    class="material-symbols-outlined text-[14px]"
                                    :class="
                                        star <= Math.round(winner.review.rating)
                                            ? 'fill-1 text-lemon'
                                            : 'text-sage-mid'
                                    "
                                >
                                    star
                                </span>
                            </div>
                            <span
                                v-if="winner.review.social_handle"
                                class="rounded bg-sage-bg px-1.5 py-0.5 text-[9px] font-bold text-forest"
                            >
                                {{ winner.review.social_handle }}
                            </span>
                        </div>

                        <p
                            class="line-clamp-2 text-[11px] leading-normal font-semibold text-ink italic"
                        >
                            "{{ winner.review.comment }}"
                        </p>

                        <div
                            v-if="
                                winner.review.photos?.length ||
                                winner.review.video
                            "
                            class="mt-2 flex items-center gap-1.5"
                        >
                            <div
                                v-for="(photo, idx) in winner.review.photos"
                                :key="idx"
                                class="h-8 w-8 flex-shrink-0 cursor-pointer overflow-hidden rounded border border-sage-border bg-sage-bg"
                                @click.stop="expandedImage = photo"
                            >
                                <img
                                    :src="photo"
                                    class="h-full w-full object-cover"
                                />
                            </div>
                            <div
                                v-if="winner.review.video"
                                class="relative flex h-8 w-12 flex-shrink-0 cursor-pointer items-center justify-center overflow-hidden rounded border border-sage-border bg-black"
                                @click.stop="
                                    expandedVideo = winner.review.video
                                "
                            >
                                <span
                                    class="material-symbols-outlined absolute z-10 text-[12px] text-white"
                                    >play_arrow</span
                                >
                                <video
                                    :src="winner.review.video"
                                    class="h-full w-full object-cover opacity-60"
                                ></video>
                            </div>
                        </div>
                    </div>

                    <div
                        class="mt-auto flex items-center justify-between gap-2"
                    >
                        <span class="text-xs font-bold text-muted-green">{{
                            getDaysAgo(winner.created_at)
                        }}</span>
                        <Link
                            v-if="winner.bid"
                            :href="auctionShow.url(winner.bid)"
                            class="rounded-md bg-navy px-3 py-1.5 text-xs font-extrabold text-lemon transition-colors hover:bg-forest"
                        >
                            View Auction
                        </Link>
                    </div>
                </div>
            </article>
        </div>

        <AppPaginator
            :current-page="winners.current_page"
            :last-page="winners.last_page"
            @page-change="goToPage"
        />
    </section>

    <Teleport to="body">
        <div
            v-if="expandedImage"
            class="fixed inset-0 z-100 flex cursor-pointer items-center justify-center bg-black/90 p-4 backdrop-blur-sm"
            @click="expandedImage = null"
        >
            <img
                :src="expandedImage"
                class="max-h-full max-w-full rounded-2xl object-contain shadow-2xl"
                alt="Expanded review image"
            />
        </div>

        <div
            v-if="expandedVideo"
            class="fixed inset-0 z-100 flex items-center justify-center bg-black/90 p-4 backdrop-blur-sm"
            @click="expandedVideo = null"
        >
            <div
                class="relative mx-4 w-full max-w-3xl overflow-hidden rounded-2xl bg-black shadow-2xl"
                @click.stop
            >
                <video
                    :src="expandedVideo"
                    controls
                    autoplay
                    class="aspect-video w-full"
                ></video>
                <button
                    type="button"
                    class="absolute top-3 right-3 flex h-8 w-8 items-center justify-center rounded-full bg-black/75 text-white hover:bg-black"
                    @click="expandedVideo = null"
                >
                    <span class="material-symbols-outlined text-sm">close</span>
                </button>
            </div>
        </div>
    </Teleport>
</template>
