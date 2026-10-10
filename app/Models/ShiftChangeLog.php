<?php

namespace App\Models;

use App\Traits\TenantTrait;
use Illuminate\Database\Eloquent\Model;

/**
 * Append-only trail of every change to an employee's shift on a day (see
 * App\Services\Shift\ShiftChangeRecorder). Never updated or deleted.
 */
class ShiftChangeLog extends Model
{
    use TenantTrait;

    public const UPDATED_AT = null;

    public const SOURCE_LABELS = [
        'roster_assign' => 'Roster: assign',
        'roster_edit' => 'Roster: edit day',
        'bulk_edit' => 'Roster: bulk edit',
        'bulk_assign' => 'Roster: bulk assign',
        'roster_delete' => 'Roster: delete',
        'end_permanent' => 'Permanent shift ended',
        'admin_swap' => 'Direct swap',
        'admin_change' => 'Direct change',
        'swap_request' => 'Swap request',
        'change_request' => 'Change request',
        'request_revert' => 'Request reverted',
        'rotation' => 'Rotation pattern',
        'employee360' => 'Employee 360',
    ];

    protected $guarded = [];

    protected $casts = [
        'date' => 'date',
        'is_additional' => 'boolean',
        'created_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function fromShift()
    {
        return $this->belongsTo(Shift::class, 'from_shift_id');
    }

    public function toShift()
    {
        return $this->belongsTo(Shift::class, 'to_shift_id');
    }

    public function shiftRequest()
    {
        return $this->belongsTo(ShiftRequest::class, 'shift_request_id');
    }

    public function sourceLabel(): string
    {
        return self::SOURCE_LABELS[$this->source] ?? ucfirst(str_replace('_', ' ', (string) $this->source));
    }
}
