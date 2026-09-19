<?php

namespace App\Models;

use App\Traits\TenantTrait;
use Illuminate\Database\Eloquent\Model;

class AssetDisposal extends Model
{
    use TenantTrait;

    protected $fillable = [
        'tenant_id', 'asset_id', 'disposed_by', 'disposed_at',
        'method', 'sale_value', 'buyer_or_recipient', 'reason', 'approved_by',
    ];

    protected $casts = [
        'disposed_at' => 'datetime',
        'sale_value' => 'decimal:2',
    ];

    public function asset()
    {
        return $this->belongsTo(Asset::class, 'asset_id');
    }

    public function disposedBy()
    {
        return $this->belongsTo(User::class, 'disposed_by');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
