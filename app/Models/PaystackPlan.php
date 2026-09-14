<?php

namespace App\Models;

use App\Enums\SubscriptionFrequency;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'amount_kobo',
    'interval',
    'plan_code',
    'name',
])]
class PaystackPlan extends Model
{
    protected function casts(): array
    {
        return [
            'amount_kobo' => 'integer',
            'interval' => SubscriptionFrequency::class,
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(PointSubscription::class);
    }

    public function amountNaira(): float
    {
        return $this->amount_kobo / 100;
    }
}
