<?php

namespace App\Models;

use App\Traits\TenantTrait;
use Illuminate\Database\Eloquent\Model;

/** Insert-only timeline entry of a shift request (who did what, when, from where). */
class ShiftRequestEvent extends Model
{
    use TenantTrait;

    public const UPDATED_AT = null;

    public const LABELS = [
        'created' => 'Request raised',
        'created_direct' => 'Changed directly (no request)',
        'created_on_behalf' => 'Raised on behalf of the employee',
        'peer_accepted' => 'Colleague accepted',
        'peer_declined' => 'Colleague declined',
        'submitted_for_approval' => 'Sent for approval',
        'level_approved' => 'Approved at a level',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
        'cancelled' => 'Cancelled',
        'expired' => 'Expired',
        'applied' => 'Shifts updated',
        'reverted' => 'Reverted',
        'stale_blocked' => 'Approval blocked — shifts changed since the request',
    ];

    protected $guarded = [];

    protected $casts = [
        'meta' => 'array',
        'created_at' => 'datetime',
    ];

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function label(): string
    {
        return self::LABELS[$this->event] ?? ucfirst(str_replace('_', ' ', $this->event));
    }
}
