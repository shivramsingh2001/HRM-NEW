<?php

use Illuminate\Support\Facades\Schedule;

// Alert HR/manager when an employee misses check-in past the grace period.
// Dedupe is persisted on user_shifts.missed_checkin_notified; FirebaseService
// degrades gracefully when credentials are absent.
Schedule::command('attendance:check-missed-checkins')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/missed-checkin.log'));

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

// Fire meeting reminders once a meeting enters its reminder_minutes_before
// window. Runs every five minutes (like the missed-checkin sweep above)
// since the window is minute-granular; reminder_sent dedupes.
Schedule::command('meetings:send-reminders')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/meeting-reminders.log'));

// Remind task assignees the day before a deadline, then daily while overdue.
Schedule::command('tasks:deadline-reminders')
    ->dailyAt('08:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/task-deadline-reminders.log'));

// Remind project managers/teams when a deadline is within 7 days, then daily while overdue.
Schedule::command('projects:check-deadlines')
    ->dailyAt('08:15')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/project-deadline-reminders.log'));

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

// Field GPS tracking — meter seat usage for billing, then prune old breadcrumbs.
Schedule::command('field-tracking:meter')
    ->dailyAt('02:30')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/field-tracking-meter.log'));

Schedule::command('field-tracking:prune')
    ->dailyAt('03:30')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/field-tracking-prune.log'));

// Biometric-terminal integration — retry stuck punches; prune settled ones.
Schedule::command('biometric:reprocess --remap')
    ->everyTenMinutes()
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/biometric-reprocess.log'));

Schedule::command('biometric:prune')
    ->dailyAt('03:45')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/biometric-prune.log'));

// Reconcile terminal user lists against HRM (safety net for the User observer).
Schedule::command('biometric:sync-roster')
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/biometric-sync-roster.log'));