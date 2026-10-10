<?php

namespace App\Services\Attendance;

use App\Services\AttendanceSummaryService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Re-grade saved attendance after a grading rule changed (e.g. Day
 * Classification switched on / off): recompute every employee's
 * effective_status / day_fraction (LatePolicyService) and monthly summary
 * from $from to today, month by month.
 *
 * Never touches a locked attendance month, nor an employee's month whose
 * payslip is already processed / paid — those are reported as skipped.
 */
class AttendanceRegradeService
{
    public function __construct(
        private LatePolicyService $latePolicy,
        private AttendanceSummaryService $summary,
        private PeriodLockService $locks,
    ) {
    }

    /** @return array{months:int, employee_months:int, skipped_locked:string[], skipped_paid:int} */
    public function regrade(int $tenantId, Carbon $from, ?Carbon $to = null): array
    {
        $to = ($to ?? Carbon::today())->copy()->endOfMonth();
        $result = ['months' => 0, 'employee_months' => 0, 'skipped_locked' => [], 'skipped_paid' => 0];

        for ($m = $from->copy()->startOfMonth(); $m->lte($to); $m->addMonth()) {
            $ym = $m->format('Y-m');
            if ($this->locks->isLocked($tenantId, $ym)) {
                $result['skipped_locked'][] = $ym;
                continue;
            }
            $result['months']++;

            $paid = DB::table('monthly_payrolls')->where('tenant_id', $tenantId)->where('payroll_month', $ym)
                ->whereIn('payment_status', ['processed', 'paid'])->pluck('user_id')->map(fn ($i) => (int) $i)->all();

            $userIds = DB::table('attendances')->where('tenant_id', $tenantId)
                ->whereBetween('date', [$m->copy()->startOfMonth()->toDateString(), $m->copy()->endOfMonth()->toDateString()])
                ->distinct()->pluck('user_id');

            foreach ($userIds as $userId) {
                if (in_array((int) $userId, $paid, true)) {
                    $result['skipped_paid']++;
                    continue;
                }
                $this->latePolicy->recalculateMonth((int) $userId, $tenantId, $ym);
                $this->summary->updateMonthlySummary((int) $userId, $ym, $tenantId);
                $result['employee_months']++;
            }
        }

        return $result;
    }
}
