<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Attendance\PayrollDaysService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Tier 2 / T2-E — compare the legacy payroll attendance recompute against
 * PayrollDaysService for a month. Enable ATTENDANCE_PAYROLL_READTHROUGH only
 * when the drift is acceptable.
 */
class PayrollDaysDiff extends Command
{
    protected $signature = 'attendance:payroll-days-diff
                            {--month= : Y-m (default: last month)}
                            {--tenant= : restrict to one tenant}
                            {--limit=25 : users to sample}
                            {--tolerance=0.5 : ignore day/hour deltas at or below this}';

    protected $description = 'Diff legacy payroll attendance figures vs PayrollDaysService.';

    public function handle(PayrollDaysService $pds): int
    {
        $month = $this->option('month') ?: now()->subMonthNoOverflow()->format('Y-m');
        $tol = (float) $this->option('tolerance');
        [$start, $end] = [
            Carbon::parse($month . '-01')->startOfMonth()->toDateString(),
            Carbon::parse($month . '-01')->endOfMonth()->toDateString(),
        ];

        $users = User::withoutGlobalScopes()
            ->when($this->option('tenant'), fn ($q) => $q->where('tenant_id', $this->option('tenant')))
            ->where('status', 1)
            ->limit((int) $this->option('limit'))
            ->get(['id', 'tenant_id', 'name']);

        $this->info("Month {$month} — {$users->count()} users (tolerance {$tol})");

        $rows = [];
        foreach ($users as $u) {
            $legacy = $this->legacy($u->id, $u->tenant_id, $start, $end);
            $new = $pds->forMonth($u->id, $month, $u->tenant_id);

            $deltas = [];
            foreach ([
                'total_working_hours' => 'actual_worked_hours',
                'overtime_hours' => 'overtime_hours',
            ] as $lKey => $nKey) {
                $a = (float) $legacy[$lKey];
                $b = (float) $new[$nKey];
                if (abs($a - $b) > $tol) {
                    $deltas[] = "{$lKey}: legacy={$a} new={$b}";
                }
            }
            $presentLegacy = (float) $legacy['present_days'];
            $presentNew = (float) $new['present_days'] + 0.5 * (float) $new['half_days'];
            if (abs($presentLegacy - $presentNew) > $tol) {
                $deltas[] = "present_days: legacy={$presentLegacy} new={$presentNew}";
            }

            if ($deltas) {
                $rows[] = [$u->id, $u->name, implode('; ', $deltas)];
            }
        }

        if ($rows) {
            $this->table(['User', 'Name', 'Drift'], $rows);
            $this->warn(count($rows) . ' of ' . $users->count() . ' drifted beyond tolerance.');

            return self::FAILURE;
        }

        $this->info('No material drift — safe to enable ATTENDANCE_PAYROLL_READTHROUGH.');

        return self::SUCCESS;
    }

    /** Mirror of MonthlyPayrollController::calculateAttendanceSummary (raw path). */
    private function legacy(int $userId, ?int $tenantId, string $start, string $end): array
    {
        $rows = DB::select(
            'SELECT total_hours, worked_hours, scheduled_shift_start, scheduled_shift_end
               FROM attendances
              WHERE user_id = ? AND tenant_id = ? AND status = 1 AND date BETWEEN ? AND ?',
            [$userId, $tenantId, $start, $end]
        );

        $full = 0;
        $half = 0;
        $hoursTotal = 0.0;
        $ot = 0.0;
        $std = 8.0;

        foreach ($rows as $r) {
            $h = ($r->worked_hours !== null && $r->worked_hours !== '')
                ? (float) $r->worked_hours
                : 0.0;

            $expected = 0.0;
            if ($r->scheduled_shift_start && $r->scheduled_shift_end) {
                $s = strtotime($r->scheduled_shift_start);
                $e = strtotime($r->scheduled_shift_end);
                $expected = max(0, ($e - $s) / 3600);
            }
            $ratio = $expected > 0 ? $h / $expected : ($h / $std);

            if ($ratio >= 0.9 || $h >= $std) {
                $full++;
                $hoursTotal += $h;
                if ($h > $std) {
                    $ot += $h - $std;
                }
            } elseif ($ratio >= 0.5 || $h >= 4) {
                $half++;
                $hoursTotal += $h;
            }
        }

        return [
            'present_days' => $full + $half * 0.5,
            'total_working_hours' => round($hoursTotal, 2),
            'overtime_hours' => round($ot, 2),
        ];
    }
}
