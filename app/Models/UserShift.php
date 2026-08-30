<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\TenantTrait;

class UserShift extends Model
{
    use TenantTrait;
    protected $guarded = [];
    
    // protected $casts = [
    //     'date' => 'date'
    // ];

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
    
}
