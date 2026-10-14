<?php

namespace App\Services\Payroll;

use App\Services\Attendance\OvertimePolicyService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Company Policies → "Additional shift pay" = Extra working day: every
 * EXTRA shift an employee worked (a shift beyond the day's normal one — see
 * AttendanceRollupService; working only the additional shift is the normal
 * day, not extra) is paid as an extra working day at one day's salary,
 * instead of as overtime hours
 * (App\Services\Attendance\ExtraShiftOvertime then pays nothing). Shared by
 * both payroll engines, like ShiftAllowanceCalculator.
 *
 *  - Days come from attendance_shift_segments (is_extra, worked_minutes
 *    > 0): 1 day per extra shift, ½ day when less than half of that
 *    shift's own hours were worked.
 *  - One day's salary = the full monthly gross (before proration) ÷ days in
 *    the month — the same per-day figure an absent day costs.
 *  - Paid as its own earning line; never added to payable days (those are
 *    capped at the month) and not a PF/ESI/PT base, like overtime.
 */
class ExtraWorkDayCalculator
{
    public function __construct(private OvertimePolicyService $policy)
    {
    }

    public function enabled(int $tenantId): bool
    {
        return $this->policy->paysExtraShiftAsDays($tenantId);
    }

    /**
     * @return array{days: float, details: array<int, array{date:string, shift_id:?int, worked_minutes:int, days:float}>}
     */
    public function days(int $tenantId, int $userId, string $yearMonth): array
    {
        if (! $this->enabled($tenantId)) {
            return ['days' => 0.0, 'details' => []];
        }

        $month = Carbon::createFromFormat('Y-m', $yearMonth);
        $rows = DB::table('attendance_shift_segments as s')
            ->join('attendances as a', 'a.id', '=', 's.attendance_id')
            ->where('a.tenant_id', $tenantId)
            ->where('a.user_id', $userId)
            ->whereBetween('a.date', [$month->copy()->startOfMonth()->toDateString(), $month->copy()->endOfMonth()->toDateString()])
            ->where('s.is_extra', 1)
            ->where('s.worked_minutes', '>', 0)
            ->orderBy('a.date')
            ->get(['a.date', 's.shift_id', 's.worked_minutes', 's.expected_minutes']);

        $details = $rows->map(fn ($r) => [
            'date' => substr((string) $r->date, 0, 10),
            'shift_id' => $r->shift_id ? (int) $r->shift_id : null,
            'worked_minutes' => (int) $r->worked_minutes,
            'days' => self::dayValue((int) $r->worked_minutes, (int) $r->expected_minutes),
        ])->all();

        return ['days' => (float) array_sum(array_column($details, 'days')), 'details' => $details];
    }

    /**
     * Extra working days per employee for a date range (monthly overtime
     * report), same ½ / 1 day rule; empty when 2nd shifts are paid as overtime.
     *
     * @return array<int, float>  user_id => days
     */
    public function daysByUser(int $tenantId, string $startDate, string $endDate): array
    {
        if (! $this->enabled($tenantId)) {
            return [];
        }

        return DB::table('attendance_shift_segments as s')
            ->join('attendances as a', 'a.id', '=', 's.attendance_id')
            ->where('a.tenant_id', $tenantId)
            ->whereBetween('a.date', [$startDate, $endDate])
            ->where('s.is_extra', 1)
            ->where('s.worked_minutes', '>', 0)
            ->groupBy('a.user_id')
            ->selectRaw('a.user_id AS uid, SUM(CASE WHEN s.expected_minutes > 0 AND s.worked_minutes * 2 < s.expected_minutes THEN 0.5 ELSE 1 END) AS days')
            ->pluck('days', 'uid')
            ->map(fn ($d) => (float) $d)
            ->all();
    }

    /**
     * @return array{days: float, rate: float, amount: float, label: string}
     */
    public function calculate(int $tenantId, int $userId, string $yearMonth, float $monthlyGross): array
    {
        $days = $this->days($tenantId, $userId, $yearMonth)['days'];
        $rate = self::dayRate($monthlyGross, Carbon::createFromFormat('Y-m', $yearMonth)->daysInMonth);

        return ['days' => $days, 'rate' => $rate, 'amount' => round($days * $rate, 2), 'label' => self::label($days)];
    }

    /** 1 day, or ½ when less than half the shift's own hours were worked. */
    public static function dayValue(int $workedMinutes, int $expectedMinutes): float
    {
        return $expectedMinutes > 0 && $workedMinutes * 2 < $expectedMinutes ? 0.5 : 1.0;
    }

    /** One day's salary: full monthly gross ÷ days in the month. */
    public static function dayRate(float $monthlyGross, int $daysInMonth): float
    {
        return $daysInMonth > 0 ? round($monthlyGross / $daysInMonth, 2) : 0.0;
    }

    /** "Extra Working Days (2 days)" — the payslip line. */
    public static function label(float $days): string
    {
        $n = rtrim(rtrim(number_format($days, 1), '0'), '.');

        return 'Extra Working Days (' . $n . ' ' . ($n === '1' ? 'day' : 'days') . ')';
    }
}
