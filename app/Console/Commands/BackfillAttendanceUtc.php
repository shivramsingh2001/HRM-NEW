<?php

namespace App\Console\Commands;

use App\Services\Attendance\AttendanceCalculator;
use App\Services\Attendance\TimezoneResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Tier 1 / W4 — populate attendances.clock_in_utc / clock_out_utc / tz from the
 * legacy local-time strings.
 *
 * The zone used per row is the tenant's timezone (or --assume-tz / app default).
 * Re-runnable: only rows whose tz is NULL, or all rows with --force.
 */
class BackfillAttendanceUtc extends Command
{
    protected $signature = 'attendance:backfill-utc
                            {--tenant= : restrict to one tenant}
                            {--assume-tz= : override the zone the local strings were captured in}
                            {--chunk=500 : rows per batch}
                            {--force : also reprocess rows that already have tz set}
                            {--dry-run : count only, write nothing}';

    protected $description = 'Backfill UTC punch instants on attendances from the local-time strings.';

    public function handle(TimezoneResolver $tzResolver, AttendanceCalculator $calc): int
    {
        $tenantOpt = $this->option('tenant');
        $assumeTz = $this->option('assume-tz');
        $chunk = max(50, (int) $this->option('chunk'));
        $force = (bool) $this->option('force');
        $dry = (bool) $this->option('dry-run');

        if ($assumeTz && ! in_array($assumeTz, timezone_identifiers_list(), true)) {
            $this->error("Unknown timezone: {$assumeTz}");

            return self::FAILURE;
        }

        $base = DB::table('attendances')
            ->when($tenantOpt, fn ($q) => $q->where('tenant_id', $tenantOpt))
            ->when(! $force, fn ($q) => $q->whereNull('tz'))
            ->where(function ($q) {
                $q->whereNotNull('clock_in')->orWhereNotNull('clock_out');
            });

        $total = (clone $base)->count();
        $this->info("Rows to process: {$total}" . ($dry ? '  [DRY RUN]' : ''));
        if ($total === 0) {
            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $tzCache = [];
        $updated = 0;
        $skipped = 0;

        (clone $base)->orderBy('id')->chunkById($chunk, function ($rows) use (
            &$updated, &$skipped, &$tzCache, $assumeTz, $tzResolver, $calc, $dry, $bar
        ) {
            foreach ($rows as $row) {
                $tz = $assumeTz
                    ?: ($tzCache[$row->tenant_id] ??= $tzResolver->forTenant((int) $row->tenant_id));

                $inUtc = $calc->toUtc($row->clock_in, $tz);
                $outUtc = $calc->toUtc($row->clock_out, $tz);

                if (! $inUtc && ! $outUtc) {
                    $skipped++;
                    $bar->advance();
                    continue;
                }

                if (! $dry) {
                    DB::table('attendances')->where('id', $row->id)->update([
                        'clock_in_utc' => $inUtc?->format('Y-m-d H:i:s'),
                        'clock_out_utc' => $outUtc?->format('Y-m-d H:i:s'),
                        'tz' => $tz,
                    ]);
                }
                $updated++;
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);
        $this->info("Updated: {$updated}   Skipped (unparseable): {$skipped}");

        return self::SUCCESS;
    }
}
