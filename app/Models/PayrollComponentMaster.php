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

    /**
     * Pivot rows for the multi-base "percentage of several Earnings
     * components" selection (calculation_base_type === 'component').
     */
    public function baseComponentSelections()
    {
        return $this->hasMany(PayrollComponentBaseComponent::class, 'payroll_component_master_id');
    }

    /**
     * The actual Earnings components this component's percentage is
     * calculated against, resolved through the pivot above.
     */
    public function baseComponents()
    {
        return $this->belongsToMany(
            self::class,
            'payroll_component_base_components',
            'payroll_component_master_id',
            'base_payroll_component_master_id'
        )->withTimestamps();
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

    /**
     * Validates a submitted multi-base selection (the "Calculate % Of —
     * Earnings components" multi-select) before it's synced onto
     * baseComponentSelections(). Every base must be an Earnings component
     * (the multi-select's option list already filters to this, but the
     * server can't trust that), and this component's priority must exceed
     * the highest priority among all its bases — same two-pass-engine
     * reasoning as the single-base check in booted() above, generalized to
     * a set instead of one value.
     *
     * @param  int[]  $baseIds
     */
    public static function assertValidBaseComponents(string $componentType, int $priority, array $baseIds, ?int $tenantId = null): void
    {
        if (empty($baseIds)) {
            return;
        }

        $query = self::withoutGlobalScope('tenant')->whereIn('id', $baseIds);
        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        }
        $bases = $query->get();

        foreach ($baseIds as $baseId) {
            if (! $bases->firstWhere('id', $baseId)) {
                throw new RuntimeException("Base component #{$baseId} was not found in this tenant's catalog.");
            }
        }

        foreach ($bases as $base) {
            if ($base->component_type !== 'earning') {
                throw new RuntimeException(
                    "Base component '{$base->name}' must be an Earnings component — ".
                    "percentage components can only be calculated against Earnings."
                );
            }
        }

        $maxBasePriority = $bases->max('priority');
        if ($maxBasePriority !== null && $priority <= $maxBasePriority) {
            throw new RuntimeException(
                "This component's priority ({$priority}) must be higher than all of its base components' ".
                "priority (highest base priority: {$maxBasePriority})."
            );
        }
    }

    /**
     * The base-related fields to copy onto a PayrollEmployeeComponent
     * snapshot at assignment time. calculation_base_component_id is kept
     * only as a denormalized "primary base" mirror (first submitted base) —
     * the real, authoritative multi-base set lives in the pivot synced
     * separately via syncBaseComponentsOnto(). See the 4 call sites in
     * PayrollEmployeeStructureController::store() and
     * PayrollStructureAssignmentService that must all use this instead of
     * hardcoding calculation_base_type => 'none'.
     */
    public function snapshotBaseFields(): array
    {
        return [
            'calculation_base_type' => $this->calculation_base_type,
            'calculation_base' => $this->calculation_base,
            'calculation_base_component_id' => $this->calculation_base_component_id,
        ];
    }
}
