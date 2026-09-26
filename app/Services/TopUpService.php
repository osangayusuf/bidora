<?php

namespace App\Services;

use App\Enums\TopUpStatus;
use App\Models\TopUp;
use App\Models\User;

class TopUpService
{
    public function __construct(
        private readonly PaystackService $paystackService,
    ) {}

    /**
     * Start a top-up for a user-chosen Naira amount: records a pending
     * top-up quoted at the current points_per_naira rate and initializes a
     * plain Paystack charge open to every payment channel.
     *
     * @return array{init: array<string, mixed>, top_up: TopUp}|null
     */
    public function start(User $user, float $amountNaira, ?string $callbackUrl = null): ?array
    {
        $pointsPerNaira = (float) config('points.points_per_naira');

        $topUp = TopUp::create([
            'user_id' => $user->id,
            'amount_naira' => $amountNaira,
            'points_per_naira' => $pointsPerNaira,
            'points' => (int) floor($amountNaira * $pointsPerNaira),
            'status' => TopUpStatus::PENDING,
        ]);

        $init = $this->paystackService->initializeTransaction(
            $user,
            $amountNaira,
            $callbackUrl,
            null,
            ['top_up_id' => $topUp->id, 'purpose' => 'top_up'],
        );

        if ($init === null) {
            $topUp->delete();

            return null;
        }

        $topUp->update(['reference' => $init['reference']]);

        return ['init' => $init, 'top_up' => $topUp];
    }

    /**
     * Resolve which top-up a `charge.success` payload pays for, via the
     * metadata set at initialization or, failing that, the reference.
     *
     * @param  array<string, mixed>  $chargeData
     */
    public function resolveForCharge(array $chargeData): ?TopUp
    {
        $topUpId = $chargeData['metadata']['top_up_id'] ?? null;

        if ($topUpId !== null) {
            $topUp = TopUp::find($topUpId);

            if ($topUp !== null) {
                return $topUp;
            }
        }

        $reference = $chargeData['reference'] ?? null;

        return $reference !== null ? TopUp::where('reference', $reference)->first() : null;
    }
}
