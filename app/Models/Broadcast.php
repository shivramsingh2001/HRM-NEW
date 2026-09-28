<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A broadcast notification definition, created either by a Tenant Admin
 * (origin=tenant_admin) in this app or by a Superadmin (origin=superadmin)
 * in the separate hrm-superadmin app, which points a plain, unscoped model
 * at this same shared table.
 *
 * Deliberately does NOT use TenantTrait: this table is cross-cutting by
 * design (one superadmin broadcast can target many tenants at once), so it
 * must never be scoped to a single tenant_id. `origin_tenant_id` is
 * provenance only ("which tenant's admin authored this") — the real
 * security boundary is BroadcastRecipient::tenant_id, see that model.
 */
class Broadcast extends Model
{
    /** Matches the `status` enum on the migration — reused for the history page's status filter dropdown. */
    public const STATUSES = ['draft', 'scheduled', 'sending', 'sent', 'failed', 'cancelled', 'expired'];

    protected $table = 'broadcast_notifications';

    protected $guarded = [];

    protected $casts = [
        'audience_filters' => 'array',
        'channels' => 'array',
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function recipients()
    {
        return $this->hasMany(BroadcastRecipient::class, 'broadcast_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function scopeDueToSend($query)
    {
        return $query->where('status', 'scheduled')->where('scheduled_at', '<=', now());
    }

    public function scopeExpirable($query)
    {
        return $query->where('status', 'sent')->whereNotNull('expires_at')->where('expires_at', '<=', now());
    }

    public function scopeMine($query, int $userId)
    {
        return $query->where('origin', 'tenant_admin')->where('created_by_user_id', $userId);
    }
}
