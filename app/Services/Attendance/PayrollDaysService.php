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
        $breakdown = $this->breakdown($userId, $yearMonth, (int) ($tenantId ?? ($m['tenant_id'] ?? 0)));

        // Sandwich leave (Company Policies → Working-time thresholds): a run of
        // week-offs / holidays with no worked or paid-leave day on either side
        // is not paid — same rule as the legacy engine (SandwichRule).
        [$unpaidHolidays, $unpaidWeekOffs] = $this->sandwichUnpaid($breakdown, $userId, (int) ($tenantId ?? ($m['tenant_id'] ?? 0)));
        $paidHolidays = max(0, $holidays - $unpaidHolidays);
        $paidWeekOffs = max(0, $weekOffs - $unpaidWeekOffs);

        // A holiday / week-off the employee worked is ALSO counted in
        // present_days (or half_days) by the attendance summary. The day is
        // paid once — as the holiday / week-off — so its worked credit is taken
        // back out (it used to be counted three times: present + holiday +
        // holiday_work, which inflated payable days and could hide absences
        // under the full-month cap). Extra pay for working it comes from
        // overtime / additional-shift pay, as in the legacy engine.
        $workedOffDayCredit = $this->workedOffDayCredit($breakdown, (float) ($m['holiday_work_days'] ?? 0) + (float) ($m['weekoff_work_days'] ?? 0));

        // Paid days = worked (full + half) + paid leave + paid holidays + paid
        // week-offs, each calendar day at most once. LWP (unpaid leave) and
        // plain absence are not paid.
        $payable = max(0, $present + 0.5 * $half - $workedOffDayCredit) + $paidLeave + $paidHolidays + $paidWeekOffs;

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
            'sandwich_unpaid_days' => $unpaidHolidays + $unpaidWeekOffs,
            // Worked holidays / week-offs already inside present_days / half_days (paid once, as the off-day).
            'worked_off_day_credit' => $workedOffDayCredit,
        ];
    }

    /** The attendance summary's day-by-day breakdown for the month (Y-m-d => day). */
    private function breakdown(int $userId, string $yearMonth, int $tenantId): array
    {
        if ($tenantId <= 0) {
            return [];
        }
        $meta = \Illuminate\Support\Facades\DB::table('attendance_summaries')
            ->where('tenant_id', $tenantId)->where('user_id', $userId)->where('year_month', $yearMonth)->value('metadata');

        return json_decode((string) $meta, true)['daily_breakdown'] ?? [];
    }

    /**
     * Present / half-day credit the summary gave to worked holidays and
     * week-offs (present = 1, half day = 0.5, too short = 0). A summary saved
     * before the grade was stored per day has no `worked_as`; then each worked
     * off-day is assumed graded present, which is how almost every one is.
     */
    private function workedOffDayCredit(array $breakdown, float $fallbackDays): float
    {
        if (! $breakdown) {
            return $fallbackDays;
        }
        $credit = 0.0;
        foreach ($breakdown as $d) {
            if (in_array($d['status'] ?? null, ['holiday', 'week_off'], true) && (float) ($d['worked_hours'] ?? 0) > 0) {
                $credit += match ($d['worked_as'] ?? 'present') {
                    'present' => 1.0,
                    'half_day' => 0.5,
                    default => 0.0,
                };
            }
        }

        return $credit;
    }

    /**
     * [unpaid holidays, unpaid week-offs] for the month under the sandwich
     * rule, from the day-by-day breakdown the attendance summary stores.
     * A holiday / week-off actually worked counts as a worked day.
     *
     * @return array{0:int,1:int}
     */
    private function sandwichUnpaid(array $breakdown, int $userId, int $tenantId): array
    {
        if ($tenantId <= 0 || ! $breakdown) {
            return [0, 0];
        }

        $days = [];
        foreach ($breakdown as $date => $d) {
            $status = $d['status'] ?? 'absent';
            $days[$date] = in_array($status, ['holiday', 'week_off'], true) && (float) ($d['worked_hours'] ?? 0) > 0 ? 'present' : $status;
        }

        $policies = app(PolicyResolver::class);
        $pay = SandwichRule::offDayPay($days, fn (string $date) => $policies->forUserDate($tenantId, $userId, $date)->sandwichLeave);

        $holidays = 0;
        $weekOffs = 0;
        foreach ($pay as $date => $paid) {
            if (! $paid) {
                $days[$date] === 'holiday' ? $holidays++ : $weekOffs++;
            }
        }

        return [$holidays, $weekOffs];
    }
}
