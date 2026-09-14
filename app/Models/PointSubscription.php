<?php

namespace App\Models;

use App\Enums\SubscriptionFrequency;
use App\Enums\SubscriptionStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id',
    'paystack_plan_id',
    'pending_reference',
    'subscription_code',
    'email_token',
    'customer_code',
    'authorization_code',
    'authorization_last4',
    'authorization_brand',
    'amount_naira',
    'frequency',
    'status',
    'next_payment_date',
    'last_charged_at',
    'failure_count',
    'metadata',
])]
class PointSubscription extends Model
{
    protected function casts(): array
    {
        return [
            'frequency' => SubscriptionFrequency::class,
            'status' => SubscriptionStatus::class,
            'amount_naira' => 'decimal:2',
            'next_payment_date' => 'datetime',
            'last_charged_at' => 'datetime',
            'failure_count' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(PaystackPlan::class, 'paystack_plan_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(PointTransaction::class);
    }

    public function isCancellable(): bool
    {
        return in_array($this->status, [
            SubscriptionStatus::PENDING,
            SubscriptionStatus::ACTIVE,
            SubscriptionStatus::ATTENTION,
            SubscriptionStatus::NON_RENEWING,
        ], true);
    }
}
