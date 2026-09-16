<?php

namespace App\Console\Commands;

use App\Events\AttendanceDomainEvent;
use App\Services\Attendance\PeriodLockService;
use Illuminate\Console\Command;

/**
 * Tier 2 / T2-E — lock or reopen a tenant's attendance for a month.
 *
 *   php artisan attendance:period 7 2026-08 --lock --reason="August payroll run"
 *   php artisan attendance:period 7 2026-08 --reopen --reason="correction needed"
 */
class AttendancePeriodLock extends Command
{
    protected $signature = 'attendance:period
                            {tenant : tenant id}
                            {month : Y-m}
                            {--lock : lock the period (default)}
                            {--reopen : reopen a locked period}
                            {--reason= : recorded on the lock row}';

    protected $description = 'Lock or reopen an attendance month for a tenant (payroll period control).';

    public function handle(PeriodLockService $locks): int
    {
        $tenantId = (int) $this->argument('tenant');
        $month = $this->argument('month');

        if (! preg_match('/^\d{4}-\d{2}$/', $month)) {
            $this->error('month must be Y-m, e.g. 2026-08');

            return self::FAILURE;
        }

        $reason = $this->option('reason');

        if ($this->option('reopen')) {
            $locks->reopen($tenantId, $month, null, $reason);
            $this->info("Reopened {$month} for tenant {$tenantId}.");

            return self::SUCCESS;
        }

        $locks->lock($tenantId, $month, null, $reason);
        event(new AttendanceDomainEvent('attendance.month_finalised', $tenantId, [
            'year_month' => $month,
            'reason' => $reason,
        ]));
        $this->info("Locked {$month} for tenant {$tenantId}. Attendance edits now require an override.");

        return self::SUCCESS;
    }
}
