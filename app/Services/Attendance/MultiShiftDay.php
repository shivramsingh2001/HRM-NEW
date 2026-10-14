<?php

namespace App\Services\Attendance;

use App\Services\Payroll\ExtraWorkDayCalculator;
use Illuminate\Support\Facades\DB;

/**
 * Display data for days worked across several shifts (multi-shift pay plan
 * Phase 4) — shared by both attendance calendars, the mobile today/history API
 * and the Team page, so they all agree with payroll.
 *
 * The day's NORMAL shift (attendance_shift_segments.is_extra = 0, the first
 * shift worked) is the day; every other shift worked is EXTRA. When Company
 * Policies pays extra shifts as extra working days, each closed extra shift
 * adds 1 day (½ when under half its hours — ExtraWorkDayCalculator::dayValue),
 * so a day reads "Present ×2" / "×1.5". When extra shifts are paid as OT hours
 * nothing is added: the day is plain Present.
 */
class MultiShiftDay
{
    public function __construct(private OvertimePolicyService $overtime)
    {
    }

    /**
     * attendance_id => [normal_closed, extra_days, shifts[{user_shift_id, name, in, out, is_extra}]]
     * for days with 2+ shifts worked; other days are left out.
     *
     * @param  iterable<int>  $attendanceIds
     * @return array<int, array{normal_closed: bool, extra_days: float, shifts: array<int, array<string, mixed>>}>
     */
    public function forAttendances(int $tenantId, iterable $attendanceIds): array
    {
        $ids = collect($attendanceIds)->filter()->map(fn ($id) => (int) $id)->unique()->values();
        if ($ids->isEmpty()) {
            return [];
        }
        $paysDays = $this->overtime->paysExtraShiftAsDays($tenantId);

        return DB::table('attendance_shift_segments as s')
            ->leftJoin('shifts as sh', 'sh.id', '=', 's.shift_id')
            ->where('s.tenant_id', $tenantId)
            ->whereIn('s.attendance_id', $ids)
            ->orderBy('s.id')
            ->get(['s.attendance_id', 's.user_shift_id', 's.is_extra', 's.is_open', 's.first_in', 's.last_out', 's.worked_minutes', 's.expected_minutes', 'sh.name'])
            ->groupBy('attendance_id')
            ->filter(fn ($rows) => $rows->count() > 1)
            ->map(function ($rows) use ($paysDays) {
                $normal = $rows->first(fn ($s) => ! $s->is_extra);
                $extraDays = $paysDays
                    ? (float) $rows->filter(fn ($s) => $s->is_extra && ! $s->is_open && (int) $s->worked_minutes > 0)
                        ->sum(fn ($s) => ExtraWorkDayCalculator::dayValue((int) $s->worked_minutes, (int) $s->expected_minutes))
                    : 0.0;

                return [
                    'normal_closed' => $normal && ! $normal->is_open && $normal->last_out !== null,
                    'extra_days' => $extraDays,
                    'shifts' => $rows->map(fn ($s) => [
                        'user_shift_id' => $s->user_shift_id ? (int) $s->user_shift_id : null,
                        'name' => $s->name ?? 'Shift',
                        'in' => $s->first_in,
                        'out' => $s->is_open ? null : $s->last_out,
                        'is_extra' => (bool) $s->is_extra,
                    ])->values()->all(),
                ];
            })
            ->all();
    }

    /** The day's worth: the normal shift's (1 present / 0.5 half day) + extra working days. */
    public static function dayValue(float $base, ?array $day): float
    {
        return round($base + ($day['extra_days'] ?? 0.0), 2);
    }

    /** "Present ×2" / "Present ×1.5" for a day worth more than one; null otherwise. */
    public static function label(float $value): ?string
    {
        return $value > 1 ? 'Present ×' . rtrim(rtrim(number_format($value, 2), '0'), '.') : null;
    }
}
