<?php

namespace App\Models;

use App\Traits\TenantTrait;
use Illuminate\Database\Eloquent\Model;

/**
 * Per-structure-template override of which Earnings components a
 * percentage component's base sums — falls back to
 * PayrollComponentBaseComponent (the catalog default) when a
 * PayrollStructureComponent has no rows here.
 */
class PayrollStructureComponentBase extends Model
{
    use TenantTrait;

    protected $guarded = ['id'];

    public function structureComponent()
    {
        return $this->belongsTo(PayrollStructureComponent::class, 'payroll_structure_component_id');
    }

    public function baseComponent()
    {
        return $this->belongsTo(PayrollComponentMaster::class, 'base_payroll_component_master_id');
    }
}
