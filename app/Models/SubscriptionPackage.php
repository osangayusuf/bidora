<?php

namespace App\Models;

use App\Enums\PackageRenewalCycle;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name',
    'slug',
    'renewal_cycle',
    'points_allocated',
    'price_naira',
    'is_active',
    'sort_order',
])]
class SubscriptionPackage extends Model
{
    protected function casts(): array
    {
        return [
            'renewal_cycle' => PackageRenewalCycle::class,
            'points_allocated' => 'integer',
            'price_naira' => 'decimal:2',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(PointSubscription::class, 'subscription_package_id');
    }

    public function priceKobo(): int
    {
        return (int) round($this->price_naira * 100);
    }
}
