<?php

namespace App\Enums;

enum SubscriptionStatus: string
{
    /** Created locally, awaiting the first charge to confirm it on Paystack. */
    case PENDING = 'pending';

    case ACTIVE = 'active';

    /** A charge attempt failed; Paystack will retry before disabling. */
    case ATTENTION = 'attention';

    /** User or Paystack chose not to renew after the current period. */
    case NON_RENEWING = 'non_renewing';

    case COMPLETED = 'completed';

    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::ACTIVE => 'Active',
            self::ATTENTION => 'Needs attention',
            self::NON_RENEWING => 'Not renewing',
            self::COMPLETED => 'Completed',
            self::CANCELLED => 'Cancelled',
        };
    }
}
