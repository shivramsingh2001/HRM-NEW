<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\MissedCheckInNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
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
                ->select(
                    'user_shifts.id as user_shift_id',
                    'user_shifts.user_id',
                    'users.name as user_name',
                    'users.tenant_id as tenant_id',
                    'shifts.start_time',
                    'shifts.grace_minutes'
                )
                ->get();

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

                // Check if we've already sent a notification today (unless force send)
                $notificationSent = $this->checkIfNotificationSent($userShift->user_id, $checkDate);

                if ($notificationSent && !$forceSend) {
                    $alreadyNotified++;
                    continue;
                }

                // Send notifications — returns count of recipients actually notified
                $successCount = $this->sendMissedCheckInNotifications($userShift, $graceMinutes);

                if ($successCount > 0) {
                    $notificationsSent++;

                    // Only cache as "sent" once something actually succeeded.
                    // Prevents a failed run from permanently blocking retries
                    // for the rest of the day.
                    $this->markNotificationSent($userShift->user_id, $checkDate);

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
     * Check if notification was already sent for this user on this date
     */
    private function checkIfNotificationSent($userId, $date)
    {
        $cacheKey = "missed_checkin_{$userId}_{$date->format('Y-m-d')}";
        return Cache::has($cacheKey);
    }

    /**
     * Mark that notification was sent for this user on this date
     */
    private function markNotificationSent($userId, $date)
    {
        $cacheKey = "missed_checkin_{$userId}_{$date->format('Y-m-d')}";
        // Store for 24 hours (until end of day)
        $expiresAt = $date->copy()->endOfDay();
        Cache::put($cacheKey, true, $expiresAt);

        Log::info('Marked notification as sent', [
            'user_id' => $userId,
            'date' => $date->toDateString(),
            'cache_key' => $cacheKey,
            'expires_at' => $expiresAt->toDateTimeString()
        ]);
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

        // 1. Reporting head (same tenant only)
        $reportingHeadId = $this->getReportingHeadId($userId, $tenantId);
        if ($reportingHeadId) {
            $ids->push($reportingHeadId);
        }

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

        // 4. Managers (same tenant only, optional)
        $managerIds = DB::table('users')
            ->where('status', 1)
            ->where('tenant_id', $tenantId)
            ->where('role', 'manager')
            ->pluck('id');
        $ids = $ids->merge($managerIds);

        return $ids->unique()->values();
    }

    /**
     * Get the reporting head's user ID for a user, scoped to the same
     * tenant on BOTH the job-detail lookup and the resulting user lookup.
     */
    private function getReportingHeadId($userId, $tenantId)
    {
        $jobDetail = DB::table('user_job_details')
            ->where('user_id', $userId)
            ->where('tenant_id', $tenantId)
            ->first();

        if (!$jobDetail || !$jobDetail->reporting_head) {
            return null;
        }

        $reportingHead = DB::table('users')
            ->where('id', $jobDetail->reporting_head)
            ->where('tenant_id', $tenantId)
            ->where('status', 1)
            ->first();

        return $reportingHead->id ?? null;
    }
}