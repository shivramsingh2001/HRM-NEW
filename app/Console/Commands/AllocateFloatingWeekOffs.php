<?php

namespace App\Console\Commands;

use App\Services\Attendance\FloatingWeekOffAllocator;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Nightly: turn missed days into floating week-offs, up to each employee's
 * monthly count (FloatingWeekOffAllocator). Runs for the current month, and in
 * the first week also for last month (late leave approvals / regularizations
 * can move a slot). Safe to re-run: each run converges to the same days.
 */
class AllocateFloatingWeekOffs extends Command
{
    protected $signature = 'weekoffs:allocate-floating
                            {--month= : Y-m, default this month (plus last month during the first 7 days)}
                            {--tenant= : Only this tenant id}
                            {--user= : Only this employee id}';

    protected $description = 'Turn missed days into floating week-offs up to each employee\'s monthly count';

    public function handle(FloatingWeekOffAllocator $allocator): int
    {
        $months = $this->option('month') ? [$this->option('month')] : array_unique(array_filter([
            Carbon::today()->day <= 7 ? Carbon::today()->subMonthNoOverflow()->format('Y-m') : null,
            Carbon::yesterday()->format('Y-m'),
        ]));
        $tenants = $this->option('tenant') ? [(int) $this->option('tenant')]
            : DB::table('tenants')->pluck('id')->map(fn ($id) => (int) $id)->all();

        $added = $removed = 0;
        foreach ($months as $ym) {
            foreach ($tenants as $tenantId) {
                $users = $this->option('user') ? collect([(int) $this->option('user')]) : $allocator->usersToAllocate($tenantId, $ym);
                foreach ($users as $userId) {
                    try {
                        $r = $allocator->allocateMonth($tenantId, (int) $userId, $ym);
                        $added += count($r['added']);
                        $removed += count($r['removed']);
                        if ($r['added'] || $r['removed']) {
                            $this->line("{$ym} tenant {$tenantId} user {$userId}: +" . implode(',', $r['added']) . ' -' . implode(',', $r['removed']));
                        }
                    } catch (\Throwable $e) {
                        report($e);
                        $this->error("{$ym} tenant {$tenantId} user {$userId}: {$e->getMessage()}");
                    }
                }
            }
        }

        $this->info('Floating week-offs: ' . implode(', ', $months) . " — {$added} added, {$removed} removed.");

        return self::SUCCESS;
    }
}
