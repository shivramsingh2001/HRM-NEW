<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Tier 2 / T2-C — a single carrier for every attendance domain event.
 *
 *   event(new AttendanceDomainEvent('attendance.marked', $tenantId, [...]));
 *
 * Consumed by App\Listeners\DispatchWebhooks (and any internal listener). The
 * `name` is the stable event string used in webhook_endpoints.events.
 */
class AttendanceDomainEvent
{
    use Dispatchable, SerializesModels;

    public const NAMES = [
        'attendance.marked',
        'attendance.regularized',
        'attendance.clock_in',
        'attendance.clock_out',
        'attendance.clock_in_late',
        'regularization.submitted',
        'regularization.decided',
        'overtime.decided',
        'leave.decided',
        'payroll_revision.decided',
        'payroll_bonus.decided',
        'payroll_run.decided',
        'shift_request.decided',
        'attendance.month_finalised',
        'attendance.anomaly_detected',
        'field_tracking.seat_assigned',
        'field_tracking.seat_removed',
        'biometric.punch_recorded',
    ];

    /**
     * @param  array<string,mixed>  $payload
     */
    public function __construct(
        public string $name,
        public int $tenantId,
        public array $payload = [],
    ) {
    }

    public function toDelivery(): array
    {
        return [
            'event' => $this->name,
            'tenant_id' => $this->tenantId,
            'occurred_at' => now()->toIso8601String(),
            'data' => $this->payload,
        ];
    }
}
