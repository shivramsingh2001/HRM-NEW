<?php

namespace App\Console\Commands;

use App\Services\Attendance\AutoOvertimeService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Company Policies → Overtime → automatic mode: recalculates overtime from
 * attendance for companies in automatic mode. Daily safety net for writes
 * that bypass AttendanceEntryService (e.g. the legacy auto clock-out path);
 * also used to backfill a month. Safe to run any number of times.
 */
class CalculateAutoOvertime extends Command
{
    protected $signature = 'overtime:auto-calculate {tenant_id?*}
        {--date= : One day (Y-m-d); default yesterday and today}
        {--month= : A whole month (Y-m)}';

    protected $description = 'Calculate automatic overtime from attendance for companies in automatic overtime mode';

    public function handle(AutoOvertimeService $service): int
    {
        $tenantIds = $this->argument('tenant_id') ?: DB::table('overtime_settings')
            ->where('enabled', 1)->where('mode', 'auto')->pluck('tenant_id')->all();

        if ($this->option('month')) {
            $from = Carbon::createFromFormat('Y-m-d', $this->option('month') . '-01')->startOfMonth();
            $to = $from->copy()->endOfMonth();
        } elseif ($this->option('date')) {
            $from = Carbon::parse($this->option('date'))->startOfDay();
            $to = $from->copy();
        } else {
            $from = Carbon::yesterday();
            $to = Carbon::today();
        }

        foreach ($tenantIds as $tenantId) {
            try {
                $n = $service->syncRange((int) $tenantId, $from, $to);
                $this->info("Tenant {$tenantId}: {$n} day(s) with automatic overtime ({$from->toDateString()} – {$to->toDateString()}).");
            } catch (\Throwable $e) {
                $this->error("Tenant {$tenantId}: failed — " . $e->getMessage());
                report($e);
            }
        }

        return self::SUCCESS;
    }
}
