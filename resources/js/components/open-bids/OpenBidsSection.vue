<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import BidCard from '@/components/cards/BidCard.vue';
import AppPaginator from '@/components/layout/AppPaginator.vue';
import YoutubeTestimonialsCta from '@/components/shared/YoutubeTestimonialsCta.vue';
import { openBids } from '@/routes/index';
import type { Bid, LengthAwarePaginator } from '@/types/auction';

const props = defineProps<{
    bids: LengthAwarePaginator<Bid>;
    userPoints: number | null;
    search?: string | null;
    sort?: string | null;
}>();

const sort = ref(props.sort ?? 'ending_soon');

const sortOptions: { value: string; label: string }[] = [
    { value: 'ending_soon', label: 'Ending Soonest' },
    { value: 'recent', label: 'Most Recent Activity' },
    { value: 'popular', label: 'Most Popular' },
    { value: 'price', label: 'Value: High to Low' },
];

const sortLabel = computed(
    () =>
        sortOptions.find((option) => option.value === sort.value)?.label ??
        'Sort By',
);

const activeFilters = computed(() => {
    const chips: { key: string; label: string }[] = [];

    if (props.search?.trim()) {
        chips.push({ key: 'search', label: `Search: ${props.search.trim()}` });
    }

    return chips;
});

function visit(extra: Record<string, string | number> = {}): void {
    const query: Record<string, string | number> = {
        ...(props.search?.trim() ? { search: props.search.trim() } : {}),
        ...(sort.value !== 'ending_soon' ? { sort: sort.value } : {}),
        ...extra,
    };

    router.get(
        openBids.url({ query }),
        {},
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function onSortChange(): void {
    visit({ page: 1 });
}

function removeFilter(key: string): void {
    if (key === 'search') {
        router.get(
            openBids.url({
                query: {
                    ...(sort.value !== 'ending_soon'
                        ? { sort: sort.value }
                        : {}),
                    page: 1,
                },
            }),
            {},
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }
}

function goToPage(page: number): void {
    visit({ page });
}
</script>

<template>
    <section class="mx-auto max-w-[1300px] px-4 pt-8 pb-20">
        <header class="mb-10">
            <div
                class="flex flex-col justify-between gap-6 md:flex-row md:items-end"
            >
                <div class="space-y-2">
                    <span
                        class="text-xs font-bold tracking-widest text-muted-green uppercase"
                        >Active Bids</span
                    >
                    <h1
                        class="font-condensed text-4xl font-extrabold tracking-tight text-ink md:text-5xl"
                    >
                        Open Bids
                    </h1>
                </div>

                <div class="relative min-w-[220px]">
                    <div
                        class="flex cursor-pointer items-center justify-between rounded-xl border-2 border-sage-border bg-white px-5 py-3 transition-colors hover:border-lemon"
                    >
                        <span class="mr-4 text-sm font-bold text-ink">{{
                            sortLabel
                        }}</span>
                        <span
                            class="material-symbols-outlined text-sm text-muted-green"
                            >expand_more</span
                        >
                    </div>
                    <select
                        v-model="sort"
                        class="absolute inset-0 w-full cursor-pointer appearance-none opacity-0"
                        @change="onSortChange"
                    >
                        <option
                            v-for="option in sortOptions"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </select>
                </div>
            </div>

            <div
                v-if="activeFilters.length > 0 || bids.total > 0"
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
                    {{ bids.total }} result{{ bids.total !== 1 ? 's' : '' }}
                </span>
            </div>
        </header>
        
        <YoutubeTestimonialsCta
    	   heading="Not Sure Yet? Watch Winners Share Their Story"
    	   subheading="See real testimonials from verified winners before you place your next bid."
    	   compact
    	   class="mb-10"
	/>

        <div
            v-if="bids.data.length === 0"
            class="flex flex-col items-center justify-center py-24 text-center"
        >
            <span
                class="material-symbols-outlined mb-4 text-5xl text-muted-green"
                >timer_off</span
            >
            <p class="font-condensed text-xl font-extrabold text-ink">
                No auctions are currently in countdown phase.
            </p>
            <p class="mt-2 text-sm text-muted-green">
                Check back soon or browse trending items.
            </p>
        </div>

        <div
            v-else
            class="grid grid-cols-2 gap-3 md:grid-cols-3 md:gap-4 lg:grid-cols-4 xl:grid-cols-5"
        >
            <BidCard
                v-for="bid in bids.data"
                :key="bid.id"
                :bid="bid"
                :user-points="props.userPoints"
            />
        </div>

        <AppPaginator
            :current-page="bids.current_page"
            :last-page="bids.last_page"
            @page-change="goToPage"
        />
    </section>
</template>
