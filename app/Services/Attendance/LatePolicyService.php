<?php

namespace App\Services\Attendance;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Feature B — per-tenant monthly late-arrival allowance.
 *
 * Sole writer of attendances.effective_status / day_fraction / policy_note.
 *
 * recalculateMonth() is deterministic and idempotent: it derives everything
 * from the raw attendance rows + the tenant policy, so it can be re-run any
 * number of times (after a clock-in, after a regularization that clears a
 * late, nightly) and always converges to the same result.
 */
class LatePolicyService
{
    public function __construct(private AttendanceCalculator $calc)
    {
    }

    /**
     * Recompute effective_status / day_fraction for every attendance row of one
     * user in one calendar month.
     */
    public function recalculateMonth(int $userId, int $tenantId, string $yearMonth): void
    {
        $start = Carbon::parse($yearMonth . '-01')->startOfMonth()->format('Y-m-d');
        $end = Carbon::parse($yearMonth . '-01')->endOfMonth()->format('Y-m-d');

        $policy = DB::table('tenants')
            ->where('id', $tenantId)
            ->first(['late_halfday_enabled', 'monthly_late_allowance']);

        $enabled = (bool) ($policy->late_halfday_enabled ?? false);
        $allowance = $policy->monthly_late_allowance ?? null; // null = unlimited

        $rows = DB::table('attendances')
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->whereBetween('date', [$start, $end])
            ->orderBy('date')
            ->orderBy('clock_in')
            ->get();

        $lateSeen = 0;

        foreach ($rows as $row) {
            [$base, $fraction] = $this->baseStatus($row);

            $effective = $base;
            $note = null;

            $isLate = ($base === 'late');

            // A regularized row is no longer "late" — it does not consume the
            // allowance and is never downgraded.
            if ($isLate && !$row->is_regularized) {
                $lateSeen++;

                if ($enabled && $allowance !== null && $lateSeen > (int) $allowance) {
                    $effective = 'half_day';
                    $fraction = 0.50;
                    $note = "late #{$lateSeen} exceeds monthly allowance of {$allowance} → half day";
                } else {
                    $note = $enabled && $allowance !== null
                        ? "late #{$lateSeen} within monthly allowance of {$allowance}"
                        : null;
                }
            }

            DB::table('attendances')
                ->where('id', $row->id)
                ->update([
                    'effective_status' => $effective,
                    'day_fraction' => $fraction,
                    'policy_note' => $note,
                    'updated_at' => now(),
                ]);
        }
    }

    /**
     * Raw day status + day fraction for a single attendance row, before the
     * late allowance is applied.
     *
     * @return array{0:string,1:float}  [status, day_fraction]
     */
    private function baseStatus($row): array
    {
        $status = $row->attendance_status;

        // A row set by hand (admin / HR / manager via the mark-attendance
        // screen) is authoritative — never reclassify it from worked hours.
        $isManual = ($row->attendance_type === 'manual') || !empty($row->marked_by);
        if ($isManual) {
            return match ($status) {
                'on_leave', 'absent' => [$status, 0.00],
                'half_day', 'first_half_leave', 'second_half_leave' => [$status, 0.50],
                default => [$status ?: 'present', 1.00],
            };
        }

        // Auto-captured leave markings pass straight through too.
        if ($status === 'on_leave') {
            return ['on_leave', 0.00];
        }
        if (in_array($status, ['first_half_leave', 'second_half_leave'], true)) {
            return [$status, 0.50];
        }
        if ($status === 'absent') {
            return ['absent', 0.00];
        }

        $hasWork = $row->clock_in && $row->clock_out;

        if (!$hasWork) {
            // Incomplete / open row — leave the raw status, no worked fraction.
            return [$status ?: 'absent', $status === 'absent' ? 0.00 : 1.00];
        }

        $seconds = $this->calc->workedSeconds(Carbon::parse($row->clock_in), Carbon::parse($row->clock_out));
        $seconds = min($seconds, 24 * 3600);
        $hours = $seconds / 3600;

        $expected = 0;
        if ($row->scheduled_shift_start && $row->scheduled_shift_end) {
            $expected = $this->calc->expectedWorkSeconds([
                'start_time' => $row->scheduled_shift_start,
                'end_time' => $row->scheduled_shift_end,
            ]);
        }

        $class = $this->classify($hours, $expected);

        if ($class === 'absent') {
            return ['absent', 0.00];
        }
        if ($class === 'half_day') {
            return ['half_day', 0.50];
        }

        // Full day worked — was the arrival late?
        $isLate = ($status === 'late') || ((int) ($row->late_minutes ?? 0) > 0);

        return [$isLate ? 'late' : ($status === 'overtime' ? 'overtime' : 'present'), 1.00];
    }

    private function classify(float $hours, int $expectedSeconds): string
    {
        if ($expectedSeconds > 0) {
            $ratio = ($hours * 3600) / $expectedSeconds;
            if ($ratio >= (float) config('attendance.ratio.present', 0.9)) {
                return 'present';
            }
            return $ratio >= (float) config('attendance.ratio.half', 0.5) ? 'half_day' : 'absent';
        }

        if ($hours >= (float) config('attendance.fallback_hours.present', 8)) {
            return 'present';
        }
        return $hours >= (float) config('attendance.fallback_hours.half', 4) ? 'half_day' : 'absent';
    }
}
