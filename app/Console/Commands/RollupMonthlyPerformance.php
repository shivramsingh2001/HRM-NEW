<?php

namespace App\Console\Commands;

use App\Models\EmployeeKpiScore;
use App\Models\User;
use App\Services\Performance\PerformanceRollupService;
use App\Services\PerformanceNotificationService;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Aggregates a month's App\Models\EmployeeDailyPerformance rows into
 * `employee_kpi_scores` via PerformanceRollupService — replaces the old
 * performance:calculate command, which computed the month from scratch
 * itself. Scheduled monthlyOn(1, '03:00'), after the daily job has produced
 * a full month of rows.
 */
class RollupMonthlyPerformance extends Command
{
    protected $signature = 'performance:rollup-monthly
        {--month= : Y-m, defaults to last month}
        {--user= : Only this user id}
        {--force : Recalculate even if already calculated today}
        {--dry-run : Compute and report without writing anything}
        {--notify : Send a "your score is ready" notification to each rolled-up employee}';

    protected $description = 'Roll up daily performance scores into the monthly employee_kpi_scores table';

    public function handle(PerformanceRollupService $rollup, PerformanceNotificationService $notifier): int
    {
        $month = $this->option('month') ?: now()->subMonth()->format('Y-m');
        $force = (bool) $this->option('force');
        $dryRun = (bool) $this->option('dry-run');
        $notify = (bool) $this->option('notify');

        $users = User::withoutGlobalScopes()
            ->where('status', '1')
            ->whereNotNull('tenant_id')
            ->whereIn('role', ['employee', 'manager'])
            ->when($this->option('user'), fn ($q, $userId) => $q->where('id', $userId))
            ->with('jobDetails:user_id,joining_date')
            ->get();

        $done = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($users as $user) {
            if (! $user->tenant_id) {
                $skipped++;
                continue;
            }

            if (! $force) {
                $existing = EmployeeKpiScore::withoutGlobalScopes()
                    ->where('tenant_id', $user->tenant_id)->where('user_id', $user->id)
                    ->where('reporting_month', $month . '-01')
                    ->first();
                if ($existing && $existing->calculated_at && Carbon::parse($existing->calculated_at)->isToday()) {
                    $skipped++;
                    continue;
                }
            }

            if ($dryRun) {
                $done++;
                continue;
            }

            try {
                $kpiScore = $rollup->rollupMonth($user, $month);
                $done++;

                if ($notify) {
                    $notifier->notifyMonthlyScoreReady($kpiScore, $user);
                }
            } catch (\Throwable $e) {
                $failed++;
                $this->error("Failed for user {$user->id}: {$e->getMessage()}");
            }
        }

        $this->info(sprintf(
            '%sRolled up: %d, Skipped (already calculated today): %d, Failed: %d',
            $dryRun ? '[DRY RUN] ' : '',
            $done,
            $skipped,
            $failed
        ));

        return self::SUCCESS;
    }
}
