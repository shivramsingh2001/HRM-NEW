<?php

use Illuminate\Support\Facades\Schedule;
use App\Console\Commands\CheckMissedCheckIns;

// Run every 5 minutes to check for missed check-ins
// Schedule::command('attendance:check-missed-checkins')
//     ->everyFiveMinutes()
//     ->withoutOverlapping()
//     ->runInBackground()
//     ->appendOutputTo(storage_path('logs/missed-checkin.log'));
    
Schedule::command('leaves:credit-weekly')
    ->weekly()
    ->mondays()
    ->at('00:30')
    ->withoutOverlapping()
    ->runInBackground();

// Monthly credits - 1st of every month at 00:30 AM
Schedule::command('leaves:credit-monthly')
    ->monthlyOn(1, '00:30')
    ->withoutOverlapping()
    ->runInBackground();

// Yearly credits - 1st April at 00:30 AM
Schedule::command('leaves:credit-yearly')
    ->cron('30 0 1 4 *')
    ->withoutOverlapping()
    ->runInBackground();
    
Schedule::command('attendance:auto-clockout --hours=15')
    ->everyTenMinutes()
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/auto-clockout.log'));

// Rebuild the current month's attendance summaries nightly, and on the 1st also
// finalise the month that just ended.
Schedule::command('attendance:update-summaries')
    ->dailyAt('01:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/attendance-summaries.log'));

Schedule::call(function () {
    // Evaluated at fire time so it always targets the month that just ended.
    \Illuminate\Support\Facades\Artisan::call('attendance:update-summaries', [
        '--month' => now()->subMonthNoOverflow()->format('Y-m'),
        '--force' => true,
    ]);
})->monthlyOn(1, '02:00')->name('attendance-summaries-prev-month')->withoutOverlapping();
    
Schedule::command('performance:calculate')
->monthlyOn(1, '02:00')
->appendOutputTo(storage_path('logs/performance-calculation.log'));