<?php

use App\Console\Commands\MarkIdleAgentsAway;
use App\Console\Commands\ReconcileAuctionsCommand;
use App\Services\RewardsService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command(ReconcileAuctionsCommand::class)
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();

Schedule::call(fn (RewardsService $rewards) => $rewards->grantDailySpins())
    ->dailyAt('00:00')
    ->name('rewards:grant-daily-spins');

Schedule::call(fn (RewardsService $rewards) => $rewards->processWeeklyLeaderboard())
    ->weeklyOn(0, '23:55')
    ->name('rewards:process-weekly-leaderboard');

Schedule::command(MarkIdleAgentsAway::class)
    ->everyFiveMinutes()
    ->runInBackground();
