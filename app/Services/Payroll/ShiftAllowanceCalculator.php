<?php

namespace App\Services\Payroll;

use App\Services\Attendance\TenantShiftResolver;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Shift allowance (Shifts → allowance per day / per hour) for one employee's
 * month — shared by both payroll engines, like LateEarlyDeductionCalculator.
 *
 * Walks the month's attendance days. A day pays for every shift actually
 * worked on it: multi-shift days use attendance_shift_segments (one row per
 * shift worked, with its own minutes), every other day the attendance row's
 * shift (else the shift the employee was rostered on that day). Because the
 * shift comes from what was worked / rostered on the day, swapped, changed,
 * overridden and rotating days are paid for the shift they really had.
 *
 * A shift pays only when:
 *  - the day's final status is a worked one — present / late / overtime /
 *    early departure pay in full, half day and half-day leave pay 50%;
 *    absent, leave, holiday and week-off rows pay nothing;
 *  - the hours worked in that shift reach its allowance_min_hours (if set).
 * Per hour = amount × hours worked in the shift, capped at the shift's own
 * scheduled hours (extra time is overtime, paid separately).
 */
class ShiftAllowanceCalculator
{
    private const FULL = ['present', 'late', 'overtime', 'early_departure'];
    private const HALF = ['half_day', 'first_half_leave', 'second_half_leave'];

    public function __construct(private TenantShiftResolver $shifts)
    {
    }

    /**
     * @return array{days: float, hours: float, amount: float, label: string,
     *               breakdown: array<int, array{shift_id:int, name:string, type:string, rate:float, days:float, hours:float, amount:float}>}
     */
    public function calculate(int $tenantId, int $userId, string $yearMonth): array
    {
        $empty = ['days' => 0.0, 'hours' => 0.0, 'amount' => 0.0, 'label' => 'Shift Allowance', 'breakdown' => []];

        $paying = DB::table('shifts')->where('tenant_id', $tenantId)
            ->where('allowance_type', '!=', 'none')->where('allowance_amount', '>', 0)
            ->get(['id', 'name', 'allowance_type', 'allowance_amount', 'allowance_min_hours', 'total_hours'])
            ->keyBy('id');
        if ($paying->isEmpty()) {
            return $empty;
        }

        $month = Carbon::createFromFormat('Y-m', $yearMonth);
        $days = DB::table('attendances')->where('tenant_id', $tenantId)->where('user_id', $userId)
            ->whereBetween('date', [$month->copy()->startOfMonth()->toDateString(), $month->copy()->endOfMonth()->toDateString()])
            ->get(['id', 'date', 'shift_id', 'worked_hours', 'attendance_status', 'effective_status']);
        if ($days->isEmpty()) {
            return $empty;
        }

        $segments = DB::table('attendance_shift_segments')->whereIn('attendance_id', $days->pluck('id'))
            ->get(['attendance_id', 'shift_id', 'worked_minutes'])->groupBy('attendance_id');

        $lines = [];
        foreach ($days as $day) {
            $status = $day->effective_status ?: $day->attendance_status;
            $factor = in_array($status, self::FULL, true) ? 1.0 : (in_array($status, self::HALF, true) ? 0.5 : 0.0);
            if ($factor === 0.0) {
                continue;
            }

            // [shift_id, hours worked in it] for each shift worked that day.
            $worked = $segments->has($day->id)
                ? $segments[$day->id]->map(fn ($s) => [(int) $s->shift_id, $s->worked_minutes / 60])->all()
                : [[(int) ($day->shift_id ?: $this->shifts->forUserDate($userId, $tenantId, substr((string) $day->date, 0, 10))?->id), (float) $day->worked_hours]];

            foreach ($worked as [$shiftId, $hours]) {
                $shift = $paying->get($shiftId);
                if (! $shift || ($shift->allowance_min_hours !== null && $hours < (float) $shift->allowance_min_hours)) {
                    continue;
                }

                $rate = (float) $shift->allowance_amount;
                $paidHours = (float) $shift->total_hours > 0 ? min($hours, (float) $shift->total_hours) : $hours;
                $amount = $shift->allowance_type === 'per_hour' ? $rate * $paidHours : $rate * $factor;

                $line = &$lines[$shiftId];
                $line ??= ['shift_id' => $shiftId, 'name' => $shift->name, 'type' => $shift->allowance_type, 'rate' => $rate, 'days' => 0.0, 'hours' => 0.0, 'amount' => 0.0];
                $line['days'] += $factor;
                $line['hours'] += $paidHours;
                $line['amount'] += $amount;
                unset($line);
            }
        }

        if (! $lines) {
            return $empty;
        }

        $lines = array_values(array_map(fn ($l) => array_merge($l, [
            'hours' => round($l['hours'], 2), 'amount' => round($l['amount'], 2),
        ]), $lines));

        return [
            'days' => (float) array_sum(array_column($lines, 'days')),
            'hours' => round(array_sum(array_column($lines, 'hours')), 2),
            'amount' => round(array_sum(array_column($lines, 'amount')), 2),
            'label' => self::label($lines),
            'breakdown' => $lines,
        ];
    }

    /** "Shift Allowance (Night × 12 days)", several shifts comma-separated. */
    public static function label(array $breakdown): string
    {
        $parts = array_map(function ($l) {
            $n = rtrim(rtrim(number_format($l['days'], 1), '0'), '.');

            return $l['name'] . ' × ' . ($l['type'] === 'per_hour'
                ? rtrim(rtrim(number_format($l['hours'], 2), '0'), '.') . ' h'
                : $n . ' ' . ($n === '1' ? 'day' : 'days'));
        }, $breakdown);

        return 'Shift Allowance' . ($parts ? ' (' . implode(', ', $parts) . ')' : '');
    }
}
