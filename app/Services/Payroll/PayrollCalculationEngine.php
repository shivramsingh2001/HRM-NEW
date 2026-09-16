<?php

namespace App\Services\Payroll;

use App\Models\PayrollEmployeeStructure;
use App\Models\StatutoryPtSlab;
use App\Models\StatutoryRateConfig;
use App\Models\Tenant;
use App\Models\User;
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

    public function calculate(User $employee, int $tenantId, string $yearMonth, bool $includeLoanDeductions = true): array
    {
        $monthEnd = Carbon::createFromFormat('Y-m', $yearMonth)->endOfMonth()->toDateString();

        $structure = PayrollEmployeeStructure::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->forUser($employee->id)
            ->effective($monthEnd)
            ->orderByDesc('effective_from')
            ->first();

        if (! $structure) {
            throw new RuntimeException("No dynamic payroll structure found for user {$employee->id} effective {$monthEnd}.");
        }

        $components = $structure->components()
            ->with(['component', 'baseComponent'])
            ->enabled()
            ->get()
            ->filter(fn ($ec) => $ec->component && $ec->component->is_active)
            ->sortBy(fn ($ec) => $ec->component->priority)
            ->values();

        $context = $this->contextBuilder->build($employee->id, $tenantId, $yearMonth);
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
            'employer_contributions_total' => round($employerContributions, 2),
            'net_payable' => $netPayable,
            'line_items' => $lineItems,
            'context' => $context,
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
            return $this->prorate($override, $master->proration_rule, $context);
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

        return $this->prorate($raw, $master->proration_rule, $context);
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
        if ($ec->calculation_base_type === 'component' && $ec->calculation_base_component_id && $ec->baseComponent) {
            return $resolvedSoFar[$ec->baseComponent->code] ?? 0.0;
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
