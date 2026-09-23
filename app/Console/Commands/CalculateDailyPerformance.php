<?php

namespace App\Console\Commands;

use App\Models\EmployeeDailyPerformance;
use App\Models\User;
use App\Services\Performance\PerformanceDailyScorer;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Computes one App\Models\EmployeeDailyPerformance row per employee per day.
 * Scheduled dailyAt('02:30') — 90 minutes after attendance:update-summaries'
 * 01:00 run, so the prior day's attendance data has settled. Scores
 * yesterday by default (a day isn't "done" until it's over).
 */
class CalculateDailyPerformance extends Command
{
    protected $signature = 'performance:calculate-daily
        {--date= : Score a single date (Y-m-d)}
        {--from= : Start of a date range (Y-m-d)}
        {--to= : End of a date range (Y-m-d)}
        {--user= : Only this user id}
        {--force : Recalculate even if a row already exists for the date}
        {--dry-run : Compute and report without writing anything}';

    protected $description = 'Calculate daily performance scores for all employees (or a bounded range/user)';

    public function handle(PerformanceDailyScorer $scorer): int
    {
        [$start, $end] = $this->resolveRange();
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        $users = User::withoutGlobalScopes()
            ->where('status', '1')
            ->whereIn('role', ['employee', 'manager', 'admin', 'hr'])
            ->when($this->option('user'), fn ($q, $userId) => $q->where('id', $userId))
            ->with('jobDetails:user_id,joining_date')
            ->get(['id', 'tenant_id']);

        $calculated = 0;
        $skipped = 0;
        $excluded = 0;
        $failed = 0;

        foreach ($users as $user) {
            $tenantId = (int) $user->tenant_id;
            if (! $tenantId) {
                continue;
            }

            $joiningDate = $user->jobDetails?->joining_date ? Carbon::parse($user->jobDetails->joining_date) : null;

            for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
                if ($joiningDate && $date->lt($joiningDate->copy()->startOfDay())) {
                    continue;
                }

                $dateStr = $date->format('Y-m-d');

                if (! $force) {
                    $exists = EmployeeDailyPerformance::withoutGlobalScopes()
                        ->where('tenant_id', $tenantId)->where('user_id', $user->id)
                        ->where('performance_date', $dateStr)
                        ->exists();
                    if ($exists) {
                        $skipped++;
                        continue;
                    }
                }

                try {
                    $result = $scorer->scoreDay((int) $user->id, $tenantId, $dateStr);
                } catch (\Throwable $e) {
                    $failed++;
                    if (! $dryRun) {
                        EmployeeDailyPerformance::withoutGlobalScopes()->updateOrCreate(
                            ['tenant_id' => $tenantId, 'user_id' => $user->id, 'performance_date' => $dateStr],
                            ['calculation_status' => 'failed', 'remarks' => mb_substr($e->getMessage(), 0, 500), 'calculated_at' => now()]
                        );
                    }
                    $this->error("Failed for user {$user->id} on {$dateStr}: {$e->getMessage()}");
                    continue;
                }

                if (($result['calculation_status'] ?? null) === 'pending') {
                    // Future date — no row written, matches "never rendered as 0".
                    continue;
                }

                if ($result['calculation_status'] === 'excluded') {
                    $excluded++;
                } else {
                    $calculated++;
                }

                if (! $dryRun) {
                    EmployeeDailyPerformance::withoutGlobalScopes()->updateOrCreate(
                        ['tenant_id' => $tenantId, 'user_id' => $user->id, 'performance_date' => $dateStr],
                        array_merge($result, ['calculated_at' => now()])
                    );
                }
            }
        }

        $this->info(sprintf(
            '%sCalculated: %d, Excluded (no activity): %d, Skipped (already existed): %d, Failed: %d',
            $dryRun ? '[DRY RUN] ' : '',
            $calculated,
            $excluded,
            $skipped,
            $failed
        ));

        return self::SUCCESS;
    }

    /** @return array{0: \Carbon\Carbon, 1: \Carbon\Carbon} */
    private function resolveRange(): array
    {
        if ($this->option('date')) {
            $d = Carbon::parse($this->option('date'))->startOfDay();

            return [$d, $d->copy()];
        }

        if ($this->option('from') || $this->option('to')) {
            $from = Carbon::parse($this->option('from') ?: now()->subDay())->startOfDay();
            $to = Carbon::parse($this->option('to') ?: now()->subDay())->startOfDay();

            return [$from, $to];
        }

        $yesterday = now()->subDay()->startOfDay();

        return [$yesterday, $yesterday->copy()];
    }
}
