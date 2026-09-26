<?php

namespace App\Enums;

enum TopUpStatus: string
{
    /** Initialized on Paystack, awaiting payment. */
    case PENDING = 'pending';

    /** Paid and credited to the user's wallet. */
    case COMPLETED = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::COMPLETED => 'Completed',
        };
    }
}
