<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { useIntervalFn } from '@vueuse/core';
import { computed, ref } from 'vue';
import { howToPlay, trending } from '@/routes/index';

const page = usePage();

defineProps<{ getCategoryIcon: (category: string) => string }>();

const categories = computed(() => (page.props.categories as string[]) ?? []);

const bannerImages = [
    '/images/banner1.png',
    '/images/banner3.jpeg',
];
const currentBannerIndex = ref(0);

const { pause } = useIntervalFn(() => {
    currentBannerIndex.value =
        (currentBannerIndex.value + 1) % bannerImages.length;
}, 3000);

const stopAutoSlide = () => pause();

const goToSlide = (index: number) => {
    stopAutoSlide();
    currentBannerIndex.value = index;
};

const nextSlide = () => {
    stopAutoSlide();
    currentBannerIndex.value =
        (currentBannerIndex.value + 1) % bannerImages.length;
};

const prevSlide = () => {
    stopAutoSlide();
    currentBannerIndex.value =
        (currentBannerIndex.value - 1 + bannerImages.length) %
        bannerImages.length;
};

const scrollToHowToPlay = (e: MouseEvent) => {
    const el = document.getElementById('how-to-play-section');
    if (el) {
        e.preventDefault();
        el.scrollIntoView({ behavior: 'smooth' });
    }
};
</script>

<template>
    <!-- HERO -->
    <section
        class="mx-auto mt-2.5 max-w-7xl items-stretch gap-2.5 px-4 md:grid"
        style="grid-template-columns: 1fr 4fr"
    >
        <!-- LEFT CATEGORIES NAV -->
        <div
            class="hidden flex-col overflow-hidden rounded-xl border border-outline-variant bg-white md:flex"
        >
            <Link
                v-for="cat in categories"
                :key="cat"
                :href="trending.url() + '?category=' + encodeURIComponent(cat)"
                class="flex flex-1 items-center gap-2.5 border-b border-sage-tint px-3.5 text-sm font-bold whitespace-nowrap text-ink no-underline transition-all duration-150 last:border-b-0 hover:bg-sage-tint hover:pl-4.5 hover:text-primary"
            >
                <span
                    v-if="getCategoryIcon(cat)"
                    class="material-symbols-outlined mr-1 text-sm"
                >
                    {{ getCategoryIcon(cat) }}
                </span>
                {{ cat }} <i class="pi pi-chevron-right ml-auto text-xs"></i>
            </Link>
        </div>

        <!-- MAIN BANNER -->
        <div
            class="relative flex min-h-84 items-stretch overflow-hidden rounded-xl bg-forest-dark"
        >
            <div class="z-2 flex grow flex-col justify-center px-8 py-7.5">
                <div
                    class="mb-3.5 inline-flex items-center gap-1 self-start rounded-full bg-lemon px-3 py-1 text-xs font-extrabold tracking-wider text-navy uppercase"
                >
                    🇳🇬 Nigeria's #1 Auction Platform
                </div>
                <h1
                    class="mb-2.5 font-condensed text-5xl leading-none font-black text-white"
                >
                    Bid Small.<span class="block text-lemon">Win Big.</span>
                </h1>
                <p class="mb-5.5 text-sm leading-relaxed text-white/65">
                    Your points give you a chance to win real products.
                </p>
                <div class="mb-6 flex gap-2.5">
                    <Link
                        :href="trending.url()"
                        class="cursor-pointer rounded-lg border-none bg-lemon px-6 py-2.5 text-sm font-extrabold whitespace-nowrap text-navy"
                    >
                        Start Bidding
                    </Link>
                    <Link
                        :href="howToPlay.url()"
                        class="cursor-pointer rounded-lg border-2 border-lemon bg-transparent px-5 py-2 text-sm font-bold whitespace-nowrap text-lemon transition-colors hover:bg-lemon/10"
                        @click="scrollToHowToPlay"
                    >
                        How It Works
                    </Link>
                </div>
                <div class="flex gap-7">
                    <div>
                        <span
                            class="block text-2xl leading-none font-black text-lemon"
                            >50K+</span
                        >
                        <span class="mt-0.5 block text-xs text-white/55"
                            >Happy Winners</span
                        >
                    </div>
                    <div>
                        <span
                            class="block text-2xl leading-none font-black text-lemon"
                            >₦0</span
                        >
                        <span class="mt-0.5 block text-xs text-white/55"
                            >Lost Bid Cost</span
                        >
                    </div>
                    <div>
                        <span
                            class="block text-2xl leading-none font-black text-lemon"
                            >100%</span
                        >
                        <span class="mt-0.5 block text-xs text-white/55"
                            >Transparent</span
                        >
                    </div>
                </div>
            </div>

            <div
                class="hero-banner-right-bg group relative hidden w-1/2 shrink-0 overflow-hidden select-none md:flex"
            >
                <div
                    id="heroCarousel"
                    class="relative flex h-full w-full items-center justify-center overflow-hidden"
                >
                    <div
                        class="flex h-full w-full items-center transition-transform duration-500 ease-in-out"
                        :style="{
                            transform: `translateX(-${currentBannerIndex * 100}%)`,
                        }"
                    >
                        <div
                            v-for="(img, index) in bannerImages"
                            :key="index"
                            class="flex min-w-full flex-col items-center justify-center"
                        >
                            <img
                                :src="`${$page.props.asset_url}${img}`"
                                alt="Banner"
                                class="h-auto w-full animate-float-img rounded-md object-contain px-7"
                            />
                        </div>
                    </div>

                    <button
                        class="absolute top-1/2 left-2 z-10 flex h-8 w-8 -translate-y-1/2 cursor-pointer items-center justify-center rounded-full border-none bg-black/30 text-white opacity-0 transition-colors group-hover:opacity-100 hover:bg-black/50"
                        @click="prevSlide"
                    >
                        <i class="pi pi-chevron-left text-sm"></i>
                    </button>
                    <button
                        class="absolute top-1/2 right-2 z-10 flex h-8 w-8 -translate-y-1/2 cursor-pointer items-center justify-center rounded-full border-none bg-black/30 text-white opacity-0 transition-colors group-hover:opacity-100 hover:bg-black/50"
                        @click="nextSlide"
                    >
                        <i class="pi pi-chevron-right text-sm"></i>
                    </button>

                    <div
                        class="absolute bottom-4 left-1/2 z-10 flex -translate-x-1/2 items-center gap-2 drop-shadow-md"
                    >
                        <button
                            v-for="(_, index) in bannerImages"
                            :key="`dot-${index}`"
                            class="cursor-pointer rounded-full border-none p-0 transition-all duration-300"
                            :class="
                                currentBannerIndex === index
                                    ? 'h-2 w-6 bg-lemon'
                                    : 'h-2 w-2 bg-white/50 hover:bg-white/80'
                            "
                            @click="goToSlide(index)"
                        ></button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Mobile banner carousel -->
        <div
            class="group relative my-5 hidden w-full overflow-hidden select-none max-md:flex"
        >
            <div
                id="heroCarouselMobile"
                class="relative flex h-full w-full items-center justify-center overflow-hidden"
            >
                <div
                    class="flex h-full w-full items-center transition-transform duration-500 ease-in-out"
                    :style="{
                        transform: `translateX(-${currentBannerIndex * 100}%)`,
                    }"
                >
                    <div
                        v-for="(img, index) in bannerImages"
                        :key="index"
                        class="flex min-w-full flex-col items-center justify-center"
                    >
                        <img
                            :src="`${$page.props.asset_url}${img}`"
                            alt="Banner"
                            class="h-auto w-full animate-float-img rounded-md object-contain px-7"
                        />
                    </div>
                </div>

                <button
                    class="absolute top-1/2 left-2 z-10 flex h-8 w-8 -translate-y-1/2 cursor-pointer items-center justify-center rounded-full border-none bg-black/30 text-white transition-colors hover:bg-black/50"
                    @click="prevSlide"
                >
                    <i class="pi pi-chevron-left text-sm"></i>
                </button>
                <button
                    class="absolute top-1/2 right-2 z-10 flex h-8 w-8 -translate-y-1/2 cursor-pointer items-center justify-center rounded-full border-none bg-black/30 text-white transition-colors hover:bg-black/50"
                    @click="nextSlide"
                >
                    <i class="pi pi-chevron-right text-sm"></i>
                </button>

                <div
                    class="absolute bottom-0 left-1/2 z-10 flex -translate-x-1/2 items-center gap-2"
                >
                    <button
                        v-for="(_, index) in bannerImages"
                        :key="`dot-mobile-${index}`"
                        class="cursor-pointer rounded-full border-none p-0 transition-all duration-300"
                        :class="
                            currentBannerIndex === index
                                ? 'h-2 w-6 bg-ink'
                                : 'h-2 w-2 bg-ink/30 hover:bg-ink/50'
                        "
                        @click="goToSlide(index)"
                    ></button>
                </div>
            </div>
        </div>
    </section>
</template>
