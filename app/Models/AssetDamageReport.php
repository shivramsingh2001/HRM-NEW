<?php

namespace App\Models;

use App\Traits\TenantTrait;
use Illuminate\Database\Eloquent\Model;

class AssetDamageReport extends Model
{
    use TenantTrait;

    protected $fillable = [
        'tenant_id', 'asset_id', 'reported_at', 'type',
        'reported_by', 'description', 'estimated_loss_value', 'is_chargeable', 'charged_amount',
        'resolution', 'resolved_at', 'resolved_by', 'remarks',
    ];

    protected $casts = [
        'reported_at' => 'datetime',
        'resolved_at' => 'datetime',
        'estimated_loss_value' => 'decimal:2',
        'charged_amount' => 'decimal:2',
        'is_chargeable' => 'boolean',
    ];

    public function asset()
    {
        return $this->belongsTo(Asset::class, 'asset_id');
    }

    public function reportedBy()
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function resolvedBy()
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
