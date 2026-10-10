<?php

namespace App\Models;

use App\Traits\TenantTrait;
use Illuminate\Database\Eloquent\Model;

/** One employee + one date that a shift request changes (from → to). */
class ShiftRequestItem extends Model
{
    use TenantTrait;

    protected $guarded = [];

    protected $casts = [
        'date' => 'date',
    ];

    public function shiftRequest()
    {
        return $this->belongsTo(ShiftRequest::class, 'shift_request_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function fromShift()
    {
        return $this->belongsTo(Shift::class, 'from_shift_id');
    }

    public function toShift()
    {
        return $this->belongsTo(Shift::class, 'to_shift_id');
    }
}
