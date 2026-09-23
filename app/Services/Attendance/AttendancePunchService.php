<?php

namespace App\Services\Attendance;

use App\Exceptions\NoOpenPunchSessionException;
use App\Exceptions\OpenPunchSessionException;
use App\Exceptions\PeriodLockedException;
use App\Models\Attendance;
use App\Models\AttendancePunch;
use App\Services\FieldTracking\TrackingSessionService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The single funnel every punch source (mobile GPS, web, manual/admin,
 * biometric, kiosk) calls to record one raw Clock In/Out event.
 *
 * For a tenant with tenants.allow_multiple_punches = 0 (the default), the
 * open/closed-session rules enforced here are byte-for-byte the same ones the
 * mobile clock-in/out controller already enforces today — this service just
 * centralises them so every source agrees. For = 1, an `in` becomes valid
 * again once the prior session has a matching `out` (multi-session days).
 *
 * Every accepted punch immediately triggers AttendanceRollupService::recompute(),
 * which re-derives the day's single `attendances` row via
 * PunchSessionCalculator and writes it through the existing, unmodified
 * AttendanceEntryService::record() funnel (period lock / audit / summary
 * refresh / domain event all keep working exactly as they do today).
 */
class AttendancePunchService
{
    public function __construct(
        private AttendanceCalculator $calc,
        private TenantShiftResolver $shifts,
        private PeriodLockService $locks,
        private AttendanceRollupService $rollup,
        private TrackingSessionService $trackingSessions,
    ) {
    }

    /**
     * How far back (and forward, for clock-skewed devices) of the incoming
     * punch's own timestamp to look when deciding "is there a session open
     * for this punch to close". Bounded — relative to the punch's own time,
     * not the server's wall-clock — so a punch backfilled/synced from an
     * unrelated period can never be mistaken for an ancient still-open
     * session (or vice versa). A few days comfortably covers the longest
     * realistic offline-sync delay plus a cross-midnight shift.
     */
    private const OPEN_SESSION_LOOKBACK_DAYS = 3;
    private const OPEN_SESSION_LOOKAHEAD_DAYS = 1;

    public function capture(PunchInput $input): AttendancePunch
    {
        $latestActive = AttendancePunch::withoutGlobalScopes()
            ->where('tenant_id', $input->tenantId)
            ->where('user_id', $input->userId)
            ->where('status', 'active')
            ->where('punched_at', '>=', $input->punchedAt->copy()->subDays(self::OPEN_SESSION_LOOKBACK_DAYS))
            ->where('punched_at', '<=', $input->punchedAt->copy()->addDays(self::OPEN_SESSION_LOOKAHEAD_DAYS))
            ->orderByDesc('punched_at')
            ->orderByDesc('id')
            ->first();

        $hasOpenSession = $latestActive && $latestActive->direction === 'in';

        // An `out` closing an in-progress session inherits that session's
        // date (the cross-midnight rule) rather than its own calendar day.
        if ($input->direction === 'out' && $hasOpenSession) {
            $date = $latestActive->date;
        } else {
            $shiftForDate = $this->shifts->forUserDate($input->userId, $input->tenantId, $input->punchedAt->format('Y-m-d'));
            $date = $this->calc->resolveAttendanceDate($input->punchedAt, $shiftForDate);
        }

        $yearMonth = Carbon::parse($date)->format('Y-m');
        if (! ($input->audit?->override) && $this->locks->isLocked($input->tenantId, $yearMonth)) {
            throw new PeriodLockedException($yearMonth);
        }

        // Idempotency: a retried offline-sync submission returns the same punch.
        if ($input->clientRef) {
            $existing = AttendancePunch::withoutGlobalScopes()
                ->where('tenant_id', $input->tenantId)
                ->where('user_id', $input->userId)
                ->where('client_ref', $input->clientRef)
                ->first();
            if ($existing) {
                return $existing;
            }
        }

        // Debounce: a same-direction punch within the window is a double-tap.
        $dedupeWindow = (int) config('biometric.dedupe_window_seconds', 60);
        if ($latestActive && $latestActive->direction === $input->direction
            && abs($latestActive->punched_at->diffInSeconds($input->punchedAt)) <= $dedupeWindow) {
            return $latestActive;
        }

        $allowMultiple = (bool) DB::table('tenants')->where('id', $input->tenantId)->value('allow_multiple_punches');

        if ($input->direction === 'in') {
            if ($hasOpenSession) {
                throw new OpenPunchSessionException();
            }

            // A day that already has an `attendances` row but zero backing
            // punches was set by a non-punch path (manual mark, applied
            // regularization) — under the pre-punch architecture, ANY
            // existing row for (tenant,user,date) unconditionally blocked a
            // new clock-in (the unique key caught it). Preserve that: an
            // admin's decision for a day is never silently overwritten by a
            // later raw punch, under either flag value. A genuine 2nd/3rd
            // session (allow_multiple_punches=1) already has punches from
            // its earlier sessions, so this never blocks that case.
            $hasAnyPunchToday = AttendancePunch::withoutGlobalScopes()
                ->where('tenant_id', $input->tenantId)
                ->where('user_id', $input->userId)
                ->where('date', $date)
                ->where('status', 'active')
                ->exists();
            if (! $hasAnyPunchToday) {
                $existingAttendance = Attendance::withoutGlobalScopes()
                    ->where('tenant_id', $input->tenantId)
                    ->where('user_id', $input->userId)
                    ->where('date', $date)
                    ->exists();
                if ($existingAttendance) {
                    throw new OpenPunchSessionException(
                        'Attendance for this date has already been set (manually or via regularization). Use regularization to make changes.'
                    );
                }
            }

            if (! $allowMultiple) {
                $hasCompletedToday = AttendancePunch::withoutGlobalScopes()
                    ->where('tenant_id', $input->tenantId)
                    ->where('user_id', $input->userId)
                    ->where('date', $date)
                    ->where('status', 'active')
                    ->where('direction', 'out')
                    ->exists();
                if ($hasCompletedToday) {
                    throw new OpenPunchSessionException();
                }
            }
        } else {
            if (! $hasOpenSession) {
                throw new NoOpenPunchSessionException();
            }
        }

        $outOfOrder = $latestActive && $input->punchedAt->lt($latestActive->punched_at);
        $tz = $this->resolveTimezone($input);

        $punch = AttendancePunch::create([
            'tenant_id' => $input->tenantId,
            'user_id' => $input->userId,
            'date' => $date,
            'direction' => $input->direction,
            'punched_at' => $input->punchedAt->format('Y-m-d H:i:s'),
            'punched_at_utc' => $this->calc->toUtc($input->punchedAt->format('Y-m-d H:i:s'), $tz),
            'timezone' => $tz,
            'source' => $input->source,
            'method' => $input->method,
            'biometric_device_id' => $input->biometricDeviceId,
            'lat' => $input->lat,
            'long' => $input->long,
            'address' => $input->address,
            'location_verification' => $input->locationVerification,
            'attendance_location_id' => $input->attendanceLocationId,
            'distance_meters' => $input->distanceMeters,
            'accuracy_meters' => $input->accuracyMeters,
            'device_id' => $input->deviceId,
            'network_type' => $input->networkType,
            'wifi_ssid' => $input->wifiSsid,
            'ip_address' => $input->ipAddress,
            'battery_percent' => $input->batteryPercent,
            'actor_id' => $input->audit?->actorId,
            'actor_role' => $input->audit?->actorRole,
            'reason' => $input->audit?->reason,
            'status' => 'active',
            'client_ref' => $input->clientRef,
            'metadata' => $outOfOrder ? array_merge($input->metadata, ['out_of_order' => true]) : ($input->metadata ?: null),
        ]);

        if ($input->direction === 'out' && $latestActive) {
            $punch->forceFill([
                'paired_punch_id' => $latestActive->id,
                'session_seq' => $latestActive->session_seq ?? 1,
            ])->save();
            $latestActive->forceFill(['paired_punch_id' => $punch->id])->save();
        } else {
            $seq = (int) AttendancePunch::withoutGlobalScopes()
                ->where('tenant_id', $input->tenantId)
                ->where('user_id', $input->userId)
                ->where('date', $date)
                ->where('status', 'active')
                ->max('session_seq');
            $punch->forceFill(['session_seq' => $seq + 1])->save();
        }

        $this->trackingSessions->onPunchCaptured($punch);

        $ctx = $input->audit ?? new AuditContext(
            source: $input->direction === 'in' ? 'clock_in' : 'clock_out',
        );
        $this->rollup->recompute($input->userId, $input->tenantId, $date, $ctx);

        return $punch->fresh();
    }

    private function resolveTimezone(PunchInput $input): string
    {
        return app(TimezoneResolver::class)->forUser($input->userId, $input->tenantId);
    }
}
