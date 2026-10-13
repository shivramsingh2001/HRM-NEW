<?php

namespace App\Services\Attendance;

use Illuminate\Support\Facades\DB;

/**
 * Multi-shift: hours worked in additional (2nd+) shifts are paid as overtime
 * automatically — no overtime request needed. They sit on
 * attendances.extra_shift_minutes (written by AttendanceRollupService); both
 * payroll engines add them on top of approved overtime requests via this one
 * reader.
 *
 * When the company pays additional shifts as extra working days (Company
 * Policies → "Additional shift pay"), this returns nothing and
 * App\Services\Payroll\ExtraWorkDayCalculator pays them instead — never both.
 */
class ExtraShiftOvertime
{
    /**
     * Additional-shift overtime minutes per employee for a date range (monthly
     * overtime report); empty when the company pays 2nd shifts as extra days.
     *
     * @return \Illuminate\Support\Collection<int, int|string>  user_id => minutes
     */
    public function minutesByUser(int $tenantId, string $startDate, string $endDate)
    {
        if (app(OvertimePolicyService::class)->paysExtraShiftAsDays($tenantId)) {
            return collect();
        }

        return DB::table('attendances')
            ->where('tenant_id', $tenantId)
            ->whereBetween('date', [$startDate, $endDate])
            ->where('extra_shift_minutes', '>', 0)
            ->groupBy('user_id')
            ->pluck(DB::raw('SUM(extra_shift_minutes)'), 'user_id');
    }

    /**
     * @return array{total_hours: float, details: array<int,array{date:string,hours:float,reason:string}>, day_count: int}
     */
    public function forPeriod(int $userId, int $tenantId, string $startDate, string $endDate): array
    {
        if (app(OvertimePolicyService::class)->paysExtraShiftAsDays($tenantId)) {
            return ['total_hours' => 0.0, 'details' => [], 'day_count' => 0];
        }

        $rows = DB::table('attendances')
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->whereBetween('date', [$startDate, $endDate])
            ->where('extra_shift_minutes', '>', 0)
            ->orderBy('date')
            ->get(['date', 'extra_shift_minutes']);

        $details = $rows->map(fn ($r) => [
            'date' => substr((string) $r->date, 0, 10),
            'hours' => round(((int) $r->extra_shift_minutes) / 60, 2),
            'reason' => 'Additional shift (automatic overtime)',
        ])->all();

        return [
            'total_hours' => round($rows->sum('extra_shift_minutes') / 60, 2),
            'details' => $details,
            'day_count' => $rows->count(),
        ];
    }
}
