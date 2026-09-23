<?php

namespace App\Models;

use App\Traits\TenantTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single GPS breadcrumb belonging to an attendance_tracking_sessions row.
 * Written via App\Services\FieldTracking\TrackingPointIngestService (bulk
 * insertOrIgnore) — this model is for reads only, no $timestamps updated_at.
 */
class AttendanceTrackingPoint extends Model
{
    use TenantTrait;

    protected $guarded = [];

    public $timestamps = false;

    protected $casts = [
        'track_time' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(AttendanceTrackingSession::class, 'session_id');
    }

    /**
     * Points for every tracking session tied to one day-rollup attendances.id —
     * the compatibility read path for the legacy attendance_id-keyed views
     * (todayLocations, admin map view, profile "today's trail").
     */
    public static function forAttendanceId(int $attendanceId, ?string $date = null): Builder
    {
        $sessionIds = AttendanceTrackingSession::where('attendance_id', $attendanceId)->pluck('id');

        $query = static::whereIn('session_id', $sessionIds)->orderBy('track_time', 'asc');

        if ($date) {
            $query->whereDate('track_time', $date);
        }

        return $query;
    }

    /**
     * Same compatibility read path as forAttendanceId(), for several
     * day-rollup attendance ids at once — annotates each point with its own
     * session's attendance_id so callers can group by it, matching the old
     * AttendanceTrack::whereIn('attendance_id', $ids)->groupBy('attendance_id')
     * shape used by the admin map view.
     *
     * @param  array<int,int>  $attendanceIds
     */
    public static function forAttendanceIds(array $attendanceIds): \Illuminate\Support\Collection
    {
        return static::query()
            ->join('attendance_tracking_sessions', 'attendance_tracking_sessions.id', '=', 'attendance_tracking_points.session_id')
            ->whereIn('attendance_tracking_sessions.attendance_id', $attendanceIds)
            ->orderBy('attendance_tracking_points.track_time')
            ->get([
                'attendance_tracking_points.track_time',
                'attendance_tracking_points.lat',
                'attendance_tracking_points.long',
                'attendance_tracking_points.address',
                'attendance_tracking_points.battery_per',
                'attendance_tracking_sessions.attendance_id',
            ]);
    }
}
