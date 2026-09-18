<?php

namespace App\Console\Commands;

use App\Enums\TransactionType;
use App\Models\LaunchPromotion;
use App\Models\PointTransaction;
use App\Models\User;
use App\Notifications\LaunchBonusAwarded;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class BackfillLaunchBonusCommand extends Command
{
    protected $signature = 'bonus:backfill-launch
                            {--dry-run : Report counts only, write nothing}
                            {--chunk=200 : How many users to process per batch}';

    protected $description = 'Credit the launch-week 5,000 point bonus to verified users who missed it';

    public function handle(): int
    {
        $promo = LaunchPromotion::where('slug', LaunchPromotion::FIRST_HUNDRED_SLUG)->first();

        if (! $promo) {
            $this->error('LaunchPromotion row not found for slug: '.LaunchPromotion::FIRST_HUNDRED_SLUG);

            return self::FAILURE;
        }

        if (! $promo->is_active) {
            $this->error('Promo is_active=false — activate it first, otherwise this will credit nobody.');

            return self::FAILURE;
        }

        $missingIds = User::whereNotNull('email_verified_at')
            ->whereDoesntHave('pointTransactions', fn ($q) => $q->where('type', TransactionType::LAUNCH_BONUS))
            ->orderBy('email_verified_at')
            ->pluck('id')
            ->all();

        $total = count($missingIds);

        $this->info("Verified users missing the bonus: {$total}");
        $this->info("Promo slots: {$promo->slots_claimed}/{$promo->slots_total}, amount={$promo->amount}");

        if ($total === 0) {
            $this->info('Nothing to do.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->info('Dry run — no changes made.');

            return self::SUCCESS;
        }

        $slotsAvailable = max(0, $promo->slots_total - $promo->slots_claimed);
        $chunkSize = max(1, (int) $this->option('chunk'));

        $credited = 0;
        $skipped = 0;
        $failed = 0;

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        foreach (array_chunk($missingIds, $chunkSize) as $chunk) {
            if ($slotsAvailable <= 0) {
                $this->newLine();
                $this->warn('Ran out of promo slots — stopping early.');
                break;
            }

            foreach ($chunk as $userId) {
                if ($slotsAvailable <= 0) {
                    break;
                }

                // Fresh idempotency check right before writing, in case this
                // user was credited by something else since we fetched the
                // missing-user list above.
                $alreadyAwarded = PointTransaction::query()
                    ->where('user_id', $userId)
                    ->where('type', TransactionType::LAUNCH_BONUS)
                    ->exists();

                if ($alreadyAwarded) {
                    $skipped++;
                    $bar->advance();

                    continue;
                }

                try {
                    $tx = null;

                    // Deliberately does NOT lock the launch_promotions row:
                    // every user here is already verified, so the live
                    // Verified-event listener can never re-fire for them —
                    // there is no concurrent writer to race against for
                    // these specific rows. slots_claimed is resynced once,
                    // atomically, after the loop instead of being held
                    // locked across ~8k iterations.
                    DB::transaction(function () use ($userId, $promo, &$tx) {
                        User::where('id', $userId)->increment('points_balance', $promo->amount);

                        $tx = PointTransaction::create([
                            'user_id' => $userId,
                            'type' => TransactionType::LAUNCH_BONUS,
                            'amount' => $promo->amount,
                            'exchange_rate' => 1.0,
                            'status' => 'completed',
                            'metadata' => [
                                'description' => 'Launch promo: first 100 users bonus (backfill)',
                                'promo_slug' => $promo->slug,
                            ],
                        ]);
                    });

                    $credited++;
                    $slotsAvailable--;

                    $user = User::find($userId);

                    if ($user && $tx) {
                        $user->notify(new LaunchBonusAwarded($tx, $promo->slots_claimed + $credited));
                    }
                } catch (Throwable $e) {
                    $failed++;
                    $this->newLine();
                    $this->error("Failed on user #{$userId}: {$e->getMessage()}");
                }

                $bar->advance();
            }
        }

        $bar->finish();
        $this->newLine();

        // Resync to ground truth rather than trusting an incremental add —
        // safe, fast, single atomic statement.
        $actualClaimed = PointTransaction::query()->where('type', TransactionType::LAUNCH_BONUS)->count();
        $promo->update(['slots_claimed' => $actualClaimed]);

        $this->info("Done. Credited: {$credited}. Skipped (already had it): {$skipped}. Failed: {$failed}.");
        $this->info("Promo slots now: {$actualClaimed}/{$promo->slots_total}.");

        return self::SUCCESS;
    }
}
