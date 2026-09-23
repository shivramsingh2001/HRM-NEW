<?php

namespace App\Models;

use App\Traits\TenantTrait;
use Illuminate\Database\Eloquent\Model;

class Vendor extends Model
{
    use TenantTrait;

    protected $fillable = [
        'tenant_id', 'name', 'contact_person', 'phone', 'email', 'address', 'tax_number', 'status',
    ];

    protected $casts = [
        'status' => 'boolean',
    ];

    public function assets()
    {
        return $this->hasMany(Asset::class, 'vendor_id');
    }

    public function repairs()
    {
        return $this->hasMany(AssetRepair::class, 'sent_to_vendor_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }
}
