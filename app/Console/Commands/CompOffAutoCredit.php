<?php

namespace App\Console\Commands;

use App\Services\Attendance\PolicyResolver;
use App\Services\Leave\CompOffService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Nightly, for employees whose comp-off is "credited automatically": credit
 * every worked week-off / holiday of the last N days not claimed yet
 * (CompOffService::autoCredit). Request-mode employees are left to request.
 */
class CompOffAutoCredit extends Command
{
    protected $signature = 'comp-off:auto-credit
                            {--days=7 : How many days back to look}
                            {--tenant= : Only this tenant id}';

    protected $description = 'Credit comp-off automatically for worked week-offs / holidays (auto mode)';

    public function handle(CompOffService $compOff, PolicyResolver $policies): int
    {
        $from = Carbon::today()->subDays(max(1, (int) $this->option('days')))->toDateString();
        $to = Carbon::yesterday()->toDateString();
        $count = 0;

        $rows = DB::table('attendances')->whereBetween('date', [$from, $to])->whereNotNull('clock_in')->whereNotNull('clock_out')
            ->when($this->option('tenant'), fn ($q, $t) => $q->where('tenant_id', (int) $t))
            ->select('tenant_id', 'user_id')->distinct()->get();
        foreach ($rows as $r) {
            $policy = $policies->forUserDate((int) $r->tenant_id, (int) $r->user_id, $to);
            if (! $policy->compOffEnabled || $policy->compOffMode !== 'auto') {
                continue;
            }
            try {
                $dates = $compOff->autoCredit((int) $r->tenant_id, (int) $r->user_id, $from, $to);
                $count += count($dates);
                foreach ($dates as $d) {
                    $this->line("tenant {$r->tenant_id} user {$r->user_id}: {$d}");
                }
            } catch (\Throwable $e) {
                report($e);
                $this->error("tenant {$r->tenant_id} user {$r->user_id}: {$e->getMessage()}");
            }
        }

        $this->info("Comp-off credited automatically: {$count} day(s) ({$from} → {$to}).");

        return self::SUCCESS;
    }
}
