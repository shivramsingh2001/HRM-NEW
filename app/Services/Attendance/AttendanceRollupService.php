<?php

namespace App\Services\Attendance;

use App\Models\Attendance;
use App\Models\AttendancePunch;
use App\Models\AttendanceShiftSegment;
use App\Support\ShiftWindow;
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
            [$scheduledStart, $scheduledEnd] = ShiftWindow::window($date, $shift);
        }

        $paired = $this->sessions->pairSessions($punches, $shift);
        $summary = $this->sessions->summarize($paired, $shift, $scheduledStart, $scheduledEnd);

        // Multi-shift: split the day's sessions by the shift each one was
        // clocked in for. A single-shift day keeps $summary exactly as is.
        $segments = $this->segments($userId, $tenantId, $date, $paired, $shift, $scheduledStart, $scheduledEnd);
        // (Also when the only shift worked was an additional one — its own
        // window, not the primary's, decides late/early.)
        if (count($segments) > 1 || ($segments[0]['row']['is_additional'] ?? false)) {
            $summary = $this->multiShiftSummary($summary, $segments);
        }

        $columns = [
            'clock_in' => $summary['clock_in'],
            'clock_out' => $summary['clock_out'],
            'worked_hours' => $summary['worked_hours'],
            'total_hours' => $summary['total_hours'],
            'late_minutes' => $summary['late_minutes'],
            'early_departure_minutes' => $summary['early_departure_minutes'],
            'overtime_minutes' => $summary['overtime_minutes'],
            'session_count' => $summary['session_count'],
            'shift_count' => count($segments) ?: null,
            'expected_minutes' => $shift ? intdiv($this->calc->expectedWorkSeconds($shift), 60) : null,
            'extra_shift_minutes' => array_sum(array_map(
                fn ($seg) => $seg['row']['is_additional'] ? $seg['row']['worked_minutes'] : 0,
                $segments
            )),
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

        \App\Models\AttendanceTrackingSession::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->where('date', $date)
            ->whereNull('attendance_id')
            ->update(['attendance_id' => $row->id]);

        AttendanceShiftSegment::withoutGlobalScopes()->where('attendance_id', $row->id)->delete();
        foreach ($segments as $segment) {
            AttendanceShiftSegment::create($segment['row'] + [
                'tenant_id' => $tenantId,
                'attendance_id' => $row->id,
                'user_id' => $userId,
                'date' => $date,
            ]);
        }

        return $row;
    }

    /**
     * One entry per shift the user clocked in for on $date, primary first:
     * ['summary' => summarize() output for that shift's sessions,
     *  'row' => attendance_shift_segments columns]. A session belongs to the
     * shift its clock-in punch was matched to (user_shift_id); punches with
     * no / an unknown user_shift_id count toward the primary shift.
     */
    private function segments(int $userId, int $tenantId, string $date, array $paired, $shift, $scheduledStart, $scheduledEnd): array
    {
        $instances = $this->shifts->instancesForUserDate($userId, $tenantId, $date)->keyBy(fn ($i) => (string) $i['user_shift_id']);
        $primary = $instances->first();
        $primaryKey = $primary ? (string) $primary['user_shift_id'] : '';

        $keyOf = function ($punch) use ($instances, $primaryKey) {
            $key = (string) ($punch->user_shift_id ?? '');

            return $instances->has($key) ? $key : $primaryKey;
        };

        $groups = [];
        foreach ($paired['sessions'] as $session) {
            $groups[$keyOf($session['in_punch'])]['sessions'][] = $session;
        }
        if ($paired['open_session'] && $paired['open_in_punch']) {
            $groups[$keyOf($paired['open_in_punch'])]['open'] = $paired['open_in_punch'];
        }

        // Primary first, then additional shifts in start order.
        $order = $instances->keys()->all();
        uksort($groups, fn ($a, $b) => array_search($a, $order, true) <=> array_search($b, $order, true));

        $segments = [];
        foreach ($groups as $key => $group) {
            $instance = $instances->get($key);
            $segShift = $instance['shift'] ?? $shift;
            [$start, $end] = $instance ? [$instance['start'], $instance['end']] : [$scheduledStart, $scheduledEnd];

            $sub = $this->subPaired($group['sessions'] ?? [], $group['open'] ?? null);
            $summary = $this->sessions->summarize($sub, $segShift, $start, $end);

            $segments[] = [
                'summary' => $summary,
                'row' => [
                    'user_shift_id' => $instance['user_shift_id'] ?? null,
                    'shift_id' => $segShift?->id,
                    'is_additional' => (bool) ($instance['is_additional'] ?? false),
                    'scheduled_start' => $start?->format('Y-m-d H:i:s'),
                    'scheduled_end' => $end?->format('Y-m-d H:i:s'),
                    'first_in' => $summary['clock_in'],
                    'last_out' => $summary['clock_out'],
                    'worked_minutes' => intdiv($sub['worked_seconds'], 60),
                    'expected_minutes' => $segShift ? intdiv($this->calc->expectedWorkSeconds($segShift), 60) : 0,
                    'late_minutes' => $summary['late_minutes'],
                    'early_departure_minutes' => $summary['early_departure_minutes'],
                    'overtime_minutes' => $summary['overtime_minutes'],
                    'session_count' => $summary['session_count'],
                    'is_open' => $sub['open_session'],
                ],
            ];
        }

        return $segments;
    }

    /** pairSessions()-shaped subset for one shift's sessions (+ its open clock-in). */
    private function subPaired(array $sessions, $openPunch): array
    {
        $open = $openPunch !== null;
        $last = $sessions ? $sessions[count($sessions) - 1] : null;

        return [
            'sessions' => $sessions,
            'first_clock_in' => $sessions[0]['in'] ?? ($open ? Carbon::parse($openPunch->punched_at) : null),
            'last_clock_out' => (! $open && $last) ? $last['out'] : null,
            'worked_seconds' => array_sum(array_column($sessions, 'worked_seconds')),
            'session_count' => count($sessions) + ($open ? 1 : 0),
            'open_session' => $open,
        ];
    }

    /**
     * Day-level numbers for a day worked across several shifts. Clock-in/out,
     * worked hours and session count stay the whole day's (as with multiple
     * punches today); late/early/overtime and the status come from the first
     * shift worked (the primary when it was worked), and the day counts as
     * late when ANY shift started late (late minutes are summed).
     */
    private function multiShiftSummary(array $day, array $segments): array
    {
        $lead = $segments[0]['summary'];
        $late = array_sum(array_map(fn ($seg) => $seg['summary']['late_minutes'], $segments));

        $status = $lead['attendance_status'] ?? $day['attendance_status'];
        if ($late > 0 && in_array($status, ['present', null], true)) {
            $status = 'late';
        }

        return array_merge($day, [
            'late_minutes' => $late,
            'early_departure_minutes' => $lead['early_departure_minutes'],
            'overtime_minutes' => $lead['overtime_minutes'],
            'attendance_status' => $day['clock_in'] ? $status : null,
        ]);
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
