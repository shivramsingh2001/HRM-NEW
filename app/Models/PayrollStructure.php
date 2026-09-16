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
}
