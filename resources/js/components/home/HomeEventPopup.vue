<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { usePlaceBidModal } from '@/composables/usePlaceBidModal';
import { getRemainingTime } from '@/lib/utils';
import type { Bid } from '@/types/auction';

const props = defineProps<{
    bid: Bid;
    userPoints?: number | null;
}>();

const { open } = usePlaceBidModal();
const isVisible = ref(false);

onMounted(() => {
    isVisible.value = true;
});

function dismiss(): void {
    isVisible.value = false;
}

function bidNow(): void {
    dismiss();
    open(props.bid, props.userPoints ?? null);
}
</script>

<template>
    <Transition name="popup">
        <div
            v-if="isVisible"
            class="fixed inset-0 z-50 flex items-center justify-center p-4"
            role="dialog"
            aria-modal="true"
            aria-labelledby="event-popup-title"
            @click.self="dismiss"
        >
            <div
                class="absolute inset-0 bg-black/75 backdrop-blur-sm"
                @click="dismiss"
            />

            <div class="relative z-10 w-full max-w-2xl">
                <button
                    type="button"
                    class="absolute top-2 right-2 z-20 flex h-11 w-11 cursor-pointer items-center justify-center rounded-full bg-navy text-white shadow-lg ring-2 ring-white transition-colors hover:bg-black"
                    aria-label="Close"
                    @click="dismiss"
                >
                    <span
                        class="material-symbols-outlined text-2xl leading-none"
                        >close</span
                    >
                </button>

                <div
                    class="flex max-h-[calc(100dvh-2rem)] w-full overflow-x-hidden overflow-y-auto rounded-3xl bg-surface-container-lowest shadow-2xl max-md:flex-col"
                >
                    <div
                        class="relative flex w-2/5 shrink-0 items-center justify-center bg-surface-container p-8 pt-10 max-md:w-full"
                    >
                        <div
                            class="absolute inset-x-0 top-0 h-1.5 bg-linear-to-r from-forest via-lemon to-forest"
                        />
                        <div
                            class="rounded-2xl border-4 border-lemon bg-surface-container-high p-4 shadow-lg shadow-lemon/20"
                        >
                            <img
                                v-if="props.bid.image"
                                :src="props.bid.image"
                                :alt="props.bid.name"
                                class="h-56 w-full object-contain"
                            />
                            <div
                                v-else
                                class="flex h-56 w-full items-center justify-center"
                            >
                                <span
                                    class="material-symbols-outlined text-6xl text-secondary"
                                    >stars</span
                                >
                            </div>
                        </div>
                    </div>

                    <div
                        class="flex flex-1 flex-col justify-center px-10 py-12 md:min-h-[420px]"
                    >
                        <div
                            class="mb-5 flex h-14 w-14 items-center justify-center rounded-full bg-lemon/15"
                        >
                            <span
                                class="material-symbols-outlined text-3xl text-forest"
                                data-weight="fill"
                                >stars</span
                            >
                        </div>
                        <h2
                            id="event-popup-title"
                            class="mb-3 font-headline text-4xl font-extrabold tracking-tight text-forest drop-shadow-[0_2px_8px_rgba(250,204,21,0.4)] max-md:text-2xl"
                        >
                            Special Event!
                        </h2>
                        <p
                            class="mb-6 text-sm leading-relaxed font-medium text-on-surface/70 max-md:mb-3 max-md:text-xs"
                        >
                            Don't miss out on
                            <span class="font-bold text-on-surface">{{
                                props.bid.name
                            }}</span>
                        </p>
                        <div class="mb-6 h-px w-full bg-surface-container" />
                        <p
                            class="mb-4 text-base font-bold text-forest max-md:mb-2 max-md:text-sm"
                        >
                            Closes in:
                            {{ getRemainingTime(props.bid.expires_at) }}
                        </p>
                        <button
                            @click="bidNow"
                            class="mt-auto w-full cursor-pointer rounded-xl bg-lemon py-3 text-center font-extrabold text-navy transition-colors hover:bg-amber"
                        >
                            Bid Now
                        </button>
                    </div>
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

.popup-enter-active .z-10 {
    transition: transform 0.35s cubic-bezier(0.34, 1.56, 0.64, 1);
}

.popup-enter-from .z-10 {
    transform: scale(0.9) translateY(12px);
}

.popup-enter-to .z-10 {
    transform: scale(1) translateY(0);
}
</style>
