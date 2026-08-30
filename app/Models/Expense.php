<?php
// app/Models/Expense.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\TenantTrait;

class Expense extends Model
{
    use TenantTrait;
    protected $table = 'expenses';

    protected $fillable = [
        'tenant_id',
        'user_id',
        'expense_number',
        'requirement_type',
        'expense_type',
        'project_id',
        'amount',
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
        'is_billable',

    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'is_billable' => 'boolean'
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
     * Get all payments for this expense
     */
    public function payments()
    {
        return $this->hasMany(ExpensePayment::class, 'expense_id');
    }

    /**
     * Get the status history for this expense
     */
    public function statusHistory()
    {
        return $this->hasMany(ExpenseStatusHistory::class);
    }

    /**
     * Get the attachments for this expense
     */
    public function attachments()
    {
        return $this->hasMany(ExpenseAttachment::class);
    }

    /**
     * Calculate total paid amount
     */
    public function getTotalPaidAttribute()
    {
        return $this->payments()->sum('amount');
    }

    /**
     * Calculate remaining amount
     */
    public function getRemainingAmountAttribute()
    {
        return $this->amount - $this->total_paid;
    }

    /**
     * Check if expense is fully paid
     */
    public function getIsFullyPaidAttribute()
    {
        return $this->remaining_amount <= 0;
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

    public function transactions()
    {
        return $this->hasMany(ExpenseTransaction::class);
    }

    public function statusHistories()
    {
        return $this->hasMany(ExpenseStatusHistory::class);
    }
}
