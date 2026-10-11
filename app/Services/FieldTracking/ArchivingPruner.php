<?php

namespace App\Services\FieldTracking;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Deletes a tenant's GPS rows older than a cutoff, archiving EVERY row it
 * deletes first (NDJSON, one file per calendar month of track_time, appended).
 *
 * Used by field-tracking:prune (attendance_tracks) and field-tracking:sweep-sessions
 * (attendance_tracking_points). Replaces the old "dump whole months, then delete
 * everything older than the cutoff" logic, which deleted the cutoff's partial month
 * without any archive (54,014 rows lost that way on 2026-10-11).
 *
 * Works chunk by chunk: read due rows by id → append them to their month file →
 * delete exactly those ids. A row is never deleted before it is written, and a
 * re-run never archives a row twice (it is gone after its chunk). A crash between
 * write and delete can at most repeat that chunk's lines in the file.
 */
class ArchivingPruner
{
    /**
     * @param  string|null  $archiveDir  directory for the NDJSON files; null = delete without archiving
     * @return int rows deleted
     */
    public function prune(string $table, int $tenantId, Carbon $cutoff, int $chunk, ?string $archiveDir, string $filePrefix = ''): int
    {
        if ($archiveDir !== null) {
            File::ensureDirectoryExists($archiveDir);
        }

        $deleted = 0;
        $afterId = 0;

        while (true) {
            $rows = DB::table($table)
                ->where('tenant_id', $tenantId)
                ->where('track_time', '<', $cutoff)
                ->where('id', '>', $afterId)
                ->orderBy('id')
                ->limit($chunk)
                ->get();

            if ($rows->isEmpty()) {
                break;
            }

            if ($archiveDir !== null) {
                $this->append($rows, $archiveDir, $filePrefix);
            }

            $ids = $rows->pluck('id')->all();
            $deleted += DB::table($table)->whereIn('id', $ids)->delete();
            $afterId = (int) end($ids);
        }

        return $deleted;
    }

    /** Append rows to <dir>/<prefix>YYYY-MM.ndjson by the month of their track_time. */
    private function append($rows, string $dir, string $prefix): void
    {
        $byMonth = [];
        foreach ($rows as $r) {
            $month = Carbon::parse($r->track_time)->format('Y-m');
            $byMonth[$month][] = json_encode($r, JSON_UNESCAPED_SLASHES);
        }

        foreach ($byMonth as $month => $lines) {
            $path = "{$dir}/{$prefix}{$month}.ndjson";
            if (file_put_contents($path, implode("\n", $lines) . "\n", FILE_APPEND | LOCK_EX) === false) {
                // Never delete what could not be archived.
                throw new \RuntimeException("Could not write GPS archive {$path}");
            }
        }
    }
}
