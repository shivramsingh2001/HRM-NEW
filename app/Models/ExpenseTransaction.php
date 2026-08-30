<?php
// app/Models/ExpenseTransaction.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\TenantTrait;

class ExpenseTransaction extends Model
{
    use TenantTrait, HasFactory;
    
    protected $fillable = [
        'tenant_id',
        'expense_id',
        'user_id',
        'transaction_type',
        'amount',
        'balance_before',
        'balance_after',
        'description'
    ];
    
    protected $casts = [
        'amount' => 'decimal:2',
        'balance_before' => 'decimal:2',
        'balance_after' => 'decimal:2'
    ];
    
    public function expense()
    {
        return $this->belongsTo(Expense::class);
    }
    
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    
    // Scope for advance transactions
    public function scopeAdvanceCredits($query)
    {
        return $query->where('transaction_type', 'advance_credited');
    }
    
    // Scope for settlement transactions
    public function scopeSettlementDebits($query)
    {
        return $query->where('transaction_type', 'settlement_debited');
    }
}