<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Attendance;
use App\Models\AttendanceLog;
use App\Models\AttendancePunch;
use App\Models\Shift;
use App\Models\UserShift;
use App\Services\Attendance\AttendanceCalculator;
use App\Services\Attendance\AttendanceEntryService;
use App\Services\Attendance\AttendancePunchService;
use App\Services\Attendance\AuditContext;
use App\Services\Attendance\PunchInput;
use App\Services\Attendance\LatePolicyService;
use App\Services\AttendanceSummaryService;
use App\Support\ShiftWindow;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AutoClockOutCommand extends Command
{
    protected $signature = 'attendance:auto-clockout
                            {--hours=15 : Max hours before auto clock-out}
                            {--dry-run : Test mode}
                            {--user-id= : Specific user ID}';

    protected $description = 'Auto clock-out users who never clocked out, capping the day at a sane time';

    public function __construct(
        protected AttendanceCalculator $calc,
        protected AttendanceSummaryService $summaryService,
        protected LatePolicyService $latePolicyService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        // --hours is the fallback; each company's own limit (Company Policies →
        // Multiple Punches, tenants.auto_clockout_hours) wins — so a company with
        // long duties (06:30 → 09:00 next morning) can let a day run past 24 h.
        $maxHours = max(1, (int) $this->option('hours'));
        $defaultHours = $maxHours;
        $companyHours = DB::table('tenants')->pluck('auto_clockout_hours', 'id');
        $hoursFor = fn ($tenantId) => max(1, (int) ($companyHours[$tenantId] ?? $defaultHours) ?: $defaultHours);
        $isDryRun = (bool) $this->option('dry-run');
        $specificUserId = $this->option('user-id');
        $now = Carbon::now();

        $this->info('Auto clock-out — cap ' . $maxHours . 'h' . ($isDryRun ? '  [DRY RUN]' : ''));

        // Global job: TenantTrait's scope is a no-op in the CLI, so this covers
        // every tenant. Each row carries its own tenant_id.
        $records = Attendance::withoutGlobalScopes()
            ->whereNotNull('clock_in')
            ->where(function ($q) {
                $q->whereNull('clock_out')
                    ->orWhere('clock_out', '')
                    ->orWhere('clock_out', '0000-00-00 00:00:00');
            })
            ->when($specificUserId, fn ($q) => $q->where('user_id', $specificUserId))
            ->get();

        $due = $records->filter(function ($a) use ($hoursFor, $now) {
            try {
                $clockIn = Carbon::parse($a->clock_in);
                return $now->getTimestamp() - $clockIn->getTimestamp() >= $hoursFor($a->tenant_id) * 3600;
            } catch (\Throwable $e) {
                return false;
            }
        })->values();

        if ($due->isEmpty()) {
            $this->info('Nothing to close.');
            return self::SUCCESS;
        }

        $this->info("Closing {$due->count()} open attendance row(s)");
        $bar = $this->output->createProgressBar($due->count());
        $bar->start();

        $ok = 0;
        $errors = 0;
        $touched = []; // [tenant_id][user_id][Y-m] => true, for summary refresh

        foreach ($due as $attendance) {
            try {
                $maxHours = $hoursFor($attendance->tenant_id);
                // The open session's own clock-in punch (punch pipeline days):
                // with several sessions/shifts in a day, the cap and the shift
                // end are measured from the session still open, not the day's
                // first clock-in.
                $openPunch = $this->openPunch($attendance);
                $clockIn = Carbon::parse($openPunch->punched_at ?? $attendance->clock_in);

                if ($openPunch && $now->getTimestamp() - $clockIn->getTimestamp() < $maxHours * 3600) {
                    $bar->advance();
                    continue; // an earlier session started long ago, but this one is recent
                }

                // Close at the earliest sane moment: clock_in + maxHours, or the
                // scheduled shift end (with overnight handling) when that is earlier.
                $cap = $clockIn->copy()->addHours($maxHours);
                $shiftEnd = ($openPunch ? $this->punchShiftEnd($openPunch) : null)
                    ?? $this->scheduledShiftEnd($attendance, $clockIn);
                $clockOut = ($shiftEnd && $shiftEnd->greaterThan($clockIn) && $shiftEnd->lessThan($cap))
                    ? $shiftEnd
                    : $cap;

                $workedSeconds = $this->calc->workedSeconds($clockIn, $clockOut);
                $workedHours = $this->calc->decimalHours($workedSeconds);
                $totalHours = $this->calc->formatDuration($workedSeconds);
                $status = $this->classify($attendance, $workedHours);

                if ($isDryRun) {
                    $ok++;
                    $bar->advance();
                    continue;
                }

                // Punch-pipeline day: record a real `out` punch so the punch
                // table, the day's sessions/shift segments and the attendance
                // row all agree (writing the row directly left the `in` punch
                // open, blocking the next clock-in and being undone by the next
                // rollup).
                if ($openPunch) {
                    $this->closeViaPunch($attendance, $openPunch, $clockOut, $maxHours, $now);
                    $touched[$attendance->tenant_id][$attendance->user_id][Carbon::parse($attendance->date)->format('Y-m')] = true;
                    $ok++;
                    $bar->advance();
                    continue;
                }

                DB::transaction(function () use ($attendance, $clockOut, $totalHours, $workedHours, $status, $maxHours, $now, $clockIn, $workedSeconds) {
                    $before = [
                        'clock_out' => $attendance->clock_out,
                        'total_hours' => $attendance->total_hours,
                        'worked_hours' => $attendance->worked_hours,
                        'attendance_status' => $attendance->attendance_status,
                    ];

                    $attendance->clock_out = $clockOut->format('Y-m-d H:i:s');
                    $attendance->total_hours = $totalHours;
                    $attendance->worked_hours = $workedHours;
                    $attendance->attendance_status = $status;
                    $attendance->remarks = trim(($attendance->remarks ? $attendance->remarks . ' | ' : '')
                        . "Auto clock-out (cap {$maxHours}h) on " . $now->format('Y-m-d H:i'));
                    $attendance->save();

                    // Not wrapped in its own try/catch — if the audit log fails the
                    // whole close is rolled back rather than silently committed.
                    AttendanceLog::create([
                        'tenant_id' => $attendance->tenant_id,
                        'user_id' => $attendance->user_id,
                        'actor_id' => null,
                        'actor_role' => null,
                        'source' => 'auto_clockout',
                        'attendance_id' => $attendance->id,
                        'event_type' => 'manual_adjustment',
                        'event_time' => $clockOut->format('Y-m-d H:i:s'),
                        'latitude' => $attendance->clock_in_lat,
                        'longitude' => $attendance->clock_in_long,
                        'address' => 'Auto clock-out',
                        'verification_method' => 'system',
                        'user_agent' => 'AutoClockOutCommand',
                        'reason' => "Exceeded {$maxHours}h without clock-out",
                        'before' => $before,
                        'after' => [
                            'clock_out' => $attendance->clock_out,
                            'total_hours' => $attendance->total_hours,
                            'worked_hours' => $attendance->worked_hours,
                            'attendance_status' => $attendance->attendance_status,
                        ],
                        'raw_data' => json_encode([
                            'reason' => "exceeded {$maxHours}h without clock-out",
                            'clock_in' => $clockIn->format('Y-m-d H:i:s'),
                            'clock_out' => $clockOut->format('Y-m-d H:i:s'),
                            'worked_hours' => $workedHours,
                            'worked_seconds' => $workedSeconds,
                        ]),
                    ]);

                    UserShift::withoutGlobalScopes()
                        ->where('tenant_id', $attendance->tenant_id)
                        ->where('user_id', $attendance->user_id)
                        ->where('date', $attendance->date)
                        ->where('is_additional', 0)
                        ->update(['status' => 'complete']);
                });

                $touched[$attendance->tenant_id][$attendance->user_id][Carbon::parse($attendance->date)->format('Y-m')] = true;
                $ok++;
            } catch (\Throwable $e) {
                $errors++;
                Log::error('Auto clock-out failed', [
                    'attendance_id' => $attendance->id ?? null,
                    'error' => $e->getMessage(),
                ]);
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        // Refresh affected monthly summaries.
        if (!$isDryRun) {
            foreach ($touched as $tenantId => $users) {
                foreach ($users as $userId => $months) {
                    foreach (array_keys($months) as $ym) {
                        try {
                            $this->latePolicyService->recalculateMonth((int) $userId, (int) $tenantId, $ym);
                            $this->summaryService->updateMonthlySummary($userId, $ym, $tenantId);
                        } catch (\Throwable $e) {
                            Log::error('Summary refresh after auto clock-out failed', [
                                'user_id' => $userId, 'month' => $ym, 'error' => $e->getMessage(),
                            ]);
                        }
                    }
                }
            }
        }

        $this->info("Closed: {$ok}");
        $this->line("Errors: {$errors}");

        return $errors ? self::FAILURE : self::SUCCESS;
    }

    /** The still-open `in` punch behind this attendance row, if the day uses punches. */
    private function openPunch($attendance): ?AttendancePunch
    {
        $latest = AttendancePunch::withoutGlobalScopes()
            ->where('tenant_id', $attendance->tenant_id)
            ->where('user_id', $attendance->user_id)
            ->where('date', Carbon::parse($attendance->date)->format('Y-m-d'))
            ->where('status', 'active')
            ->orderByDesc('punched_at')
            ->orderByDesc('id')
            ->first();

        return ($latest && $latest->direction === 'in') ? $latest : null;
    }

    /** End of the shift the open punch was clocked in for (multi-shift aware). */
    private function punchShiftEnd(AttendancePunch $punch): ?Carbon
    {
        if (!$punch->user_shift_id) {
            return null;
        }

        $row = UserShift::withoutGlobalScopes()->with('shift')->find($punch->user_shift_id);
        if (!$row || !$row->shift) {
            return null;
        }

        return ShiftWindow::window(Carbon::parse($row->date)->format('Y-m-d'), $row->shift)[1];
    }

    private function closeViaPunch($attendance, AttendancePunch $openPunch, Carbon $clockOut, int $maxHours, Carbon $now): void
    {
        $reason = "Auto clock-out (cap {$maxHours}h) on " . $now->format('Y-m-d H:i');

        app(AttendancePunchService::class)->capture(new PunchInput(
            userId: (int) $attendance->user_id,
            tenantId: (int) $attendance->tenant_id,
            direction: 'out',
            punchedAt: $clockOut,
            source: 'system',
            method: 'auto_clockout',
            address: 'Auto clock-out',
            audit: new AuditContext(source: 'auto_clockout', reason: "Exceeded {$maxHours}h without clock-out"),
            metadata: ['auto_clockout' => true, 'cap_hours' => $maxHours],
        ));

        // Same day classification + remark the direct path writes.
        $row = $attendance->fresh();
        app(AttendanceEntryService::class)->record(
            (int) $row->user_id,
            (int) $row->tenant_id,
            Carbon::parse($row->date)->format('Y-m-d'),
            [
                'attendance_status' => $this->classify($row, (float) $row->worked_hours),
                'remarks' => trim(($row->remarks ? $row->remarks . ' | ' : '') . $reason),
            ],
            new AuditContext(source: 'auto_clockout', reason: "Exceeded {$maxHours}h without clock-out"),
        );
    }

    /**
     * Scheduled shift end as a datetime on the attendance date (or the next day
     * for an overnight shift). Null when no shift info is available.
     */
    private function scheduledShiftEnd($attendance, Carbon $clockIn): ?Carbon
    {
        $start = $attendance->scheduled_shift_start;
        $end = $attendance->scheduled_shift_end;
        $overnight = null;

        if ((!$start || !$end) && $attendance->shift_id) {
            $shift = Shift::withoutGlobalScopes()->find($attendance->shift_id);
            $start = $shift->start_time ?? null;
            $end = $shift->end_time ?? null;
            $overnight = $shift->is_overnight ?? null;
        }

        if (!$end) {
            return null;
        }

        $date = Carbon::parse($attendance->date)->format('Y-m-d');
        $endAt = Carbon::parse($date . ' ' . $end);

        // Overnight shift -> end is on the following day.
        if ($start && $this->calc->isOvernight(['start_time' => $start, 'end_time' => $end, 'is_overnight' => $overnight])) {
            $endAt->addDay();
        }

        return $endAt;
    }

    /**
     * present / half_day / absent / overtime for the closed day.
     */
    private function classify($attendance, float $workedHours): string
    {
        $expected = 0;
        if ($attendance->scheduled_shift_start && $attendance->scheduled_shift_end) {
            $expected = $this->calc->expectedWorkSeconds([
                'start_time' => $attendance->scheduled_shift_start,
                'end_time' => $attendance->scheduled_shift_end,
            ]);
        }

        $policy = app(\App\Services\Attendance\PolicyResolver::class)
            ->forUserDate((int) $attendance->tenant_id, (int) $attendance->user_id, (string) $attendance->date);

        if ($expected > 0) {
            $ratio = ($workedHours * 3600) / $expected;
            if ($ratio >= 1.0) {
                return 'overtime';
            }
        }

        return $policy->classify($workedHours, (int) $expected);
    }
}
