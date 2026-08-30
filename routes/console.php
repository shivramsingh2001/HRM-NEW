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
    
Schedule::command('performance:calculate')
->monthlyOn(1, '02:00')
->appendOutputTo(storage_path('logs/performance-calculation.log'));