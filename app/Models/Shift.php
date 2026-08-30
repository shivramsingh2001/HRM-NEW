<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\TenantTrait;

class Shift extends Model
{
     use TenantTrait;
    protected $guarded = [];
    // protected $casts = [
    //     'start_time' => 'string',
    //     'end_time' => 'string',
    //     'status' => 'boolean'
    // ];

    /**
     * Get the user shifts for this shift
     */
    public function userShifts()
    {
        return $this->hasMany(UserShift::class, 'shift_id');
    }

    /**
     * Get the user who created this shift
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
