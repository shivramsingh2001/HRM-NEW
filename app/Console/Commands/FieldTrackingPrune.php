<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Deletes GPS breadcrumb rows (attendance_tracks) older than each tenant's
 * retention window, taking an NDJSON backup of each whole month first when
 * config('location.archive_before_prune') is on. Clock-in/out locations on the
 * attendances row are never touched. Schedule daily.
 */
class FieldTrackingPrune extends Command
{
    protected $signature = 'field-tracking:prune
                            {--tenant= : restrict to one tenant}
                            {--dry-run : count only, delete nothing}
                            {--chunk=5000 : rows per delete batch}';

    protected $description = 'Prune (and optionally archive) old GPS track rows past retention.';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $chunk = max(500, (int) $this->option('chunk'));
        $floor = (int) config('location.retention_floor_days', 30);
        $archive = (bool) config('location.archive_before_prune', true);

        $tenants = DB::table('tenants')
            ->when($this->option('tenant'), fn ($q) => $q->where('id', $this->option('tenant')))
            ->get(['id', 'field_tracking_retention_days']);

        $totalDeleted = 0;
        foreach ($tenants as $t) {
            $days = max($floor, (int) ($t->field_tracking_retention_days ?: 90));
            $cutoff = now()->subDays($days);

            $due = DB::table('attendance_tracks')->where('tenant_id', $t->id)
                ->where('track_time', '<', $cutoff)->count();
            if ($due === 0) {
                continue;
            }

            $this->line("tenant {$t->id}: {$due} row(s) older than {$days}d" . ($dry ? ' [dry-run]' : ''));
            if ($dry) {
                $totalDeleted += $due;
                continue;
            }

            if ($archive) {
                $this->archiveMonths((int) $t->id, $cutoff);
            }

            do {
                $n = DB::table('attendance_tracks')->where('tenant_id', $t->id)
                    ->where('track_time', '<', $cutoff)->limit($chunk)->delete();
                $totalDeleted += $n;
            } while ($n > 0);
        }

        $this->info(($dry ? 'Would delete ' : 'Deleted ') . $totalDeleted . ' row(s).');

        return self::SUCCESS;
    }

    /** NDJSON-dump each whole calendar month fully older than the cutoff. */
    private function archiveMonths(int $tenantId, Carbon $cutoff): void
    {
        $oldest = DB::table('attendance_tracks')->where('tenant_id', $tenantId)
            ->where('track_time', '<', $cutoff)->min('track_time');
        if (! $oldest) {
            return;
        }

        $cursor = Carbon::parse($oldest)->startOfMonth();
        $lastFull = $cutoff->copy()->startOfMonth();

        $dir = storage_path("app/field-tracking-archive/tenant-{$tenantId}");
        File::ensureDirectoryExists($dir);

        while ($cursor->lt($lastFull)) {
            $mStart = $cursor->copy()->startOfMonth();
            $mEnd = $cursor->copy()->endOfMonth()->endOfDay();
            $path = "{$dir}/{$cursor->format('Y-m')}.ndjson";

            if (! File::exists($path)) {
                $handle = fopen($path, 'w');
                DB::table('attendance_tracks')->where('tenant_id', $tenantId)
                    ->whereBetween('track_time', [$mStart, $mEnd])->orderBy('id')
                    ->chunk(2000, function ($rows) use ($handle) {
                        foreach ($rows as $r) {
                            fwrite($handle, json_encode($r, JSON_UNESCAPED_SLASHES) . "\n");
                        }
                    });
                fclose($handle);
                if (filesize($path) === 0) {
                    @unlink($path);
                }
            }

            $cursor->addMonth();
        }
    }
}
