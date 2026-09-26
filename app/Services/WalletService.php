<?php

namespace App\Services;

use App\Enums\RewardSource;
use App\Enums\SubscriptionStatus;
use App\Enums\TopUpStatus;
use App\Enums\TransactionType;
use App\Models\LaunchPromotion;
use App\Models\PaystackTransaction;
use App\Models\PointSubscription;
use App\Models\PointTransaction;
use App\Models\TopUp;
use App\Models\User;
use App\Notifications\BonusPointsAwarded;
use App\Notifications\BonusPointsClaimed;
use App\Notifications\LaunchBonusAwarded;
use App\Notifications\PaymentConfirmed;
use App\Notifications\WelcomeNotification;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class WalletService
{
    /**
     * Process a user deposit, converting Naira to Points.
     */
    public function processDeposit(
        User $user,
        float $nairaAmount,
        string $reference,
        array $metadata = [],
        ?int $paystackTransactionId = null,
        ?int $pointSubscriptionId = null,
        ?int $topUpId = null,
    ): PointTransaction {
        $existing = PointTransaction::query()
            ->where('provider_reference', $reference)
            ->where('type', TransactionType::DEPOSIT)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $subscription = $pointSubscriptionId !== null
            ? PointSubscription::with('subscriptionPackage')->find($pointSubscriptionId)
            : null;
        $package = $subscription?->subscriptionPackage;

        $topUp = $topUpId !== null
            ? TopUp::where('user_id', $user->id)->find($topUpId)
            : null;

        return DB::transaction(function () use ($user, $nairaAmount, $reference, $metadata, $paystackTransactionId, $pointSubscriptionId, $subscription, $package, $topUp) {
            // A package subscription credits its fixed points_allocated
            // (which may include a bonus over the standard rate) and a
            // top-up credits at the rate locked in when it was started;
            // anything else uses the current naira x points_per_naira rate.
            [$pointsAmount, $exchangeRate] = match (true) {
                $package !== null => [
                    $package->points_allocated,
                    $nairaAmount > 0 ? $package->points_allocated / $nairaAmount : 0,
                ],
                $topUp !== null => [$topUp->pointsFor($nairaAmount), $topUp->points_per_naira],
                default => [
                    $nairaAmount * (float) config('points.points_per_naira'),
                    (float) config('points.points_per_naira'),
                ],
            };

            $user = User::where('id', $user->id)->lockForUpdate()->firstOrFail();

            $isFirstDeposit = ! PointTransaction::query()
                ->where('user_id', $user->id)
                ->where('type', TransactionType::DEPOSIT)
                ->where('status', 'completed')
                ->exists();

            $user->points_balance += (int) $pointsAmount;
            $user->save();

            $transaction = PointTransaction::create([
                'user_id' => $user->id,
                'paystack_transaction_id' => $paystackTransactionId,
                'point_subscription_id' => $pointSubscriptionId,
                'top_up_id' => $topUp?->id,
                'type' => TransactionType::DEPOSIT,
                'amount' => $pointsAmount,
                'naira_amount' => $nairaAmount,
                'exchange_rate' => $exchangeRate,
                'provider_reference' => $reference,
                'status' => 'completed',
                'metadata' => $metadata,
            ]);

            if ($subscription !== null && $package !== null) {
                // Recurring packages are renewed by Paystack itself; a charge
                // confirms the subscription, first charge or renewal alike.
                $subscription->update([
                    'status' => $package->renewal_cycle->isRecurring()
                        ? SubscriptionStatus::ACTIVE
                        : SubscriptionStatus::COMPLETED,
                ]);
            }

            $topUp?->update([
                'status' => TopUpStatus::COMPLETED,
                'paid_at' => now(),
            ]);

            $user->notify(new PaymentConfirmed($transaction));

            if ($isFirstDeposit && $user->referred_by !== null && config('points.referral.enabled', true)) {
                $referrer = User::find($user->referred_by);
                if ($referrer !== null) {
                    $referrerDepositPoints = (int) config('points.referral.referrer_first_deposit_points', 0);
                    if ($referrerDepositPoints > 0) {
                        $this->awardBonusPoints($referrer, $referrerDepositPoints, [
                            'source' => RewardSource::ReferralDeposit->value,
                            'description' => 'Referral deposit bonus for '.$user->name.'\'s first purchase',
                            'referred_user_id' => $user->id,
                        ], true);
                    }
                }
            }

            return $transaction;
        });
    }

    /**
     * Finalize a Paystack deposit after inline payment or callback verification.
     */
    public function finalizePaystackDeposit(
        User $user,
        string $reference,
        array $paystackData,
        ?int $pointSubscriptionId = null,
        ?int $topUpId = null,
    ): ?PointTransaction {
        $status = $paystackData['status'] ?? null;

        if ($status !== 'success') {
            return null;
        }

        $amountInKobo = (int) ($paystackData['amount'] ?? 0);
        $nairaAmount = $amountInKobo / 100;

        $paystackTransaction = PaystackTransaction::query()
            ->where('reference', $reference)
            ->where('user_id', $user->id)
            ->first();

        $transaction = $this->processDeposit(
            $user,
            $nairaAmount,
            $reference,
            $paystackData,
            $paystackTransaction?->id,
            $pointSubscriptionId,
            $topUpId,
        );

        if ($paystackTransaction !== null && $paystackTransaction->status !== 'success') {
            $paystackTransaction->update([
                'status' => 'success',
                'paid_at' => now(),
                'channel' => $paystackData['channel'] ?? $paystackTransaction->channel,
            ]);
        }

        return $transaction;
    }

    /**
     * Award bonus points to a user.
     */
    public function awardBonusPoints(
        User $user,
        int $bonusAmount,
        array $metadata = [],
        bool $sendBonusAwardedNotification = true,
    ): PointTransaction {
        if ($bonusAmount <= 0) {
            throw new InvalidArgumentException('Bonus amount must be positive.');
        }

        $transaction = DB::transaction(function () use ($user, $bonusAmount, $metadata) {
            $user = User::where('id', $user->id)->lockForUpdate()->firstOrFail();
            $user->bonus_points += $bonusAmount;
            $user->save();

            return PointTransaction::create([
                'user_id' => $user->id,
                'type' => TransactionType::BONUS_AWARD,
                'amount' => $bonusAmount,
                'exchange_rate' => 1.0,
                'status' => 'completed',
                'metadata' => $metadata,
            ]);
        });

        if ($sendBonusAwardedNotification) {
            $user->notify(new BonusPointsAwarded($transaction));
        }

        return $transaction;
    }

    /**
     * Claim all bonus points, converting them to spendable points balance.
     */
    public function claimAllBonusPoints(User $user): PointTransaction
    {
        $bonusAmount = (int) $user->bonus_points;

        if ($bonusAmount <= 0) {
            throw new InvalidArgumentException('You have no bonus points to claim.');
        }

        return $this->claimBonusPoints($user, $bonusAmount);
    }

    /**
     * Claim bonus points, converting them to spendable points balance.
     */
    public function claimBonusPoints(User $user, int $bonusAmount): PointTransaction
    {
        $transaction = DB::transaction(function () use ($user, $bonusAmount) {
            $user = User::where('id', $user->id)->lockForUpdate()->firstOrFail();

            if ($user->bonus_points < $bonusAmount) {
                throw new InvalidArgumentException('Insufficient bonus points balance.');
            }

            $conversionRate = (float) config('points.bonus_conversion_rate');
            $spendablePoints = (int) floor($bonusAmount * $conversionRate);

            $user->bonus_points -= $bonusAmount;
            $user->points_balance += $spendablePoints;
            $user->save();

            return PointTransaction::create([
                'user_id' => $user->id,
                'type' => TransactionType::BONUS_CLAIM,
                'amount' => $spendablePoints,
                'exchange_rate' => $conversionRate,
                'status' => 'completed',
                'metadata' => [
                    'bonus_claimed' => $bonusAmount,
                    'conversion_rate' => $conversionRate,
                ],
            ]);
        });

        $user->notify(new BonusPointsClaimed($transaction));

        return $transaction;
    }

    /**
     * Award welcome registration points to a user.
     */
    public function awardWelcomePoints(User $user): ?PointTransaction
    {
        $pointsAmount = (int) config('points.registration_points', 50);

        if ($pointsAmount <= 0) {
            return null;
        }

        return DB::transaction(function () use ($user, $pointsAmount) {
            // Lock user for update to prevent race conditions/double award
            $user = User::where('id', $user->id)->lockForUpdate()->firstOrFail();

            $alreadyAwarded = PointTransaction::query()
                ->where('user_id', $user->id)
                ->where('type', TransactionType::WELCOME_BONUS)
                ->exists();

            if ($alreadyAwarded) {
                return null;
            }

            $user->points_balance += $pointsAmount;
            $user->save();

            $transaction = PointTransaction::create([
                'user_id' => $user->id,
                'type' => TransactionType::WELCOME_BONUS,
                'amount' => $pointsAmount,
                'exchange_rate' => 1.0,
                'status' => 'completed',
                'metadata' => [
                    'description' => 'Welcome points awarded on email verification',
                ],
            ]);

            $user->notify(new WelcomeNotification($transaction));

            return $transaction;
        });
    }

    /**
     * Award the launch-week "first 100 users" bonus, if a slot is still
     * available. Safe under concurrent claims: slot allocation happens via
     * a row lock on the singleton LaunchPromotion record, which serializes
     * every concurrent claim attempt instead of racing on a COUNT(*).
     */
    public function awardLaunchBonus(User $user, string $slug = LaunchPromotion::FIRST_HUNDRED_SLUG): ?PointTransaction
    {
        $result = DB::transaction(function () use ($user, $slug) {
            $promo = LaunchPromotion::query()
                ->where('slug', $slug)
                ->lockForUpdate()
                ->first();

            if ($promo === null || ! $promo->hasSlotsRemaining()) {
                return null;
            }

            $alreadyAwarded = PointTransaction::query()
                ->where('user_id', $user->id)
                ->where('type', TransactionType::LAUNCH_BONUS)
                ->exists();

            if ($alreadyAwarded) {
                return null;
            }

            $promo->slots_claimed++;
            $promo->save();
            $slot = $promo->slots_claimed;

            $user = User::where('id', $user->id)->lockForUpdate()->firstOrFail();
            $user->points_balance += $promo->amount;
            $user->save();

            $transaction = PointTransaction::create([
                'user_id' => $user->id,
                'type' => TransactionType::LAUNCH_BONUS,
                'amount' => $promo->amount,
                'exchange_rate' => 1.0,
                'status' => 'completed',
                'metadata' => [
                    'description' => 'Launch promo: first 100 users bonus',
                    'promo_slug' => $slug,
                    'slot' => $slot,
                ],
            ]);

            return ['transaction' => $transaction, 'slot' => $slot];
        });

        if ($result === null) {
            return null;
        }

        $user->notify(new LaunchBonusAwarded($result['transaction'], $result['slot']));

        return $result['transaction'];
    }
}
