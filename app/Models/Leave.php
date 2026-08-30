<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\TenantTrait;

class Leave extends Model
{
    use TenantTrait;
    
    protected $guarded = [];
    // protected $casts = [
    //     'start_date' => 'date',
    //     'end_date'   => 'date',
    // ];


    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function leaveType()
    {
        return $this->belongsTo(LeaveType::class, 'leave_type');
    }
}
