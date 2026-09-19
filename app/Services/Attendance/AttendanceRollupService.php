<?php

namespace App\Services\Attendance;

use App\Models\Attendance;
use App\Models\AttendancePunch;
use Carbon\Carbon;

/**
 * Bridges the new punch pipeline to the existing, unmodified attendances
 * write funnel. Re-derives one day's `attendances` row from its active
 * `attendance_punches`, via PunchSessionCalculator, then writes it through
 * AttendanceEntryService::record() exactly like every other write path
 * (manual mark, regularization, biometric) already does — so period-lock
 * checks, the attendance_logs audit trail, LatePolicyService / summary
 * recompute, and AttendanceDomainEvent emission all keep working unchanged.
 */
class AttendanceRollupService
{
    public function __construct(
        private PunchSessionCalculator $sessions,
        private AttendanceCalculator $calc,
        private AttendanceEntryService $entry,
        private TenantShiftResolver $shifts,
    ) {
    }

    public function recompute(int $userId, int $tenantId, string $date, AuditContext $ctx): Attendance
    {
        $date = Carbon::parse($date)->format('Y-m-d');

        $punches = AttendancePunch::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->where('date', $date)
            ->where('status', 'active')
            ->get();

        $shift = $this->shifts->forUserDate($userId, $tenantId, $date);

        $scheduledStart = $scheduledEnd = null;
        if ($shift) {
            $scheduledStart = Carbon::parse($date . ' ' . $shift->start_time);
            $scheduledEnd = Carbon::parse($date . ' ' . $shift->end_time);
            if ($this->calc->isOvernight($shift)) {
                $scheduledEnd->addDay();
            }
        }

        $paired = $this->sessions->pairSessions($punches, $shift);
        $summary = $this->sessions->summarize($paired, $shift, $scheduledStart, $scheduledEnd);

        $columns = [
            'clock_in' => $summary['clock_in'],
            'clock_out' => $summary['clock_out'],
            'worked_hours' => $summary['worked_hours'],
            'total_hours' => $summary['total_hours'],
            'late_minutes' => $summary['late_minutes'],
            'early_departure_minutes' => $summary['early_departure_minutes'],
            'overtime_minutes' => $summary['overtime_minutes'],
            'session_count' => $summary['session_count'],
            'punches_last_synced_at' => now(),
            'status' => 1,
        ];

        if ($summary['attendance_status']) {
            $columns['attendance_status'] = $summary['attendance_status'];
        }

        if ($shift) {
            $columns['shift_id'] = $shift->id;
            $columns['scheduled_shift_start'] = $shift->start_time;
            $columns['scheduled_shift_end'] = $shift->end_time;
        }

        // Per-event fields mirror the first clock-in / last clock-out punch —
        // exactly like today's single in/out pair, generalised to N sessions.
        if ($firstIn = $paired['first_in_punch']) {
            $columns['clock_in_lat'] = $firstIn->lat;
            $columns['clock_in_long'] = $firstIn->long;
            $columns['clock_in_address'] = $firstIn->address;
            $columns['location_verification'] = $firstIn->location_verification;
            $columns['check_in_distance'] = $firstIn->distance_meters;
            // Set once at the day's first clock-in, exactly like the mobile
            // controller always has — clock-out and later sessions never
            // touch these; AnomalyScanner::buddyPunch() reads device_id.
            $columns['device_id'] = $firstIn->device_id;
            $columns['ip_address'] = $firstIn->ip_address ?? null;
            $columns['wifi_ssid'] = $firstIn->wifi_ssid;
            if ($firstIn->attendance_location_id) {
                $columns['branch_id'] = $firstIn->attendance_location_id;
            }
        }
        if ($lastOut = $paired['last_out_punch']) {
            $columns['clock_out_lat'] = $lastOut->lat;
            $columns['clock_out_long'] = $lastOut->long;
            $columns['clock_out_address'] = $lastOut->address;
            $columns['check_out_distance'] = $lastOut->distance_meters;
        }

        [$attendanceType, $markedBy] = $this->resolveTypeAndActor($punches);
        $columns['attendance_type'] = $attendanceType;
        if ($markedBy) {
            $columns['marked_by'] = $markedBy;
        }

        $columns['metadata'] = $this->mergedMetadata($tenantId, $userId, $date, $paired);

        $row = $this->entry->record($userId, $tenantId, $date, $columns, $ctx);

        AttendancePunch::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->where('date', $date)
            ->update(['attendance_id' => $row->id]);

        return $row;
    }

    /**
     * @param  \Illuminate\Support\Collection<int,AttendancePunch>  $punches
     * @return array{0:string,1:?int}  [attendance_type, marked_by]
     */
    private function resolveTypeAndActor($punches): array
    {
        if ($manual = $punches->firstWhere('source', 'manual')) {
            return ['manual', $manual->actor_id];
        }

        if ($biometric = $punches->firstWhere('source', 'biometric')) {
            return [match ($biometric->method) {
                'face' => 'face',
                'card' => 'card',
                default => 'fingerprint',
            }, null];
        }

        return ['app', null];
    }

    private function mergedMetadata(int $tenantId, int $userId, string $date, array $paired): ?string
    {
        $raw = Attendance::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)->where('user_id', $userId)->where('date', $date)
            ->value('metadata');
        $meta = $raw ? (json_decode($raw, true) ?: []) : [];

        if ($paired['needs_review']) {
            $meta['needs_review'] = true;
            $meta['review_punch_ids'] = array_values(array_map(
                fn ($p) => $p->id,
                array_merge($paired['leading_orphan_outs'], $paired['orphaned_ins'])
            ));
        } else {
            unset($meta['needs_review'], $meta['review_punch_ids']);
        }

        return $meta ? json_encode($meta) : null;
    }
}
