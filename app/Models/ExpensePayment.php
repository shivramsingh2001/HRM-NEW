<?php
// app/Models/ExpensePayment.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\TenantTrait;
class ExpensePayment extends Model
{
     use TenantTrait;
    protected $table = 'expense_payments';

    protected $fillable = [
        'tenant_id',
        'expense_id',
        'payment_date',
        'amount',
        'payment_mode',
        'reference_number',
        'bank_name',
        'paid_to',
        'paid_by',
        'remarks'
    ];

    protected $casts = [
        'payment_date' => 'date',
        'amount' => 'decimal:2'
    ];

    /**
     * Get the expense associated with this payment
     */
    public function expense()
    {
        return $this->belongsTo(Expense::class);
    }

    /**
     * Get the user who made the payment
     */
    public function payer()
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    /**
     * Get payment mode badge color
     */
    public function getModeBadgeAttribute()
    {
        $colors = [
            'cash' => 'success',
            'bank_transfer' => 'info',
            'cheque' => 'warning',
            'upi' => 'primary'
        ];
        
        return $colors[$this->payment_mode] ?? 'secondary';
    }

    /**
     * Format amount with currency
     */
    public function getFormattedAmountAttribute()
    {
        return '₹' . number_format($this->amount, 2);
    }

    /**
     * Get payment status
     */
    public function getPaymentStatusAttribute()
    {
        if (!$this->expense) {
            return 'unknown';
        }
        
        $totalPaid = $this->expense->payments()->sum('amount');
        
        if ($totalPaid >= $this->expense->amount) {
            return 'complete';
        } elseif ($this->expense->payments()->count() > 1) {
            return 'partial';
        } else {
            return 'pending';
        }
    }
}