<?php

namespace App\Services\Attendance;

/**
 * Who / how / why an attendance row changed — passed to AttendanceEntryService
 * so it can write a meaningful attendance_logs row.
 */
final class AuditContext
{
    public function __construct(
        public readonly ?int $actorId = null,
        public readonly ?string $actorRole = null,
        /** clock_in|clock_out|biometric|fingerprint|manual|regularization|auto_clockout|policy_recalc|locked_override */
        public readonly ?string $source = null,
        public readonly ?string $reason = null,
        /** Tier 2 / T2-E — bypass an attendance_period_locks lock (admin only, needs a reason). */
        public readonly bool $override = false,
    ) {
    }
}
