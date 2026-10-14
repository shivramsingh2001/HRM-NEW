<?php

namespace App\Console\Commands;

use App\Exceptions\PeriodLockedException;
use App\Services\Attendance\AttendanceRollupService;
use App\Services\Attendance\AuditContext;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Re-derives past multi-shift days under the 2026-10-14 rules (multi-shift pay
 * plan Phase 5): the first shift worked (the main one when worked) is the
 * day's normal shift, every other shift worked is extra (is_extra,
 * extra_shift_minutes), and the day's status / late come from the normal shift.
 *
 * Only days of an employee who had an additional shift that date AND still has
 * active punches are rebuilt (AttendanceRollupService::recompute(), the same
 * path every punch takes) — a day set by hand with no punches is left alone so
 * an admin's mark is never overwritten. Locked payroll periods are skipped.
 * Existing payslips change only when regenerated / re-saved.
 */
class RecomputeMultiShiftDays extends Command
{
    protected $signature = 'attendance:recompute-shifts
                            {--from= : First date (Y-m-d), default the 1st of three months ago}
                            {--to= : Last date (Y-m-d), default today}
                            {--tenant= : Only this tenant id}
                            {--dry-run : Show what would change, then roll back}';

    protected $description = 'Rebuild past multi-shift days with the normal-shift / extra-shift rules';

    public function handle(AttendanceRollupService $rollup): int
    {
        $from = $this->option('from') ?: now()->subMonthsNoOverflow(3)->startOfMonth()->toDateString();
        $to = $this->option('to') ?: now()->toDateString();
        $dryRun = (bool) $this->option('dry-run');

        $days = DB::table('attendances as a')
            ->whereBetween('a.date', [Carbon::parse($from)->toDateString(), Carbon::parse($to)->toDateString()])
            ->when($this->option('tenant'), fn ($q, $t) => $q->where('a.tenant_id', (int) $t))
            ->whereExists(fn ($q) => $q->from('user_shifts as us')->whereColumn('us.user_id', 'a.user_id')
                ->whereColumn('us.date', 'a.date')->where('us.is_additional', 1))
            ->whereExists(fn ($q) => $q->from('attendance_punches as p')->whereColumn('p.user_id', 'a.user_id')
                ->whereColumn('p.date', 'a.date')->where('p.status', 'active'))
            ->orderBy('a.date')
            ->get(['a.id', 'a.tenant_id', 'a.user_id', 'a.date', 'a.attendance_status', 'a.late_minutes', 'a.extra_shift_minutes']);

        $this->info("Multi-shift days {$from} → {$to}: {$days->count()}" . ($dryRun ? '  [DRY RUN]' : ''));
        $ctx = new AuditContext(source: 'policy_recalc', reason: 'Multi-shift rules: normal shift + extra shifts (attendance:recompute-shifts)');
        $rows = [];
        $counts = ['changed' => 0, 'unchanged' => 0, 'locked' => 0];

        foreach ($days as $day) {
            $date = substr((string) $day->date, 0, 10);
            DB::beginTransaction();
            try {
                $after = $rollup->recompute((int) $day->user_id, (int) $day->tenant_id, $date, $ctx);
                $dryRun ? DB::rollBack() : DB::commit();
            } catch (PeriodLockedException) {
                DB::rollBack();
                $counts['locked']++;
                $rows[] = [$day->tenant_id, $day->user_id, $date, 'payroll period locked — skipped', '', ''];

                continue;
            } catch (\Throwable $e) {
                DB::rollBack();
                report($e);
                $this->error("User {$day->user_id} {$date}: {$e->getMessage()}");

                continue;
            }

            $before = "{$day->attendance_status} · late {$day->late_minutes}m · extra {$day->extra_shift_minutes}m";
            $now = $after->getAttribute('attendance_status') . ' · late ' . $after->getAttribute('late_minutes') . 'm · extra '
                . $after->getAttribute('extra_shift_minutes') . 'm';
            $changed = $before !== $now;
            $counts[$changed ? 'changed' : 'unchanged']++;
            $rows[] = [$day->tenant_id, $day->user_id, $date, $before, $now, $changed ? 'changed' : ''];
        }

        if ($rows) {
            $this->table(['Tenant', 'User', 'Date', 'Before', 'After', ''], $rows);
        }
        $this->info("Changed {$counts['changed']}, unchanged {$counts['unchanged']}, locked {$counts['locked']}"
            . ($dryRun ? ' — nothing saved (dry run).' : '.'));

        return self::SUCCESS;
    }
}
