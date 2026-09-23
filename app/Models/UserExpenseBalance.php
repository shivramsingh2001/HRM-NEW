<?php
// app/Models/UserExpenseBalance.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\TenantTrait;

/**
 * An employee's running expense balances. READ-ONLY from application code:
 * every change goes through App\Services\Expense\ExpenseLedgerService, which
 * locks the row and writes the matching expense_transactions entry. (The old
 * updateBalances()/addAdvance()/addSettlement()/canSettle() helpers were removed:
 * they mutated with floats, no lock and no ledger row, and nothing used them.)
 *
 * current_balance = advance_balance - settlement_balance;
 * reimbursement_balance is a separate running total and is NOT part of current.
 */
class UserExpenseBalance extends Model
{
    use TenantTrait, HasFactory;

    protected $guarded = [];

    protected $casts = [
        'current_balance' => 'decimal:2',
        'advance_balance' => 'decimal:2',
        'settlement_balance' => 'decimal:2',
        'reimbursement_balance' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
