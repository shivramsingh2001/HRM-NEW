<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A single recipient's snapshot of a Broadcast — the module's real
 * tenant-scoping boundary. `tenant_id` is always populated (from the acting
 * tenant admin's own tenant, or from each resolved user's own tenant_id for
 * a cross-tenant superadmin broadcast) and is never trusted from client
 * input; see App\Services\Broadcast\BroadcastAudienceResolver.
 *
 * No TenantTrait here either — a tenant admin's own queries explicitly
 * scope by tenant_id (see BroadcastController), and hrm-superadmin's own
 * plain model over this same table is deliberately unscoped.
 */
class BroadcastRecipient extends Model
{
    protected $guarded = [];

    protected $casts = [
        'channel_status' => 'array',
        'delivered_at' => 'datetime',
        'read_at' => 'datetime',
        'action_clicked_at' => 'datetime',
    ];

    public function broadcast()
    {
        return $this->belongsTo(Broadcast::class, 'broadcast_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }
}
