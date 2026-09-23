<?php

namespace App\Models;

use App\Traits\TenantTrait;
use Illuminate\Database\Eloquent\Model;

/**
 * Per-tenant configurable clearance checklist catalog. tenant_id = NULL rows
 * are the shared global default set (same pattern as OnboardingTask), used
 * automatically for any tenant that hasn't configured its own checklist.
 */
class OffboardingClearanceTemplate extends Model
{
    use TenantTrait;

    protected $fillable = [
        'tenant_id', 'category', 'label', 'description',
        'applies_to_reasons', 'is_active', 'sort_order', 'created_by',
    ];

    protected $casts = [
        'applies_to_reasons' => 'array',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    const CATEGORIES = ['it', 'admin', 'finance', 'hr', 'other'];

    /**
     * Built-in fallback set used when a tenant has configured no templates
     * of its own and no global (tenant_id = NULL) rows exist either — a new
     * tenant gets a working clearance checklist on day one with zero setup.
     */
    public static array $defaults = [
        ['category' => 'it', 'label' => 'Laptop / desktop returned', 'sort_order' => 10],
        ['category' => 'it', 'label' => 'Email & system access revoked', 'sort_order' => 20],
        ['category' => 'admin', 'label' => 'ID card / access badge returned', 'sort_order' => 30],
        ['category' => 'admin', 'label' => 'Office keys returned', 'sort_order' => 40],
        ['category' => 'finance', 'label' => 'Department no-dues clearance', 'sort_order' => 50],
        ['category' => 'hr', 'label' => 'Handover documentation received', 'sort_order' => 60],
    ];

    public function scopeApplicableTo($query, ?string $reason)
    {
        return $query->where('is_active', true)
            ->where(function ($q) use ($reason) {
                $q->whereNull('applies_to_reasons')
                    ->orWhereJsonContains('applies_to_reasons', $reason);
            })
            ->orderBy('sort_order');
    }
}
