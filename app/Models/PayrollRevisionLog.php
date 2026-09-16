<?php

namespace App\Models;

use App\Traits\TenantTrait;
use Illuminate\Database\Eloquent\Model;

/**
 * Append-only history of every salary change: who, when, why, and a
 * component-level before/after diff.
 */
class PayrollRevisionLog extends Model
{
    use TenantTrait;

    const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected $casts = [
        'effective_from' => 'date',
        'diff' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function structure()
    {
        return $this->belongsTo(PayrollEmployeeStructure::class, 'payroll_employee_structure_id');
    }

    public function previousStructure()
    {
        return $this->belongsTo(PayrollEmployeeStructure::class, 'previous_payroll_employee_structure_id');
    }

    public function changedBy()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
