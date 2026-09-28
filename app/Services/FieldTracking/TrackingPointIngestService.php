<?php

namespace App\Services\FieldTracking;

use App\Models\AttendanceTrackingSession;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Bulk, idempotent GPS-point ingest for attendance_tracking_points. Reuses
 * this codebase's existing array_chunk(...,500) bulk-insert shape (see
 * App\Jobs\RecordLocationPings) — synchronous by default, no new queue for
 * normal ingest. Dedup relies on the atp_session_point_uq DB constraint via
 * insertOrIgnore (same idiom as ExpenseLedgerService::lockedBalance()), not a
 * manual per-row existence check.
 */
class TrackingPointIngestService
{
    private const CHUNK_SIZE = 500;
    private const FUTURE_TOLERANCE_MINUTES = 5;

    /**
     * @param  array<int,array{point_id?:?string,lat:mixed,long:mixed,track_time:mixed,accuracy_meters?:mixed,address?:mixed,battery_per?:mixed}>  $points
     * @return array{saved:int, rejected:int, duplicates:int, session_id:?int, no_session:bool}
     */
    public function ingestBatch(int $tenantId, int $userId, array $points): array
    {
        $graceMinutes = (int) config('location.late_point_grace_minutes', 15);
        $graceCutoff = now()->subMinutes($graceMinutes);

        $candidateSessions = AttendanceTrackingSession::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->where(function ($q) use ($graceCutoff) {
                $q->where('status', 'open')
                    ->orWhere(function ($q2) use ($graceCutoff) {
                        $q2->where('status', 'closed')->where('ended_at', '>=', $graceCutoff);
                    });
            })
            ->orderByDesc('started_at')
            ->get(['id', 'started_at', 'ended_at']);

        if ($candidateSessions->isEmpty()) {
            return ['saved' => 0, 'rejected' => count($points), 'duplicates' => 0, 'session_id' => null, 'no_session' => true];
        }

        $now = now();
        $upperBound = $now->copy()->addMinutes(self::FUTURE_TOLERANCE_MINUTES);

        $rows = [];
        $rejected = 0;
        $lastSessionId = null;

        foreach ($points as $p) {
            $t = $this->parseTrackTime($p['track_time'] ?? null);

            if (! $t || $t->gt($upperBound)) {
                $rejected++;
                continue;
            }

            $session = $candidateSessions->first(function ($s) use ($t) {
                $end = $s->ended_at ?? now();

                return $t->gte($s->started_at) && $t->lte($end);
            });

            if (! $session) {
                $rejected++;
                continue;
            }

            $lastSessionId = $session->id;

            $rows[] = [
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'session_id' => $session->id,
                'point_id' => $this->resolvePointId($p, $t),
                'track_time' => $t->format('Y-m-d H:i:s'),
                'lat' => $p['lat'],
                'long' => $p['long'],
                'accuracy_meters' => is_numeric($p['accuracy_meters'] ?? null) ? $p['accuracy_meters'] : null,
                'address' => $p['address'] ?? null,
                'battery_per' => is_numeric($p['battery_per'] ?? null) ? (int) $p['battery_per'] : null,
                'created_at' => $now,
            ];
        }

        $attempted = count($rows);
        $inserted = $this->insertChunked($rows);

        return [
            'saved' => $inserted,
            'rejected' => $rejected,
            'duplicates' => max(0, $attempted - $inserted),
            'session_id' => $lastSessionId,
            'no_session' => false,
        ];
    }

    /** @param  array<int,array>  $rows */
    private function insertChunked(array $rows): int
    {
        if (empty($rows)) {
            return 0;
        }

        if (config('location.async_ingest')) {
            foreach (array_chunk($rows, self::CHUNK_SIZE) as $chunk) {
                \App\Jobs\RecordLocationPings::dispatch($chunk);
            }

            // Optimistic — the actual insertOrIgnore happens off-request, so an
            // exact duplicate count isn't available synchronously here.
            return count($rows);
        }

        $inserted = 0;
        foreach (array_chunk($rows, self::CHUNK_SIZE) as $chunk) {
            $inserted += DB::table('attendance_tracking_points')->insertOrIgnore($chunk);
        }

        $this->refreshSessionStats(array_values(array_unique(array_column($rows, 'session_id'))));

        return $inserted;
    }

    /** @param  array<int,int>  $sessionIds */
    private function refreshSessionStats(array $sessionIds): void
    {
        foreach ($sessionIds as $sessionId) {
            DB::table('attendance_tracking_sessions')->where('id', $sessionId)->update([
                'point_count' => DB::table('attendance_tracking_points')->where('session_id', $sessionId)->count(),
                'last_point_at' => DB::table('attendance_tracking_points')->where('session_id', $sessionId)->max('track_time'),
            ]);
        }
    }

    /**
     * Client-sent point_id wins. Without one, derive a deterministic id from the
     * reading itself (coords at the column's 7-decimal precision + epoch second)
     * so a retried batch of the same readings maps to the same ids and is
     * deduped by atp_session_point_uq — a random server id would not be.
     */
    private function resolvePointId(array $p, Carbon $t): string
    {
        $clientId = trim((string) ($p['point_id'] ?? ''));
        if ($clientId !== '') {
            return $clientId;
        }

        return sha1(sprintf(
            '%s|%s|%d',
            number_format((float) $p['lat'], 7, '.', ''),
            number_format((float) $p['long'], 7, '.', ''),
            $t->getTimestamp()
        ));
    }

    private function parseTrackTime(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            if (is_numeric($value)) {
                $seconds = strlen((string) $value) > 10 ? $value / 1000 : $value;

                return Carbon::createFromTimestamp((int) $seconds);
            }

            return Carbon::parse($value);
        } catch (\Throwable $e) {
            return null;
        }
    }
}
