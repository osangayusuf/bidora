<script setup lang="ts">
import { ArrowUpRight, Youtube } from 'lucide-vue-next';

withDefaults(
    defineProps<{
        heading?: string;
        subheading?: string;
        /** Tightens vertical padding for denser pages like Open Bids. */
        compact?: boolean;
    }>(),
    {
        heading: 'Watch Real Winner Testimonials',
        subheading:
            'Hear it straight from verified Bidora winners — real people, real wins, real reactions.',
        compact: false,
    },
);

const CHANNEL_URL = 'https://www.youtube.com/@Bidora_ng';
</script>

<template>
    <!--
        Deliberately no outer max-width/padding container here — this banner
        is dropped inside pages/sections that already constrain their own
        width (Home, Winners, Open Bids), so it should just fill whatever
        space its parent gives it.
    -->
    <a
        :href="CHANNEL_URL"
        target="_blank"
        rel="noopener noreferrer"
        aria-label="Watch testimonial videos on the Bidora YouTube channel (opens in a new tab)"
        class="group relative flex flex-col items-center gap-6 overflow-hidden rounded-2xl border-2 border-lemon bg-navy px-6 text-center shadow-[0_12px_40px_rgba(13,27,42,0.45)] transition-all hover:-translate-y-0.5 hover:shadow-[0_16px_48px_rgba(13,27,42,0.55)] md:flex-row md:justify-between md:text-left"
        :class="compact ? 'py-6' : 'py-8 md:py-10'"
    >
        <!-- Decorative glow -->
        <div
            class="pointer-events-none absolute -top-10 -right-10 h-40 w-40 rounded-full bg-[#FF0000]/20 blur-3xl"
        ></div>

        <div class="relative flex flex-col items-center gap-4 md:flex-row">
            <div
                class="flex h-14 w-14 shrink-0 items-center justify-center rounded-full bg-[#FF0000] shadow-lg transition-transform duration-300 group-hover:scale-110"
            >
                <Youtube class="h-7 w-7 text-white"  />
            </div>
            <div>
                <span
                    class="text-xs font-bold tracking-widest text-lemon uppercase"
                    >Straight From Our Community</span
                >
                <h3
                    class="mt-1 font-condensed text-2xl font-extrabold tracking-tight text-white md:text-3xl"
                >
                    {{ heading }}
                </h3>
                <p class="mt-1 max-w-md text-sm font-medium text-white/70">
                    {{ subheading }}
                </p>
            </div>
        </div>

        <span
            class="relative inline-flex shrink-0 items-center gap-2 rounded-full bg-lemon px-6 py-3 text-sm font-extrabold whitespace-nowrap text-navy transition-colors duration-300 group-hover:bg-white"
        >
            Watch on YouTube
            <ArrowUpRight
                class="h-4 w-4 transition-transform duration-300 group-hover:translate-x-0.5 group-hover:-translate-y-0.5"
            />
        </span>
    </a>
</template>
