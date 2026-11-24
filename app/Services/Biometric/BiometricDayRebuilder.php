<?php

namespace App\Services\Biometric;

use App\Models\Attendance;
use App\Models\BiometricDevice;
use App\Models\BiometricPunch;
use App\Services\Attendance\AttendanceEntryService;
use App\Services\Attendance\AuditContext;
use App\Services\Attendance\PeriodLockService;
use App\Services\Attendance\TenantShiftResolver;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

/**
 * Re-applies a biometric device's CURRENT Direction rule to past days: each
 * employee-day that has punches from the device is cleared and its machine
 * punches (from every device of the tenant) are replayed in time order through
 * BiometricAttendanceService::apply() — the same code live punches use, so a
 * rebuilt day is exactly what live processing would have produced.
 *
 * Never touched: payroll-locked months, days set by hand (manual mark,
 * regularization, bulk mark), days whose times came from another source (app),
 * multi-shift days, and tenants with allow_multiple_punches on (punch-session
 * path). $dryRun runs everything inside a rolled-back transaction with events
 * faked, so the summary shows what would change and nothing is written or sent.
 */
class BiometricDayRebuilder
{
    public const MAX_DAYS = 92;

    private const BIOMETRIC_TYPES = ['fingerprint', 'face', 'card'];

    /** Punch-derived columns cleared before the replay (same set a hand-marked day resets). */
    private const CLEARED = [
        'clock_in' => null, 'clock_out' => null, 'clock_in_address' => null, 'clock_out_address' => null,
        'worked_hours' => 0, 'total_hours' => null, 'late_minutes' => 0, 'early_departure_minutes' => 0,
        'overtime_minutes' => 0,
    ];

    public function __construct(
        private BiometricAttendanceService $punches,
        private AttendanceEntryService $entry,
        private PeriodLockService $locks,
        private TenantShiftResolver $shifts,
    ) {}

    /**
     * @return array{mode:string, from:string, to:string, dry_run:bool, days_rebuilt:int, days_unchanged:int,
     *               employees:int, skipped:array<string,int>, changes:list<array<string,mixed>>}
     */
    public function rebuild(BiometricDevice $device, string $from, string $to, bool $dryRun = false, ?string $actorName = null): array
    {
        [$from, $to] = $this->range($from, $to);
        $tenantId = (int) $device->tenant_id;
        $summary = [
            'mode' => (string) $device->direction_mode, 'from' => $from, 'to' => $to, 'dry_run' => $dryRun,
            'days_rebuilt' => 0, 'days_unchanged' => 0, 'employees' => 0,
            'skipped' => ['locked' => 0, 'hand_set' => 0, 'other_source' => 0, 'multi_shift' => 0, 'multi_punch_tenant' => 0, 'failed' => 0],
            'changes' => [],
        ];

        $days = BiometricPunch::where('biometric_device_id', $device->id)
            ->whereNotNull('user_id')
            ->whereBetween('punched_at', [$from.' 00:00:00', $to.' 23:59:59'])
            ->selectRaw('user_id, DATE(punched_at) as day')
            ->distinct()->orderBy('day')->orderBy('user_id')
            ->get();

        if ((bool) DB::table('tenants')->where('id', $tenantId)->value('allow_multiple_punches')) {
            $summary['skipped']['multi_punch_tenant'] = $days->count();

            return $summary;
        }

        $run = function () use ($days, $tenantId, $device, $actorName, &$summary) {
            $employees = [];
            foreach ($days as $d) {
                try {
                    $result = $this->rebuildDay((int) $d->user_id, $tenantId, (string) $d->getAttribute('day'), $device, $actorName);
                } catch (\Throwable $e) {
                    report($e);   // that day rolled back; carry on with the rest
                    $result = ['status' => 'failed'];
                }
                if (isset($summary['skipped'][$result['status']])) {
                    $summary['skipped'][$result['status']]++;

                    continue;
                }
                $employees[$d->user_id] = true;
                $summary[$result['status'] === 'changed' ? 'days_rebuilt' : 'days_unchanged']++;
                if ($result['status'] === 'changed') {
                    $summary['changes'][] = $result;
                }
            }
            $summary['employees'] = count($employees);
        };

        if ($dryRun) {
            Event::fakeFor(function () use ($run) {
                DB::beginTransaction();
                try {
                    $run();
                } finally {
                    DB::rollBack();
                }
            });
        } else {
            $run();
        }

        return $summary;
    }

    /** @return array{status:string, user_id?:int, date?:string, before?:array, after?:array} */
    private function rebuildDay(int $userId, int $tenantId, string $date, BiometricDevice $device, ?string $actorName): array
    {
        if ($this->locks->isLocked($tenantId, Carbon::parse($date)->format('Y-m'))) {
            return ['status' => 'locked'];
        }
        if ($this->shifts->hasAdditionalAround($userId, $tenantId, $date)) {
            return ['status' => 'multi_shift'];
        }

        $row = Attendance::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)->where('user_id', $userId)->where('date', $date)->first();
        if ($row) {
            if ($row->getAttribute('attendance_type') === 'manual' || ! empty($row->regularized_by) || ! empty($row->marked_by)) {
                return ['status' => 'hand_set'];
            }
            if (! empty($row->clock_in) && ! in_array($row->getAttribute('attendance_type'), self::BIOMETRIC_TYPES, true)) {
                return ['status' => 'other_source'];
            }
        }
        $before = ['clock_in' => $row?->getAttribute('clock_in'), 'clock_out' => $row?->getAttribute('clock_out')];

        DB::transaction(function () use ($userId, $tenantId, $date, $device, $actorName, $row) {
            if ($row) {
                $this->entry->record($userId, $tenantId, $date, self::CLEARED, new AuditContext(
                    actorId: null,
                    actorRole: 'admin',
                    source: 'biometric',
                    reason: "Direction rebuild: {$device->direction_mode}".($actorName ? " by {$actorName}" : ''),
                ));
            }

            // Every machine punch of the employee that day, any device of the tenant (IN + OUT readers).
            $dayPunches = BiometricPunch::where('tenant_id', $tenantId)->where('user_id', $userId)
                ->whereBetween('punched_at', [$date.' 00:00:00', $date.' 23:59:59'])
                ->orderBy('punched_at')->orderBy('id')->get();

            foreach ($dayPunches as $punch) {
                $punch->update(['status' => 'pending', 'error' => null, 'direction' => 'auto', 'attendance_id' => null, 'processed_at' => null]);
                $this->punches->apply($punch->refresh());
            }
        });

        $after = Attendance::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)->where('user_id', $userId)->where('date', $date)
            ->first(['clock_in', 'clock_out']);
        $after = ['clock_in' => $after?->getAttribute('clock_in'), 'clock_out' => $after?->getAttribute('clock_out')];

        return [
            'status' => $before == $after ? 'unchanged' : 'changed',
            'user_id' => $userId, 'date' => $date, 'before' => $before, 'after' => $after,
        ];
    }

    /** One line for the admin / console: "34 days rebuilt for 12 employees (5 already correct); skipped: 2 locked month, 1 set by HR." */
    public static function message(array $s): string
    {
        $labels = ['locked' => 'locked month', 'hand_set' => 'set by HR', 'other_source' => 'from the app',
            'multi_shift' => 'multi-shift', 'multi_punch_tenant' => 'multiple punches on', 'failed' => 'failed'];
        $msg = ($s['dry_run'] ? 'Dry run: ' : '')."{$s['days_rebuilt']} day(s) ".($s['dry_run'] ? 'would change' : 'rebuilt')
            ." for {$s['employees']} employee(s) ({$s['from']} to {$s['to']}, rule: {$s['mode']}; {$s['days_unchanged']} already correct)";
        $skips = collect($s['skipped'])->filter()->map(fn ($n, $k) => "{$n} {$labels[$k]}")->implode(', ');

        return $msg.($skips !== '' ? "; skipped: {$skips}." : '.');
    }

    /** @return array{0:string, 1:string} from/to as Y-m-d, to capped at today, span capped at MAX_DAYS. */
    public function range(string $from, string $to): array
    {
        $today = now()->toDateString();
        $to = min(Carbon::parse($to)->toDateString(), $today);
        $from = Carbon::parse($from)->toDateString();
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }
        $earliest = Carbon::parse($to)->subDays(self::MAX_DAYS)->toDateString();

        return [max($from, $earliest), $to];
    }
}
