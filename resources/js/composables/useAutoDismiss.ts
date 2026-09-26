import { onBeforeUnmount, type Ref } from 'vue';

/**
 * Auto-closes a popup after `delayMs` (default 5000ms) if the visitor
 * doesn't act on it first. Call `start()` right when the popup becomes
 * visible, and `clear()` from your own dismiss handler so a manual close
 * (or an action like "Bid Now") doesn't race with the timer firing late.
 */
export function useAutoDismiss(isVisible: Ref<boolean>, delayMs = 5000) {
    let timer: ReturnType<typeof setTimeout> | null = null;

    function start(): void {
        clear();
        timer = setTimeout(() => {
            isVisible.value = false;
        }, delayMs);
    }

    function clear(): void {
        if (timer) {
            clearTimeout(timer);
            timer = null;
        }
    }

    onBeforeUnmount(clear);

    return { start, clear };
}
