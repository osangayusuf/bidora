<?php

namespace App\Enums;

/**
 * Renewal cadence for a fixed-tier SubscriptionPackage. Recurring cycles map
 * onto a native Paystack Plan interval (see paystackInterval()). ONE_OFF and
 * BI_WEEKLY are legacy and can no longer be sold: one-off purchases are now
 * standalone top-ups, and Paystack has no fortnightly interval. Both only
 * remain so historical rows still load.
 */
enum PackageRenewalCycle: string
{
    case ONE_OFF = 'one_off';
    case DAILY = 'daily';
    case WEEKLY = 'weekly';
    case BI_WEEKLY = 'bi_weekly';
    case MONTHLY = 'monthly';

    public function label(): string
    {
        return match ($this) {
            self::ONE_OFF => 'One-Off',
            self::DAILY => 'Daily',
            self::WEEKLY => 'Weekly',
            self::BI_WEEKLY => 'Bi-Weekly',
            self::MONTHLY => 'Monthly',
        };
    }

    /**
     * The Paystack Plan interval for this cycle, or null when it can't be
     * a native Paystack subscription (one-off, or the legacy bi-weekly).
     */
    public function paystackInterval(): ?SubscriptionFrequency
    {
        return match ($this) {
            self::DAILY => SubscriptionFrequency::DAILY,
            self::WEEKLY => SubscriptionFrequency::WEEKLY,
            self::MONTHLY => SubscriptionFrequency::MONTHLY,
            self::ONE_OFF, self::BI_WEEKLY => null,
        };
    }

    /**
     * Cycles an admin can assign to a package today.
     *
     * @return array<int, self>
     */
    public static function sellable(): array
    {
        return array_values(array_filter(self::cases(), fn (self $cycle) => $cycle->paystackInterval() !== null));
    }

    public function isRecurring(): bool
    {
        return $this !== self::ONE_OFF;
    }
}
