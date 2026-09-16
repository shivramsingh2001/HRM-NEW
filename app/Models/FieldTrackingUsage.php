<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Monthly field-tracking usage per tenant (billing record).
 *
 * Deliberately NOT tenant-scoped: `field-tracking:meter` iterates every tenant
 * and writes an explicit tenant_id, like the tenant lifecycle commands.
 */
class FieldTrackingUsage extends Model
{
    protected $table = 'field_tracking_usage';

    protected $guarded = [];

    protected $casts = [
        'enabled_user_ids' => 'array',
        'seats_purchased' => 'integer',
        'peak_seats_used' => 'integer',
        'avg_seats_used' => 'decimal:2',
        'samples' => 'integer',
        'track_rows_written' => 'integer',
        'computed_at' => 'datetime',
        'finalized_at' => 'datetime',
    ];
}
