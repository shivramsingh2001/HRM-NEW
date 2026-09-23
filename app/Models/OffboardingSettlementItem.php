<?php

namespace App\Models;

use App\Traits\TenantTrait;
use Illuminate\Database\Eloquent\Model;

/**
 * One line of the final-settlement worksheet. `computed_amount` is the
 * system-calculated value (never overwritten after generation);
 * `override_amount` is an HR/Finance edit; `final_amount` (= override ??
 * computed) is kept in sync by OffboardingSettlementService on every save.
 */
class OffboardingSettlementItem extends Model
{
    use TenantTrait;

    protected $fillable = [
        'tenant_id', 'offboarding_request_id', 'line_type', 'is_addition',
        'source_type', 'source_id', 'label', 'computed_amount', 'override_amount',
        'final_amount', 'notes', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'is_addition' => 'boolean',
        'computed_amount' => 'decimal:2',
        'override_amount' => 'decimal:2',
        'final_amount' => 'decimal:2',
    ];

    const TYPE_PENDING_SALARY = 'pending_salary';
    const TYPE_LEAVE_ENCASHMENT = 'leave_encashment';
    const TYPE_LOAN_DEDUCTION = 'loan_deduction';
    const TYPE_EXPENSE_ADVANCE_DEDUCTION = 'expense_advance_deduction';
    const TYPE_EXPENSE_REIMBURSEMENT = 'expense_reimbursement';
    const TYPE_NOTICE_SHORTFALL_DEDUCTION = 'notice_shortfall_deduction';
    const TYPE_SEVERANCE = 'severance';
    const TYPE_CUSTOM_ADDITION = 'custom_addition';
    const TYPE_CUSTOM_DEDUCTION = 'custom_deduction';

    /** Line types generated automatically by generateWorksheet() and cleared/regenerated on recompute (unless overridden). */
    const AUTO_GENERATED_TYPES = [
        self::TYPE_PENDING_SALARY, self::TYPE_LEAVE_ENCASHMENT, self::TYPE_LOAN_DEDUCTION,
        self::TYPE_EXPENSE_ADVANCE_DEDUCTION, self::TYPE_EXPENSE_REIMBURSEMENT, self::TYPE_NOTICE_SHORTFALL_DEDUCTION,
    ];

    public function offboardingRequest()
    {
        return $this->belongsTo(OffboardingRequest::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function signedAmount(): float
    {
        return $this->is_addition ? (float) $this->final_amount : -1 * (float) $this->final_amount;
    }
}
