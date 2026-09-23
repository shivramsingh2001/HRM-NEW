<?php

namespace App\Models;

use App\Traits\TenantTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Asset extends Model
{
    use TenantTrait;
    use SoftDeletes;

    protected $fillable = [
        'tenant_id', 'asset_code', 'name', 'status',
        'asset_category_id', 'asset_type_id',
        'serial_number', 'model_number', 'brand', 'description', 'barcode_value', 'image',
        'vendor_id', 'purchase_date', 'purchase_cost', 'purchase_order_number', 'invoice_number',
        'warranty_start_date', 'warranty_end_date', 'warranty_provider',
        'branch_id', 'location_notes',
        'condition', 'current_assignee_id',
        'depreciation_method', 'salvage_value', 'useful_life_months',
        'notes', 'created_by',
        'pre_repair_status', 'retired_at', 'disposed_at',
    ];

    protected $casts = [
        'purchase_date' => 'date',
        'warranty_start_date' => 'date',
        'warranty_end_date' => 'date',
        'purchase_cost' => 'decimal:2',
        'salvage_value' => 'decimal:2',
        'retired_at' => 'datetime',
        'disposed_at' => 'datetime',
    ];

    const STATUSES = [
        'available', 'pending_acceptance', 'assigned', 'in_repair',
        'damaged', 'lost', 'retired', 'disposed',
    ];

    public function category()
    {
        return $this->belongsTo(AssetCategory::class, 'asset_category_id');
    }

    public function type()
    {
        return $this->belongsTo(AssetType::class, 'asset_type_id');
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class, 'vendor_id');
    }

    public function branch()
    {
        return $this->belongsTo(CompanyBranch::class, 'branch_id');
    }

    public function currentAssigneeUser()
    {
        return $this->belongsTo(User::class, 'current_assignee_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignments()
    {
        return $this->hasMany(AssetAssignment::class, 'asset_id');
    }

    public function currentAssignment()
    {
        return $this->hasOne(AssetAssignment::class, 'asset_id')
            ->whereNotIn('status', ['returned', 'transferred'])
            ->latestOfMany('assigned_at');
    }

    public function activeRepair()
    {
        return $this->hasOne(AssetRepair::class, 'asset_id')
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->latestOfMany('reported_at');
    }

    public function activeDamageReport()
    {
        return $this->hasOne(AssetDamageReport::class, 'asset_id')
            ->whereNull('resolution')
            ->latestOfMany('reported_at');
    }

    public function transfers()
    {
        return $this->hasMany(AssetTransfer::class, 'asset_id');
    }

    public function repairs()
    {
        return $this->hasMany(AssetRepair::class, 'asset_id');
    }

    public function damageReports()
    {
        return $this->hasMany(AssetDamageReport::class, 'asset_id');
    }

    public function disposal()
    {
        return $this->hasOne(AssetDisposal::class, 'asset_id');
    }

    public function histories()
    {
        return $this->hasMany(AssetHistory::class, 'asset_id')->orderByDesc('created_at');
    }

    public function attachments()
    {
        return $this->hasMany(AssetAttachment::class, 'asset_id');
    }

    public function scopeStatus($query, $status)
    {
        return $query->where('status', $status);
    }
}
