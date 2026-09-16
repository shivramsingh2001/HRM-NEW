<?php

namespace App\Models;

use App\Traits\LogsPayrollActivity;
use App\Traits\TenantTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use RuntimeException;

/**
 * Tenant-owned, admin-editable payroll component catalog — the core of the
 * dynamic payroll rebuild. One row per component per tenant.
 */
class PayrollComponentMaster extends Model
{
    use TenantTrait, HasFactory, SoftDeletes, LogsPayrollActivity;

    protected $table = 'payroll_component_master';

    protected $guarded = ['id'];

    protected $casts = [
        'is_statutory' => 'boolean',
        'percentage_value' => 'decimal:3',
        'default_amount' => 'decimal:2',
        'priority' => 'integer',
        'is_taxable' => 'boolean',
        'has_wage_ceiling' => 'boolean',
        'ceiling_amount' => 'decimal:2',
        'eligibility_rules' => 'array',
        'affects_gross' => 'boolean',
        'affects_ctc' => 'boolean',
        'affects_net' => 'boolean',
        'display_order' => 'integer',
        'is_active' => 'boolean',
        'is_system_default' => 'boolean',
    ];

    public function baseComponent()
    {
        return $this->belongsTo(self::class, 'calculation_base_component_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeEarnings($query)
    {
        return $query->where('component_type', 'earning');
    }

    public function scopeDeductions($query)
    {
        return $query->where('component_type', 'deduction');
    }

    /**
     * A component whose base is another component must have a strictly
     * higher priority than that base — otherwise the two-pass calculation
     * engine can't guarantee the base is already resolved. Enforced here
     * (not as a DB constraint, which MySQL can't express relationally).
     *
     * Priority ordering alone isn't sufficient: PayrollCalculationEngine
     * hard-splits components into Pass 1 (earning/employer_contribution/
     * reimbursement) and Pass 2 (deduction), resolved as two separate
     * foreach loops regardless of priority number. A Pass-1 component can
     * never legitimately depend on a Pass-2 (deduction) component's value —
     * it hasn't been resolved yet when Pass 1 runs — even if its priority
     * number is numerically higher, so that case is rejected explicitly
     * rather than silently resolving to ₹0 via resolveBase()'s ?? 0.0
     * fallback.
     */
    protected static function booted()
    {
        static::saving(function (self $component) {
            if ($component->calculation_base_type === 'component' && $component->calculation_base_component_id) {
                $base = self::withoutGlobalScope('tenant')
                    ->where('id', $component->calculation_base_component_id)
                    ->first();

                if (! $base) {
                    return;
                }

                if ($component->component_type !== 'deduction' && $base->component_type === 'deduction') {
                    throw new RuntimeException(
                        "Component '{$component->name}' ({$component->component_type}) cannot be based on ".
                        "'{$base->name}' (deduction) — deductions are resolved after earnings/employer ".
                        "contributions in the calculation engine, so this would always compute to ₹0."
                    );
                }

                if ($component->priority <= $base->priority) {
                    throw new RuntimeException(
                        "Component '{$component->name}' (priority {$component->priority}) must have a higher priority ".
                        "than its base component '{$base->name}' (priority {$base->priority})."
                    );
                }
            }
        });
    }
}
