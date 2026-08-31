<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Attendance\LatePolicyService;
use App\Services\AttendanceSummaryService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class UpdateAttendanceSummaries extends Command
{
    protected $signature = 'attendance:update-summaries
                            {--month= : Single month in Y-m format (default: current month)}
                            {--from= : Start month Y-m for a range backfill}
                            {--to= : End month Y-m for a range backfill (default: current month)}
                            {--user= : Specific user ID to update}
                            {--tenant= : Restrict to a single tenant ID}
                            {--force : Delete and recalculate existing summaries}
                            {--dry-run : Calculate but roll back (no writes)}';

    protected $description = 'Recalculate monthly attendance summaries for active users (tenant-safe).';

    public function __construct(
        protected AttendanceSummaryService $summaryService,
        protected LatePolicyService $latePolicyService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $userId = $this->option('user');
        $tenantId = $this->option('tenant');
        $force = (bool) $this->option('force');
        $dryRun = (bool) $this->option('dry-run');

        // Build the list of months to process.
        if ($this->option('from')) {
            $cursor = Carbon::parse($this->option('from') . '-01')->startOfMonth();
            $end = Carbon::parse(($this->option('to') ?: Carbon::now()->format('Y-m')) . '-01')->startOfMonth();
            $months = [];
            while ($cursor->lte($end)) {
                $months[] = $cursor->format('Y-m');
                $cursor->addMonth();
            }
        } else {
            $months = [$this->option('month') ?: Carbon::now()->format('Y-m')];
        }

        $this->info('Attendance summary recalculation — months: ' . implode(', ', $months)
            . ($dryRun ? '  [DRY RUN]' : ''));

        // TenantTrait's global scope is a no-op in the CLI, so this spans every
        // tenant by design; the summary service re-scopes per user internally.
        $users = User::withoutGlobalScopes()
            ->where('status', 1)
            ->when($userId, fn ($q) => $q->where('id', $userId))
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->get(['id', 'tenant_id', 'name']);

        if ($users->isEmpty()) {
            $this->error('No matching active users.');
            return self::FAILURE;
        }

        $this->info("Processing {$users->count()} user(s) x " . count($months) . ' month(s)');
        $bar = $this->output->createProgressBar($users->count() * count($months));
        $bar->start();

        $ok = 0;
        $failed = [];

        foreach ($months as $month) {
        foreach ($users as $user) {
            try {
                DB::beginTransaction();

                if ($force) {
                    \App\Models\AttendanceSummary::withoutGlobalScopes()
                        ->where('tenant_id', $user->tenant_id)
                        ->where('user_id', $user->id)
                        ->where('year_month', $month)
                        ->delete();
                }

                $this->latePolicyService->recalculateMonth($user->id, (int) $user->tenant_id, $month);
                $result = $this->summaryService->updateMonthlySummary($user->id, $month, $user->tenant_id);

                if ($result === false) {
                    throw new \RuntimeException('summary service returned false');
                }

                $dryRun ? DB::rollBack() : DB::commit();
                $ok++;
            } catch (\Throwable $e) {
                DB::rollBack();
                $failed[] = ['id' => $user->id, 'name' => $user->name, 'error' => $e->getMessage()];
            }

            $bar->advance();
        }
        } // months

        $bar->finish();
        $this->newLine(2);

        $this->info("Success: {$ok}");
        $this->line('Failed:  ' . count($failed));

        if ($failed) {
            $this->newLine();
            $this->table(['ID', 'Name', 'Error'], $failed);
        }

        if ($dryRun) {
            $this->warn('Dry run — nothing was saved.');
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
