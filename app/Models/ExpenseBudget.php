<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\TenantTrait;

/**
 * Budget allocation per fiscal year / department / project / expense type.
 * Not enforced anywhere yet (Phase 4 wires it into approval); the model exists
 * so create()/update() no longer throw a mass-assignment error.
 */
class ExpenseBudget extends Model
{
    use TenantTrait;

    protected $fillable = [
        'tenant_id',
        'fiscal_year',
        'department_id',
        'project_id',
        'expense_type_id',
        'allocated_amount',
        'used_amount',
        'remaining_amount',
        'enforcement',
    ];

    public const ENFORCE_WARN = 'warn';
    public const ENFORCE_BLOCK = 'block';

    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function project()
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function expenseType()
    {
        return $this->belongsTo(ExpenseType::class, 'expense_type_id');
    }

    protected $casts = [
        'allocated_amount' => 'decimal:2',
        'used_amount' => 'decimal:2',
        'remaining_amount' => 'decimal:2',
    ];
}
