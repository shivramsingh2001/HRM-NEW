<?php

namespace App\Models;

use App\Traits\TenantTrait;
use Illuminate\Database\Eloquent\Model;

class AssetRepair extends Model
{
    use TenantTrait;

    protected $fillable = [
        'tenant_id', 'asset_id', 'reported_at', 'status',
        'reported_by', 'issue_description', 'sent_to_vendor_id', 'sent_at',
        'expected_return_date', 'repair_cost', 'completed_at', 'resolution_notes',
    ];

    protected $casts = [
        'reported_at' => 'datetime',
        'sent_at' => 'date',
        'expected_return_date' => 'date',
        'completed_at' => 'datetime',
        'repair_cost' => 'decimal:2',
    ];

    public function asset()
    {
        return $this->belongsTo(Asset::class, 'asset_id');
    }

    public function reportedBy()
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class, 'sent_to_vendor_id');
    }
}
