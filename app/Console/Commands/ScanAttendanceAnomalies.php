<?php

namespace App\Console\Commands;

use App\Services\Analytics\AnomalyScanner;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Tier 2 / T2-F — daily anomaly sweep. Schedule once a day (after auto-clockout).
 *
 *   php artisan attendance:scan-anomalies              (yesterday, all tenants)
 *   php artisan attendance:scan-anomalies --date=2026-08-31 --tenant=7
 */
class ScanAttendanceAnomalies extends Command
{
    protected $signature = 'attendance:scan-anomalies
                            {--date= : Y-m-d (default: yesterday)}
                            {--tenant= : restrict to one tenant}
                            {--days=1 : scan this many days back from --date}';

    protected $description = 'Flag attendance anomalies (buddy-punch, impossible travel, chronic missing clock-out).';

    public function handle(AnomalyScanner $scanner): int
    {
        $end = $this->option('date') ? Carbon::parse($this->option('date')) : Carbon::yesterday();
        $days = max(1, (int) $this->option('days'));

        $tenantIds = DB::table('tenants')
            ->when($this->option('tenant'), fn ($q) => $q->where('id', $this->option('tenant')))
            ->pluck('id');

        $total = 0;
        foreach ($tenantIds as $tid) {
            for ($i = 0; $i < $days; $i++) {
                $date = $end->copy()->subDays($i)->toDateString();
                $found = $scanner->scanDay((int) $tid, $date);
                $total += $found;
                if ($found) {
                    $this->line("  tenant {$tid} {$date}: {$found} new");
                }
            }
        }

        $this->info("Done. {$total} new anomal" . ($total === 1 ? 'y' : 'ies') . ' flagged.');

        return self::SUCCESS;
    }
}
