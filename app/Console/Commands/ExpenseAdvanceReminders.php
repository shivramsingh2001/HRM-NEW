<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\ExpenseAdvanceReminderNotification;
use App\Services\Expense\ExpenseAgeingService;
use App\Services\ExpensePaymentNotificationService;
use App\Services\FeatureService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Nudges employees who still hold an UNSPENT advance older than N days (default 30) to settle it.
 *
 *  - Age comes from ExpenseAgeingService (the oldest unspent lot of the employee's ledger, FIFO).
 *  - Idempotent by design: an employee reminded within the last `--every` days (default 7) is skipped
 *    (looked up in the notifications table), so it is safe to schedule daily or to re-run.
 *  - Only companies with the expense module switched on (feature `expense_management`).
 *  - `--dry-run` reports who WOULD be reminded and sends nothing.
 */
class ExpenseAdvanceReminders extends Command
{
    protected $signature = 'expense:advance-reminders
        {--days=30 : Remind when the oldest unspent advance is at least this many days old}
        {--every=7 : Do not remind the same person more often than every N days}
        {--tenant= : Only this tenant id}
        {--dry-run : List who would be reminded, send nothing}';

    protected $description = 'Remind employees to settle advances that have been unspent for too long';

    public function handle(ExpenseAgeingService $ageing, ExpensePaymentNotificationService $notifier, FeatureService $features): int
    {
        $days = max(1, (int) $this->option('days'));
        $every = max(1, (int) $this->option('every'));
        $dryRun = (bool) $this->option('dry-run');

        $tenantIds = DB::table('tenants')->when($this->option('tenant'), fn ($q, $t) => $q->where('id', $t))->pluck('id');

        $reminded = 0;
        $skipped = 0;

        foreach ($tenantIds as $tenantId) {
            if (! $features->enabled((int) $tenantId, 'expense_management')) {
                continue;
            }

            foreach ($ageing->snapshot((int) $tenantId)['rows'] as $row) {
                if ($row['oldest_days'] === null || $row['oldest_days'] < $days) {
                    continue;
                }

                $recently = DB::table('notifications')
                    ->where('type', ExpenseAdvanceReminderNotification::class)
                    ->where('notifiable_id', $row['user_id'])
                    ->where('created_at', '>=', now()->subDays($every))
                    ->exists();

                if ($recently) {
                    $skipped++;
                    continue;
                }

                $total = number_format($row['balance'], 2);
                $this->line(sprintf('%s#%d %s — ₹%s unspent, oldest %d day(s)', $dryRun ? '[dry] ' : '', $row['user_id'], $row['name'], $total, $row['oldest_days']));

                if (! $dryRun) {
                    $user = User::withoutGlobalScopes()->where('tenant_id', $tenantId)->find($row['user_id']);
                    if ($user && $user->status == 1) {
                        $notifier->notifyAdvanceReminder($user, $total, (int) $row['oldest_days']);
                    }
                }
                $reminded++;
            }
        }

        $this->info(sprintf('%s%d reminder(s)%s, %d skipped (reminded within %d day(s)).', $dryRun ? '[DRY RUN] ' : '', $reminded, $dryRun ? ' would be sent' : ' sent', $skipped, $every));

        return self::SUCCESS;
    }
}
