<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\TenantTrait;
class OvertimeSetting extends Model
{
    use TenantTrait;

    protected $fillable = [
        'tenant_id',
        'rate_multiplier',
        'max_hours_per_day',
        'max_hours_per_month',
        'require_approval',
        'auto_approve_limit',
    ];

    protected $casts = [
        'rate_multiplier' => 'decimal:2',
        'max_hours_per_day' => 'decimal:2',
        'max_hours_per_month' => 'decimal:2',
        'auto_approve_limit' => 'decimal:2',
        'require_approval' => 'boolean',
    ];

    // Get active settings for tenant
    public static function getActiveSettings($tenantId = null)
    {
        return self::where('tenant_id', $tenantId)
            ->orWhereNull('tenant_id')
            ->first() ?? new self();
    }
}