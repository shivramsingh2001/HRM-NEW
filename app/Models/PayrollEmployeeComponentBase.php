<?php

namespace App\Models;

use App\Traits\TenantTrait;
use Illuminate\Database\Eloquent\Model;

/**
 * Snapshot of PayrollComponentBaseComponent onto one employee's assigned
 * component at assignment time — later catalog edits never retroactively
 * change an already-assigned employee.
 */
class PayrollEmployeeComponentBase extends Model
{
    use TenantTrait;

    protected $guarded = ['id'];

    public function employeeComponent()
    {
        return $this->belongsTo(PayrollEmployeeComponent::class, 'payroll_employee_component_id');
    }

    public function baseComponent()
    {
        return $this->belongsTo(PayrollComponentMaster::class, 'base_payroll_component_master_id');
    }
}
