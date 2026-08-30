<?php
// app/Models/LoanRepayment.php

namespace App\Models;

use App\Traits\TenantTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LoanRepayment extends Model
{
    use TenantTrait, HasFactory;

    protected $table = 'loan_repayments';

    protected $fillable = [
        'tenant_id',
        'loan_id',
        'repayment_number',
        'installment_number',
        'month',
        'due_date',
        'emi_amount',
        'principal_amount',
        'interest_amount',
        'penalty_amount',
        'late_fee',
        'total_amount',
        'paid_amount',
        'paid_date',
        'status',
        'payment_mode',
        'transaction_reference',
        'payment_received_by',
        'processed_by',
        'is_auto_deducted',
        'salary_month',
        'remarks'
    ];

    // protected $casts = [
    //     'emi_amount' => 'decimal:2',
    //     'principal_amount' => 'decimal:2',
    //     'interest_amount' => 'decimal:2',
    //     'penalty_amount' => 'decimal:2',
    //     'late_fee' => 'decimal:2',
    //     'total_amount' => 'decimal:2',
    //     'paid_amount' => 'decimal:2',
    //     'due_date' => 'date',
    //     'paid_date' => 'date',
    //     'is_auto_deducted' => 'boolean',
    //     'created_at' => 'datetime',
    //     'updated_at' => 'datetime',
    // ];

    // Status Constants
    const STATUS_PENDING = 'pending';
    const STATUS_PAID = 'paid';
    const STATUS_OVERDUE = 'overdue';
    const STATUS_PARTIAL = 'partial';

    // Payment Mode Constants
    const PAYMENT_MODE_SALARY_DEDUCTION = 'salary_deduction';
    const PAYMENT_MODE_BANK_TRANSFER = 'bank_transfer';
    const PAYMENT_MODE_CHEQUE = 'cheque';
    const PAYMENT_MODE_CASH = 'cash';

    /**
     * Get all statuses
     */
    public static function getStatuses()
    {
        return [
            self::STATUS_PENDING => 'Pending',
            self::STATUS_PAID => 'Paid',
            self::STATUS_OVERDUE => 'Overdue',
            self::STATUS_PARTIAL => 'Partial',
        ];
    }

    /**
     * Get all payment modes
     */
    public static function getPaymentModes()
    {
        return [
            self::PAYMENT_MODE_SALARY_DEDUCTION => 'Salary Deduction',
            self::PAYMENT_MODE_BANK_TRANSFER => 'Bank Transfer',
            self::PAYMENT_MODE_CHEQUE => 'Cheque',
            self::PAYMENT_MODE_CASH => 'Cash',
        ];
    }

    // Relationships
    public function loan()
    {
        return $this->belongsTo(Loan::class, 'loan_id');
    }

    public function paymentReceivedBy()
    {
        return $this->belongsTo(User::class, 'payment_received_by');
    }

    public function processedBy()
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopePaid($query)
    {
        return $query->where('status', self::STATUS_PAID);
    }

    public function scopeOverdue($query)
    {
        return $query->where('status', self::STATUS_OVERDUE);
    }

    public function scopePartial($query)
    {
        return $query->where('status', self::STATUS_PARTIAL);
    }

    public function scopeByMonth($query, $month)
    {
        return $query->where('month', $month);
    }

    public function scopeBySalaryMonth($query, $salaryMonth)
    {
        return $query->where('salary_month', $salaryMonth);
    }

    public function scopeAutoDeducted($query)
    {
        return $query->where('is_auto_deducted', true);
    }

    public function scopeDueInRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('due_date', [$startDate, $endDate]);
    }

    // Helper Methods
    public function isPending()
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isPaid()
    {
        return $this->status === self::STATUS_PAID;
    }

    public function isOverdue()
    {
        return $this->status === self::STATUS_OVERDUE;
    }

    public function isPartial()
    {
        return $this->status === self::STATUS_PARTIAL;
    }

    public function isFullyPaid()
    {
        return $this->paid_amount >= $this->total_amount;
    }

    public function getRemainingAmountAttribute()
    {
        return $this->total_amount - $this->paid_amount;
    }

    public function getDaysOverdueAttribute()
    {
        if (!$this->isOverdue() && $this->due_date >= now()) {
            return 0;
        }
        
        return now()->diffInDays($this->due_date);
    }

    public function markAsPaid($paymentData = [])
    {
        $this->update(array_merge([
            'status' => self::STATUS_PAID,
            'paid_amount' => $this->total_amount,
            'paid_date' => now(),
            'updated_at' => now(),
        ], $paymentData));
    }

    public function markAsOverdue()
    {
        if ($this->isPending() && $this->due_date < now()) {
            $this->update([
                'status' => self::STATUS_OVERDUE,
                'updated_at' => now(),
            ]);
        }
    }
}