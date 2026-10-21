<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\TenantTrait;

/**
 * @property int $user_id
 * @property int|null $leave_type
 * @property mixed $start_date
 * @property mixed $end_date
 */
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
    /** @return BelongsTo<LeaveType, $this> */
    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class, 'leave_type');
    }
}
