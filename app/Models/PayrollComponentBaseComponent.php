<?php

namespace App\Models;

use App\Traits\TenantTrait;
use Illuminate\Database\Eloquent\Model;

/**
 * One row per Earnings component selected as part of a percentage
 * component's multi-base ("percentage of Basic + HRA" etc). Catalog-level —
 * see PayrollEmployeeComponentBase for the per-employee snapshot.
 */
class PayrollComponentBaseComponent extends Model
{
    use TenantTrait;

    protected $guarded = ['id'];

    public function component()
    {
        return $this->belongsTo(PayrollComponentMaster::class, 'payroll_component_master_id');
    }

    public function baseComponent()
    {
        return $this->belongsTo(PayrollComponentMaster::class, 'base_payroll_component_master_id');
    }
}
