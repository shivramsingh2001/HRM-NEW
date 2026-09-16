<?php

namespace App\Models;

use App\Traits\LogsPayrollActivity;
use App\Traits\TenantTrait;
use Illuminate\Database\Eloquent\Model;

/**
 * Tenant/region-configurable PF/ESI/PT/TDS rates and wage ceilings,
 * versioned by effective date range. Replaces the hardcoded ESI ₹21,000
 * ceiling literal previously baked into add-user.blade.php/update-user.blade.php.
 */
class StatutoryRateConfig extends Model
{
    use TenantTrait, LogsPayrollActivity;

    protected $guarded = ['id'];

    protected $casts = [
        'effective_from' => 'date',
        'effective_to' => 'date',
        'config' => 'array',
    ];

    public function scopeActiveOn($query, string $type, $date, ?string $regionCode = null)
    {
        return $query->where('statutory_type', $type)
            ->when($regionCode, fn ($q) => $q->where(function ($q2) use ($regionCode) {
                $q2->whereNull('region_code')->orWhere('region_code', $regionCode);
            }))
            ->where('effective_from', '<=', $date)
            ->where(function ($q) use ($date) {
                $q->whereNull('effective_to')->orWhere('effective_to', '>=', $date);
            })
            ->orderByDesc('effective_from');
    }
}
