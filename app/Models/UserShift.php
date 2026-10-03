<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\TenantTrait;

class UserShift extends Model
{
    use TenantTrait;
    protected $guarded = [];

    // `date` stays a plain string (varchar column) — callers Carbon::parse() it.
    protected $casts = [
        'is_additional' => 'boolean',
    ];

    /** Generated DB column backing the one-primary-per-day unique key. */
    protected $hidden = ['primary_slot'];

    /**
     * Get the user that owns the shift
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the shift details
     */
    public function shift()
    {
        return $this->belongsTo(Shift::class, 'shift_id');
    }

    /**
     * Get the user who created this assignment
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * The Permanent/Flexible assignment record this cached day was
     * materialized from (null for rows written before this feature, or by a
     * raw single-day edit that doesn't go through ShiftMaterializer).
     */
    public function shiftAssignment()
    {
        return $this->belongsTo(ShiftAssignment::class, 'shift_assignment_id');
    }

}
