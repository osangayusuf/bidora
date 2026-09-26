<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { useAutoDismiss } from '@/composables/useAutoDismiss';
import { getLaunchCountdownParts, hasLaunched } from '@/lib/utils';

const isVisible = ref(false);
const { start, clear } = useAutoDismiss(isVisible);

onMounted(() => {
    if (!hasLaunched()) {
        isVisible.value = true;
        start();
    }
});

function dismiss(): void {
    clear();
    isVisible.value = false;
}

const units: {
    key: keyof ReturnType<typeof getLaunchCountdownParts>;
    label: string;
}[] = [
    { key: 'days', label: 'Days' },
    { key: 'hours', label: 'Hrs' },
    { key: 'minutes', label: 'Min' },
    { key: 'seconds', label: 'Sec' },
];
</script>

<template>
    <Transition name="popup">
        <div
            v-if="isVisible"
            class="fixed inset-0 z-[60] flex items-center justify-center p-4"
            role="dialog"
            aria-modal="true"
            aria-labelledby="launch-notice-title"
            @click.self="dismiss"
        >
            <div
                class="absolute inset-0 bg-black/75 backdrop-blur-sm"
                @click="dismiss"
            />

            <div
                class="relative z-10 w-full max-w-lg overflow-hidden rounded-3xl bg-surface-container-lowest shadow-2xl"
            >
                <div
                    class="absolute inset-x-0 top-0 h-1.5 bg-linear-to-r from-forest via-lemon to-forest"
                />

                <button
                    type="button"
                    class="absolute top-4 right-4 z-20 flex h-8 w-8 items-center justify-center rounded-full bg-black/10 text-on-surface/60 transition-colors hover:bg-black/20 hover:text-on-surface"
                    aria-label="Close"
                    @click="dismiss"
                >
                    <span class="material-symbols-outlined text-xl leading-none"
                        >close</span
                    >
                </button>

                <div class="flex flex-col items-center px-8 py-10 text-center">
                    <div
                        class="mb-5 flex h-14 w-14 items-center justify-center rounded-full bg-lemon/15"
                    >
                        <span
                            class="material-symbols-outlined text-3xl text-forest"
                            data-weight="fill"
                            >campaign</span
                        >
                    </div>

                    <h2
                        id="launch-notice-title"
                        class="mb-3 font-headline text-3xl font-extrabold tracking-tight text-forest"
                    >
                        We're Launching Soon!
                    </h2>

                    <p
                        class="mb-6 max-w-sm text-sm leading-relaxed font-medium text-on-surface/70"
                    >
                        Bidora officially launches on
                        <span class="font-bold text-on-surface"
                            >24 August 2026</span
                        >. Any bids placed before this date will be
                        <span class="font-bold text-on-surface"
                            >voided and non-refundable</span
                        >.
                    </p>

                    <div class="grid w-full max-w-xs grid-cols-4 gap-2">
                        <div
                            v-for="unit in units"
                            :key="unit.key"
                            class="flex flex-col items-center rounded-xl bg-surface-container px-2 py-3"
                        >
                            <span
                                class="font-headline text-2xl font-extrabold text-forest tabular-nums"
                                >{{
                                    getLaunchCountdownParts()
                                        [unit.key].toString()
                                        .padStart(2, '0')
                                }}</span
                            >
                            <span
                                class="mt-1 text-[11px] font-semibold tracking-wide text-on-surface/50 uppercase"
                                >{{ unit.label }}</span
                            >
                        </div>
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
