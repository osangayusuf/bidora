<?php

namespace App\Enums;

/**
 * Values are intentionally identical to Paystack's Plan `interval` values,
 * so no mapping is needed when talking to the Paystack API.
 */
enum SubscriptionFrequency: string
{
    case DAILY = 'daily';
    case WEEKLY = 'weekly';
    case MONTHLY = 'monthly';

    public function label(): string
    {
        return match ($this) {
            self::DAILY => 'Daily',
            self::WEEKLY => 'Weekly',
            self::MONTHLY => 'Monthly',
        };
    }
}
