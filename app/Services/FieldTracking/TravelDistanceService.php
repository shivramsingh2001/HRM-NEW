<?php

namespace App\Services\FieldTracking;

use Illuminate\Support\Facades\DB;

/**
 * Distance an employee travelled, from their field-tracking GPS points
 * (attendance_tracking_points). One day = every tracking session whose
 * `date` is that day, walked in track_time order — the same route the
 * attendance sessions map draws, so the report and the map agree.
 *
 * Used by the Daywise and Monthly Summary attendance reports and the
 * attendance sessions page.
 */
class TravelDistanceService
{
    /** GPS jitter: hops shorter than this (metres) are not counted. */
    private const MIN_HOP_METRES = 10;

    /** Field tracking is a paid add-on — no distance column without it. */
    public function enabledFor(int $tenantId): bool
    {
        return (bool) DB::table('tenants')->where('id', $tenantId)->value('field_tracking_enabled');
    }

    /**
     * Kilometres travelled per employee per day in [$from, $to] (Y-m-d).
     * Days without GPS points are absent from the result.
     *
     * @param  iterable<int>  $userIds
     * @return array<int, array<string, float>> user_id => [Y-m-d => km]
     */
    public function kmByUserAndDate(int $tenantId, iterable $userIds, string $from, string $to): array
    {
        $result = [];

        foreach (array_chunk(array_values(array_unique(array_map('intval', collect($userIds)->all()))), 500) as $chunk) {
            $rows = DB::table('attendance_tracking_points as p')
                ->join('attendance_tracking_sessions as s', 's.id', '=', 'p.session_id')
                ->where('s.tenant_id', $tenantId)
                ->where('p.tenant_id', $tenantId)
                ->whereIn('s.user_id', $chunk)
                ->whereBetween('s.date', [$from, $to])
                ->orderBy('s.user_id')
                ->orderBy('s.date')
                ->orderBy('p.track_time')
                ->select('s.user_id', 's.date', 'p.lat', 'p.long')
                ->cursor();

            $key = null;
            $points = [];
            foreach ($rows as $row) {
                $rowKey = $row->user_id.'|'.$row->date;
                if ($rowKey !== $key) {
                    $this->store($result, $key, $points);
                    [$key, $points] = [$rowKey, []];
                }
                $points[] = [$row->lat, $row->long];
            }
            $this->store($result, $key, $points);
        }

        return $result;
    }

    /**
     * Route length in km over [lat, long] pairs in travel order. Skips
     * missing / 0,0 fixes and hops under MIN_HOP_METRES (GPS jitter).
     *
     * @param  iterable<array{0:mixed,1:mixed}>  $points
     */
    public function routeKm(iterable $points): float
    {
        $metres = 0.0;
        $previous = null;

        foreach ($points as [$lat, $long]) {
            if (! is_numeric($lat) || ! is_numeric($long) || ((float) $lat == 0.0 && (float) $long == 0.0)) {
                continue;
            }

            $point = [deg2rad((float) $lat), deg2rad((float) $long)];

            if ($previous !== null) {
                $a = sin(($point[0] - $previous[0]) / 2) ** 2
                    + cos($previous[0]) * cos($point[0]) * sin(($point[1] - $previous[1]) / 2) ** 2;
                $hop = 6371000 * 2 * atan2(sqrt($a), sqrt(1 - $a));

                if ($hop < self::MIN_HOP_METRES) {
                    continue;
                }

                $metres += $hop;
            }

            $previous = $point;
        }

        return round($metres / 1000, 2);
    }

    /** @param array<int, array<string, float>> $result */
    private function store(array &$result, ?string $key, array $points): void
    {
        if ($key === null) {
            return;
        }
        [$userId, $date] = explode('|', $key, 2);
        $result[(int) $userId][substr($date, 0, 10)] = $this->routeKm($points);
    }
}
