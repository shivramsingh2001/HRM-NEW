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
    public function __construct(
        private AttendanceCalculator $calc,
        private PolicyResolver $policies,
    ) {
    }

    /**
     * Recompute effective_status / day_fraction for every attendance row of one
     * user in one calendar month.
     */
    public function recalculateMonth(int $userId, int $tenantId, string $yearMonth): void
    {
        $start = Carbon::parse($yearMonth . '-01')->startOfMonth()->format('Y-m-d');
        $end = Carbon::parse($yearMonth . '-01')->endOfMonth()->format('Y-m-d');

        // Anchor to the policy in force on the 1st of the month so a later-dated
        // policy change never retroactively re-grades a closed month.
        $policy = $this->policies->forTenantMonth($tenantId, $yearMonth);
        $enabled = $policy->lateHalfdayEnabled;
        $allowance = $policy->monthlyLateAllowance;

        $rows = DB::table('attendances')
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->whereBetween('date', [$start, $end])
            ->orderBy('date')
            ->orderBy('clock_in')
            ->get();

        DB::transaction(function () use ($rows, $enabled, $allowance, $policy) {
            $this->applyRows($rows, $enabled, $allowance, $policy);
        });
    }

    /**
     * @param  \Illuminate\Support\Collection  $rows
     */
    private function applyRows($rows, bool $enabled, int $allowance, AttendancePolicySnapshot $policy): void
    {
        $lateSeen = 0;

        foreach ($rows as $row) {
            [$base, $fraction] = $this->baseStatus($row, $policy);

            $effective = $base;
            $note = null;

            $isLate = ($base === 'late');

            // A regularized row is no longer "late" — it does not consume the
            // allowance and is never downgraded.
            if ($isLate && !$row->is_regularized) {
                $lateSeen++;

                if ($enabled && $lateSeen > $allowance) {
                    $effective = 'half_day';
                    $fraction = 0.50;
                    $note = "late #{$lateSeen} exceeds monthly allowance of {$allowance} → half day";
                } elseif ($enabled) {
                    $note = "late #{$lateSeen} within monthly allowance of {$allowance}";
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
    private function baseStatus($row, AttendancePolicySnapshot $policy): array
    {
        $status = $row->attendance_status;

        // A row set by hand (admin / HR / manager via the mark-attendance
        // screen) is authoritative — never reclassify it from worked hours.
        $isManual = ($row->attendance_type === 'manual') || !empty($row->marked_by);
        if ($isManual) {
            return match ($status) {
                'on_leave', 'absent', 'holiday', 'weekoff' => [$status, 0.00],
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

        $class = $policy->classify($hours, $expected);

        if ($class === 'absent') {
            return ['absent', 0.00];
        }
        if ($class === 'half_day') {
            return ['half_day', 0.50];
        }

        // Full day worked — was the arrival late (past the grace window)?
        $isLate = ($status === 'late') || $policy->isLate((int) ($row->late_minutes ?? 0));

        return [$isLate ? 'late' : ($status === 'overtime' ? 'overtime' : 'present'), 1.00];
    }
}
