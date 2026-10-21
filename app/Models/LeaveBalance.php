<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\TenantTrait;

/**
 * @property int $user_id
 * @property int $leave_type_id
 * @property mixed $balance DECIMAL column (string from the driver)
 */
class LeaveBalance extends Model
{
     use TenantTrait;
    protected $guarded=[];
    
    /** @return BelongsTo<LeaveType, $this> */
    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class, 'leave_type_id');
    }
}
