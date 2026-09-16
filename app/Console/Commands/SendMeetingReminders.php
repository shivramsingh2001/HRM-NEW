<?php

namespace App\Console\Commands;

use App\Services\MeetingNotificationService;
use Illuminate\Console\Command;

/**
 * Fires meeting reminders for every tenant. reminder_minutes_before/
 * reminder_sent columns on `meetings` existed before this command did —
 * nothing ever called MeetingNotificationService::sendMeetingReminders()
 * until now. Runs frequently (see routes/console.php) since reminder
 * windows are minute-granular, not daily like task deadlines; dedup is
 * `reminder_sent`, flipped to true by the service once a reminder actually
 * goes out for a meeting, so re-running this on a tight schedule is safe.
 *
 * No tenant context is bound when the scheduler runs this, so Meeting's
 * TenantTrait global scope no-ops (see TenantTrait::bootTenantTrait) and
 * the underlying query naturally covers every tenant in one pass — no
 * per-tenant loop needed here, unlike commands that must fall back to
 * DB::table() to work around a stricter scope.
 */
class SendMeetingReminders extends Command
{
    protected $signature = 'meetings:send-reminders';

    protected $description = 'Send reminder notifications for meetings starting within their reminder window';

    public function handle(MeetingNotificationService $service): int
    {
        $count = $service->sendMeetingReminders();

        $this->info("Sent reminders for {$count} meeting(s).");

        return self::SUCCESS;
    }
}
