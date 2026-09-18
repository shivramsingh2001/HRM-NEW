<?php

namespace App\Models;

use App\Traits\LogsPayrollActivity;
use App\Traits\TenantTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Named, reusable salary-structure template — a bundle of chosen
 * components. Replaces the *role* of PayrollMaster for tenants on the new
 * dynamic engine, without touching PayrollMaster itself.
 */
class PayrollStructure extends Model
{
    use TenantTrait, HasFactory, LogsPayrollActivity;

    protected $guarded = ['id'];

    protected $casts = [
        'working_hours_per_day' => 'decimal:2',
        'hourly_rate_applied' => 'decimal:2',
        'is_default' => 'boolean',
        'status' => 'boolean',
    ];

    public function components()
    {
        return $this->hasMany(PayrollStructureComponent::class, 'payroll_structure_id');
    }

    public function employeeStructures()
    {
        return $this->hasMany(PayrollEmployeeStructure::class, 'payroll_structure_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Component codes the employee-wizard's client-side CTC calculator
     * understands, keyed to what it resolves per code: a percentage of a
     * known base (only 'basic' is supported — the calculator solves for
     * basic before gross/CTC are known, so a component based on those can't
     * be resolved here) or a flat monthly amount. A code absent from the
     * result means this structure hasn't configured that component yet —
     * the caller falls back to the wizard's standard default for it.
     */
    private const CALCULATOR_CODES = [
        'hra', 'conveyance', 'medical_allowance', 'children_allowance',
        'post_allowance', 'leave_travel_allowance', 'monthly_incentive',
        'pf_employee', 'pf_employer', 'esi_employee', 'esi_employer', 'pt',
    ];

    public function calculatorComponents(): array
    {
        $this->loadMissing('components.component');

        $map = [];

        foreach ($this->components as $row) {
            $component = $row->component;

            if (! $component || ! in_array($component->code, self::CALCULATOR_CODES, true)) {
                continue;
            }

            $method = $row->override_calculation_method ?? $component->calculation_method;

            if ($method === 'percentage' && $component->calculation_base_type === 'fixed_base') {
                $value = $row->override_percentage ?? $component->percentage_value;

                if ($value === null || ! $component->calculation_base) {
                    continue;
                }

                $map[$component->code] = [
                    'type' => 'percentage',
                    'base' => $component->calculation_base,
                    'value' => (float) $value,
                    'ceiling_amount' => $component->has_wage_ceiling ? (float) $component->ceiling_amount : null,
                    'ceiling_rule' => $component->ceiling_apply_rule,
                ];
            } elseif ($method === 'fixed_amount') {
                $value = $row->override_amount ?? $component->default_amount;

                if ($value === null) {
                    continue;
                }

                $map[$component->code] = [
                    'type' => 'fixed',
                    'value' => (float) $value,
                ];
            }
        }

        return $map;
    }
}
