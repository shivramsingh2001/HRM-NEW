<?php

namespace App\Models;

use App\Traits\TenantTrait;
use Illuminate\Database\Eloquent\Model;

/**
 * The actual dynamic salary breakdown for one employee-structure version.
 * calculation_method/base/value are snapshotted at assignment time so later
 * catalog edits never retroactively change an already-approved structure.
 */
class PayrollEmployeeComponent extends Model
{
    use TenantTrait;

    protected $guarded = ['id'];

    protected $casts = [
        'amount' => 'decimal:2',
        'percentage_value' => 'decimal:3',
        'is_enabled' => 'boolean',
    ];

    public function structure()
    {
        return $this->belongsTo(PayrollEmployeeStructure::class, 'payroll_employee_structure_id');
    }

    public function component()
    {
        return $this->belongsTo(PayrollComponentMaster::class, 'payroll_component_master_id');
    }

    public function baseComponent()
    {
        return $this->belongsTo(PayrollComponentMaster::class, 'calculation_base_component_id');
    }

    /**
     * Snapshot pivot rows for the multi-base "percentage of several
     * Earnings components" selection, copied from the catalog's
     * PayrollComponentMaster::baseComponents() at assignment time.
     */
    public function baseComponentBases()
    {
        return $this->hasMany(PayrollEmployeeComponentBase::class, 'payroll_employee_component_id');
    }

    /**
     * The actual Earnings PayrollComponentMaster rows this snapshot's
     * percentage is calculated against.
     */
    public function baseComponents()
    {
        return $this->belongsToMany(
            PayrollComponentMaster::class,
            'payroll_employee_component_bases',
            'payroll_employee_component_id',
            'base_payroll_component_master_id'
        )->withTimestamps();
    }

    public function scopeEnabled($query)
    {
        return $query->where('is_enabled', true);
    }
}
