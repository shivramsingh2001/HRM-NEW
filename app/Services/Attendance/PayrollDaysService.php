<?php

namespace App\Services\Attendance;

use App\Services\AttendanceSummaryService;

/**
 * Tier 2 / T2-E — the one attested "payroll days" figure for a user-month.
 *
 * Payroll must consume this instead of recomputing attendance itself, so the
 * numbers on the payslip always match the attendance module.
 */
class PayrollDaysService
{
    public function __construct(private AttendanceSummaryService $summary)
    {
    }

    /**
     * @return array{
     *   payable_days: float, present_days: float, absent_days: float,
     *   half_days: float, paid_leave_days: float, unpaid_leave_days: float,
     *   holidays: float, week_offs: float, overtime_hours: float,
     *   actual_worked_hours: float, total_late_minutes: float,
     *   source_calculated_at: ?string
     * }
     */
    public function forMonth(int $userId, string $yearMonth, ?int $tenantId = null): array
    {
        $m = $this->summary->getMonthly($userId, $yearMonth, $tenantId);

        $present = (float) ($m['present_days'] ?? 0);
        $half = (float) ($m['half_days'] ?? 0);
        $paidLeave = (float) ($m['paid_leaves'] ?? 0);
        $unpaidLeave = (float) ($m['unpaid_leaves'] ?? 0);
        $holidays = (float) ($m['holidays'] ?? 0);
        $weekOffs = (float) ($m['week_offs'] ?? 0);
        $holidayWork = (float) ($m['holiday_work_days'] ?? 0);
        $weekoffWork = (float) ($m['weekoff_work_days'] ?? 0);

        // Paid days = worked (full + half) + paid leave + holidays + week-offs
        // + any holiday/week-off actually worked. LWP (unpaid leave) and plain
        // absence are not paid.
        $payable = $present + 0.5 * $half + $paidLeave + $holidays + $weekOffs + $holidayWork + $weekoffWork;

        return [
            'payable_days' => round($payable, 2),
            'present_days' => $present,
            'absent_days' => (float) ($m['absent_days'] ?? 0),
            'half_days' => $half,
            'paid_leave_days' => $paidLeave,
            'unpaid_leave_days' => $unpaidLeave,
            'holidays' => $holidays,
            'week_offs' => $weekOffs,
            'overtime_hours' => (float) ($m['total_overtime_hours'] ?? 0),
            'actual_worked_hours' => (float) ($m['total_worked_hours'] ?? 0),
            'total_late_minutes' => (float) ($m['total_late_minutes'] ?? 0),
            'source_calculated_at' => $m['calculated_at'] ?? null,
        ];
    }
}
