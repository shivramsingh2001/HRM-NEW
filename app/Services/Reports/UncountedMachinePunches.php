<?php

namespace App\Services\Reports;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Raw biometric_punches the attendance did NOT use — 3rd+ punches with
 * multiple punches off ("already clocked out"), double taps, punches moved by
 * "first in, last out", errors. A punch counts when its time is a day's
 * attendances.clock_in / clock_out or an active attendance_punches row (same
 * employee, that day or the day before for overnight). Unmapped enrolls
 * (no user_id) are left out.
 *
 * Select list matches the Clock In/Out Log union (PunchReportController::query()),
 * with direction 'extra' and counted = 0.
 */
class UncountedMachinePunches
{
    public static function query(int $tenantId, string $start, string $end): Builder
    {
        $sameDayWindow = 'BETWEEN DATE_SUB(DATE(b.punched_at), INTERVAL 1 DAY) AND DATE(b.punched_at)';
        $null = fn (string $col) => DB::raw("NULL as {$col}");

        return DB::table('biometric_punches as b')
            ->where('b.tenant_id', $tenantId)
            ->whereNotNull('b.user_id')
            ->whereBetween('b.punched_at', [$start.' 00:00:00', $end.' 23:59:59'])
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('attendances as ca')
                ->whereColumn('ca.tenant_id', 'b.tenant_id')->whereColumn('ca.user_id', 'b.user_id')
                ->whereRaw("ca.date {$sameDayWindow}")
                ->where(fn ($w) => $w->whereColumn('ca.clock_in', 'b.punched_at')->orWhereColumn('ca.clock_out', 'b.punched_at')))
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('attendance_punches as cp')
                ->whereColumn('cp.tenant_id', 'b.tenant_id')->whereColumn('cp.user_id', 'b.user_id')
                ->whereRaw("cp.date {$sameDayWindow}")
                ->whereColumn('cp.punched_at', 'b.punched_at')->where('cp.status', 'active'))
            ->select([
                'b.id as row_id', DB::raw("'machine' as row_type"), 'b.user_id', DB::raw('DATE(b.punched_at) as date'),
                DB::raw("'extra' as direction"), 'b.punched_at', DB::raw("'biometric' as source"), 'b.method',
                'b.biometric_device_id', $null('lat'), $null('lng'), $null('address'), $null('location_verification'),
                $null('attendance_location_id'), $null('distance_meters'), $null('accuracy_meters'), $null('device_id'),
                $null('network_type'), $null('wifi_ssid'), $null('ip_address'), $null('battery_percent'),
                $null('session_seq'), DB::raw('0 as is_regularized'), $null('actor_id'),
                DB::raw("CONCAT('Not counted: ', COALESCE(b.error, b.status)) as reason"), DB::raw('0 as counted'),
            ]);
    }
}
