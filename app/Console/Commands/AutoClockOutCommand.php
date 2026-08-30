<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Attendance;
use App\Models\AttendanceLog;
use App\Models\Shift;
use App\Models\UserShift;
use App\Services\Attendance\AttendanceCalculator;
use App\Services\Attendance\LatePolicyService;
use App\Services\AttendanceSummaryService;
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
        $maxHours = max(1, (int) $this->option('hours'));
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

        $due = $records->filter(function ($a) use ($maxHours, $now) {
            try {
                $clockIn = Carbon::parse($a->clock_in);
                return $now->getTimestamp() - $clockIn->getTimestamp() >= $maxHours * 3600;
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
                $clockIn = Carbon::parse($attendance->clock_in);

                // Close at the earliest sane moment: clock_in + maxHours, or the
                // scheduled shift end (with overnight handling) when that is earlier.
                $cap = $clockIn->copy()->addHours($maxHours);
                $shiftEnd = $this->scheduledShiftEnd($attendance, $clockIn);
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

                DB::transaction(function () use ($attendance, $clockOut, $totalHours, $workedHours, $status, $maxHours, $now, $clockIn, $workedSeconds) {
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
                        'attendance_id' => $attendance->id,
                        'event_type' => 'manual_adjustment',
                        'event_time' => $clockOut->format('Y-m-d H:i:s'),
                        'latitude' => $attendance->clock_in_lat,
                        'longitude' => $attendance->clock_in_long,
                        'address' => 'Auto clock-out',
                        'verification_method' => 'system',
                        'user_agent' => 'AutoClockOutCommand',
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

    /**
     * Scheduled shift end as a datetime on the attendance date (or the next day
     * for an overnight shift). Null when no shift info is available.
     */
    private function scheduledShiftEnd($attendance, Carbon $clockIn): ?Carbon
    {
        $start = $attendance->scheduled_shift_start;
        $end = $attendance->scheduled_shift_end;

        if ((!$start || !$end) && $attendance->shift_id) {
            $shift = Shift::withoutGlobalScopes()->find($attendance->shift_id);
            $start = $shift->start_time ?? null;
            $end = $shift->end_time ?? null;
        }

        if (!$end) {
            return null;
        }

        $date = Carbon::parse($attendance->date)->format('Y-m-d');
        $endAt = Carbon::parse($date . ' ' . $end);

        // Overnight shift -> end is on the following day.
        if ($start && $this->calc->isOvernight(['start_time' => $start, 'end_time' => $end])) {
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

        if ($expected > 0) {
            $ratio = ($workedHours * 3600) / $expected;
            if ($ratio >= 1.0) {
                return 'overtime';
            }
            if ($ratio >= (float) config('attendance.ratio.present', 0.9)) {
                return 'present';
            }
            return $ratio >= (float) config('attendance.ratio.half', 0.5) ? 'half_day' : 'absent';
        }

        if ($workedHours >= (float) config('attendance.fallback_hours.present', 8)) {
            return 'present';
        }
        return $workedHours >= (float) config('attendance.fallback_hours.half', 4) ? 'half_day' : 'absent';
    }
}
