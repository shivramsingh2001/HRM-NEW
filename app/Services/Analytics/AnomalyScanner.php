<?php

namespace App\Services\Analytics;

use App\Events\AttendanceDomainEvent;
use App\Models\AttendanceAnomaly;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Tier 2 / T2-F — flags suspicious attendance. Idempotent per (tenant,
 * fingerprint): re-running a day never double-flags.
 */
class AnomalyScanner
{
    /** Speed above which two consecutive punches imply impossible travel (km/h). */
    private const MAX_KMH = 900;

    public function scanDay(int $tenantId, string $date): int
    {
        $found = 0;
        $found += $this->buddyPunch($tenantId, $date);
        $found += $this->impossibleTravel($tenantId, $date);
        $found += $this->chronicOpen($tenantId, $date);

        return $found;
    }

    private function record(int $tenantId, ?int $userId, ?string $date, string $type, string $severity, array $detail): bool
    {
        $fingerprint = hash('sha256', implode('|', [$type, $userId, $date, json_encode($detail['key'] ?? $detail)]));

        try {
            AttendanceAnomaly::withoutGlobalScopes()->create([
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'date' => $date,
                'type' => $type,
                'severity' => $severity,
                'detail' => $detail,
                'status' => 'open',
                'fingerprint' => $fingerprint,
                'created_at' => now(),
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            if (($e->errorInfo[1] ?? null) == 1062) {
                return false; // already flagged
            }
            throw $e;
        }

        event(new AttendanceDomainEvent('attendance.anomaly_detected', $tenantId, [
            'type' => $type, 'severity' => $severity, 'user_id' => $userId, 'date' => $date,
        ]));

        return true;
    }

    /** Same device_id or near-identical geo used for 2+ users within 5 min. */
    private function buddyPunch(int $tenantId, string $date): int
    {
        $rows = DB::table('attendances')
            ->where('tenant_id', $tenantId)->where('date', $date)
            ->whereNotNull('clock_in')
            ->get(['user_id', 'clock_in', 'device_id', 'clock_in_lat', 'clock_in_long']);

        $n = 0;
        $byDevice = $rows->filter(fn ($r) => ! empty($r->device_id))->groupBy('device_id');
        foreach ($byDevice as $device => $group) {
            $users = $group->pluck('user_id')->unique();
            if ($users->count() < 2) {
                continue;
            }
            // any two within 5 minutes?
            $times = $group->map(fn ($r) => strtotime($r->clock_in))->sort()->values();
            for ($i = 1; $i < $times->count(); $i++) {
                if ($times[$i] - $times[$i - 1] <= 300) {
                    $n += (int) $this->record($tenantId, $users->first(), $date, 'buddy_punch', 'high', [
                        'device_id' => $device, 'user_ids' => $users->values()->all(),
                        'key' => 'dev:' . $device,
                    ]);
                    break;
                }
            }
        }

        return $n;
    }

    /** Consecutive punches for one user implying > MAX_KMH. */
    private function impossibleTravel(int $tenantId, string $date): int
    {
        $rows = DB::table('attendances')
            ->where('tenant_id', $tenantId)->where('date', $date)
            ->whereNotNull('clock_in_lat')->whereNotNull('clock_out_lat')
            ->get(['user_id', 'clock_in', 'clock_out', 'clock_in_lat', 'clock_in_long', 'clock_out_lat', 'clock_out_long']);

        $n = 0;
        foreach ($rows as $r) {
            $km = $this->haversine($r->clock_in_lat, $r->clock_in_long, $r->clock_out_lat, $r->clock_out_long);
            $hours = max(0.01, (strtotime($r->clock_out) - strtotime($r->clock_in)) / 3600);
            $kmh = $km / $hours;
            if ($kmh > self::MAX_KMH) {
                $n += (int) $this->record($tenantId, $r->user_id, $date, 'impossible_travel', 'high', [
                    'km' => round($km, 1), 'hours' => round($hours, 2), 'kmh' => round($kmh),
                    'key' => 'it:' . $r->user_id,
                ]);
            }
        }

        return $n;
    }

    /** User has ≥ 3 open (no clock-out) rows in the trailing 30 days. */
    private function chronicOpen(int $tenantId, string $date): int
    {
        $since = Carbon::parse($date)->subDays(30)->toDateString();
        $bad = DB::table('attendances')
            ->select('user_id', DB::raw('COUNT(*) as c'))
            ->where('tenant_id', $tenantId)
            ->whereBetween('date', [$since, $date])
            ->whereNotNull('clock_in')
            ->where(function ($q) {
                $q->whereNull('clock_out')->orWhere('clock_out', '')->orWhere('clock_out', '0000-00-00 00:00:00');
            })
            ->groupBy('user_id')
            ->having('c', '>=', 3)
            ->get();

        $n = 0;
        foreach ($bad as $row) {
            $n += (int) $this->record($tenantId, $row->user_id, $date, 'chronic_open', 'medium', [
                'open_rows_30d' => (int) $row->c, 'key' => 'co:' . $row->user_id . ':' . Carbon::parse($date)->format('Y-W'),
            ]);
        }

        return $n;
    }

    private function haversine($lat1, $lon1, $lat2, $lon2): float
    {
        $r = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return $r * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
