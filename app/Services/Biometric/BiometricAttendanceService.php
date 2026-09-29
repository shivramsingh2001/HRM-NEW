<?php

namespace App\Services\Biometric;

use App\Exceptions\NoOpenPunchSessionException;
use App\Exceptions\OpenPunchSessionException;
use App\Models\Attendance;
use App\Models\AttendancePunch;
use App\Models\BiometricDevice;
use App\Models\BiometricPunch;
use App\Models\User;
use App\Models\UserJobDetail;
use App\Services\Attendance\AttendanceCalculator;
use App\Services\Attendance\AttendanceEntryService;
use App\Services\Attendance\AttendancePunchService;
use App\Services\Attendance\AuditContext;
use App\Services\Attendance\PolicyResolver;
use App\Services\Attendance\PunchInput;
use App\Services\Attendance\TenantShiftResolver;
use App\Services\Attendance\TimezoneResolver;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Turns one biometric_punches row into an attendances write, routed through the
 * AttendanceEntryService funnel (period lock + audit + domain event + summary
 * refresh). Clock math is a straight lift of the retired ProcessFingerprintPunch.
 *
 * For a tenant with allow_multiple_punches = 1, punches are routed instead
 * through AttendancePunchService — the same funnel mobile/web/manual punches
 * use — so a 3rd+ punch in a day opens a new session instead of being
 * discarded. For allow_multiple_punches = 0 (the default), behaviour here is
 * completely unchanged.
 */
class BiometricAttendanceService
{
    public function __construct(
        private AttendanceEntryService $entry,
        private AttendanceCalculator $calc,
    ) {
    }

    public function apply(BiometricPunch $punch): void
    {
        if ($punch->status !== 'pending') {
            return;
        }

        $user = $punch->user_id ? User::find($punch->user_id) : null;
        if (! $user) {
            $punch->update(['status' => 'skipped', 'error' => 'no mapped user']);

            return;
        }

        $device = BiometricDevice::find($punch->biometric_device_id);
        $tenantId = (int) $punch->tenant_id;

        if ((bool) DB::table('tenants')->where('id', $tenantId)->value('allow_multiple_punches')) {
            $this->applyViaPunchPipeline($punch, $user, $device, $tenantId);

            return;
        }

        // Resolve the true instant + attendance date in the employee's zone.
        $tz = $device?->site_timezone
            ?: app(TimezoneResolver::class)->forUser($user->id, $tenantId);
        $punchLocal = Carbon::parse($punch->punched_at);
        $punchUtc = $this->calc->toUtc($punchLocal->format('Y-m-d H:i:s'), $tz);
        $date = $punchLocal->format('Y-m-d');

        $direction = $this->resolveDirection($punch, $device, $tenantId, $user->id, $date);

        // Debounce: same-direction punch within the window is a double-tap.
        $existing = Attendance::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)->where('user_id', $user->id)->where('date', $date)
            ->first();
        if ($this->isDoubleTap($existing, $direction, $punchLocal)) {
            $punch->update(['status' => 'skipped', 'error' => 'debounce (double tap)', 'punched_at_utc' => $punchUtc]);

            return;
        }

        $shift = app(TenantShiftResolver::class)->forUserDate($user->id, $tenantId, $date);
        $branchId = UserJobDetail::where('user_id', $user->id)->where('tenant_id', $tenantId)->value('office_branch');
        $label = $device?->name ?: $punch->serial_number;

        $columns = $direction === 'in'
            ? $this->clockInColumns($punchLocal, $shift, $date, $branchId, $label, $punch)
            : $this->clockOutColumns($punchLocal, $shift, $date, $existing, $label, $punch);

        if ($columns === null) {
            // e.g. clock-out with no prior clock-in, or already set — not an error.
            $punch->update(['status' => 'skipped', 'error' => $this->skipReason, 'punched_at_utc' => $punchUtc]);

            return;
        }

        try {
            $row = $this->entry->record(
                $user->id,
                $tenantId,
                $date,
                $columns,
                new AuditContext(
                    actorId: null,
                    actorRole: 'device',
                    source: 'biometric',
                    reason: "Biometric punch · {$label} · enroll {$punch->enroll_no}",
                ),
            );
        } catch (\Throwable $e) {
            $punch->update([
                'status' => 'error',
                'error' => mb_substr($e->getMessage(), 0, 500),
                'punched_at_utc' => $punchUtc,
            ]);
            throw $e;
        }

        $punch->update([
            'status' => 'processed',
            'user_id' => $user->id,
            'direction' => $direction,
            'attendance_id' => $row->id,
            'punched_at_utc' => $punchUtc,
            'processed_at' => now(),
            'error' => null,
        ]);

        try {
            event(new \App\Events\AttendanceDomainEvent('biometric.punch_recorded', $tenantId, [
                'device_id' => $device?->id,
                'serial_number' => $punch->serial_number,
                'enroll_no' => $punch->enroll_no,
                'user_id' => $user->id,
                'direction' => $direction,
                'attendance_id' => $row->id,
            ]));
        } catch (\Throwable $e) {
            // never block on the event
        }
    }

    private string $skipReason = 'skipped';

    // ------------------------------------------------------------------

    /**
     * allow_multiple_punches = 1 path: hand the raw punch to the same funnel
     * mobile/web/manual punches use, instead of folding it into a single
     * clock_in/clock_out pair. A 3rd+ punch opens a new session instead of
     * being discarded.
     */
    private function applyViaPunchPipeline(BiometricPunch $punch, User $user, ?BiometricDevice $device, int $tenantId): void
    {
        $tz = $device?->site_timezone ?: app(TimezoneResolver::class)->forUser($user->id, $tenantId);
        $punchLocal = Carbon::parse($punch->punched_at);
        $punchUtc = $this->calc->toUtc($punchLocal->format('Y-m-d H:i:s'), $tz);
        $this->adoptSinglePunchDay($user->id, $tenantId, $punchLocal, $tz, $device);
        $direction = $this->resolveDirectionForPunchPipeline($punch, $device, $tenantId, $user->id);
        $label = $device?->name ?: $punch->serial_number;

        try {
            $recorded = app(AttendancePunchService::class)->capture(new PunchInput(
                userId: $user->id,
                tenantId: $tenantId,
                direction: $direction,
                punchedAt: $punchLocal,
                source: 'biometric',
                method: $punch->method,
                address: $this->addressLine($punch, $label),
                biometricDeviceId: $device?->id,
                audit: new AuditContext(
                    actorId: null,
                    actorRole: 'device',
                    source: 'biometric',
                    reason: "Biometric punch · {$label} · enroll {$punch->enroll_no}",
                ),
                metadata: array_filter([
                    'temperature' => $punch->temperature,
                    'raw_verify_mode' => $punch->raw_verify_mode,
                ], fn ($v) => $v !== null),
            ));
        } catch (OpenPunchSessionException|NoOpenPunchSessionException $e) {
            $punch->update(['status' => 'skipped', 'error' => $e->getMessage(), 'punched_at_utc' => $punchUtc]);

            return;
        } catch (\Throwable $e) {
            $punch->update(['status' => 'error', 'error' => mb_substr($e->getMessage(), 0, 500), 'punched_at_utc' => $punchUtc]);
            throw $e;
        }

        $punch->update([
            'status' => 'processed',
            'user_id' => $user->id,
            'direction' => $direction,
            'attendance_id' => $recorded->attendance_id,
            'punched_at_utc' => $punchUtc,
            'processed_at' => now(),
            'error' => null,
        ]);

        try {
            event(new \App\Events\AttendanceDomainEvent('biometric.punch_recorded', $tenantId, [
                'device_id' => $device?->id,
                'serial_number' => $punch->serial_number,
                'enroll_no' => $punch->enroll_no,
                'user_id' => $user->id,
                'direction' => $direction,
                'attendance_id' => $recorded->attendance_id,
            ]));
        } catch (\Throwable $e) {
            // never block on the event
        }
    }

    /**
     * A day written by the allow_multiple_punches = 0 path has an `attendances`
     * row but no attendance_punches behind it, which AttendancePunchService
     * reads as "set manually" and blocks every further clock-in. When the flag
     * is switched on mid-day, turn that biometric-written row into its
     * in/out punches so the next device punch opens session 2 instead.
     * Rows from any other source (manual mark, regularization) are left alone.
     */
    private function adoptSinglePunchDay(int $userId, int $tenantId, Carbon $punchLocal, string $tz, ?BiometricDevice $device): void
    {
        $shift = app(TenantShiftResolver::class)->forUserDate($userId, $tenantId, $punchLocal->format('Y-m-d'));
        $date = $this->calc->resolveAttendanceDate($punchLocal, $shift);

        $hasPunches = AttendancePunch::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)->where('user_id', $userId)
            ->where('date', $date)->where('status', 'active')
            ->exists();
        if ($hasPunches) {
            return;
        }

        $row = Attendance::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)->where('user_id', $userId)->where('date', $date)
            ->first();
        if (! $row || empty($row->clock_in) || $row->attendance_type === 'manual') {
            return;
        }
        $meta = is_array($row->metadata) ? $row->metadata : (json_decode((string) $row->metadata, true) ?: []);
        if (($meta['source'] ?? null) !== 'biometric') {
            return;
        }

        $base = [
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'attendance_id' => $row->id,
            'date' => $date,
            'timezone' => $tz,
            'source' => 'biometric',
            'method' => $meta['method'] ?? null,
            'biometric_device_id' => $device?->id,
            'actor_role' => 'device',
            'reason' => 'Adopted from single-punch biometric attendance',
            'status' => 'active',
            'session_seq' => 1,
            'metadata' => ['adopted_from_attendance' => true],
        ];

        DB::transaction(function () use ($base, $row) {
            $in = Carbon::parse($row->clock_in);
            $inPunch = AttendancePunch::create($base + [
                'direction' => 'in',
                'address' => $row->clock_in_address,
                'punched_at' => $in->format('Y-m-d H:i:s'),
                'punched_at_utc' => $this->calc->toUtc($in->format('Y-m-d H:i:s'), $base['timezone']),
            ]);

            if (! empty($row->clock_out)) {
                $out = Carbon::parse($row->clock_out);
                $outPunch = AttendancePunch::create($base + [
                    'direction' => 'out',
                    'address' => $row->clock_out_address,
                    'punched_at' => $out->format('Y-m-d H:i:s'),
                    'punched_at_utc' => $this->calc->toUtc($out->format('Y-m-d H:i:s'), $base['timezone']),
                    'paired_punch_id' => $inPunch->id,
                ]);
                $inPunch->forceFill(['paired_punch_id' => $outPunch->id])->save();
            }
        });
    }

    /** Same priority order as resolveDirection(), but 'auto' reads open-session state from attendance_punches. */
    private function resolveDirectionForPunchPipeline(BiometricPunch $punch, ?BiometricDevice $device, int $tenantId, int $userId): string
    {
        foreach (config('biometric.direction_priority', ['payload', 'device_mode', 'verify_mode', 'auto']) as $src) {
            $d = match ($src) {
                'payload' => in_array($punch->direction, ['in', 'out'], true) ? $punch->direction : null,
                'device_mode' => match ($device?->direction_mode) {
                    'in' => 'in',
                    'out' => 'out',
                    'by_verify_mode' => $this->fromVerifyMode($punch->raw_verify_mode),
                    default => null,
                },
                'verify_mode' => $this->fromVerifyMode($punch->raw_verify_mode),
                'auto' => $this->fromPunchState($tenantId, $userId),
                default => null,
            };
            if ($d) {
                return $d;
            }
        }

        return 'in';
    }

    private function fromPunchState(int $tenantId, int $userId): string
    {
        $open = AttendancePunch::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->orderByDesc('punched_at')
            ->first();

        return ($open && $open->direction === 'in') ? 'out' : 'in';
    }

    private function resolveDirection(BiometricPunch $punch, ?BiometricDevice $device, int $tenantId, int $userId, string $date): string
    {
        foreach (config('biometric.direction_priority', ['payload', 'device_mode', 'verify_mode', 'auto']) as $src) {
            $d = match ($src) {
                'payload' => in_array($punch->direction, ['in', 'out'], true) ? $punch->direction : null,
                'device_mode' => match ($device?->direction_mode) {
                    'in' => 'in',
                    'out' => 'out',
                    'by_verify_mode' => $this->fromVerifyMode($punch->raw_verify_mode),
                    default => null,
                },
                'verify_mode' => $this->fromVerifyMode($punch->raw_verify_mode),
                'auto' => $this->fromState($tenantId, $userId, $date),
                default => null,
            };
            if ($d) {
                return $d;
            }
        }

        return 'in';
    }

    /**
     * Byte 1 of the packed verify_mode. Only 4 (go-in) / 5 (go-out) are an
     * explicit operator-pressed direction; 0-3 (duty / overtime on-off) are
     * often a device default and must not override the device-mode / first-punch
     * resolution.
     */
    private function fromVerifyMode(?int $vm): ?string
    {
        if ($vm === null) {
            return null;
        }

        return match (($vm >> 8) & 0xFF) {
            4 => 'in',
            5 => 'out',
            default => null,
        };
    }

    private function fromState(int $tenantId, int $userId, string $date): string
    {
        $row = Attendance::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)->where('user_id', $userId)->where('date', $date)
            ->first(['clock_in', 'clock_out']);

        return ($row && ! empty($row->clock_in)) ? 'out' : 'in';
    }

    private function isDoubleTap(?Attendance $row, string $direction, Carbon $at): bool
    {
        if (! $row) {
            return false;
        }
        $window = (int) config('biometric.dedupe_window_seconds', 60);
        $ref = $direction === 'in' ? $row->clock_in : $row->clock_out;
        if (empty($ref)) {
            return false;
        }

        try {
            return abs(Carbon::parse($ref)->diffInSeconds($at)) <= $window;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** attendances.attendance_type — enum('manual','fingerprint','face','card','app'). */
    private function methodToType(?string $method): string
    {
        return match ($method) {
            'face' => 'face',
            'card' => 'card',
            default => 'fingerprint',   // password / mixed / unknown — still a device punch
        };
    }

    private function addressLine(BiometricPunch $punch, string $label): string
    {
        $how = $punch->method && $punch->method !== 'unknown' ? ucfirst($punch->method) : 'Biometric';

        return "{$how} · {$label} · #{$punch->enroll_no}";
    }

    private function clockInColumns(Carbon $at, $shift, string $date, $branchId, string $label, BiometricPunch $punch): ?array
    {
        $lateMinutes = 0;
        if ($shift) {
            $scheduledStart = Carbon::parse($date . ' ' . $shift->start_time);
            $lateMinutes = $this->calc->lateMinutes($shift, $scheduledStart, $at);
        }

        return [
            'clock_in' => $at->format('Y-m-d H:i:s'),
            'clock_in_address' => $this->addressLine($punch, $label),
            'attendance_type' => $this->methodToType($punch->method),
            'status' => 1,
            'shift_id' => $shift->id ?? null,
            'scheduled_shift_start' => $shift->start_time ?? null,
            'scheduled_shift_end' => $shift->end_time ?? null,
            'branch_id' => $branchId,
            'late_minutes' => $lateMinutes,
            'attendance_status' => $lateMinutes > 0 ? 'late' : 'present',
            'metadata' => json_encode([
                'method' => $punch->method,
                'temperature' => $punch->temperature,
                'raw_verify_mode' => $punch->raw_verify_mode,
                'source' => 'biometric',
            ]),
        ];
    }

    private function clockOutColumns(Carbon $at, $shift, string $date, ?Attendance $existing, string $label, BiometricPunch $punch): ?array
    {
        if (! $existing || empty($existing->clock_in)) {
            $this->skipReason = 'no prior clock-in';

            return null;
        }
        if (! empty($existing->clock_out)) {
            $this->skipReason = 'already clocked out';

            return null;
        }

        $clockIn = Carbon::parse($existing->clock_in);
        $workedSeconds = max(0, $this->calc->workedSeconds($clockIn, $at));
        $workedHours = $this->calc->decimalHours($workedSeconds);

        $early = 0;
        $overtime = 0;
        $expected = 0;
        if ($shift) {
            $scheduledEnd = Carbon::parse($date . ' ' . $shift->end_time);
            if ($this->calc->isOvernight(['start_time' => $shift->start_time, 'end_time' => $shift->end_time])) {
                $scheduledEnd->addDay();
            }
            $grace = (int) ($shift->grace_minutes ?? 0);
            if ($at->lt($scheduledEnd)) {
                $mins = (int) $at->diffInMinutes($scheduledEnd);
                $early = $mins > $grace ? $mins : 0;
            } else {
                $overtime = (int) $scheduledEnd->diffInMinutes($at);
            }
            $expected = $this->calc->expectedWorkSeconds([
                'start_time' => $shift->start_time, 'end_time' => $shift->end_time,
            ]);
        }
        // Same tenant policy (ratios + Day Classification switch) as every other path.
        $status = app(PolicyResolver::class)
            ->forTenantDate((int) $existing->tenant_id, $date)
            ->classify($workedHours, (int) $expected);

        return [
            'clock_out' => $at->format('Y-m-d H:i:s'),
            'clock_out_address' => $this->addressLine($punch, $label),
            'worked_hours' => $workedHours,
            'total_hours' => $this->calc->formatDuration($workedSeconds),
            'early_departure_minutes' => $early,
            'overtime_minutes' => $overtime,
            'attendance_status' => $status,
        ];
    }
}
