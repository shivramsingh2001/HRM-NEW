<?php

namespace App\Services\Attendance;

use App\Models\AttendancePolicy;
use Carbon\Carbon;

/**
 * The one way the Company Policies attendance cards (Day Classification,
 * Late Arrival, Early Leaving, Working-time thresholds) save: write a
 * versioned attendance_policies row that carries the rest of the policy
 * forward, then re-grade the saved days so the change shows at once.
 *
 *   - $applyFrom null  → effective today.
 *   - $applyFrom earlier → effective from that day; policy versions saved
 *     after it get the same $fields too, so the change holds through today.
 *
 * Days before the effective date are never re-graded with the new values
 * (LatePolicyService grades each day with the policy in force on that day).
 */
class AttendancePolicyWriter
{
    public function __construct(
        private PolicyResolver $resolver,
        private AttendanceRegradeService $regrade,
    ) {
    }

    /** @return array{effective_from:string, regrade:array} */
    public function save(int $tenantId, array $fields, ?string $applyFrom, ?int $actorId, bool $regrade = true): array
    {
        $today = now()->format('Y-m-d');
        $effectiveFrom = $applyFrom ? Carbon::parse($applyFrom)->format('Y-m-d') : $today;
        $current = $this->resolver->forTenantDate($tenantId, $effectiveFrom);

        AttendancePolicy::updateOrCreate(
            ['tenant_id' => $tenantId, 'effective_from' => $effectiveFrom],
            array_merge($current->toPersistableArray(), $fields, ['created_by' => $actorId])
        );

        if ($effectiveFrom < $today) {
            AttendancePolicy::where('tenant_id', $tenantId)->where('effective_from', '>', $effectiveFrom)->update($fields);
        }

        $this->resolver->forget();

        $result = $regrade
            ? $this->regrade->regrade($tenantId, Carbon::parse($effectiveFrom))
            : ['months' => 0, 'employee_months' => 0, 'skipped_locked' => [], 'skipped_paid' => 0];

        return ['effective_from' => $effectiveFrom, 'regrade' => $result];
    }

    /** "… from 09 Oct 2026. Re-graded 12 employee-month(s). …" for the flash message. */
    public static function summary(array $saved): string
    {
        $r = $saved['regrade'];
        $msg = ' from ' . Carbon::parse($saved['effective_from'])->format('d M Y') . '.';
        if ($r['employee_months']) {
            $msg .= " Re-graded {$r['employee_months']} employee-month(s) of saved attendance.";
        }
        if ($r['skipped_locked']) {
            $msg .= ' Locked month(s) not changed: ' . implode(', ', $r['skipped_locked']) . '.';
        }
        if ($r['skipped_paid']) {
            $msg .= " {$r['skipped_paid']} employee-month(s) with a processed / paid payslip were not changed.";
        }

        return $msg;
    }
}
