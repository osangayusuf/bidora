import type { InertiaLinkProps } from '@inertiajs/vue3';
import { useNow } from '@vueuse/core';
import { clsx } from 'clsx';
import type { ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';
import type { Bid } from '@/types/auction';

const now = useNow();

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

export function toUrl(href: NonNullable<InertiaLinkProps['href']>) {
    return typeof href === 'string' ? href : href?.url;
}

export function formatPrice(price: string | number): string {
    if (!price && price !== 0) {
        return '₦ 0';
    }

    const cleanPrice =
        typeof price === 'string' ? price.replace(/,/g, '') : price;
    const num = parseFloat(cleanPrice.toString());

    return isNaN(num) ? price.toString() : `₦ ${num.toLocaleString()}`;
}

/**
 * Returns the item price marked up by 110%, formatted for display as the
 * slashed/strikethrough "original" price next to the real price on bid cards.
 */
export function formatSlashedPrice(price: string | number): string {
    const cleanPrice =
        typeof price === 'string' ? price.replace(/,/g, '') : price;
    const num = parseFloat(cleanPrice.toString());

    return isNaN(num) ? formatPrice(price) : formatPrice(num * 1.1);
}

export function calcProgress(bid: Bid): number {
    if (!bid.opening_points) {
        return 0;
    }

    const progress = ((bid.current_points || 0) / bid.opening_points) * 100;

    return Math.round(Math.min(100, progress));
}

export function formatDate(dateStr: string): string {
    const date = new Date(dateStr);

    return date.toLocaleDateString('en-GB', { day: 'numeric', month: 'short' });
}

/** Business operating timezone — matches MetricsController::BUSINESS_TIMEZONE. */
export const BUSINESS_TIMEZONE = 'Africa/Lagos';

/**
 * Formats a timestamp in the business's own timezone rather than the
 * viewing device's local timezone, so admins in different timezones (or a
 * server clock in UTC) all see the same "day" for the same transaction.
 */
export function formatDateTime(dateStr: string): string {
    return new Date(dateStr).toLocaleString('en-NG', {
        timeZone: BUSINESS_TIMEZONE,
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
        second: '2-digit',
        hour12: true,
    });
}

export function getRemainingTime(expiresAt: string | null | undefined): string {
    if (!expiresAt) {
        return '';
    }

    const diffInMs = new Date(expiresAt).getTime() - now.value.getTime();

    if (diffInMs <= 0) {
        return '0 hour(s), 0 minute(s)';
    }

    const totalMinutes = Math.floor(diffInMs / (1000 * 60));
    const hours = Math.floor(totalMinutes / 60);
    const minutes = totalMinutes % 60;

    return `${hours} hour(s), ${minutes} minute(s)`;
}

/**
 * Same as getRemainingTime(), but includes a seconds component. Intended for
 * surfaces (like the persistent navbar banner) that want a live, per-second
 * ticking countdown rather than the card grid's minute-granularity text.
 */
export function getRemainingTimeWithSeconds(
    expiresAt: string | null | undefined,
): string {
    if (!expiresAt) {
        return '';
    }

    const diffInMs = new Date(expiresAt).getTime() - now.value.getTime();

    if (diffInMs <= 0) {
        return '0 hour(s), 0 minute(s), 0 second(s)';
    }

    const totalSeconds = Math.floor(diffInMs / 1000);
    const hours = Math.floor(totalSeconds / 3600);
    const minutes = Math.floor((totalSeconds % 3600) / 60);
    const seconds = totalSeconds % 60;

    return `${hours} hour(s), ${minutes} minute(s), ${seconds} second(s)`;
}

/**
 * Reactively reports whether a given expires_at timestamp is in the past,
 * ticking off the same shared clock as getRemainingTime().
 */
export function hasExpired(expiresAt: string | null | undefined): boolean {
    if (!expiresAt) {
        return true;
    }

    return new Date(expiresAt).getTime() - now.value.getTime() <= 0;
}

/** Public launch date/time — bids placed before this are voided and non-refundable. */
export const LAUNCH_DATE = '2026-08-24T00:00:00Z';

export type LaunchCountdownParts = {
    days: number;
    hours: number;
    minutes: number;
    seconds: number;
};

/**
 * Reactively reports whether the launch date has passed, ticking off the same
 * shared clock as getRemainingTime().
 */
export function hasLaunched(): boolean {
    return new Date(LAUNCH_DATE).getTime() - now.value.getTime() <= 0;
}

/**
 * Live day/hour/minute/second breakdown of the time remaining until launch,
 * for the pre-launch notice countdown.
 */
export function getLaunchCountdownParts(): LaunchCountdownParts {
    const diffInMs = new Date(LAUNCH_DATE).getTime() - now.value.getTime();

    if (diffInMs <= 0) {
        return { days: 0, hours: 0, minutes: 0, seconds: 0 };
    }

    const totalSeconds = Math.floor(diffInMs / 1000);

    return {
        days: Math.floor(totalSeconds / 86400),
        hours: Math.floor((totalSeconds % 86400) / 3600),
        minutes: Math.floor((totalSeconds % 3600) / 60),
        seconds: totalSeconds % 60,
    };
}

export function maskedMsisdnParts(msisdn: string): {
    prefix: string;
    suffix: string;
} {
    const digits = msisdn.replace(/\D/g, '');

    if (digits.length < 7) {
        return { prefix: '', suffix: '' };
    }

    return {
        prefix: `${digits.slice(0, 3)} ${digits.slice(3, 6)}`,
        suffix: digits.slice(-4),
    };
}

export function formatMsisdn(msisdn: string): string {
    if (!msisdn || msisdn.length < 5) {
        return 'Unknown';
    }

    const start = Math.floor((msisdn.length - 5) / 2);

    return msisdn.slice(0, start) + '*****' + msisdn.slice(start + 5);
}

export function getDaysAgo(dateStr: string): string {
    const date = new Date(dateStr);
    const diffInMs = now.value.getTime() - date.getTime();
    const diffInDays = Math.floor(diffInMs / (1000 * 60 * 60 * 24));

    if (diffInDays === 0) {
        return 'Today';
    } else if (diffInDays === 1) {
        return '1 day ago';
    } else {
        return `${diffInDays} days ago`;
    }
}
