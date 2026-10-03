<?php

namespace App\Services\Attendance;

use Illuminate\Support\Facades\DB;

/**
 * Multi-shift: hours worked in additional (2nd+) shifts are paid as overtime
 * automatically — no overtime request needed. They sit on
 * attendances.extra_shift_minutes (written by AttendanceRollupService); both
 * payroll engines add them on top of approved overtime requests via this one
 * reader.
 */
class ExtraShiftOvertime
{
    /**
     * @return array{total_hours: float, details: array<int,array{date:string,hours:float,reason:string}>, day_count: int}
     */
    public function forPeriod(int $userId, int $tenantId, string $startDate, string $endDate): array
    {
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
