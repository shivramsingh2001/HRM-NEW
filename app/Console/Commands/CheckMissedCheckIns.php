<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\MissedCheckInNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class CheckMissedCheckIns extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'attendance:check-missed-checkins
                            {--force : Send notifications even if already sent today}
                            {--date= : Check for a specific date (Y-m-d format)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check for users who missed check-in after grace period';

    /**
     * NOTE ON TENANCY:
     * This command runs via `php artisan` / the scheduler — there is no
     * authenticated user and no HTTP request context. Any Eloquent
     * "BelongsToTenant"-style global scope on the User model that resolves
     * the current tenant from auth()/session will NOT reliably apply here.
     * That was the root cause of cross-tenant notifications previously.
     *
     * To avoid depending on that scope at all, every query below uses
     * DB::table(...) with the real table names and an EXPLICIT tenant_id
     * filter on every single query. The Eloquent User model is only
     * touched at the very last moment — hydrated via
     * ->withoutGlobalScopes()->whereIn('id', ...)->get() — because
     * ->notify() requires an actual Notifiable model instance for the
     * `database` channel to store notifiable_type/notifiable_id correctly.
     *
     * ⚠️ Table/column names assumed below — adjust if yours differ:
     *   users, user_shifts, shifts, attendances, user_job_details
     *   tenant_id column exists on: users, user_shifts, shifts, attendances,
     *   user_job_details
     *
     * NOTE ON CACHE MARKING:
     * markNotificationSent() only fires when sendMissedCheckInNotifications()
     * reports at least one successful ->notify() call. Previously it fired
     * unconditionally, so a run where every send failed (queue down, bad
     * notification class, etc.) would still mark the user as "already
     * notified" for the rest of the day — silently blocking all retries
     * with zero notifications ever delivered or stored.
     */

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🚀 Starting missed check-in check at ' . now());
        Log::info('🚀 Starting missed check-in check', ['time' => now()]);

        try {
            $checkDate = $this->option('date')
                ? Carbon::parse($this->option('date'))
                : Carbon::today();

            $currentTime = Carbon::now();
            $forceSend = $this->option('force');

            $this->info("Checking date: {$checkDate->toDateString()}");
            $this->info("Current time: {$currentTime->toDateTimeString()}");
            $this->info("Force send: " . ($forceSend ? 'Yes' : 'No'));

            // Get all users who have shifts on the check date, joined to
            // their shift + tenant_id. tenant_id is read from `users`
            // (the source of truth for which tenant the employee belongs to).
            $userShifts = DB::table('user_shifts')
                ->join('users', 'users.id', '=', 'user_shifts.user_id')
                ->join('shifts', 'shifts.id', '=', 'user_shifts.shift_id')
                ->whereDate('user_shifts.date', $checkDate)
                ->where('user_shifts.status', 'upcoming')
                ->where('users.status', 1) // don't alert about deactivated staff
                ->select(
                    'user_shifts.id as user_shift_id',
                    'user_shifts.user_id',
                    'user_shifts.missed_checkin_notified',
                    'users.name as user_name',
                    'users.tenant_id as tenant_id',
                    'shifts.start_time',
                    'shifts.grace_minutes'
                )
                ->get();

            // Tenants with custom shifts OFF have no user_shifts rows — synthesise
            // one row per active user from the tenant's fixed company shift so
            // they still get missed-check-in alerts.
            $userShifts = $userShifts->concat($this->fixedShiftTargets($checkDate));

            $this->info('📊 Found ' . $userShifts->count() . ' users with shifts on ' . $checkDate->toDateString());
            Log::info('Users with shifts', [
                'date' => $checkDate->toDateString(),
                'count' => $userShifts->count()
            ]);

            $processed = 0;
            $notificationsSent = 0;
            $alreadyCheckedIn = 0;
            $notYetGracePassed = 0;
            $alreadyNotified = 0;
            $notificationFailed = 0;
            $notExpected = 0;

            foreach ($userShifts as $userShift) {
                $processed++;

                // Show progress
                $this->output->write("\rProcessing: $processed/" . $userShifts->count());

                if (!$userShift->tenant_id) {
                    Log::warning('Skipping user_shift — user has no tenant_id', [
                        'user_id' => $userShift->user_id,
                    ]);
                    continue;
                }

                // Check if user already checked in today — scoped to their tenant
                $attendance = DB::table('attendances')
                    ->where('user_id', $userShift->user_id)
                    ->where('tenant_id', $userShift->tenant_id)
                    ->whereDate('date', $checkDate)
                    ->whereNotNull('clock_in')
                    ->first();

                if ($attendance) {
                    // User already checked in
                    $alreadyCheckedIn++;
                    Log::info('User already checked in', [
                        'user_id' => $userShift->user_id,
                        'user_name' => $userShift->user_name,
                        'clock_in' => $attendance->clock_in
                    ]);
                    continue;
                }

                // Calculate shift start time with grace period
                $shiftStart = Carbon::parse($checkDate->format('Y-m-d') . ' ' . $userShift->start_time);
                $graceMinutes = $userShift->grace_minutes ?? 0;
                $graceEndTime = $shiftStart->copy()->addMinutes($graceMinutes);

                // Check if current time is past the grace period
                if ($currentTime->lte($graceEndTime)) {
                    $notYetGracePassed++;
                    continue;
                }

                // Already notified for this shift row? (persistent per-day flag on
                // user_shifts — survives cache flushes / the array cache driver.)
                if ($userShift->missed_checkin_notified && !$forceSend) {
                    $alreadyNotified++;
                    continue;
                }

                // On leave, a holiday or a week-off: nobody expects a clock-in.
                if ($reason = $this->notExpectedReason($userShift, $checkDate)) {
                    $notExpected++;
                    Log::info('Missed check-in skipped', ['user_id' => $userShift->user_id, 'reason' => $reason]);
                    continue;
                }

                // Send notifications — returns count of recipients actually notified
                $successCount = $this->sendMissedCheckInNotifications($userShift, $graceMinutes);

                if ($successCount > 0) {
                    $notificationsSent++;

                    // Only mark once something actually succeeded, so a fully
                    // failed run does not permanently block retries.
                    $this->markNotificationSent($userShift, $checkDate);

                    Log::warning('Missed check-in detected and notified', [
                        'user_id' => $userShift->user_id,
                        'user_name' => $userShift->user_name,
                        'tenant_id' => $userShift->tenant_id,
                        'shift_start' => $shiftStart->toDateTimeString(),
                        'grace_end' => $graceEndTime->toDateTimeString(),
                        'recipients_notified' => $successCount
                    ]);
                } else {
                    $notificationFailed++;

                    Log::error('Missed check-in detected but ALL notifications failed — not caching, will retry next run', [
                        'user_id' => $userShift->user_id,
                        'user_name' => $userShift->user_name,
                        'tenant_id' => $userShift->tenant_id,
                        'shift_start' => $shiftStart->toDateTimeString(),
                        'grace_end' => $graceEndTime->toDateTimeString()
                    ]);
                }
            }

            $this->line(''); // New line after progress indicator

            // Display summary
            $this->table(
                ['Metric', 'Count'],
                [
                    ['Total users with shifts', $userShifts->count()],
                    ['Already checked in', $alreadyCheckedIn],
                    ['Grace period not passed', $notYetGracePassed],
                    ['Already notified today', $alreadyNotified],
                    ['New notifications sent', $notificationsSent],
                    ['Notification send failed', $notificationFailed],
                    ['On leave / holiday / week-off', $notExpected],
                    ['Processed', $processed],
                ]
            );

            $this->info('✅ Missed check-in check completed successfully');
            Log::info('Missed check-in check completed', [
                'date' => $checkDate->toDateString(),
                'total' => $userShifts->count(),
                'already_checked_in' => $alreadyCheckedIn,
                'not_yet_grace_passed' => $notYetGracePassed,
                'already_notified' => $alreadyNotified,
                'notifications_sent' => $notificationsSent,
                'notification_failed' => $notificationFailed,
                'not_expected' => $notExpected,
                'processed' => $processed
            ]);

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("\n❌ Error: " . $e->getMessage());
            Log::error('Missed check-in check failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return Command::FAILURE;
        }
    }

    /**
     * Mark this shift row as notified (persistent per-day dedupe). Real
     * user_shifts rows use their column; synthesised fixed-shift rows
     * (user_shift_id === null) use a per-day cache key instead.
     */
    private function markNotificationSent($userShift, Carbon $checkDate)
    {
        if (!empty($userShift->user_shift_id)) {
            DB::table('user_shifts')
                ->where('id', $userShift->user_shift_id)
                ->update([
                    'missed_checkin_notified' => 1,
                    'missed_checkin_notified_at' => now(),
                    'updated_at' => now(),
                ]);

            Log::info('Marked missed check-in notification as sent', ['user_shift_id' => $userShift->user_shift_id]);
            return;
        }

        Cache::put(
            $this->fixedShiftDedupeKey($userShift->tenant_id, $userShift->user_id, $checkDate),
            1,
            now()->addDay()->startOfDay()
        );
        Log::info('Marked missed check-in notification as sent (fixed shift)', [
            'tenant_id' => $userShift->tenant_id,
            'user_id' => $userShift->user_id,
        ]);
    }

    private function fixedShiftDedupeKey($tenantId, $userId, Carbon $checkDate): string
    {
        return "missed_checkin:{$tenantId}:{$userId}:{$checkDate->toDateString()}";
    }

    /**
     * Synthesise a shift row per active user for every tenant that has custom
     * shifts OFF and a configured default shift. Matches the stdClass shape of
     * the user_shifts query in handle().
     */
    private function fixedShiftTargets(Carbon $checkDate)
    {
        $rows = collect();

        $tenants = DB::table('tenants')
            ->where('custom_shifts_enabled', 0)
            ->whereNotNull('default_shift_id')
            ->get(['id', 'default_shift_id']);

        foreach ($tenants as $tenant) {
            $shift = DB::table('shifts')
                ->where('id', $tenant->default_shift_id)
                ->where('tenant_id', $tenant->id)
                ->first(['start_time', 'grace_minutes']);

            if (!$shift || !$shift->start_time) {
                continue;
            }

            $users = DB::table('users')
                ->where('tenant_id', $tenant->id)
                ->where('status', 1)
                ->get(['id', 'name']);

            foreach ($users as $user) {
                $rows->push((object) [
                    'user_shift_id' => null,
                    'user_id' => $user->id,
                    'missed_checkin_notified' => Cache::has(
                        $this->fixedShiftDedupeKey($tenant->id, $user->id, $checkDate)
                    ) ? 1 : 0,
                    'user_name' => $user->name,
                    'tenant_id' => $tenant->id,
                    'start_time' => $shift->start_time,
                    'grace_minutes' => $shift->grace_minutes ?? 0,
                ]);
            }
        }

        return $rows;
    }

    /**
     * Send notifications to user, HR, admin, manager, and reporting head.
     * $userShift here is a stdClass row from the DB::table() query above.
     *
     * @return int Number of recipients successfully notified.
     */
    private function sendMissedCheckInNotifications($userShift, $graceMinutes)
    {
        $this->line("   📨 Sending notifications for: {$userShift->user_name}");

        // Get recipient user IDs via raw table queries, strictly scoped
        // to this user's tenant_id on every single lookup.
        $recipientIds = $this->getNotificationRecipientIds($userShift->user_id, $userShift->tenant_id);

        if ($recipientIds->isEmpty()) {
            Log::warning('No recipient IDs resolved for missed check-in notification', [
                'user_id' => $userShift->user_id,
                'tenant_id' => $userShift->tenant_id
            ]);
            $this->line("      ⚠️ No recipients found");
            return 0;
        }

        // Hydrate actual notifiable User models for the resolved recipient
        // IDs only. withoutGlobalScopes() ensures no implicit tenant scope
        // interferes — tenant filtering was already done explicitly above
        // via raw `users` table queries, and we additionally re-verify
        // tenant_id here as a defense-in-depth check before notifying.
        $recipients = User::withoutGlobalScopes()
            ->whereIn('id', $recipientIds)
            ->where('tenant_id', $userShift->tenant_id)
            ->get();

        Log::info('Sending missed check-in notifications', [
            'about_user' => $userShift->user_id,
            'tenant_id' => $userShift->tenant_id,
            'recipient_ids_resolved' => $recipientIds->toArray(),
            'recipient_count_hydrated' => $recipients->count(),
            'recipients' => $recipients->pluck('id')->toArray()
        ]);

        if ($recipients->isEmpty()) {
            Log::warning('Recipient IDs resolved but none hydrated as valid users — check tenant_id consistency', [
                'user_id' => $userShift->user_id,
                'tenant_id' => $userShift->tenant_id,
                'attempted_ids' => $recipientIds->toArray()
            ]);
            $this->line("      ⚠️ Recipient IDs found but hydration returned 0 users");
            return 0;
        }

        $successCount = 0;
        $failureCount = 0;

        foreach ($recipients as $recipient) {
            try {
                $recipient->notify(new MissedCheckInNotification(
                    $userShift->user_id,
                    $userShift,
                    $graceMinutes
                ));

                $successCount++;

                Log::info('Missed check-in notification sent', [
                    'to_user_id' => $recipient->id,
                    'to_user_name' => $recipient->name,
                    'to_user_role' => $recipient->role,
                    'to_tenant_id' => $recipient->tenant_id,
                    'about_user_id' => $userShift->user_id
                ]);
            } catch (\Exception $e) {
                $failureCount++;
                Log::error('Failed to send missed check-in notification', [
                    'recipient_id' => $recipient->id,
                    'about_user_id' => $userShift->user_id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }

        $this->line("      ✅ Sent to $successCount recipients" . ($failureCount > 0 ? ", Failed: $failureCount" : ""));

        return $successCount;
    }

    /**
     * Get all notification recipient IDs for a user — strictly scoped to
     * $tenantId on every query. Uses DB::table('users') (not the Eloquent
     * User model) so there is zero dependency on any tenant global scope,
     * which cannot reliably resolve a tenant outside an authenticated
     * request context.
     */
    private function getNotificationRecipientIds($userId, $tenantId)
    {
        $ids = collect([$userId]);

        // 1. Reporting heads (same tenant only)
        $ids = $ids->merge($this->getReportingHeadIds($userId, $tenantId));

        // 2. HR users (same tenant only)
        $hrIds = DB::table('users')
            ->where('status', 1)
            ->where('tenant_id', $tenantId)
            ->where(function ($q) {
                $q->where('role', 'hr')
                  ->orWhere('role', 'HR')
                  ->orWhere('role', 'Hr')
                  ->orWhereRaw('LOWER(role) = ?', ['hr'])
                  ->orWhereRaw('LOWER(role) = ?', ['human resource'])
                  ->orWhereRaw('LOWER(role) = ?', ['human resources']);
            })
            ->pluck('id');
        $ids = $ids->merge($hrIds);

        // 3. Admins (same tenant only)
        $adminIds = DB::table('users')
            ->where('status', 1)
            ->where('tenant_id', $tenantId)
            ->where('role', 'admin')
            ->pluck('id');
        $ids = $ids->merge($adminIds);

        // Managers are reached through step 1 (the employee's own reporting heads) —
        // not every manager in the company.

        return $ids->unique()->values();
    }

    /**
     * Why this employee is not expected to clock in on $date (approved leave
     * covering the morning, company holiday, or week-off), or null.
     */
    private function notExpectedReason($userShift, Carbon $date): ?string
    {
        $day = $date->toDateString();

        // Approved leave that covers the shift start. A leave that only starts in the
        // second half on this day (start_session = session2) still expects a morning clock-in.
        $onLeave = DB::table('leaves')
            ->where('tenant_id', $userShift->tenant_id)
            ->where('user_id', $userShift->user_id)
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $day)
            ->whereDate('end_date', '>=', $day)
            ->where(fn ($q) => $q->whereDate('start_date', '<', $day)->orWhere('start_session', '!=', 'session2')->orWhereNull('start_session'))
            ->exists();
        if ($onLeave) {
            return 'leave';
        }

        $holiday = DB::table('holidays')
            ->where('tenant_id', $userShift->tenant_id)
            ->where('status', 1)
            ->whereDate('start_date', '<=', $day)
            ->whereRaw('DATE(COALESCE(end_date, start_date)) >= ?', [$day])
            ->exists();
        if ($holiday) {
            return 'holiday';
        }

        $weekoffs = \App\Models\UserWeekoffs::withoutGlobalScopes()
            ->where('tenant_id', $userShift->tenant_id)
            ->where('user_id', $userShift->user_id)
            ->where('status', 1)
            ->get();

        return \App\Support\WeekOffPredicate::isWeekOff($weekoffs, $date) ? 'week_off' : null;
    }

    /**
     * Get all of a user's reporting heads' ids (multi reporting-head
     * support), scoped to the same tenant on both the pivot lookup and the
     * resulting user lookup.
     */
    private function getReportingHeadIds($userId, $tenantId)
    {
        $headIds = DB::table('user_reporting_heads')
            ->where('user_id', $userId)
            ->where('tenant_id', $tenantId)
            ->pluck('reporting_head_id');

        if ($headIds->isEmpty()) {
            return collect();
        }

        return DB::table('users')
            ->whereIn('id', $headIds)
            ->where('tenant_id', $tenantId)
            ->where('status', 1)
            ->pluck('id');
    }
}