<?php

namespace App\Enums;

use Carbon\CarbonInterface;

/**
 * Renewal cadence for a fixed-tier SubscriptionPackage. Unlike
 * SubscriptionFrequency, these values are never sent to Paystack — package
 * renewals are charged locally against a saved card authorization, so a
 * cycle Paystack has no native interval for (e.g. bi-weekly) is no different
 * from any other.
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

    public function isRecurring(): bool
    {
        return $this !== self::ONE_OFF;
    }

    public function nextChargeAt(CarbonInterface $from): ?CarbonInterface
    {
        return match ($this) {
            self::ONE_OFF => null,
            self::DAILY => $from->clone()->addDay(),
            self::WEEKLY => $from->clone()->addWeek(),
            self::BI_WEEKLY => $from->clone()->addWeeks(2),
            self::MONTHLY => $from->clone()->addMonthNoOverflow(),
        };
    }
}
