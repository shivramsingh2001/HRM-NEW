<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\TenantTrait;
class OvertimeSetting extends Model
{
    use TenantTrait;

    public const MODE_REQUEST = 'request';
    public const MODE_AUTO = 'auto';

    protected $fillable = [
        'tenant_id',
        'enabled',
        'mode',
        'auto_start_basis',
        'auto_start_after_minutes',
        'min_hours',
        'rate_type',
        'rate_multiplier',
        'fixed_rate_per_hour',
        'max_hours_per_day',
        'max_hours_per_month',
        'require_approval',
        'auto_approve_limit',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'auto_start_after_minutes' => 'integer',
        'min_hours' => 'decimal:2',
        'rate_multiplier' => 'decimal:2',
        'fixed_rate_per_hour' => 'decimal:2',
        'max_hours_per_day' => 'decimal:2',
        'max_hours_per_month' => 'decimal:2',
        'auto_approve_limit' => 'decimal:2',
        'require_approval' => 'boolean',
    ];

    // Get active settings for tenant
    public static function getActiveSettings($tenantId = null)
    {
        return self::where('tenant_id', $tenantId)->first() ?? new self();
    }
}
