<?php

use Illuminate\Support\Facades\Schedule;

// Guarantee every tenant's payroll component catalog has the platform's
// default components (Basic Salary, HRA, PF, ESI, ...) — idempotent, skips
// any tenant/code pair that already exists. Tenants are provisioned by the
// separate hrm-superadmin app directly against the shared DB, so this app
// has no create event to hook; running this daily is how a brand-new tenant
// picks up Basic Salary without a manual `--tenant=` run.
Schedule::command('payroll:backfill-component-catalog')
    ->dailyAt('00:10')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/payroll-backfill-component-catalog.log'));

// Keep every active Permanent shift assignment's user_shifts cache extended
// to the rolling horizon — the mechanism that removes any need to manually
// re-assign a standing shift.
Schedule::command('shift:roll-permanent-horizon')
    ->dailyAt('00:20')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/shift-roll-permanent-horizon.log'));

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

// Carry forward — daily, before the credits: at the
// start of each credit period (1st of month, Monday, or leave-year start), leave
// above a type's carry-forward limit lapses; carried leave of yearly types not
// used by its expiry date lapses (Company Policies → Leave carry forward).
Schedule::command('leaves:carry-forward')
    ->dailyAt('00:10')
    ->withoutOverlapping()
    ->runInBackground();

// Yearly credits — daily check; each company is credited once per leave year,
// on/after its own leave-year start (Company Policies, default 1 April).
Schedule::command('leaves:credit-yearly')
    ->dailyAt('00:30')
    ->withoutOverlapping()
    ->runInBackground();
    
Schedule::command('attendance:auto-clockout --hours=15')
    ->everyTenMinutes()
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/auto-clockout.log'));

// Automatic overtime (Company Policies → Overtime, mode "automatic"): yesterday
// + today recalculated from attendance — catches writes that skip the live
// hook (legacy auto clock-out). After auto clock-out has closed the night.
Schedule::command('overtime:auto-calculate')
    ->dailyAt('01:00')
    ->withoutOverlapping()
    ->runInBackground();

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
    
// Daily performance scoring — 90 min after attendance:update-summaries'
// 01:00 run, so yesterday's attendance data has settled. Monthly rollup runs
// after a full month of daily rows exists (replaces the old, from-scratch
// performance:calculate command).
Schedule::command('performance:calculate-daily')
->dailyAt('02:30')
->withoutOverlapping()
->runInBackground()
->appendOutputTo(storage_path('logs/performance-daily.log'));

Schedule::command('performance:rollup-monthly --notify')
->monthlyOn(1, '03:00')
->withoutOverlapping()
->runInBackground()
->appendOutputTo(storage_path('logs/performance-monthly.log'));

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

// Two-table GPS tracking (sessions + points) — stale-session close + prune.
Schedule::command('field-tracking:sweep-sessions')
    ->dailyAt('03:45')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/field-tracking-sweep-sessions.log'));

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
// Expense — nudge employees who still hold an UNSPENT advance older than 30 days. Idempotent (an employee
// reminded in the last 7 days is skipped), so a daily run yields at most one reminder a week per person.
Schedule::command('expense:advance-reminders')
    ->dailyAt('09:30')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/expense-advance-reminders.log'));

// Expense — permanently remove claims that were soft-deleted more than 90 days ago (and their receipts).
Schedule::command('expense:purge-deleted')
    ->weeklyOn(0, '03:30')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/expense-purge-deleted.log'));

// Broadcast — fire every scheduled broadcast whose scheduled_at has arrived. everyMinute() (not the more
// common 5-minute cadence) because a broadcast scheduled for "9:00am" should land at ~9:00-9:01, not up
// to five minutes late.
Schedule::command('broadcast:send-scheduled')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/broadcast-send-scheduled.log'));

// Broadcast — flip sent broadcasts past their expires_at to expired, and refresh the list-page stat
// snapshots (delivered/read/click counts) from the live broadcast_recipients rows.
Schedule::command('broadcast:expire')
    ->hourly()
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/broadcast-expire.log'));
