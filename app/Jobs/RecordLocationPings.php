<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

/**
 * Off-request bulk insert of GPS pings. Only dispatched when
 * config('location.async_ingest') is true (needs a queue worker on the
 * "attendance" queue) — the default synchronous path is
 * App\Services\FieldTracking\TrackingPointIngestService.
 *
 * @param array<int,array<string,mixed>> $rows fully-formed attendance_tracking_points
 *        rows (tenant_id / user_id / session_id / point_id / created_at already
 *        set by the caller)
 */
class RecordLocationPings implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [10, 30];

    public function __construct(public array $rows)
    {
        $this->onQueue('attendance');
    }

    public function handle(): void
    {
        foreach (array_chunk($this->rows, 500) as $chunk) {
            DB::table('attendance_tracking_points')->insertOrIgnore($chunk);
        }
    }
}
