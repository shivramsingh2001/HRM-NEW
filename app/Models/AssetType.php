<?php

namespace App\Models;

use App\Traits\TenantTrait;
use Illuminate\Database\Eloquent\Model;

class AssetType extends Model
{
    use TenantTrait;

    protected $fillable = [
        'tenant_id', 'asset_category_id', 'name', 'code', 'description', 'status',
    ];

    protected $casts = [
        'status' => 'boolean',
    ];

    public function category()
    {
        return $this->belongsTo(AssetCategory::class, 'asset_category_id');
    }

    public function assets()
    {
        return $this->hasMany(Asset::class, 'asset_type_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }
}
