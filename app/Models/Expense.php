<?php
// app/Models/Expense.php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\TenantTrait;

class Expense extends Model
{
    // Soft deletes: an employee "deleting" a never-actioned claim keeps the row (audit trail);
    // every Eloquent query excludes deleted claims automatically.
    use TenantTrait, SoftDeletes;

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_COMPLETE = 'complete';
    public const STATUS_CANCELLED = 'cancelled';

    /** expenses.payout_channel value while a reimbursement is routed through payroll (NULL = voucher route). */
    public const CHANNEL_PAYROLL = 'payroll';

    public const TYPE_ADVANCE = 'advance';
    public const TYPE_SETTLEMENT = 'settlement';
    public const TYPE_REIMBURSEMENT = 'reimbursement';

    protected $table = 'expenses';

    protected static function booted(): void
    {
        // The DB trigger that used to set expense_number was removed (Phase 2);
        // the number now comes from the row's own id right after the insert.
        static::created(fn (Expense $expense) => app(\App\Services\Expense\ExpenseNumberGenerator::class)->assign($expense));
    }

    protected $fillable = [
        'tenant_id',
        'user_id',
        'expense_number',
        'requirement_type',
        'expense_type',
        'project_id',
        'amount',
        'paid_amount',
        'is_direct_payment',
        'date',
        'file',
        'description',
        'status',
        'approved_by',
        'approved_at',
        'approval_remarks',
        'rejected_by',
        'rejected_at',
        'rejection_reason',
        'withdrawn_at',
        'withdrawn_reason',
        'parent_expense_id',
        'possible_duplicate_of',
        'payout_channel',
        'payroll_target_month',
        'is_billable',

    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'withdrawn_at' => 'datetime',
        'is_billable' => 'boolean',
        'is_direct_payment' => 'boolean',
    ];

    /**
     * Get the user who submitted the expense
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the expense type
     */
    public function expenseType()
    {
        return $this->belongsTo(ExpenseType::class, 'expense_type');
    }

    /**
     * Get the project associated with this expense
     */
    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the approver user
     */
    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Get the rejector user
     */
    public function rejector()
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    /**
     * POSTED payments only — the ones that count toward what has been paid.
     * Every "sum of payments" in the module goes through this relation, so a
     * voided payment stops counting everywhere at once. Use allPayments() when the
     * voided history must be shown.
     */
    public function payments()
    {
        return $this->hasMany(ExpensePayment::class, 'expense_id')
            ->where('expense_payments.status', ExpensePayment::STATUS_POSTED);
    }

    /** Every payment including voided ones (audit / history display). */
    public function allPayments()
    {
        return $this->hasMany(ExpensePayment::class, 'expense_id');
    }

    /**
     * Get the attachments for this expense
     */
    public function attachments()
    {
        return $this->hasMany(ExpenseAttachment::class);
    }

    /**
     * Total paid so far. Reads the cached `paid_amount` column, which
     * ExpensePaymentService keeps in sync under the same row lock as every
     * payment write (was one SUM() query per call). `expense:reconcile-balances`
     * verifies it against the payments table.
     */
    public function getTotalPaidAttribute()
    {
        return $this->attributes['paid_amount'] ?? 0;
    }

    /**
     * Amount still to be paid (integer-paise arithmetic, never float subtraction).
     */
    public function getRemainingAmountAttribute()
    {
        return Money::fromCents(Money::toCents($this->amount) - Money::toCents($this->total_paid));
    }

    /**
     * Check if expense is fully paid
     */
    public function getIsFullyPaidAttribute()
    {
        return Money::toCents($this->remaining_amount) <= 0;
    }

    public function isAdvance()
    {
        return $this->requirement_type === 'advance';
    }

    public function isSettlement()
    {
        return $this->requirement_type === 'settlement';
    }

    public function isPending()
    {
        return $this->status === 'pending';
    }

    public function isApproved()
    {
        return $this->status === 'approved';
    }

    public function isRejected()
    {
        return $this->status === 'cancelled';
    }
    /** Approved and not yet fully paid (uses the cached paid_amount, so it is a plain indexed-table filter). */
    public function scopePayable($query)
    {
        // Payable by VOUCHER: a reimbursement sent to payroll is paid with the salary instead.
        return $query->where($this->getTable() . '.status', self::STATUS_APPROVED)
            ->whereNull($this->getTable() . '.payout_channel')
            ->whereColumn($this->getTable() . '.paid_amount', '<', $this->getTable() . '.amount');
    }

    public function scopeAdvance($query)
    {
        return $query->where('requirement_type', 'advance');
    }

    public function scopeSettlement($query)
    {
        return $query->where('requirement_type', 'settlement');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    /** The settlement this reimbursement was split off from (shortfall beyond the employee's advance). */
    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_expense_id');
    }

    /** Reimbursements split off from this settlement. */
    public function children()
    {
        return $this->hasMany(self::class, 'parent_expense_id');
    }

    /** The earlier claim this one looks like (same person/type/amount/date). A flag for approvers, never a block. */
    public function possibleDuplicateOf()
    {
        return $this->belongsTo(self::class, 'possible_duplicate_of')->withTrashed();
    }

    /** Payroll links of this reimbursement (history + the one active link, if any). */
    public function payrollLinks()
    {
        return $this->hasMany(ExpensePayrollLink::class);
    }

    public function isRoutedThroughPayroll(): bool
    {
        return $this->payout_channel === self::CHANNEL_PAYROLL;
    }

    public function isWithdrawn(): bool
    {
        return $this->withdrawn_at !== null;
    }

    public function transactions()
    {
        return $this->hasMany(ExpenseTransaction::class);
    }

    public function statusHistories()
    {
        return $this->hasMany(ExpenseStatusHistory::class);
    }
}
