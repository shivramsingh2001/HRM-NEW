<?php

namespace App\Models;

use App\Traits\TenantTrait;
use Illuminate\Database\Eloquent\Model;

/**
 * One attempt to pay an approved reimbursement through payroll — see ExpenseReimbursementPayrollService.
 * queued -> linked (on a pending payslip) -> paid, or released back to the voucher route.
 */
class ExpensePayrollLink extends Model
{
    use TenantTrait;

    public const QUEUED = 'queued';
    public const LINKED = 'linked';
    public const PAID = 'paid';
    public const RELEASED = 'released';

    /** Statuses in which the reimbursement is "owned" by payroll. */
    public const ACTIVE = [self::QUEUED, self::LINKED, self::PAID];

    protected $fillable = [
        'tenant_id', 'expense_id', 'user_id', 'target_month', 'monthly_payroll_id', 'amount', 'status',
        'expense_payment_id', 'created_by', 'linked_at', 'paid_at', 'released_at', 'released_reason',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'linked_at' => 'datetime',
        'paid_at' => 'datetime',
        'released_at' => 'datetime',
    ];

    public function expense()
    {
        return $this->belongsTo(Expense::class);
    }

    public function monthlyPayroll()
    {
        return $this->belongsTo(MonthlyPayroll::class);
    }

    public function scopeActive($query)
    {
        return $query->whereIn($this->getTable() . '.status', self::ACTIVE);
    }
}
