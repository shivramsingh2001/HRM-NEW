<?php

namespace App\Models;

use App\Traits\TenantTrait;
use Illuminate\Database\Eloquent\Model;

class AssetHistory extends Model
{
    use TenantTrait;

    protected $fillable = [
        'tenant_id', 'asset_id', 'action', 'status', 'actor_id', 'related_user_id',
        'remarks', 'old_values', 'new_values',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'created_at' => 'datetime',
    ];

    const ACTION_REGISTERED = 'REGISTERED';
    const ACTION_ASSIGNED = 'ASSIGNED';
    const ACTION_ACCEPTED = 'ACCEPTED';
    const ACTION_ACCEPTANCE_FORCED = 'ACCEPTANCE_FORCED';
    const ACTION_RETURN_REQUESTED = 'RETURN_REQUESTED';
    const ACTION_RETURNED = 'RETURNED';
    const ACTION_TRANSFERRED = 'TRANSFERRED';
    const ACTION_SENT_FOR_REPAIR = 'SENT_FOR_REPAIR';
    const ACTION_REPAIR_COMPLETED = 'REPAIR_COMPLETED';
    const ACTION_DAMAGED = 'DAMAGED';
    const ACTION_LOST = 'LOST';
    const ACTION_RESOLVED = 'RESOLVED';
    const ACTION_RETIRED = 'RETIRED';
    const ACTION_DISPOSED = 'DISPOSED';
    const ACTION_UPDATED = 'UPDATED';

    public function asset()
    {
        return $this->belongsTo(Asset::class, 'asset_id');
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function relatedUser()
    {
        return $this->belongsTo(User::class, 'related_user_id');
    }

    public function getActionColorAttribute(): string
    {
        return match ($this->action) {
            self::ACTION_REGISTERED, self::ACTION_ACCEPTED, self::ACTION_RETURNED,
            self::ACTION_REPAIR_COMPLETED, self::ACTION_RESOLVED => 'success',
            self::ACTION_ASSIGNED, self::ACTION_TRANSFERRED, self::ACTION_SENT_FOR_REPAIR,
            self::ACTION_ACCEPTANCE_FORCED, self::ACTION_RETURN_REQUESTED => 'info',
            self::ACTION_DAMAGED, self::ACTION_LOST => 'danger',
            self::ACTION_RETIRED, self::ACTION_DISPOSED => 'secondary',
            default => 'secondary',
        };
    }
}
