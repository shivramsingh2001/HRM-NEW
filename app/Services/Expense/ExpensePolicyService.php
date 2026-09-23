<?php

namespace App\Services\Expense;

use App\Exceptions\ExpenseException;
use App\Models\Expense;
use App\Models\ExpenseType;
use App\Models\User;
use App\Support\Money;
use Carbon\Carbon;

/**
 * The company's per-category claim rules, enforced on submit AND edit.
 *
 * Configured on the expense category (Expense Types page); every rule is optional and NULL means
 * "no rule", so a company that configures nothing sees no change:
 *   max_amount              a claim may not exceed this
 *   receipt_required_above  a receipt is mandatory when the amount is above this (0 = always)
 *   max_backdate_days       a settlement/reimbursement must be filed within N days of the expense date
 *                           (not applied to advances — an advance is a request for FUTURE money)
 *
 * Duplicate detection is a FLAG, never a block: two identical taxi fares on one day are legitimate,
 * and the mobile app has no way to "confirm anyway". Approvers see "possible duplicate" instead.
 */
class ExpensePolicyService
{
    /**
     * @param  array{expense_type:mixed, amount:mixed, date:string, requirement_type:string}  $data
     * @param  int  $receiptCount  receipts the claim will have AFTER this operation
     *
     * @throws ExpenseException
     */
    public function check(User $actor, array $data, int $receiptCount): ExpenseType
    {
        // Tenant-scoped lookup: a bare `exists:expense_types,id` accepted another company's category.
        $type = ExpenseType::withoutGlobalScopes()->where('tenant_id', $actor->tenant_id)->find($data['expense_type'] ?? 0);

        if (! $type) {
            throw new ExpenseException('The selected expense category does not exist.', 422);
        }
        if ((int) $type->status !== 1) {
            throw new ExpenseException("The expense category '{$type->name}' is no longer active.", 422);
        }

        $cents = Money::toCents($data['amount'] ?? 0);

        if ($type->max_amount !== null && $cents > Money::toCents($type->max_amount)) {
            throw new ExpenseException(
                "The limit for {$type->name} claims is ₹" . Money::format(Money::toCents($type->max_amount)) . ' per claim.', 422
            );
        }

        if ($type->receipt_required_above !== null && $cents > Money::toCents($type->receipt_required_above) && $receiptCount < 1) {
            $above = Money::toCents($type->receipt_required_above);
            throw new ExpenseException(
                $above > 0
                    ? "A receipt is required for {$type->name} claims above ₹" . Money::format($above) . '.'
                    : "A receipt is required for {$type->name} claims.",
                422
            );
        }

        if ($type->max_backdate_days !== null && ($data['requirement_type'] ?? null) !== Expense::TYPE_ADVANCE) {
            $incurred = Carbon::parse($data['date'])->startOfDay();
            $age = (int) $incurred->diffInDays(today(), false);

            if ($age > (int) $type->max_backdate_days) {
                throw new ExpenseException(
                    "{$type->name} claims must be filed within {$type->max_backdate_days} day(s) of the expense date "
                    . "(this one is {$age} day(s) old).",
                    422
                );
            }
        }

        return $type;
    }

    /** Id of an earlier claim by the same person with the same category, type, amount and date — or null. */
    public function findDuplicate(User $actor, array $data, ?int $ignoreId = null): ?int
    {
        $query = Expense::where('user_id', $actor->id)
            ->where('expense_type', $data['expense_type'])
            ->where('requirement_type', $data['requirement_type'])
            ->whereDate('date', Carbon::parse($data['date'])->toDateString())
            ->whereRaw('ROUND(amount * 100) = ?', [Money::toCents($data['amount'])])
            ->where('status', '!=', Expense::STATUS_CANCELLED);

        if ($ignoreId) {
            $query->where('id', '!=', $ignoreId);
        }

        return $query->orderBy('id')->value('id');
    }
}
