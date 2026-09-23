<?php

namespace App\Models;

use App\Traits\TenantTrait;
use Illuminate\Database\Eloquent\Model;

/**
 * One raw Clock In/Out event, from any source (mobile GPS, web, manual/admin,
 * biometric, kiosk). `attendances` remains the one-row-per-day rollup every
 * report/payroll consumer reads; this table is what feeds it. See
 * App\Services\Attendance\AttendancePunchService (capture) and
 * App\Services\Attendance\PunchSessionCalculator (pairing/aggregation).
 */
class AttendancePunch extends Model
{
    use TenantTrait;

    protected $guarded = [];

    protected $casts = [
        'punched_at' => 'datetime',
        'punched_at_utc' => 'datetime',
        'voided_at' => 'datetime',
        'is_regularized' => 'boolean',
        'metadata' => 'array',
    ];

    public function attendance()
    {
        return $this->belongsTo(Attendance::class, 'attendance_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function pairedPunch()
    {
        return $this->belongsTo(self::class, 'paired_punch_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
