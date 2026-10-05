<?php

namespace App\Console\Commands;

use App\Services\LeaveCarryForwardService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Daily: applies each company's leave carry-forward rules at the start of each
 * credit period (month, week or leave year) and expires unused carried days —
 * see App\Services\LeaveCarryForwardService. Safe to run any number of times
 * (one leave_carry_forwards row per employee, type and period).
 */
class CarryForwardLeaves extends Command
{
    protected $signature = 'leaves:carry-forward {tenant_id?*}
        {--dry-run : Show what would lapse without changing anything}
        {--date= : Pretend today is this date (Y-m-d)}';

    protected $description = 'Lapse leave above each leave type\'s carry-forward limit at the start of each credit period, and expire unused carried leave';

    public function handle(LeaveCarryForwardService $service): int
    {
        $today = $this->option('date') ? Carbon::parse($this->option('date')) : Carbon::today();
        $dryRun = (bool) $this->option('dry-run');

        $tenantIds = $this->argument('tenant_id') ?: DB::table('tenants')
            ->where('leave_carry_forward_enabled', 1)->where('leaves', 1)->where('status', 'active')
            ->pluck('id')->all();

        if ($dryRun) {
            $this->warn('DRY RUN — nothing will be changed.');
        }

        foreach ($tenantIds as $tenantId) {
            try {
                $s = $service->run((int) $tenantId, $today, $dryRun);
            } catch (\Throwable $e) {
                $this->error("Tenant {$tenantId}: failed — " . $e->getMessage());
                report($e);
                continue;
            }

            if ($s['skipped']) {
                $this->line("Tenant {$tenantId}: skipped ({$s['skipped']}).");
                continue;
            }
            foreach ($s['lines'] as $line) {
                $this->line('  ' . $line);
            }
            $this->info(sprintf(
                'Tenant %d (leave year from %s): %d checked, %d lapsed (%s days), %d expired (%s days).',
                $tenantId, $s['year_start'], $s['processed'], $s['lapsed_rows'], round($s['lapsed_days'], 2),
                $s['expired_rows'], round($s['expired_days'], 2)
            ));
        }

        return self::SUCCESS;
    }
}
