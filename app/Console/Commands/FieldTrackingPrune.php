<?php

namespace App\Console\Commands;

use App\Services\FieldTracking\ArchivingPruner;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Deletes GPS breadcrumb rows (attendance_tracks) older than each tenant's
 * retention window. When config('location.archive_before_prune') is on, every
 * row is appended to an NDJSON file (one per month) before it is deleted —
 * including the cutoff's partial month (ArchivingPruner). Clock-in/out locations
 * on the attendances row are never touched.
 *
 * attendance_tracks is legacy/frozen and its rows were migrated into
 * attendance_tracking_points (db-merge/gps, 2026-10-11), so this command is no
 * longer scheduled; it stays for manual clean-up of the old table.
 */
class FieldTrackingPrune extends Command
{
    protected $signature = 'field-tracking:prune
                            {--tenant= : restrict to one tenant}
                            {--dry-run : count only, delete nothing}
                            {--chunk=5000 : rows per delete batch}';

    protected $description = 'Prune (and optionally archive) old GPS track rows past retention.';

    public function handle(ArchivingPruner $pruner): int
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

            $totalDeleted += $pruner->prune(
                'attendance_tracks',
                (int) $t->id,
                $cutoff,
                $chunk,
                $archive ? storage_path("app/field-tracking-archive/tenant-{$t->id}") : null
            );
        }

        $this->info(($dry ? 'Would delete ' : 'Deleted ') . $totalDeleted . ' row(s).');

        return self::SUCCESS;
    }
}
