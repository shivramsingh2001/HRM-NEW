<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Two-table sibling of field-tracking:prune, for attendance_tracking_sessions
 * + attendance_tracking_points (replaces attendance_tracks going forward — see
 * the two-table GPS tracking plan). Two responsibilities, one daily job:
 *
 *  1. Force-closes sessions left open with no activity for --stale-hours
 *     (crash/uninstall/lost device never sending a clean clock-out).
 *  2. Prunes (archiving to NDJSON first, like field-tracking:prune) points
 *     past each tenant's retention window, then deletes the now-empty closed
 *     sessions that window has aged out.
 *
 * attendance_tracks itself is untouched here — field-tracking:prune keeps
 * running against it unchanged until it's removed in a follow-up cleanup.
 */
class FieldTrackingSweepSessions extends Command
{
    protected $signature = 'field-tracking:sweep-sessions
                            {--tenant= : restrict to one tenant}
                            {--dry-run : count only, write/delete nothing}
                            {--chunk=5000 : rows per delete batch}
                            {--stale-hours=18 : force-close an open session idle longer than this}';

    protected $description = 'Force-close stale open GPS sessions and prune (archive+delete) old tracking points/sessions past retention.';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $chunk = max(500, (int) $this->option('chunk'));
        $staleHours = max(1, (int) $this->option('stale-hours'));
        $floor = (int) config('location.retention_floor_days', 30);
        $archive = (bool) config('location.archive_before_prune', true);

        $tenants = DB::table('tenants')
            ->when($this->option('tenant'), fn ($q) => $q->where('id', $this->option('tenant')))
            ->get(['id', 'field_tracking_retention_days']);

        $this->closeStaleSessions($tenants, $staleHours, $dry);

        $totalDeletedPoints = 0;
        $totalDeletedSessions = 0;

        foreach ($tenants as $t) {
            $days = max($floor, (int) ($t->field_tracking_retention_days ?: 90));
            $cutoff = now()->subDays($days);

            $duePoints = DB::table('attendance_tracking_points')->where('tenant_id', $t->id)
                ->where('track_time', '<', $cutoff)->count();
            $dueSessions = DB::table('attendance_tracking_sessions')->where('tenant_id', $t->id)
                ->where('status', 'closed')->where('ended_at', '<', $cutoff)->count();

            if ($duePoints === 0 && $dueSessions === 0) {
                continue;
            }

            $this->line("tenant {$t->id}: {$duePoints} point(s), {$dueSessions} session(s) older than {$days}d" . ($dry ? ' [dry-run]' : ''));
            if ($dry) {
                $totalDeletedPoints += $duePoints;
                $totalDeletedSessions += $dueSessions;
                continue;
            }

            if ($archive && $duePoints > 0) {
                $this->archivePointMonths((int) $t->id, $cutoff);
            }

            do {
                $n = DB::table('attendance_tracking_points')->where('tenant_id', $t->id)
                    ->where('track_time', '<', $cutoff)->limit($chunk)->delete();
                $totalDeletedPoints += $n;
            } while ($n > 0);

            do {
                $n = DB::table('attendance_tracking_sessions')->where('tenant_id', $t->id)
                    ->where('status', 'closed')->where('ended_at', '<', $cutoff)
                    ->limit($chunk)->delete();
                $totalDeletedSessions += $n;
            } while ($n > 0);
        }

        $this->info(($dry ? 'Would delete ' : 'Deleted ') . "{$totalDeletedPoints} point(s), {$totalDeletedSessions} session(s).");

        return self::SUCCESS;
    }

    private function closeStaleSessions($tenants, int $staleHours, bool $dry): void
    {
        $cutoff = now()->subHours($staleHours);

        foreach ($tenants as $t) {
            $stale = DB::table('attendance_tracking_sessions')
                ->where('tenant_id', $t->id)
                ->where('status', 'open')
                ->where(function ($q) use ($cutoff) {
                    $q->where(function ($q2) use ($cutoff) {
                        $q2->whereNotNull('last_point_at')->where('last_point_at', '<', $cutoff);
                    })->orWhere(function ($q2) use ($cutoff) {
                        $q2->whereNull('last_point_at')->where('started_at', '<', $cutoff);
                    });
                })
                ->get(['id', 'started_at', 'last_point_at']);

            if ($stale->isEmpty()) {
                continue;
            }

            $this->line("tenant {$t->id}: force-closing {$stale->count()} stale open session(s)" . ($dry ? ' [dry-run]' : ''));

            if ($dry) {
                continue;
            }

            foreach ($stale as $s) {
                DB::table('attendance_tracking_sessions')->where('id', $s->id)->update([
                    'ended_at' => $s->last_point_at ?? $s->started_at,
                    'status' => 'closed',
                    'close_reason' => 'grace_expired',
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /** NDJSON-dump each whole calendar month of points fully older than the cutoff. */
    private function archivePointMonths(int $tenantId, Carbon $cutoff): void
    {
        $oldest = DB::table('attendance_tracking_points')->where('tenant_id', $tenantId)
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
            $path = "{$dir}/points-{$cursor->format('Y-m')}.ndjson";

            if (! File::exists($path)) {
                $handle = fopen($path, 'w');
                DB::table('attendance_tracking_points')->where('tenant_id', $tenantId)
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
