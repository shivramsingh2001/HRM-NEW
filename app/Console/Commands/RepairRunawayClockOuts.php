<?php

namespace App\Console\Commands;

use App\Models\Attendance;
use App\Services\Attendance\AttendanceCalculator;
use App\Services\Attendance\AttendanceEntryService;
use App\Services\Attendance\AttendanceRegradeService;
use App\Services\Attendance\AuditContext;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Repairs attendance days whose clock-out landed days / months after the
 * clock-in — e.g. an old bulk auto clock-out run that stamped every open day
 * with "2026-05-04 18:39:39", so reports showed 296 h or 3,697 h for one day.
 *
 * Only a session longer than AttendanceCalculator::MAX_SESSION_SECONDS (48 h)
 * is touched — a genuine long duty (06:30 → 09:00 next day) is left alone.
 * The day's real length is the stored "H:i:s" total_hours (computed from the
 * clock-out time on the same calendar day); the clock-out becomes
 * clock_in + total_hours, the matching clock-out punch is moved with it, the
 * change is written to attendance_logs, and statuses / monthly summaries are
 * recalculated. A day whose total_hours can't confirm the repair is only
 * listed for manual review.
 *
 *   php artisan attendance:repair-runaway-clockouts --dry-run
 *   php artisan attendance:repair-runaway-clockouts
 */
class RepairRunawayClockOuts extends Command
{
    protected $signature = 'attendance:repair-runaway-clockouts {--dry-run : List what would change, change nothing}';

    protected $description = 'Repair attendance days whose clock-out is more than 48 h after the clock-in (bad bulk clock-outs)';

    public function handle(AttendanceCalculator $calc, AttendanceEntryService $entry, AttendanceRegradeService $regrade): int
    {
        $dry = (bool) $this->option('dry-run');
        $maxHours = AttendanceCalculator::MAX_SESSION_SECONDS / 3600;

        $rows = Attendance::withoutGlobalScopes()
            ->whereNotNull('clock_in')->whereNotNull('clock_out')
            ->where('worked_hours', '>', $maxHours)
            ->orderBy('id')->get();

        $fixed = 0;
        $review = [];
        $months = []; // tenant => earliest date touched

        foreach ($rows as $row) {
            $in = Carbon::parse($row->clock_in);
            $oldOut = Carbon::parse($row->clock_out);
            $seconds = $this->durationSeconds((string) $row->total_hours);

            // total_hours must be a sane length that ends at the same clock time
            // as the stored clock-out — then it is that day's real clock-out.
            $newOut = $seconds !== null ? $in->copy()->addSeconds($seconds) : null;
            if ($newOut === null || $seconds <= 0 || $seconds > AttendanceCalculator::MAX_SESSION_SECONDS
                || $newOut->format('H:i:s') !== $oldOut->format('H:i:s')) {
                $review[] = [$row->id, $row->tenant_id, $row->user_id, substr((string) $row->date, 0, 10), $row->clock_in, $row->clock_out, $row->worked_hours, $row->total_hours];
                continue;
            }

            $this->line(sprintf('#%d  %s  %s → %s  (%s h → %s h)', $row->id, substr((string) $row->date, 0, 10),
                $oldOut->format('Y-m-d H:i:s'), $newOut->format('Y-m-d H:i:s'), $row->worked_hours, $calc->decimalHours($seconds)));
            $fixed++;
            $date = substr((string) $row->date, 0, 10);
            $months[$row->tenant_id] = min($months[$row->tenant_id] ?? $date, $date);

            if ($dry) {
                continue;
            }

            DB::transaction(function () use ($row, $oldOut, $newOut, $seconds, $calc, $entry) {
                $before = $entry->snapshot($row);

                // The clock-out punch stamped with the bad time moves with it.
                foreach (DB::table('attendance_punches')->where('user_id', $row->user_id)->where('tenant_id', $row->tenant_id)
                    ->where('direction', 'out')->where('punched_at', $oldOut->format('Y-m-d H:i:s'))
                    ->where('date', substr((string) $row->date, 0, 10))->get(['id', 'timezone']) as $punch) {
                    DB::table('attendance_punches')->where('id', $punch->id)->update([
                        'punched_at' => $newOut->format('Y-m-d H:i:s'),
                        'punched_at_utc' => $calc->toUtc($newOut->format('Y-m-d H:i:s'), $punch->timezone ?: config('app.timezone')),
                        'updated_at' => now(),
                    ]);
                }

                $row->forceFill([
                    'clock_out' => $newOut->format('Y-m-d H:i:s'),
                    'worked_hours' => $calc->decimalHours($seconds),
                    'total_hours' => $calc->formatDuration($seconds),
                ])->save();

                $entry->logExternalWrite($row, $before, new AuditContext(
                    source: 'policy_recalc',
                    reason: 'Data repair: clock-out ' . $oldOut->format('Y-m-d H:i:s') . ' was a bulk auto clock-out stamp; restored to ' . $newOut->format('Y-m-d H:i:s'),
                ));
            });
        }

        if ($review) {
            $this->warn(count($review) . ' day(s) need a manual check (their stored total does not confirm the repair):');
            $this->table(['id', 'tenant', 'user', 'date', 'clock_in', 'clock_out', 'worked_hours', 'total_hours'], $review);
        }

        if (! $dry) {
            foreach ($months as $tenantId => $from) {
                $r = $regrade->regrade((int) $tenantId, Carbon::parse($from));
                $this->line("Company {$tenantId}: re-graded {$r['employee_months']} employee-month(s)"
                    . ($r['skipped_locked'] ? '; locked (not re-graded): ' . implode(', ', $r['skipped_locked']) : '') . '.');
            }
        }

        $this->info(($dry ? '[dry run] would repair ' : 'Repaired ') . "{$fixed} day(s); " . count($review) . ' for manual review.');

        return self::SUCCESS;
    }

    /** "07:36:20" / "26:30" → seconds; null when not a duration. */
    private function durationSeconds(string $value): ?int
    {
        if (! preg_match('/^(\d{1,3}):(\d{2})(?::(\d{2}))?$/', trim($value), $m)) {
            return null;
        }

        return ((int) $m[1]) * 3600 + ((int) $m[2]) * 60 + (int) ($m[3] ?? 0);
    }
}
