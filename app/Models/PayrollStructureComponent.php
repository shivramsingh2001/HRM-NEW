<?php

namespace App\Models;

use App\Traits\TenantTrait;
use Illuminate\Database\Eloquent\Model;

/**
 * Which components a PayrollStructure includes, with optional per-structure
 * overrides of the catalog's default calculation method/value.
 */
class PayrollStructureComponent extends Model
{
    use TenantTrait;

    protected $guarded = ['id'];

    protected $casts = [
        'override_amount' => 'decimal:2',
        'override_percentage' => 'decimal:3',
        'is_mandatory' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function structure()
    {
        return $this->belongsTo(PayrollStructure::class, 'payroll_structure_id');
    }

    public function component()
    {
        return $this->belongsTo(PayrollComponentMaster::class, 'payroll_component_master_id');
    }

    /**
     * Per-structure-template override of the "% Of (Earnings)" base
     * selection (payroll_structure_component_bases). Falls back to the
     * catalog's own PayrollComponentMaster::baseComponents() when empty —
     * see createComponentSnapshots()/PayrollCalculationEngine::resolveBase()
     * for where each layer is actually consulted.
     */
    public function baseComponentBases()
    {
        return $this->hasMany(PayrollStructureComponentBase::class, 'payroll_structure_component_id');
    }

    public function baseComponents()
    {
        return $this->belongsToMany(
            PayrollComponentMaster::class,
            'payroll_structure_component_bases',
            'payroll_structure_component_id',
            'base_payroll_component_master_id'
        )->withTimestamps();
    }
}
