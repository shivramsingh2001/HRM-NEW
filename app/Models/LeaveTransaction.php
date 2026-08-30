<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\TenantTrait;

class LeaveTransaction extends Model
{
    use TenantTrait;
    protected $guarded=[];
    
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');  // ✅ Correct: belongsTo
    }
    public function leaveType()
    {
        return $this->belongsTo(LeaveType::class, 'leave_type');
    }
}
