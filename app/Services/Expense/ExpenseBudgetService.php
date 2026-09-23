<?php

namespace App\Services\Expense;

use App\Models\Expense;
use App\Models\ExpenseBudget;
use App\Support\Money;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Expense budgets: a cap on approved SPEND for a fiscal year, optionally narrowed by department,
 * project and/or expense category (a NULL dimension means "any").
 *
 *  - SPEND = settlements + reimbursements that are approved/complete. Advances are cash handed out,
 *    not spend (they are consumed by settlements, which DO count). A reimbursement that was split
 *    off a settlement's shortfall (parent_expense_id set) is not counted again — the settlement
 *    already carries the full amount.
 *  - Usage is computed LIVE from the expenses table, so it cannot drift. (expense_budgets.used_amount /
 *    remaining_amount are refreshed on approval purely as a convenience for other readers.)
 *  - Every budget that applies to a claim is a separate cap; the claim must fit in all of them.
 *  - Enforcement per budget: 'warn' approves but reports it; 'block' refuses the approval.
 *  - While deciding an approval the applicable budget rows are locked FOR UPDATE (ascending id), so two
 *    concurrent approvals cannot both squeeze under the same cap.
 *  - Fiscal year uses the company's existing convention (config/leave.php: start month/day, April 1 by
 *    default) and is labelled "2026-27".
 */
class ExpenseBudgetService
{
    private const SPEND_TYPES = [Expense::TYPE_SETTLEMENT, Expense::TYPE_REIMBURSEMENT];

    public function fiscalYearFor(CarbonInterface $date): string
    {
        [$month, $day] = $this->startMonthDay();
        $date = Carbon::instance($date)->startOfDay();

        $startYear = $date->lt(Carbon::create($date->year, $month, $day)->startOfDay()) ? $date->year - 1 : $date->year;

        // A calendar-year company (Jan 1 start) reads better as "2026" than "2026-27".
        return ($month === 1 && $day === 1) ? (string) $startYear : $startYear . '-' . substr((string) ($startYear + 1), -2);
    }

    /** @return array{0: Carbon, 1: Carbon} inclusive start and end dates of a fiscal-year label */
    public function range(string $label): array
    {
        [$month, $day] = $this->startMonthDay();
        $start = Carbon::create((int) substr($label, 0, 4), $month, $day)->startOfDay();

        return [$start, $start->copy()->addYear()->subDay()];
    }

    /** The label for "now" — the default when creating a budget. */
    public function currentFiscalYear(): string
    {
        return $this->fiscalYearFor(now());
    }

    /**
     * @param  bool  $lock  lock the matching rows FOR UPDATE (only valid inside a transaction)
     * @return Collection<int, ExpenseBudget>
     */
    public function applicable(Expense $expense, bool $lock = false): Collection
    {
        $fiscalYear = $this->fiscalYearFor(Carbon::parse($expense->date));

        $query = ExpenseBudget::withoutGlobalScopes()
            ->where('tenant_id', $expense->tenant_id)
            ->where('fiscal_year', $fiscalYear)
            ->orderBy('id');

        if ($lock) {
            $query->lockForUpdate();
        }

        // ORDER MATTERS. When locking, this locking read must be the FIRST read of the transaction:
        // under REPEATABLE READ any plain SELECT fixes the transaction's snapshot, and an approval
        // that then waits on this lock would go on to count usage from a snapshot that predates the
        // approval it waited for — letting both slip under the same cap. So lock ALL of the tenant's
        // budgets for the year first (a handful of rows), and only then read the department and
        // narrow the list in PHP.
        $all = $query->get();

        $department = DB::table('user_job_details')->where('user_id', $expense->user_id)->value('department');

        return $all->filter(fn (ExpenseBudget $b) => (! $b->department_id || (int) $b->department_id === (int) $department)
            && (! $b->project_id || (int) $b->project_id === (int) $expense->project_id)
            && (! $b->expense_type_id || (int) $b->expense_type_id === (int) $expense->expense_type))->values();
    }

    /** Approved spend (paise) inside one budget's scope and fiscal year. */
    public function usedCents(ExpenseBudget $budget, ?int $excludeExpenseId = null): int
    {
        [$start, $end] = $this->range((string) $budget->fiscal_year);

        $query = DB::table('expenses as e')
            ->leftJoin('user_job_details as j', 'j.user_id', '=', 'e.user_id')
            ->where('e.tenant_id', $budget->tenant_id)
            ->whereNull('e.deleted_at')
            ->whereNull('e.parent_expense_id')
            ->whereIn('e.requirement_type', self::SPEND_TYPES)
            ->whereIn('e.status', [Expense::STATUS_APPROVED, Expense::STATUS_COMPLETE])
            ->whereBetween('e.date', [$start->toDateString(), $end->toDateString()]);

        if ($budget->department_id) {
            $query->where('j.department', $budget->department_id);
        }
        if ($budget->project_id) {
            $query->where('e.project_id', $budget->project_id);
        }
        if ($budget->expense_type_id) {
            $query->where('e.expense_type', $budget->expense_type_id);
        }
        if ($excludeExpenseId) {
            $query->where('e.id', '!=', $excludeExpenseId);
        }

        return Money::toCents($query->sum('e.amount'));
    }

    /**
     * Would approving this expense push any applicable budget over its cap?
     *
     * @return array{blocked: bool, warnings: string[], breaches: array<int, array{budget_id:int, label:string, used:float, allocated:float, after:float, enforcement:string}>}
     */
    public function evaluate(Expense $expense, bool $lock = false): array
    {
        $result = ['blocked' => false, 'warnings' => [], 'breaches' => []];

        if (! in_array($expense->requirement_type, self::SPEND_TYPES, true)) {
            return $result;
        }

        $claimCents = Money::toCents($expense->amount);

        foreach ($this->applicable($expense, $lock)->load(['department:id,name', 'project:id,name', 'expenseType:id,name']) as $budget) {
            $allocated = Money::toCents($budget->allocated_amount);
            $used = $this->usedCents($budget, (int) $expense->id);
            $after = $used + $claimCents;

            if ($after <= $allocated) {
                continue;
            }

            $label = $this->describe($budget);
            $blocking = $budget->enforcement === ExpenseBudget::ENFORCE_BLOCK;

            $result['blocked'] = $result['blocked'] || $blocking;
            $result['warnings'][] = "Over budget — {$label} ({$budget->fiscal_year}): ₹" . Money::format($after) . ' of ₹' . Money::format($allocated) . ' would be used.';
            $result['breaches'][] = [
                'budget_id' => (int) $budget->id,
                'label' => $label,
                'used' => Money::fromCents($used),
                'allocated' => Money::fromCents($allocated),
                'after' => Money::fromCents($after),
                'enforcement' => $budget->enforcement,
            ];
        }

        return $result;
    }

    /** Refresh used_amount / remaining_amount on the budgets that apply to this expense (convenience only). */
    public function syncUsage(Expense $expense): void
    {
        foreach ($this->applicable($expense) as $budget) {
            $used = $this->usedCents($budget);
            $budget->forceFill([
                'used_amount' => Money::fromCents($used),
                'remaining_amount' => Money::fromCents(Money::toCents($budget->allocated_amount) - $used),
            ])->save();
        }
    }

    /** Human label of a budget's scope: "IT department · Travel", "Company-wide", … */
    public function describe(ExpenseBudget $budget): string
    {
        $parts = array_filter([
            $budget->department?->name ? $budget->department->name . ' dept' : null,
            $budget->project?->name ? 'project ' . $budget->project->name : null,
            $budget->expenseType?->name,
        ]);

        return $parts ? implode(' · ', $parts) : 'Company-wide';
    }

    /** @return array{0:int, 1:int} */
    private function startMonthDay(): array
    {
        return [(int) config('leave.fiscal_year_start_month', 4), (int) config('leave.fiscal_year_start_day', 1)];
    }
}
