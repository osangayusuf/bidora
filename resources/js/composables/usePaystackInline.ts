import { router } from '@inertiajs/vue3';
import WalletSubscriptionController from '@/actions/App/Http/Controllers/WalletSubscriptionController';
import { callback as paymentCallback } from '@/routes/wallet/payment';
import type { PaystackInit } from '@/types/wallet';

type PaystackCallbackResponse = {
    reference: string;
};

type PaystackHandler = {
    openIframe: () => void;
};

declare global {
    interface Window {
        PaystackPop?: {
            setup: (config: {
                key: string;
                email: string;
                amount: number;
                ref: string;
                plan?: string;
                channels?: string[];
                onClose: () => void;
                callback: (response: PaystackCallbackResponse) => void;
            }) => PaystackHandler;
        };
    }
}

let scriptPromise: Promise<void> | null = null;

function loadPaystackScript(): Promise<void> {
    if (typeof window === 'undefined') {
        return Promise.resolve();
    }

    if (window.PaystackPop) {
        return Promise.resolve();
    }

    if (scriptPromise) {
        return scriptPromise;
    }

    scriptPromise = new Promise((resolve, reject) => {
        const script = document.createElement('script');
        script.src = 'https://js.paystack.co/v1/inline.js';
        script.async = true;
        script.onload = () => resolve();
        script.onerror = () => reject(new Error('Failed to load Paystack.'));
        document.body.appendChild(script);
    });

    return scriptPromise;
}

export function usePaystackInline(): {
    openPayment: (init: PaystackInit) => Promise<void>;
} {
    async function openPayment(init: PaystackInit): Promise<void> {
        await loadPaystackScript();

        if (!window.PaystackPop) {
            throw new Error('Paystack is unavailable.');
        }

        const callbackUrl = paymentCallback.url();
        let succeeded = false;

        const handler = window.PaystackPop.setup({
            key: init.public_key,
            email: init.email,
            amount: init.amount_kobo,
            ref: init.reference,
            ...(init.plan_code ? { plan: init.plan_code } : {}),
            ...(init.channels ? { channels: init.channels } : {}),
            onClose: () => {
                // The popup was dismissed without paying. Cancel the pending
                // subscription row it created so the package/plan is free to
                // try again immediately, instead of waiting on the sweep
                // that clears abandoned attempts.
                if (succeeded || !init.subscription_id) {
                    return;
                }

                router.delete(
                    WalletSubscriptionController.destroy.url(
                        init.subscription_id,
                    ),
                    { preserveScroll: true },
                );
            },
            callback: (response) => {
                succeeded = true;
                const url = new URL(callbackUrl, window.location.origin);
                url.searchParams.set('reference', response.reference);
                window.location.href = url.toString();
            },
        });

        handler.openIframe();
    }

    return { openPayment };
}
