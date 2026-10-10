<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\TenantTrait;

class ShiftAssignment extends Model
{
    use TenantTrait;

    protected $guarded = [];

    protected $casts = [
        'is_additional' => 'boolean',
        'is_override' => 'boolean',
        'start_date' => 'date',
        'end_date' => 'date',
        'rotation_anchor_date' => 'date',
        'ended_at' => 'datetime',
        'week_off_days' => 'array',
        'week_off_dates' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function shift()
    {
        return $this->belongsTo(Shift::class, 'shift_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function endedBy()
    {
        return $this->belongsTo(User::class, 'ended_by');
    }

    /**
     * The assignment this one was closed in favour of, if any.
     */
    public function supersededBy()
    {
        return $this->belongsTo(self::class, 'superseded_by_id');
    }

    public function shiftRequest()
    {
        return $this->belongsTo(ShiftRequest::class, 'shift_request_id');
    }

    public function rotationPattern()
    {
        return $this->belongsTo(ShiftRotationPattern::class, 'rotation_pattern_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopePermanent($query)
    {
        return $query->where('type', 'permanent');
    }

    public function scopeFlexible($query)
    {
        return $query->where('type', 'flexible');
    }

    /**
     * Does this assignment cover the given date? Permanent rows are open-ended
     * when end_date is null.
     */
    public function coversDate(string $date): bool
    {
        if ($this->start_date->format('Y-m-d') > $date) {
            return false;
        }

        return !$this->end_date || $this->end_date->format('Y-m-d') >= $date;
    }
}
