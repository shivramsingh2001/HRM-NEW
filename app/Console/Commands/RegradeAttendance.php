<?php

namespace App\Console\Commands;

use App\Services\Attendance\AttendanceRegradeService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Re-grade saved attendance (effective_status / day_fraction + monthly
 * summaries) from a date to today with the policy in force on each day —
 * e.g. after Day Classification was switched off and old half days / absents
 * stayed on the Team page. Locked months and processed / paid payslips are
 * skipped. Safe to run again (the calculation is idempotent).
 *
 *   php artisan attendance:regrade --from=2026-09-29           (every company)
 *   php artisan attendance:regrade 7 --from=2026-09-29         (one company)
 */
class RegradeAttendance extends Command
{
    protected $signature = 'attendance:regrade {tenant_id?* : Company id(s); all when omitted} {--from= : First day to re-grade (Y-m-d), required}';

    protected $description = 'Re-grade saved attendance days with the attendance policy in force on each day';

    public function handle(AttendanceRegradeService $regrade): int
    {
        if (! $this->option('from')) {
            $this->error('--from=Y-m-d is required.');

            return self::FAILURE;
        }
        $from = Carbon::parse($this->option('from'))->startOfDay();
        $tenants = $this->argument('tenant_id') ?: DB::table('tenants')->pluck('id')->all();

        foreach ($tenants as $tenantId) {
            $r = $regrade->regrade((int) $tenantId, $from);
            $this->line("Company {$tenantId}: {$r['employee_months']} employee-month(s) re-graded"
                . ($r['skipped_locked'] ? '; locked: ' . implode(', ', $r['skipped_locked']) : '')
                . ($r['skipped_paid'] ? "; {$r['skipped_paid']} skipped (payslip processed / paid)" : '') . '.');
        }

        return self::SUCCESS;
    }
}
