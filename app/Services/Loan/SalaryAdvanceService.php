<?php

namespace App\Services\Loan;

use App\Models\Loan;
use App\Models\LoanCategory;
use App\Models\LoanRepayment;
use App\Models\User;
use App\Services\Attendance\PeriodLockService;
use App\Services\AuditLogger;
use App\Services\NotificationService;
use App\Services\Payroll\PayrollStructureAssignmentService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Salary Advance — a one-time advance against ONE salary month, kept inside
 * Loans & Advances as a `loans` row with loan_kind = 'salary_advance' and
 * advance_month = 'YYYY-MM'. It has a single repayment row for that month
 * (no EMI, no interest, no tenure), which that month's payroll recovers as
 * its own "Salary Advance Deduction" line, before loan EMIs and capped at net
 * pay (LoanDeductionService / MonthlyPayrollController). Anything not
 * recovered carries forward to the next payroll.
 *
 * Request → approve / reject → disburse (pay) → deducted in payroll → closed;
 * cancel while pending/approved; manual repayment via the existing lump-sum
 * payment. Every step is audited (`salary_advance.*`).
 */
class SalaryAdvanceService
{
    /** An advance may be requested for this month and up to this many months ahead. */
    public const MONTHS_AHEAD = 2;

    public function __construct(private AuditLogger $audit)
    {
    }

    // ------------------------------------------------------------------ limits

    /** The employee's monthly gross from their current salary structure (dynamic first, else legacy). */
    public function monthlyGross(User $employee): ?float
    {
        $structure = \App\Models\PayrollEmployeeStructure::withoutGlobalScope('tenant')
            ->where('tenant_id', $employee->tenant_id)->where('user_id', $employee->id)
            ->where('is_current', true)->first();
        if ($structure) {
            $gross = (float) (app(PayrollStructureAssignmentService::class)->toLegacyShapedArray($structure)['gross_salary'] ?? 0);
            if ($gross > 0) {
                return round($gross, 2);
            }
        }

        $legacy = DB::table('user_payrolls')->where('tenant_id', $employee->tenant_id)->where('user_id', $employee->id)
            ->where('is_current', 1)->whereNull('deleted_at')->value('gross_salary');

        return $legacy !== null && (float) $legacy > 0 ? round((float) $legacy, 2) : null;
    }

    /**
     * A month whose payroll can still take the deduction: not locked, and the
     * employee's payslip for it (if any) is still pending.
     */
    public function monthOpen(int $tenantId, int $userId, string $month): bool
    {
        if (app(PeriodLockService::class)->isLocked($tenantId, $month)) {
            return false;
        }

        $status = DB::table('monthly_payrolls')->where('tenant_id', $tenantId)->where('user_id', $userId)
            ->where('payroll_month', $month)->value('payment_status');

        return $status === null || $status === 'pending';
    }

    /** Months the employee can request an advance for (current … +MONTHS_AHEAD, open ones only). */
    public function openMonths(User $employee): array
    {
        $months = [];
        for ($i = 0; $i <= self::MONTHS_AHEAD; $i++) {
            $m = Carbon::now()->startOfMonth()->addMonthsNoOverflow($i)->format('Y-m');
            if ($this->monthOpen((int) $employee->tenant_id, (int) $employee->id, $m)) {
                $months[] = $m;
            }
        }

        return $months;
    }

    /**
     * How much more the employee may take for $month under $category:
     * category max AND max_percent_of_gross % of monthly gross, both minus the
     * advances already requested/approved/paid for that month.
     *
     * @return array{gross: ?float, category_max: ?float, percent: ?float, percent_cap: ?float, already_taken: float, available: ?float}
     */
    public function limit(User $employee, LoanCategory $category, string $month, ?int $ignoreLoanId = null): array
    {
        $gross = $this->monthlyGross($employee);
        $taken = (float) Loan::withoutGlobalScope('tenant')->where('tenant_id', $employee->tenant_id)
            ->where('user_id', $employee->id)->where('loan_kind', Loan::KIND_SALARY_ADVANCE)
            ->where('advance_month', $month)
            ->whereIn('status', [Loan::STATUS_PENDING, Loan::STATUS_APPROVED, Loan::STATUS_ACTIVE, Loan::STATUS_CLOSED])
            ->when($ignoreLoanId, fn ($q) => $q->where('id', '!=', $ignoreLoanId))
            ->sum('amount');

        $percent = $category->max_percent_of_gross !== null ? (float) $category->max_percent_of_gross : null;
        $percentCap = $percent !== null && $gross !== null ? round($gross * $percent / 100, 2) : null;
        $categoryMax = $category->max_amount !== null && (float) $category->max_amount > 0 ? (float) $category->max_amount : null;

        $caps = array_filter([$categoryMax, $percentCap], fn ($v) => $v !== null);
        $available = $caps ? round(max(0, min($caps) - $taken), 2) : null;

        return ['gross' => $gross, 'category_max' => $categoryMax, 'percent' => $percent, 'percent_cap' => $percentCap,
            'already_taken' => round($taken, 2), 'available' => $available];
    }

    /** Why this advance cannot be requested, or null when it can. */
    public function validate(User $employee, LoanCategory $category, float $amount, string $month, ?int $ignoreLoanId = null): ?string
    {
        if (($category->kind ?? 'loan') !== Loan::KIND_SALARY_ADVANCE || ! $category->status) {
            return 'Choose an active Salary Advance category.';
        }
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
            return 'Choose the salary month the advance is for.';
        }
        $first = Carbon::now()->format('Y-m');
        $last = Carbon::now()->startOfMonth()->addMonthsNoOverflow(self::MONTHS_AHEAD)->format('Y-m');
        if ($month < $first || $month > $last) {
            return 'A salary advance can be taken for ' . Carbon::parse($first . '-01')->format('M Y')
                . ' to ' . Carbon::parse($last . '-01')->format('M Y') . ' only.';
        }
        if (! $this->monthOpen((int) $employee->tenant_id, (int) $employee->id, $month)) {
            return 'Payroll for ' . Carbon::parse($month . '-01')->format('M Y') . ' is already processed or locked — choose a later month.';
        }
        if ($amount <= 0) {
            return 'Enter the advance amount.';
        }

        $limit = $this->limit($employee, $category, $month, $ignoreLoanId);
        if ($category->max_percent_of_gross !== null && $limit['gross'] === null) {
            return 'This employee has no salary structure, so the advance limit cannot be worked out. Assign a salary first.';
        }
        if ($limit['available'] !== null && $amount > $limit['available']) {
            return 'The most that can be advanced for ' . Carbon::parse($month . '-01')->format('M Y') . ' is ₹'
                . number_format($limit['available'], 2)
                . ($limit['already_taken'] > 0 ? ' (₹' . number_format($limit['already_taken'], 2) . ' already taken for that month)' : '') . '.';
        }

        $leaving = DB::table('offboarding_requests')->where('tenant_id', $employee->tenant_id)->where('employee_id', $employee->id)
            ->whereIn('status', ['pending_approval', 'approved'])->whereNull('deleted_at')
            ->where('last_working_date', '<', $month . '-01')->value('last_working_date');
        if ($leaving) {
            return 'The employee\'s last working day is ' . Carbon::parse($leaving)->format('d M Y') . ', before that salary month.';
        }

        return null;
    }

    // -------------------------------------------------------------- lifecycle

    /**
     * Create the advance. $approveNow (admin / HR raising it, or a category that
     * needs no approval) saves it APPROVED with its repayment row.
     */
    public function create(User $employee, LoanCategory $category, float $amount, string $month, string $purpose,
        ?string $description, ?User $raisedBy = null, bool $approveNow = false): Loan
    {
        $actor = $raisedBy ?? $employee;
        $approved = $approveNow || ! $category->requires_approval;

        $loan = Loan::create([
            'tenant_id' => $employee->tenant_id,
            'user_id' => $employee->id,
            'created_by' => $raisedBy?->id,
            'loan_type_id' => $category->id,
            'loan_kind' => Loan::KIND_SALARY_ADVANCE,
            'advance_month' => $month,
            'repayment_type' => Loan::REPAYMENT_TYPE_LUMPSUM,
            'amount' => round($amount, 2),
            'processing_fee' => 0,
            'total_payable' => round($amount, 2),
            'interest_rate' => 0,
            'tenure_months' => 1,
            'emi_amount' => 0,
            'remaining_amount' => round($amount, 2),
            'loan_date' => now()->format('Y-m-d'),
            'lumpsum_due_date' => Carbon::parse($month . '-01')->endOfMonth()->toDateString(),
            'lumpsum_amount' => round($amount, 2),
            'purpose' => $purpose,
            'description' => $description,
            'status' => $approved ? Loan::STATUS_APPROVED : Loan::STATUS_PENDING,
            'approved_by' => $approved ? $actor->id : null,
            'approved_at' => $approved ? now() : null,
        ]);
        $loan->refresh(); // loan_number comes from the DB trigger

        if ($approved) {
            $this->scheduleRow($loan);
        }

        $this->log($raisedBy ? 'salary_advance.created_on_behalf' : 'salary_advance.requested', $loan, $actor, [
            'status' => $loan->status, 'raised_by' => $raisedBy?->name,
        ]);

        return $loan;
    }

    /** The single repayment row: the advance month, the full amount, no interest. */
    public function scheduleRow(Loan $loan): void
    {
        LoanRepayment::withoutGlobalScope('tenant')->where('loan_id', $loan->id)->where('paid_amount', '<=', 0)->delete();
        if (LoanRepayment::withoutGlobalScope('tenant')->where('loan_id', $loan->id)->exists()) {
            return; // something was already collected — never rebuild
        }

        $due = Carbon::parse($loan->advance_month . '-01')->endOfMonth()->toDateString();
        LoanRepayment::create([
            'tenant_id' => $loan->tenant_id,
            'loan_id' => $loan->id,
            'repayment_number' => $loan->loan_number . '-A1',
            'installment_number' => 1,
            'month' => $loan->advance_month,
            'due_date' => $due,
            'emi_amount' => $loan->amount,
            'principal_amount' => $loan->amount,
            'interest_amount' => 0,
            'total_amount' => $loan->amount,
            'paid_amount' => 0,
            'status' => LoanRepayment::STATUS_PENDING,
        ]);
        $loan->lumpsum_due_date = $due;
        $loan->save();
    }

    /**
     * On approve / disburse: an advance whose month's payroll is already
     * processed / paid / locked moves to the next open month (its repayment
     * row with it). Returns a notice for the person approving / paying, or null.
     */
    public function ensureRecoverableMonth(Loan $loan, User $actor): ?string
    {
        $tenantId = (int) $loan->tenant_id;
        $userId = (int) $loan->user_id;
        $month = (string) $loan->advance_month;

        if ($this->monthOpen($tenantId, $userId, $month)) {
            $pendingSlip = DB::table('monthly_payrolls')->where('tenant_id', $tenantId)->where('user_id', $userId)
                ->where('payroll_month', $month)->where('payment_status', 'pending')->exists();

            return $pendingSlip
                ? 'The ' . Carbon::parse($month . '-01')->format('M Y') . ' payslip is already generated — regenerate it (or edit and save it) to include this advance.'
                : null;
        }

        $next = Carbon::parse($month . '-01');
        $guard = 0;
        do {
            $next->addMonthNoOverflow();
            $candidate = $next->format('Y-m');
        } while (! $this->monthOpen($tenantId, $userId, $candidate) && ++$guard < 24);

        $loan->advance_month = $candidate;
        $loan->save();
        if (LoanRepayment::withoutGlobalScope('tenant')->where('loan_id', $loan->id)->where('paid_amount', '>', 0)->doesntExist()) {
            $this->scheduleRow($loan);
        }

        $this->log('salary_advance.month_moved', $loan, $actor, ['from' => $month, 'to' => $candidate]);

        return 'Payroll for ' . Carbon::parse($month . '-01')->format('M Y') . ' is already processed, so this advance will be deducted from the '
            . Carbon::parse($candidate . '-01')->format('M Y') . ' salary instead.';
    }

    // ------------------------------------------------------------------ audit

    /** Audit + (for the employee) a notification on the lifecycle steps of a loan OR an advance. */
    public function log(string $action, Loan $loan, User $actor, array $extra = []): void
    {
        $this->audit->record('tenant_user', $actor->id, (int) $loan->tenant_id, $action, 'Loan', (int) $loan->id, [], [
            'loan_number' => $loan->loan_number,
            'kind' => $loan->loan_kind ?? Loan::KIND_LOAN,
            'advance_month' => $loan->advance_month,
            'amount' => (string) $loan->amount,
            'employee_id' => $loan->user_id,
            'on_behalf_of' => $loan->user_id,
        ] + $extra);
    }

    public function notify(Loan $loan, string $title, string $body): void
    {
        try {
            $employee = User::withoutGlobalScopes()->find($loan->user_id);
            if ($employee) {
                app(NotificationService::class)->sendToUser($employee, $title, $body,
                    ['type' => 'loan_status', 'loan_id' => (string) $loan->id]);
            }
        } catch (\Throwable $e) {
            // never block on notification
        }
    }
}
