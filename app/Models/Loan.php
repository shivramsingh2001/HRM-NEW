<?php
// app/Models/Loan.php

namespace App\Models;

use App\Traits\TenantTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Loan extends Model
{
    use TenantTrait, HasFactory, SoftDeletes;

    protected $table = 'loans';

    protected $fillable = [
        'created_by', // admin / HR who raised it for the employee (NULL = the employee)
        'loan_application_id',
        'loan_type_id',
        'loan_kind',      // loan | salary_advance
        'advance_month',  // Y-m — salary advance only: the payroll month it is recovered from
        'tenant_id',
        'user_id',
        'loan_number',
        'amount',
        'processing_fee',
        'total_payable',
        'interest_rate',
        'tenure_months',
        'repayment_type',        // ADD THIS
        'emi_amount',
        'remaining_amount',
        'loan_date',
        'first_emi_date',
        'lumpsum_due_date',      // ADD THIS
        'lumpsum_amount',        // ADD THIS
        'last_emi_date',
        'closed_date',
        'status',
        'purpose',
        'description',
        'document_path',
        'approved_by',
        'approved_at',
        'disbursed_by',
        'disbursed_at',
        'cancelled_by',
        'cancelled_at',
        'cancellation_reason',
        'rejection_reason',
        'rejected_by',
        'rejected_at'
    ];

    // protected $casts = [
    //     'amount' => 'decimal:2',
    //     'processing_fee' => 'decimal:2',
    //     'total_payable' => 'decimal:2',
    //     'interest_rate' => 'decimal:2',
    //     'emi_amount' => 'decimal:2',
    //     'remaining_amount' => 'decimal:2',
    //     'loan_date' => 'date',
    //     'first_emi_date' => 'date',
    //     'last_emi_date' => 'date',
    //     'lumpsum_due_date' => 'date',    // ADD THIS
    //     'lumpsum_amount' => 'decimal:2', // ADD THIS
    //     'closed_date' => 'date',
    //     'approved_at' => 'datetime',
    //     'disbursed_at' => 'datetime',
    //     'cancelled_at' => 'datetime',
    //     'rejected_at' => 'datetime',
    //     'created_at' => 'datetime',
    //     'updated_at' => 'datetime',
    //     'deleted_at' => 'datetime',
    // ];

    // Status Constants
    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_ACTIVE = 'active';
    const STATUS_CLOSED = 'closed';
    const STATUS_DEFAULT = 'default';
    const STATUS_CANCELLED = 'cancelled';
    const REPAYMENT_TYPE_EMI = 'emi';
    const REPAYMENT_TYPE_LUMPSUM = 'lumpsum';

    // Loans & Advances: a salary advance is a one-time advance recovered from one payroll month.
    const KIND_LOAN = 'loan';
    const KIND_SALARY_ADVANCE = 'salary_advance';

    public function isSalaryAdvance(): bool
    {
        return $this->loan_kind === self::KIND_SALARY_ADVANCE;
    }

    /** "Salary advance · Mar 2026" / "Loan" — for lists and notifications. */
    public function kindLabel(): string
    {
        return $this->isSalaryAdvance()
            ? 'Salary advance' . ($this->advance_month ? ' · ' . \Carbon\Carbon::parse($this->advance_month . '-01')->format('M Y') : '')
            : 'Loan';
    }

    /** Status as shown to people — reject() stores 'default'. */
    public function statusLabel(): string
    {
        if ($this->status === self::STATUS_DEFAULT && $this->rejected_at) {
            return 'Rejected';
        }
        if ($this->isSalaryAdvance() && $this->status === self::STATUS_ACTIVE) {
            return 'Paid — to be deducted';
        }

        return ucfirst((string) $this->status);
    }

    /**
     * Get all statuses
     */
    public static function getStatuses()
    {
        return [
            self::STATUS_PENDING => 'Pending',
            self::STATUS_APPROVED => 'Approved',
            self::STATUS_ACTIVE => 'Active',
            self::STATUS_CLOSED => 'Closed',
            self::STATUS_DEFAULT => 'Default',
            self::STATUS_CANCELLED => 'Cancelled',
        ];
    }

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function loanCategory()
    {
        return $this->belongsTo(LoanCategory::class, 'loan_type_id');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function rejectedBy()
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function disbursedBy()
    {
        return $this->belongsTo(User::class, 'disbursed_by');
    }

    public function cancelledBy()
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function repayments()
    {
        return $this->hasMany(LoanRepayment::class, 'loan_id');
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeClosed($query)
    {
        return $query->where('status', self::STATUS_CLOSED);
    }

    public function scopeDefault($query)
    {
        return $query->where('status', self::STATUS_DEFAULT);
    }

    public function scopeCancelled($query)
    {
        return $query->where('status', self::STATUS_CANCELLED);
    }

    public function scopeByTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    // Helper Methods
    public function isPending()
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isApproved()
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isActive()
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isClosed()
    {
        return $this->status === self::STATUS_CLOSED;
    }

    public function isDefault()
    {
        return $this->status === self::STATUS_DEFAULT;
    }

    public function isCancelled()
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function getProgressPercentageAttribute()
    {
        if ($this->amount <= 0) {
            return 0;
        }

        $paid = $this->amount - $this->remaining_amount;
        return round(($paid / $this->amount) * 100, 2);
    }

    public function getPaidAmountAttribute()
    {
        return $this->amount - $this->remaining_amount;
    }

    public function getNextDueEmiAttribute()
    {
        return $this->repayments()
            ->where('status', LoanRepayment::STATUS_PENDING)
            ->where('due_date', '>=', now())
            ->orderBy('due_date')
            ->first();
    }

    public function getOverdueEmisCountAttribute()
    {
        return $this->repayments()
            ->where('status', LoanRepayment::STATUS_PENDING)
            ->where('due_date', '<', now())
            ->count();
    }

    public function getTotalPaidEmisAttribute()
    {
        return $this->repayments()
            ->where('status', LoanRepayment::STATUS_PAID)
            ->count();
    }

    public function getRemainingEmisAttribute()
    {
        return $this->tenure_months - $this->total_paid_emis;
    }

    public function canBeApproved()
    {
        return $this->isPending();
    }

    public function canBeDisbursed()
    {
        return $this->isApproved();
    }

    public function canBeCancelled()
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_APPROVED]);
    }
    public static function getRepaymentTypes()
    {
        return [
            self::REPAYMENT_TYPE_EMI => 'Monthly EMI (Salary Deduction)',
            self::REPAYMENT_TYPE_LUMPSUM => 'Lump Sum (One-time Payment)',
        ];
    }

    /**
     * Check if loan is lumpsum type
     */
    public function isLumpsum()
    {
        return $this->repayment_type === self::REPAYMENT_TYPE_LUMPSUM;
    }

    /**
     * Check if loan is EMI type
     */
    public function isEmi()
    {
        return $this->repayment_type === self::REPAYMENT_TYPE_EMI;
    }
}
