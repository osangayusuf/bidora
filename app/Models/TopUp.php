<?php

namespace App\Models;

use App\Enums\TopUpStatus;
use Database\Factories\TopUpFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A single user-chosen amount of Naira converted to points at the
 * configured points_per_naira rate.
 */
#[Fillable([
    'user_id',
    'reference',
    'amount_naira',
    'points_per_naira',
    'points',
    'status',
    'paid_at',
])]
class TopUp extends Model
{
    /** @use HasFactory<TopUpFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'amount_naira' => 'decimal:2',
            'points_per_naira' => 'float',
            'points' => 'integer',
            'status' => TopUpStatus::class,
            'paid_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function pointTransaction(): HasOne
    {
        return $this->hasOne(PointTransaction::class);
    }

    public function amountKobo(): int
    {
        return (int) round($this->amount_naira * 100);
    }

    /**
     * Points earned for a paid Naira amount at this top-up's locked rate.
     */
    public function pointsFor(float $nairaAmount): int
    {
        return (int) floor($nairaAmount * $this->points_per_naira);
    }
}
