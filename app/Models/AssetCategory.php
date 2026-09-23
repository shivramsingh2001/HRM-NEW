<?php

namespace App\Models;

use App\Traits\TenantTrait;
use Illuminate\Database\Eloquent\Model;

class AssetCategory extends Model
{
    use TenantTrait;

    protected $fillable = [
        'tenant_id', 'name', 'code', 'description', 'status',
    ];

    protected $casts = [
        'status' => 'boolean',
    ];

    public function types()
    {
        return $this->hasMany(AssetType::class, 'asset_category_id');
    }

    public function assets()
    {
        return $this->hasMany(Asset::class, 'asset_category_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }
}
