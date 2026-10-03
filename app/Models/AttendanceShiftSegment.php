<?php

namespace App\Models;

use App\Traits\TenantTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One shift worked on an attendance day (multi-shift). Rewritten on every
 * AttendanceRollupService::recompute(); never edited by hand.
 */
class AttendanceShiftSegment extends Model
{
    use TenantTrait;

    protected $guarded = [];

    protected $casts = [
        'date' => 'date',
        'is_additional' => 'boolean',
        'is_open' => 'boolean',
        'scheduled_start' => 'datetime',
        'scheduled_end' => 'datetime',
        'first_in' => 'datetime',
        'last_out' => 'datetime',
    ];

    public function attendance(): BelongsTo
    {
        return $this->belongsTo(Attendance::class, 'attendance_id');
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class, 'shift_id');
    }
}
