<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { useAutoDismiss } from '@/composables/useAutoDismiss';
import type { Winner } from '@/pages/Home/Index.vue';

const SESSION_KEY = 'winner_popup_dismissed';

const props = defineProps<{
    winner: Winner;
}>();

const isVisible = ref(false);
const { start, clear } = useAutoDismiss(isVisible);

onMounted(() => {
    if (!sessionStorage.getItem(SESSION_KEY)) {
        isVisible.value = true;
        start();
    }
});

function dismiss(): void {
    clear();
    sessionStorage.setItem(SESSION_KEY, '1');
    isVisible.value = false;
}

function maskedPhone(msisdn: string): string {
    const digits = msisdn.replace(/\D/g, '');
    const prefix = digits.slice(0, 3);
    const suffix = digits.slice(-3);
    const masked = '*'.repeat(Math.max(0, digits.length - 6));

    return `${prefix}${masked}${suffix}`;
}
</script>

<template>
    <Transition name="popup">
        <div
            v-if="isVisible"
            class="fixed inset-0 z-50 flex items-center justify-center p-4"
            role="dialog"
            aria-modal="true"
            aria-labelledby="winner-popup-title"
            @click.self="dismiss"
        >
            <div
                class="absolute inset-0 bg-black/75 backdrop-blur-sm"
                @click="dismiss"
            />

            <div
                class="relative z-10 flex w-full max-w-2xl overflow-hidden rounded-3xl bg-surface-container-lowest shadow-2xl"
            >
                <button
                    type="button"
                    class="absolute top-4 right-4 z-20 flex h-8 w-8 items-center justify-center rounded-full bg-black/20 text-white/80 transition-colors hover:bg-black/40 hover:text-white"
                    aria-label="Close"
                    @click="dismiss"
                >
                    <span class="material-symbols-outlined text-xl leading-none"
                        >close</span
                    >
                </button>

                <div
                    class="relative flex w-2/5 shrink-0 items-center justify-center bg-surface-container p-8 pt-10"
                >
                    <div
                        class="absolute inset-x-0 top-0 h-1.5 bg-linear-to-r from-[#ca8a04] via-[#facc15] to-[#ca8a04]"
                    />
                    <div
                        class="rounded-2xl border-4 border-[#facc15] bg-surface-container-high p-4 shadow-lg shadow-[#facc15]/20"
                    >
                        <img
                            v-if="props.winner.bid?.image"
                            :src="props.winner.bid.image"
                            :alt="props.winner.bid?.name ?? 'Prize'"
                            class="h-56 w-full object-contain"
                        />
                        <div
                            v-else
                            class="flex h-56 w-full items-center justify-center"
                        >
                            <span
                                class="material-symbols-outlined text-6xl text-secondary"
                                >emoji_events</span
                            >
                        </div>
                    </div>
                </div>

                <div
                    class="flex min-h-[420px] flex-1 flex-col justify-center px-10 py-12"
                >
                    <div
                        class="mb-5 flex h-14 w-14 items-center justify-center rounded-full bg-[#facc15]/15"
                    >
                        <span
                            class="material-symbols-outlined text-3xl text-[#facc15]"
                            data-weight="fill"
                            >emoji_events</span
                        >
                    </div>
                    <h2
                        id="winner-popup-title"
                        class="mb-3 font-headline text-4xl font-extrabold tracking-tight text-[#facc15] drop-shadow-[0_2px_8px_rgba(250,204,21,0.4)]"
                    >
                        Congratulations!
                    </h2>
                    <p
                        class="mb-6 text-sm leading-relaxed font-medium text-on-surface/70"
                    >
                        We have a winner for
                        <span class="font-bold text-on-surface">{{
                            props.winner.bid?.name ?? 'this item'
                        }}</span>
                    </p>
                    <div class="mb-6 h-px w-full bg-surface-container" />
                    <p
                        class="mb-1 font-headline text-2xl font-extrabold tracking-wide text-on-surface"
                    >
                        {{ maskedPhone(props.winner.msisdn) }}
                    </p>
                    <p class="text-base font-bold text-[#facc15]">
                        Won with
                        {{ props.winner.total_points.toLocaleString() }} points
                    </p>
                </div>
            </div>
        </div>
    </Transition>
</template>

<style scoped>
.popup-enter-active {
    transition: opacity 0.3s ease;
}

.popup-leave-active {
    transition: opacity 0.2s ease;
}

.popup-enter-from,
.popup-leave-to {
    opacity: 0;
}

.popup-enter-active .relative.z-10 {
    transition: transform 0.35s cubic-bezier(0.34, 1.56, 0.64, 1);
}

.popup-enter-from .relative.z-10 {
    transform: scale(0.9) translateY(12px);
}

.popup-enter-to .relative.z-10 {
    transform: scale(1) translateY(0);
}
</style>
