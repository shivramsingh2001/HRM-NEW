<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\TenantTrait;

/**
 * An expense category, plus the optional company POLICY for claims of this category
 * (enforced by App\Services\Expense\ExpensePolicyService). Every rule column is NULL
 * = "no rule", so existing categories behave exactly as before until configured.
 *
 *  max_amount              hard cap per claim
 *  receipt_required_above  a receipt is mandatory when the amount exceeds this (0 = always)
 *  max_backdate_days       a settlement/reimbursement may be filed at most N days after it was incurred
 */
class ExpenseType extends Model
{
    use TenantTrait;
    protected $guarded = [];

    protected $casts = [
        'max_amount' => 'decimal:2',
        'receipt_required_above' => 'decimal:2',
        'max_backdate_days' => 'integer',
    ];

    public function expenses()
    {
        return $this->hasMany(Expense::class, 'expense_type');
    }

    public function hasPolicy(): bool
    {
        return $this->max_amount !== null || $this->receipt_required_above !== null || $this->max_backdate_days !== null;
    }
}
