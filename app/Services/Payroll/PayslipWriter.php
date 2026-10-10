<?php

namespace App\Services\Payroll;

use App\Models\MonthlyPayroll;
use App\Models\PayrollComponent;
use Illuminate\Support\Facades\Auth;

/**
 * Writes payslips (monthly_payrolls + payroll_components + loan ledger +
 * linked reimbursements / arrears / bonuses) for both payroll engines — moved
 * out of MonthlyPayrollController unchanged (code-quality plan, Phase 1).
 *
 *  - createLegacy(): a LegacyPayrollCalculator::calculate() result.
 *  - createDynamic(): runs PayrollCalculationEngine and saves the result.
 *  - applyDynamic(): rewrites an existing dynamic payslip after Edit Payroll.
 *
 * Callers own the transaction.
 */
class PayslipWriter
{
    public function __construct(private PayrollLoanApplier $loans) {}

    /** Persist a LegacyPayrollCalculator::calculate() result for one employee. */
    public function createLegacy(array $calc, $employee, string $yearMonth, ?int $tenantId, bool $includeLoanDeductions): MonthlyPayroll
    {
        // Eloquent create() auto-sets tenant_id via BelongsToTenant.
        $monthlyPayroll = MonthlyPayroll::create($calc['columns']);

        $this->savePayrollComponents($monthlyPayroll->id, $calc['earnings'], $calc['employee_deductions'], $calc['employer_contributions'],
            $calc['earning_names']);

        // Mark loan repayments paid -- NO inner transaction; we are already inside one
        if ($includeLoanDeductions && $calc['advance_deduction'] > 0) {
            $this->loans->updateLoanRepayments($employee->id, $yearMonth, $calc['advance_deduction'], $tenantId, $monthlyPayroll->id, \App\Models\Loan::KIND_SALARY_ADVANCE);
        }
        if ($includeLoanDeductions && $calc['loan_deduction'] > 0) {
            $this->loans->updateLoanRepayments($employee->id, $yearMonth, $calc['loan_deduction'], $tenantId, $monthlyPayroll->id);
        }

        return $monthlyPayroll;
    }

    /**
     * Computes via PayrollCalculationEngine (the Phase 2 dynamic engine)
     * instead of this controller's legacy fixed-column math, but persists
     * into the exact same monthly_payrolls columns (plus payroll_run_id /
     * engine_version) so every existing payslip view, PDF, and export
     * keeps rendering unchanged. Only reached for a tenant flagged onto the
     * dynamic engine (see store()); never called on the legacy path.
     */
    public function createDynamic(
        $employee,
        ?int $tenantId,
        string $yearMonth,
        ?int $payrollRunId,
        bool $includeLoanDeductions = true
    ) {
        $result = app(\App\Services\Payroll\PayrollCalculationEngine::class)
            ->calculate($employee, $tenantId, $yearMonth, $includeLoanDeductions);

        // Same auto-cap policy as the legacy path: bulk generation has no
        // per-employee UI to interactively resolve a shortfall, so cap the
        // applied deductions at what's actually available rather than
        // allowing a negative net pay — the SALARY ADVANCE first, then the
        // loan EMIs out of what's left (the rest stays due for next month).
        $this->loans->capDynamicLoanAndAdvance($result, $employee->id, $yearMonth);

        $monthlyPayroll = MonthlyPayroll::create([
            'user_id' => $employee->id,
            'employee_payroll_id' => optional($employee->currentPayroll)->id, // may be null -- a dynamic-only assignment has no legacy UserPayroll row
            'payroll_run_id' => $payrollRunId,
            'engine_version' => 'dynamic_v1',
            'payroll_month' => $yearMonth,
            'processing_date' => now(),
        ] + $this->dynamicColumns($result, $includeLoanDeductions) + [
            'payment_status' => 'pending',
            'processed_by' => Auth::id(),
            'remarks' => 'Processed via dynamic payroll engine (Phase 8).',
        ]);

        $this->writeDynamicComponents($monthlyPayroll->id, $result);

        if ($includeLoanDeductions && ($result['salary_advance_deduction'] ?? 0) > 0) {
            $this->loans->updateLoanRepayments($employee->id, $yearMonth, $result['salary_advance_deduction'], $tenantId, $monthlyPayroll->id, \App\Models\Loan::KIND_SALARY_ADVANCE);
        }
        if ($includeLoanDeductions && $result['loan_deduction'] > 0) {
            $this->loans->updateLoanRepayments($employee->id, $yearMonth, $result['loan_deduction'], $tenantId, $monthlyPayroll->id, \App\Models\Loan::KIND_LOAN);
        }

        // Expense reimbursements the engine put on this payslip: link them to it (so a recalculation re-reads exactly these).
        $this->linkExpenseReimbursements($monthlyPayroll, $result);

        // Now that this payslip is actually persisted, close the loop from
        // Phase 6: mark whatever arrears/bonus rows it just paid out.
        \App\Models\PayrollArrears::where('tenant_id', $tenantId)
            ->where('user_id', $employee->id)
            ->where('status', 'pending')
            ->update(['status' => 'included_in_payroll', 'target_monthly_payroll_id' => $monthlyPayroll->id]);

        $period = \App\Models\PayrollPeriod::where('tenant_id', $tenantId)->where('year_month', $yearMonth)->first();
        if ($period) {
            \App\Models\PayrollBonus::where('tenant_id', $tenantId)
                ->where('user_id', $employee->id)
                ->where('status', 'approved')
                ->where('target_payroll_period_id', $period->id)
                ->update(['status' => 'included_in_payroll']);
        }

        return $monthlyPayroll;
    }

    /**
     * Rewrite an existing dynamic payslip from a fresh
     * PayrollCalculationEngine::calculate() result (Edit Payroll) — the same
     * columns and line items createDynamic() writes.
     */
    public function applyDynamic(MonthlyPayroll $monthlyPayroll, array $result, bool $includeLoanDeductions): void
    {
        $monthlyPayroll->fill($this->dynamicColumns($result, $includeLoanDeductions));
        $monthlyPayroll->save();

        // Replace the per-line-item log so the payslip/show view (which
        // iterates MonthlyPayroll->components) reflects the freshly
        // recomputed breakdown, not the stale set from before this edit.
        PayrollComponent::where('monthly_payroll_id', $monthlyPayroll->id)->delete();
        $this->writeDynamicComponents($monthlyPayroll->id, $result);

        // Advance and loan EMIs synced separately (each undoes only its own earlier share; 0 = just undo).
        $this->loans->updateLoanRepayments($monthlyPayroll->user_id, $monthlyPayroll->payroll_month,
            $includeLoanDeductions ? (float) ($result['salary_advance_deduction'] ?? 0) : 0.0,
            $monthlyPayroll->tenant_id, $monthlyPayroll->id, \App\Models\Loan::KIND_SALARY_ADVANCE);
        $this->loans->updateLoanRepayments($monthlyPayroll->user_id, $monthlyPayroll->payroll_month,
            $includeLoanDeductions ? (float) $result['loan_deduction'] : 0.0,
            $monthlyPayroll->tenant_id, $monthlyPayroll->id, \App\Models\Loan::KIND_LOAN);

        $this->linkExpenseReimbursements($monthlyPayroll, $result);
    }

    /**
     * monthly_payrolls columns from a PayrollCalculationEngine result — the
     * day counts, overtime / shift allowance / loan / late-early figures,
     * totals, and the fixed salary columns the payslip views read (each from
     * its catalog component; 0 when the employee has no such component).
     */
    private function dynamicColumns(array $result, bool $includeLoanDeductions): array
    {
        $context = $result['context'];
        $earnings = collect($result['line_items'])->where('component_type', 'earning')->keyBy('code');
        $deductions = collect($result['line_items'])->where('component_type', 'deduction')->keyBy('code');
        $employer = collect($result['line_items'])->where('component_type', 'employer_contribution')->keyBy('code');

        $earningColumnMap = [
            'basic' => 'basic_salary', 'hra' => 'hra', 'conveyance' => 'conveyence',
            'medical_allowance' => 'medical_allowance', 'children_allowance' => 'children_allowance',
            'post_allowance' => 'post_allowance', 'leave_travel_allowance' => 'leave_travel_allowance',
            'monthly_incentive' => 'monthly_incentive', 'special_allowance' => 'special_allowance',
        ];
        $deductionColumnMap = [
            'pf_employee' => 'provident_fund', 'esi_employee' => 'esi',
            'pt' => 'professional_tax', 'tds' => 'tds',
        ];
        $employerColumnMap = [
            'pf_employer' => 'employer_provident_fund', 'esi_employer' => 'employer_esi',
        ];

        $columns = [
            'total_working_days' => (int) round($context['calendar_days']),
            'payable_days' => $context['payable_days'],
            'present_days' => (int) round($context['present_days']),
            'half_days' => $context['half_days'],
            'absent_days' => (int) round($context['absent_days']),
            'paid_leaves' => $context['paid_leave_days'],
            'unpaid_leaves' => $context['unpaid_leave_days'],
            'holidays' => (int) round($context['holidays']),
            'week_offs' => (int) round($context['week_offs']),
            'overtime_hours' => $context['approved_overtime_hours'],
            'overtime_rate' => $result['overtime_rate'] ?? 0,
            'overtime_amount' => $result['overtime_amount'] ?? 0,
            'shift_allowance_amount' => round((float) optional($earnings->get('shift_allowance'))['amount'], 2),
            'shift_allowance_days' => $result['shift_allowance_days'] ?? 0,
            'actual_worked_hours' => $context['actual_worked_hours'],
            'loan_deduction' => $result['loan_deduction'],
            'loan_deduction_enabled' => $includeLoanDeductions,
            'loan_deduction_computed' => $result['loan_deduction_due'],
            'salary_advance_deduction' => $result['salary_advance_deduction'] ?? 0,
            'salary_advance_deduction_computed' => $result['salary_advance_deduction_due'] ?? 0,
            'late_deduction' => $result['late_deduction'] ?? 0,
            'early_deduction' => $result['early_deduction'] ?? 0,
            'gross_earnings' => $result['gross_earnings'],
            'total_deductions' => $result['total_deductions'],
            'net_payable' => $result['net_payable'],
        ];

        foreach ($earningColumnMap as $code => $column) {
            $columns[$column] = round((float) optional($earnings->get($code))['amount'], 2) ?: 0;
        }
        foreach ($deductionColumnMap as $code => $column) {
            $columns[$column] = round((float) optional($deductions->get($code))['amount'], 2) ?: 0;
        }
        foreach ($employerColumnMap as $code => $column) {
            $columns[$column] = round((float) optional($employer->get($code))['amount'], 2) ?: 0;
        }

        return $columns;
    }

    /**
     * Payslip line-item log — every component (including ones with no fixed
     * column, like arrears/bonus or a custom admin-created one) shows up via
     * the "additional components" section the payslip views render.
     * employer_contribution lines are skipped (the legacy component_type enum
     * only allows earning/deduction) since the employer_provident_fund /
     * employer_esi columns already carry them.
     */
    private function writeDynamicComponents(int $monthlyPayrollId, array $result): void
    {
        foreach ($result['line_items'] as $li) {
            if (! in_array($li['component_type'], ['earning', 'deduction', 'reimbursement'], true)) {
                continue;
            }

            PayrollComponent::create([
                'monthly_payroll_id' => $monthlyPayrollId,
                'component_name' => $li['name'],
                'component_type' => $li['component_type'] === 'deduction' ? 'deduction' : 'earning',
                'amount' => $li['amount'],
                'is_taxable' => $li['is_taxable'],
            ]);
        }
    }

    /**
     * Save payroll components to database
     * Separates earnings, employee deductions, and employer contributions
     */
    public function savePayrollComponents(int $monthlyPayrollId, array $earnings, array $employeeDeductions, array $employerContributions = [], array $earningNames = []): void
    {
        // Save earnings
        foreach ($earnings as $key => $amount) {
            // != 0, not > 0 -- a negative manual-adjustment component (e.g. a
            // clawback) must still get its own line-item row, since the
            // persisted totals (array_sum() over these same arrays) already
            // include it regardless. Skipping it here would leave the
            // line-item breakdown unable to reconcile to the persisted total.
            if ($amount != 0) {
                PayrollComponent::create([
                    'monthly_payroll_id' => $monthlyPayrollId,
                    'component_name' => $earningNames[$key] ?? $this->formatComponentName($key),
                    'component_type' => 'earning',
                    'amount' => $amount,
                    'is_taxable' => in_array($key, ['basic', 'hra', 'special', 'incentive', 'shift_allowance']),
                    'description' => $this->formatComponentName($key).' for the month',
                ]);
            }
        }

        // Save employee deductions (deducted from salary)
        foreach ($employeeDeductions as $key => $amount) {
            if ($amount != 0 && ! in_array($key, ['employer_pf', 'employer_esi'])) {
                PayrollComponent::create([
                    'monthly_payroll_id' => $monthlyPayrollId,
                    'component_name' => $this->getDeductionName($key),
                    'component_type' => 'deduction',
                    'amount' => $amount,
                    'is_taxable' => false,
                    'description' => $this->getDeductionName($key).' for the month',
                ]);
            }
        }

        // Save employer contributions (INFORMATIONAL only - NOT deducted)
        foreach ($employerContributions as $key => $amount) {
            if ($amount != 0) {
                PayrollComponent::create([
                    'monthly_payroll_id' => $monthlyPayrollId,
                    'component_name' => $this->getEmployerContributionName($key),
                    'component_type' => 'employer_contribution',
                    'amount' => $amount,
                    'is_taxable' => false,
                    'description' => $this->getEmployerContributionName($key).' for the month',
                ]);
            }
        }
    }

    // HELPERS

    private function getEmployerContributionName(string $key): string
    {
        return [
            'employer_pf' => 'Employer PF Contribution',
            'employer_esi' => 'Employer ESI Contribution',
        ][$key] ?? ucwords(str_replace('_', ' ', $key));
    }

    // =========================================================================

    private function formatComponentName(string $key): string
    {
        return [
            'basic' => 'Basic Salary',
            'hra' => 'HRA',
            'conveyence' => 'Conveyance Allowance',
            'medical' => 'Medical Allowance',
            'children' => 'Children Allowance',
            'post' => 'Post Allowance',
            'lta' => 'Leave Travel Allowance',
            'incentive' => 'Monthly Incentive',
            'special' => 'Special Allowance',
            'overtime' => 'Overtime',
            'shift_allowance' => 'Shift Allowance',
        ][$key] ?? ucwords(str_replace('_', ' ', $key));
    }

    // EARNINGS & DEDUCTIONS

    private function getDeductionName(string $key): string
    {
        return [
            'pf' => 'Provident Fund',
            'esi' => 'ESI',
            'pt' => 'Professional Tax',
            'tds' => 'TDS',
            'loan' => 'Loan Deduction',
            'advance' => 'Salary Advance Deduction',
            'late' => 'Late Arrival Deduction',
            'early' => 'Early Leaving Deduction',
            'other' => 'Other Deductions',
        ][$key] ?? ucwords(str_replace('_', ' ', $key));
    }

    /**
     * Attach the payslip's `expense_reimbursement` lines to their expenses (idempotent revoke-then-relink, so a
     * recalculation that no longer includes one lets it go). Same shape as the loan-ledger sync above.
     */
    private function linkExpenseReimbursements(MonthlyPayroll $monthlyPayroll, array $result): void
    {
        $ids = collect($result['line_items'])
            ->where('code', \App\Services\Expense\ExpenseReimbursementPayrollService::CODE)
            ->pluck('source_id')->filter()->all();

        app(\App\Services\Expense\ExpenseReimbursementPayrollService::class)->applyToPayroll((int) $monthlyPayroll->id, (int) $monthlyPayroll->tenant_id, $ids);
    }
}
