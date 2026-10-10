<?php

namespace App\Services\Payroll;

use App\Models\PayrollEmployeeStructure;
use App\Models\StatutoryPtSlab;
use App\Models\StatutoryRateConfig;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Expense\ExpenseReimbursementPayrollService;
use Carbon\Carbon;
use RuntimeException;

/**
 * Payroll rebuild — Phase 2 dynamic calculation engine.
 *
 * Computes a full payslip for one employee for one month from the dynamic
 * catalog (PayrollComponentMaster + PayrollEmployeeComponent), entirely
 * read-only — it persists nothing itself. Compute-only "shadow mode" use
 * (via PayrollEngineDiff) is still available for parity-checking before a
 * cutover, but this class IS live: MonthlyPayrollController::store() calls
 * it directly for any tenant with tenants.payroll_dynamic_ui_enabled = 1
 * (verified 2026-09-16, corrected here after this docblock was found to
 * still claim otherwise — see the Payroll Audit's C9 finding and
 * docs/payroll-dynamic-engine-migration.md for the full cutover story).
 *
 * Two-pass resolution ordered by each component's catalog priority:
 *   Pass 1 resolves earnings + employer_contributions. A percentage
 *   component based on another component reads that component's
 *   already-resolved value — guaranteed available because
 *   PayrollComponentMaster enforces (at save time) that a component's
 *   priority is strictly greater than whatever it's based on.
 *   Pass 2 resolves deductions, whose calculation_base of 'gross_pass1'
 *   reads the now-finalized sum of pass 1's earnings.
 *
 * Each component prorates using its OWN proration_rule against the shared
 * attendance context — not one blanket factor applied to everything, which
 * is the central limitation of the legacy fixed-column engine this
 * replaces.
 */
class PayrollCalculationEngine
{
    public function __construct(private PayrollAttendanceContextBuilder $contextBuilder)
    {
    }

    public function calculate(User $employee, int $tenantId, string $yearMonth, bool $includeLoanDeductions = true, ?array $dayOverrides = null): array
    {
        $monthEnd = Carbon::createFromFormat('Y-m', $yearMonth)->endOfMonth()->toDateString();

        [$structure, $components] = $this->loadStructureAndComponents($employee, $tenantId, $monthEnd);

        if (! $structure) {
            throw new RuntimeException("No dynamic payroll structure found for user {$employee->id} effective {$monthEnd}.");
        }

        $context = $this->contextBuilder->build($employee->id, $tenantId, $yearMonth, $dayOverrides);
        $context['ctc'] = (float) $structure->ctc;
        $context['tenant_id'] = $tenantId;
        $context['month_end_date'] = $monthEnd;
        $context['state_code'] = Tenant::withoutGlobalScopes()->whereKey($tenantId)->value('state');

        $resolved = []; // code => resolved amount
        $lineItems = [];

        // Pass 1: earnings + employer_contributions
        foreach ($components as $ec) {
            $type = $ec->component->component_type;
            if (! in_array($type, ['earning', 'employer_contribution', 'reimbursement'], true)) {
                continue;
            }

            $amount = $this->resolveComponent($ec, $resolved, null, $context);
            $resolved[$ec->component->code] = $amount;
            $lineItems[] = $this->lineItem($ec, $amount);
        }

        $grossPass1 = collect($lineItems)
            ->where('component_type', 'earning')
            ->sum('amount');

        // Pass 2: deductions, now that gross_pass1 is finalized
        foreach ($components as $ec) {
            if ($ec->component->component_type !== 'deduction') {
                continue;
            }

            $amount = $this->resolveComponent($ec, $resolved, $grossPass1, $context);
            $resolved[$ec->component->code] = $amount;
            $lineItems[] = $this->lineItem($ec, $amount);
        }

        // Payroll rebuild — Phase 6: outstanding arrears and this month's
        // approved bonuses are added as extra earning line items, AFTER
        // gross_pass1/deductions are finalized — they deliberately do not
        // feed statutory deduction bases (PF/ESI/PT), matching the common
        // practice of treating them separately from regular monthly gross
        // for those calculations. This is a compute-only read: nothing here
        // marks an arrears/bonus row as consumed — that happens only when a
        // real payroll run persists results (Phase 8), so calculate() stays
        // safe to call repeatedly for previews/diffs without side effects.
        foreach ($this->pendingArrears($tenantId, $employee->id) as $row) {
            $lineItems[] = [
                'code' => 'arrears',
                'name' => 'Arrears',
                'component_type' => 'earning',
                'calculation_method' => 'fixed_amount',
                'amount' => round((float) $row->arrears_amount, 2),
                'is_taxable' => true,
                'source_id' => $row->id,
            ];
        }

        foreach ($this->approvedBonuses($tenantId, $employee->id, $yearMonth) as $row) {
            $lineItems[] = [
                'code' => 'bonus',
                'name' => $row->name,
                'component_type' => 'earning',
                'calculation_method' => 'fixed_amount',
                'amount' => round((float) $row->amount, 2),
                'is_taxable' => (bool) $row->is_taxable,
                'source_id' => $row->id,
            ];
        }

        // Expense reimbursements routed through payroll (per company, behind the expense_payroll_link
        // feature — the service returns nothing when it is off and nothing is linked). One line per
        // expense. component_type MUST be 'earning': only earning/deduction feed gross_earnings and
        // net_payable, and payroll_components.component_type cannot store 'reimbursement'. Not taxable
        // (it repays money the employee spent) and, like arrears, deliberately not a statutory base.
        // Compute-only: linking to the payslip happens when a payslip is actually persisted
        // (MonthlyPayrollController -> ExpenseReimbursementPayrollService::applyToPayroll).
        foreach (app(ExpenseReimbursementPayrollService::class)->linesFor($tenantId, $employee->id, $yearMonth) as $row) {
            $lineItems[] = [
                'code' => ExpenseReimbursementPayrollService::CODE,
                'name' => ExpenseReimbursementPayrollService::lineName($row->expense_number),
                'component_type' => 'earning',
                'calculation_method' => 'fixed_amount',
                'amount' => $row->amount,
                'is_taxable' => false,
                'source_id' => $row->expense_id,
            ];
        }

        // Overtime (Company Policies → Overtime): approved hours — requests, automatic entries and
        // 2nd-shift hours — priced like the legacy engine (hourly rate from the raw basic, × multiplier
        // or a fixed amount per hour). Added after the statutory pass, like arrears, so it is not a
        // PF/ESI/PT base. Ineligible employees already arrive with 0 hours from the context.
        $overtimeHours = (float) ($context['approved_overtime_hours'] ?? 0);
        $overtimeRate = 0.0;
        $overtimeAmount = 0.0;
        if ($overtimeHours > 0) {
            $overtimeRate = app(OvertimePayService::class)->ratePerHour($tenantId, (int) $employee->id,
                $this->overtimeHourlyRate($employee, $tenantId, $yearMonth));
            $overtimeAmount = round($overtimeHours * $overtimeRate, 2);
            if ($overtimeAmount > 0) {
                $lineItems[] = [
                    'code' => 'overtime',
                    'name' => 'Overtime',
                    'component_type' => 'earning',
                    'calculation_method' => 'system_computed',
                    'amount' => $overtimeAmount,
                    'is_taxable' => true,
                ];
            }
        }

        // Shift allowance (Shifts → allowance per day / per hour) for the shifts actually worked —
        // same placement as overtime: taxable, not a PF/ESI/PT base.
        $shiftAllowance = app(ShiftAllowanceCalculator::class)->calculate($tenantId, (int) $employee->id, $yearMonth);
        if ($shiftAllowance['amount'] > 0) {
            $lineItems[] = [
                'code' => 'shift_allowance',
                'name' => $shiftAllowance['label'],
                'component_type' => 'earning',
                'calculation_method' => 'system_computed',
                'amount' => $shiftAllowance['amount'],
                'is_taxable' => true,
            ];
        }

        // Loan deduction is folded in as a real deduction line item (rather
        // than subtracted separately at the end) so total_deductions and
        // net_payable stay consistent with each other -- previously
        // total_deductions excluded the loan amount while net_payable
        // included it. $includeLoanDeductions=false zeroes the applied
        // amount here (not just skipping the ledger update afterward, which
        // is what the caller used to do), while loan_deduction_due always
        // reflects what's actually owed, for display regardless of the flag.
        $loanDeductionDue = (float) $context['loan_deduction_amount'];
        $appliedLoanDeduction = $includeLoanDeductions ? $loanDeductionDue : 0.0;

        // Salary advance against this month (Loans & Advances) — its own line, ahead of the loan EMIs
        // (the caller caps both at net pay, advance first). Same include toggle as loans.
        $advanceDue = (float) ($context['salary_advance_deduction_amount'] ?? 0);
        $appliedAdvance = $includeLoanDeductions ? $advanceDue : 0.0;

        if ($appliedAdvance > 0) {
            $lineItems[] = [
                'code' => 'salary_advance_deduction',
                'name' => 'Salary Advance Deduction',
                'component_type' => 'deduction',
                'calculation_method' => 'fixed_amount',
                'amount' => round($appliedAdvance, 2),
                'is_taxable' => false,
            ];
        }

        if ($appliedLoanDeduction > 0) {
            $lineItems[] = [
                'code' => 'loan_deduction',
                'name' => 'Loan Deduction',
                'component_type' => 'deduction',
                'calculation_method' => 'fixed_amount',
                'amount' => round($appliedLoanDeduction, 2),
                'is_taxable' => false,
            ];
        }

        // Late Arrival / Early Leaving deduction — a real deduction amount
        // (excess_days * daily_rate * multiplier), computed independently of
        // whether the tenant's attendance-status action is even enabled (see
        // App\Services\Payroll\LateEarlyDeductionCalculator). Injected as a
        // hand-added line item, same pattern as loan_deduction above — not a
        // seeded payroll_components catalog row.
        $lateEarly = app(\App\Services\Payroll\LateEarlyDeductionCalculator::class)
            ->calculate($employee, $tenantId, $yearMonth);

        if ($lateEarly['late_deduction_amount'] > 0) {
            $lineItems[] = [
                'code' => 'late_deduction',
                'name' => 'Late Arrival Deduction',
                'component_type' => 'deduction',
                'calculation_method' => 'system_computed',
                'amount' => round($lateEarly['late_deduction_amount'], 2),
                'is_taxable' => false,
            ];
        }
        if ($lateEarly['early_deduction_amount'] > 0) {
            $lineItems[] = [
                'code' => 'early_deduction',
                'name' => 'Early Leaving Deduction',
                'component_type' => 'deduction',
                'calculation_method' => 'system_computed',
                'amount' => round($lateEarly['early_deduction_amount'], 2),
                'is_taxable' => false,
            ];
        }

        $grossEarnings = collect($lineItems)->where('component_type', 'earning')->sum('amount');
        $totalDeductions = collect($lineItems)->where('component_type', 'deduction')->sum('amount');
        $employerContributions = collect($lineItems)->where('component_type', 'employer_contribution')->sum('amount');

        $netPayable = round($grossEarnings - $totalDeductions, 2);

        return [
            'engine_version' => 'dynamic_v1',
            'user_id' => $employee->id,
            'tenant_id' => $tenantId,
            'year_month' => $yearMonth,
            'payroll_employee_structure_id' => $structure->id,
            'ctc' => (float) $structure->ctc,
            'gross_earnings' => round($grossEarnings, 2),
            'total_deductions' => round($totalDeductions, 2),
            'loan_deduction_due' => round($loanDeductionDue, 2),
            'loan_deduction' => round($appliedLoanDeduction, 2),
            'salary_advance_deduction_due' => round($advanceDue, 2),
            'salary_advance_deduction' => round($appliedAdvance, 2),
            'overtime_hours' => round($overtimeHours, 2),
            'overtime_rate' => $overtimeRate,
            'overtime_amount' => $overtimeAmount,
            'shift_allowance_amount' => $shiftAllowance['amount'],
            'shift_allowance_days' => $shiftAllowance['days'],
            'late_deduction' => round($lateEarly['late_deduction_amount'], 2),
            'early_deduction' => round($lateEarly['early_deduction_amount'], 2),
            'employer_contributions_total' => round($employerContributions, 2),
            'net_payable' => $netPayable,
            'line_items' => $lineItems,
            'context' => $context,
        ];
    }

    /**
     * The single reusable "what components apply to this employee, with
     * computed amounts" resolver — built from calculate()'s own line_items,
     * not a re-derivation, so the Monthly Payroll Edit dynamic partial, the
     * salary slip, and any future consumer all agree with calculate() by
     * construction instead of drifting via independent re-implementations.
     * Zero/unused components are simply whatever calculate() didn't include
     * (disabled/unselected components never appear in $components at all —
     * see the enabled() filter in calculate()); callers that also want to
     * hide zero-*amount* rows (e.g. a component enabled but computing to 0)
     * should filter the returned arrays themselves.
     */
    public function resolveEmployeeComponents(User $employee, int $tenantId, string $yearMonth, ?array $dayOverrides = null): array
    {
        $result = $this->calculate($employee, $tenantId, $yearMonth, true, $dayOverrides);

        $grouped = collect($result['line_items'])->groupBy('component_type');

        return [
            'earnings' => $grouped->get('earning', collect())->values()->all(),
            'deductions' => $grouped->get('deduction', collect())->values()->all(),
            'employer_contributions' => $grouped->get('employer_contribution', collect())->values()->all(),
            'reimbursements' => $grouped->get('reimbursement', collect())->values()->all(),
        ];
    }

    /** All 'pending' arrears for this employee, regardless of which historical month they're from — paid out in the next run computed. */
    private function pendingArrears(int $tenantId, int $userId)
    {
        return \App\Models\PayrollArrears::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->where('status', 'pending')
            ->get();
    }

    /** Approved bonuses explicitly targeted at this month (via their payroll_period), if any period has been created for it yet. */
    private function approvedBonuses(int $tenantId, int $userId, string $yearMonth)
    {
        $period = \App\Models\PayrollPeriod::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->where('year_month', $yearMonth)
            ->first();

        if (! $period) {
            return collect();
        }

        return \App\Models\PayrollBonus::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->where('status', 'approved')
            ->where('target_payroll_period_id', $period->id)
            ->get();
    }

    private function lineItem($ec, float $amount): array
    {
        return [
            'code' => $ec->component->code,
            'name' => $ec->component->name,
            'component_type' => $ec->component->component_type,
            'calculation_method' => $ec->calculation_method,
            'amount' => round($amount, 2),
            'is_taxable' => $ec->component->is_taxable,
        ];
    }

    /**
     * Resolve one component's rupee amount: base -> raw amount -> wage
     * ceiling -> proration. $resolvedSoFar is the pass-1 map (code =>
     * amount); $grossPass1 is null during pass 1 and the finalized gross
     * during pass 2.
     */
    private function resolveComponent($ec, array $resolvedSoFar, ?float $grossPass1, array $context): float
    {
        return $this->prorate(
            $this->resolveComponentRaw($ec, $resolvedSoFar, $grossPass1, $context),
            $ec->component->proration_rule,
            $context
        );
    }

    /**
     * Same as resolveComponent() minus the final proration step — used by
     * resolveComponent() itself and by rawComponentAmount() (which needs the
     * stable, un-prorated amount for daily-rate math, e.g.
     * LateEarlyDeductionCalculator).
     */
    private function resolveComponentRaw($ec, array $resolvedSoFar, ?float $grossPass1, array $context): float
    {
        $master = $ec->component;

        $base = $this->resolveBase($ec, $resolvedSoFar, $grossPass1, $context);

        // Statutory components (PF/ESI/PT) prefer the tenant's own versioned
        // Compliance Settings (statutory_rate_configs / statutory_pt_slabs)
        // over whatever is hardcoded on the component itself — this is what
        // makes the rate genuinely tenant/region-configurable and lets an
        // admin update it for every affected component in one place instead
        // of editing each component individually. Falls through to the
        // component's own stored fields when no active config/slab exists
        // yet, so tenants who haven't visited Compliance Settings keep
        // working exactly as before.
        $override = $this->statutoryOverride($master, $ec, $base, $grossPass1, $context);
        if ($override !== null) {
            return $override;
        }

        $raw = match ($ec->calculation_method) {
            'fixed_amount' => (float) ($ec->amount ?? 0),
            'percentage' => $base * ((float) ($ec->percentage_value ?? 0) / 100),
            default => 0.0,
        };

        if ($master->has_wage_ceiling && $master->ceiling_amount !== null) {
            if ($master->ceiling_apply_rule === 'ceiling_exclude') {
                // ESI-style: not applicable at all once the base exceeds the ceiling.
                if ($base > (float) $master->ceiling_amount) {
                    return 0.0;
                }
            } elseif ($master->ceiling_apply_rule === 'cap_base_before_percentage' && $ec->calculation_method === 'percentage') {
                // PF-style: recompute against the capped base, not the raw one.
                $cappedBase = min($base, (float) $master->ceiling_amount);
                $raw = $cappedBase * ((float) ($ec->percentage_value ?? 0) / 100);
            }
        }

        return $raw;
    }

    /**
     * Structure + enabled/active components for one employee/month — shared
     * by calculate() and rawComponentAmount() so both load identically.
     *
     * @return array{0: ?PayrollEmployeeStructure, 1: \Illuminate\Support\Collection}
     */
    private function loadStructureAndComponents(User $employee, int $tenantId, string $monthEnd): array
    {
        $structure = PayrollEmployeeStructure::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->forUser($employee->id)
            ->effective($monthEnd)
            ->orderByDesc('effective_from')
            ->first();

        if (! $structure) {
            return [null, collect()];
        }

        $components = $structure->components()
            ->with(['component', 'baseComponent', 'baseComponents'])
            ->enabled()
            ->get()
            ->filter(fn ($ec) => $ec->component && $ec->component->is_active)
            ->sortBy(fn ($ec) => $ec->component->priority)
            ->values();

        return [$structure, $components];
    }

    /**
     * Raw (pre-proration) monthly amount for one catalog component code —
     * used by LateEarlyDeductionCalculator to get a stable, un-prorated
     * 'basic' for daily-rate math (mirrors the legacy engine's use of the
     * full, unprorated UserPayroll::basic_salary for the same purpose in
     * MonthlyPayrollController::overtimeHourlyRate()). Returns 0.0 if the
     * employee has no dynamic structure or no such component — the caller
     * only reaches this after already confirming no legacy UserPayroll
     * exists, so 0.0 here is a real "nothing to compute from", not a masked
     * error.
     *
     * KNOWN LIMITATION: if the requested component's own
     * calculation_base_type is 'component' referencing another
     * not-yet-resolved component, this returns 0 for that dependency
     * ($resolvedSoFar starts empty here — no full Pass-1 run). In every
     * real catalog in this codebase 'basic' is the priority-first root
     * component, so this is a flagged theoretical edge case, not a silently
     * risked one.
     */
    /**
     * Base hourly rate for overtime on the dynamic engine — same inputs as
     * LateEarlyDeductionCalculator: the legacy UserPayroll + master when one
     * exists (basic, divisor mode, hours per day), else the raw 'basic'
     * component with calendar days and 8 hours a day.
     */
    private function overtimeHourlyRate(User $employee, int $tenantId, string $yearMonth): float
    {
        $month = Carbon::createFromFormat('Y-m', $yearMonth);
        $userPayroll = \App\Models\UserPayroll::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->forUser($employee->id)
            ->effective($month->copy()->endOfMonth()->toDateString())
            ->orderByDesc('effective_from')
            ->first();
        $master = $userPayroll?->payrollMaster;
        $basic = $userPayroll && $master
            ? (float) $userPayroll->basic_salary
            : $this->rawComponentAmount($employee, $tenantId, $yearMonth, 'basic');

        return app(OvertimePayService::class)->hourlyRate(
            $basic, 'day_based', (float) ($master->working_hours_per_day ?? 8), $month->daysInMonth, $month->daysInMonth,
            $master->ot_rate_divisor_mode ?? 'calendar_days', (int) ($master->ot_fixed_working_days ?? 26)
        );
    }

    public function rawComponentAmount(User $employee, int $tenantId, string $yearMonth, string $code): float
    {
        $monthEnd = Carbon::createFromFormat('Y-m', $yearMonth)->endOfMonth()->toDateString();
        [$structure, $components] = $this->loadStructureAndComponents($employee, $tenantId, $monthEnd);

        if (! $structure) {
            return 0.0;
        }

        $ec = $components->first(fn ($c) => $c->component->code === $code);
        if (! $ec) {
            return 0.0;
        }

        $context = [
            'ctc' => (float) $structure->ctc,
            'tenant_id' => $tenantId,
            'month_end_date' => $monthEnd,
            'state_code' => Tenant::withoutGlobalScopes()->whereKey($tenantId)->value('state'),
        ];

        return $this->resolveComponentRaw($ec, [], null, $context);
    }

    /**
     * Returns the fully-resolved (pre-proration) rupee amount for a
     * statutory component using Compliance Settings, or null to fall
     * through to the component's own configuration.
     */
    private function statutoryOverride($master, $ec, float $base, ?float $grossPass1, array $context): ?float
    {
        if (! $master->is_statutory || ! $master->statutory_type) {
            return null;
        }

        if (in_array($master->statutory_type, ['pf', 'esi'], true)) {
            $config = StatutoryRateConfig::withoutGlobalScope('tenant')
                ->where('tenant_id', $context['tenant_id'])
                ->activeOn($master->statutory_type, $context['month_end_date'])
                ->first();

            if (! $config) {
                return null;
            }

            $isEmployerSide = str_ends_with($master->code, '_employer') || $master->component_type === 'employer_contribution';
            $rate = (float) ($config->config[$isEmployerSide ? 'employer_rate' : 'employee_rate'] ?? 0);
            $ceiling = $config->config['wage_ceiling'] ?? null;

            if ($ceiling === null) {
                return $base * ($rate / 100);
            }

            // Respect the catalog component's own ceiling_apply_rule for HOW
            // the config's wage_ceiling is applied — the statutory config
            // only supplies the rate/ceiling numbers, not the strategy.
            if ($master->ceiling_apply_rule === 'ceiling_exclude') {
                // ESI-style: not applicable at all once base exceeds ceiling.
                return $base > (float) $ceiling ? 0.0 : $base * ($rate / 100);
            }

            // PF-style (default): cap the base, then apply the rate.
            $cappedBase = min($base, (float) $ceiling);

            return $cappedBase * ($rate / 100);
        }

        if ($master->statutory_type === 'pt') {
            if (! $context['state_code']) {
                return null;
            }

            // PT slabs are keyed by gross salary, not whatever base this
            // component's own calculation_base_type resolves to (typically
            // 'none' for a flat/manual PT component) — gross_pass1 is only
            // available in pass 2, which is exactly when deductions (PT
            // included) are resolved.
            $grossForSlab = $grossPass1 ?? 0.0;

            $slab = StatutoryPtSlab::withoutGlobalScope('tenant')
                ->where('tenant_id', $context['tenant_id'])
                ->forSalary($context['state_code'], $grossForSlab, $context['month_end_date'])
                ->first();

            // A slab table exists for this tenant+state but no band matched
            // (e.g. base is 0) -> genuinely ₹0 PT, not "unconfigured".
            $anySlabsForState = StatutoryPtSlab::withoutGlobalScope('tenant')
                ->where('tenant_id', $context['tenant_id'])
                ->where('state_code', $context['state_code'])
                ->exists();

            if (! $anySlabsForState) {
                return null;
            }

            return $slab ? (float) $slab->pt_amount : 0.0;
        }

        return null;
    }

    private function resolveBase($ec, array $resolvedSoFar, ?float $grossPass1, array $context): float
    {
        if ($ec->calculation_base_type === 'component') {
            $baseComponents = $ec->relationLoaded('baseComponents') ? $ec->baseComponents : $ec->baseComponents()->get();

            if ($baseComponents->isNotEmpty()) {
                return $baseComponents->sum(fn ($base) => $resolvedSoFar[$base->code] ?? 0.0);
            }

            // Legacy single-FK fallback for snapshots created before the
            // multi-base pivot existed (payroll_employee_component_bases).
            if ($ec->calculation_base_component_id && $ec->baseComponent) {
                return $resolvedSoFar[$ec->baseComponent->code] ?? 0.0;
            }

            return 0.0;
        }

        return match ($ec->calculation_base) {
            'basic' => $resolvedSoFar['basic'] ?? 0.0,
            'ctc' => $context['ctc'] ?? 0.0,
            'gross_pass1' => $grossPass1 ?? 0.0,
            default => 0.0,
        };
    }

    private function prorate(float $amount, string $rule, array $context): float
    {
        return match ($rule) {
            'prorate_by_payable_days' => $amount * $context['proration_by_payable_days'],
            'prorate_by_lop_days' => $amount * $context['proration_by_lop_days'],
            'prorate_by_worked_hours' => $context['calendar_days'] > 0
                ? $amount * ($context['actual_worked_hours'] / max(1, $context['calendar_days'] * 8))
                : 0.0,
            'no_proration' => $amount,
            default => $amount,
        };
    }
}
