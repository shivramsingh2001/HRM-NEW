<?php

namespace App\Models;

use App\Traits\TenantTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One clock-in -> clock-out punch pair. See App\Services\FieldTracking\TrackingSessionService.
 */
class AttendanceTrackingSession extends Model
{
    use TenantTrait;

    protected $guarded = [];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'last_point_at' => 'datetime',
    ];

    public function points(): HasMany
    {
        return $this->hasMany(AttendanceTrackingPoint::class, 'session_id');
    }

    public function punchIn(): BelongsTo
    {
        return $this->belongsTo(AttendancePunch::class, 'punch_in_id');
    }

    public function punchOut(): BelongsTo
    {
        return $this->belongsTo(AttendancePunch::class, 'punch_out_id');
    }

    public function attendance(): BelongsTo
    {
        return $this->belongsTo(Attendance::class, 'attendance_id');
    }
}
