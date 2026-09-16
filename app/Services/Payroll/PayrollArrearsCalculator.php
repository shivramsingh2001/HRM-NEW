<?php

namespace App\Services\Payroll;

use App\Models\MonthlyPayroll;
use App\Models\PayrollArrears;
use App\Models\PayrollComponentMaster;
use App\Models\PayrollEmployeeStructure;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Payroll rebuild — Phase 6.
 *
 * When a salary revision's effective_from is backdated into a month that
 * was already processed and paid, this recomputes what the new structure
 * would have paid for each affected month and queues the delta as a
 * payroll_arrears row (positive = owed to the employee, negative = to be
 * recovered). Compares against the legacy engine's already-persisted
 * monthly_payrolls figures — the same "diff against real historical
 * truth" approach PayrollEngineDiff uses for parity testing — since that's
 * what was actually paid regardless of which engine produced it.
 *
 * Only reacts to a structure that is genuinely now current/active; never
 * runs for a draft/pending/rejected revision.
 */
class PayrollArrearsCalculator
{
    public function __construct(private PayrollCalculationEngine $engine)
    {
    }

    /**
     * @return Collection<PayrollArrears> the arrears rows created
     */
    public function computeForRevision(PayrollEmployeeStructure $structure, ?int $revisionLogId = null): Collection
    {
        $created = collect();

        if ($structure->status !== 'active' || ! $structure->is_current) {
            return $created;
        }

        $effectiveMonth = Carbon::parse($structure->effective_from)->startOfMonth();
        $currentMonth = now()->startOfMonth();

        if ($effectiveMonth->gte($currentMonth)) {
            return $created; // not backdated -- the next regular run just uses the new structure
        }

        $employee = User::withoutGlobalScopes()->find($structure->user_id);
        if (! $employee) {
            return $created;
        }

        $arrearsComponent = PayrollComponentMaster::withoutGlobalScope('tenant')
            ->where('tenant_id', $structure->tenant_id)
            ->where('code', 'arrears')
            ->first();

        $monthsChecked = 0;
        $monthsWithProcessedPayslip = 0;

        $cursor = $effectiveMonth->copy();
        while ($cursor->lt($currentMonth)) {
            $month = $cursor->format('Y-m');
            $monthsChecked++;

            $legacyPayslip = MonthlyPayroll::withoutGlobalScopes()
                ->where('user_id', $employee->id)
                ->where('payroll_month', $month)
                ->whereIn('payment_status', ['processed', 'paid'])
                ->first();

            if ($legacyPayslip) {
                $monthsWithProcessedPayslip++;
                $created->push($this->computeForMonth($structure, $employee, $month, $legacyPayslip, $arrearsComponent, $revisionLogId));
            }

            $cursor->addMonth();
        }

        $reconciled = $created->filter();

        // Distinguish "nothing to reconcile against yet" (every month in range
        // is still payment_status='pending' -- the common case if a tenant's
        // workflow never transitions past pending) from "reconciled, nothing
        // owed" (a processed/paid payslip existed for every month checked, and
        // the revised amount genuinely matched what was already paid) -- both
        // previously looked identical from the outside (an empty Collection).
        if ($monthsChecked > 0 && $monthsWithProcessedPayslip === 0) {
            \Illuminate\Support\Facades\Log::channel('daily')->warning(
                'Arrears reconciliation skipped -- no processed/paid payroll to reconcile against yet.',
                [
                    'tenant_id' => $structure->tenant_id,
                    'user_id' => $structure->user_id,
                    'structure_id' => $structure->id,
                    'months_checked' => $monthsChecked,
                ]
            );
        } else {
            \Illuminate\Support\Facades\Log::channel('daily')->info(
                'Arrears reconciliation completed.',
                [
                    'tenant_id' => $structure->tenant_id,
                    'user_id' => $structure->user_id,
                    'structure_id' => $structure->id,
                    'months_checked' => $monthsChecked,
                    'months_with_processed_payslip' => $monthsWithProcessedPayslip,
                    'arrears_rows_created' => $reconciled->count(),
                ]
            );
        }

        return $reconciled;
    }

    private function computeForMonth(
        PayrollEmployeeStructure $structure,
        User $employee,
        string $month,
        MonthlyPayroll $legacyPayslip,
        ?PayrollComponentMaster $arrearsComponent,
        ?int $revisionLogId = null
    ): ?PayrollArrears {
        // Already reconciled for this exact month? Don't double-queue —
        // covers a revision superseded by another before it was ever
        // included in a payroll run, and re-approving after a rollback.
        $existing = PayrollArrears::withoutGlobalScope('tenant')
            ->where('tenant_id', $structure->tenant_id)
            ->where('user_id', $employee->id)
            ->where('arrears_type', 'revision')
            ->where('period_from', $month)
            ->where('period_to', $month)
            ->exists();

        if ($existing) {
            return null;
        }

        try {
            $revised = $this->engine->calculate($employee, (int) $structure->tenant_id, $month);
        } catch (\Throwable $e) {
            // No dynamic structure resolvable for that historical month (e.g.
            // this employee's very first dynamic assignment) -- nothing to
            // reconcile against; skip rather than guess.
            return null;
        }

        $originalAmount = (float) $legacyPayslip->net_payable;
        $revisedAmount = (float) $revised['net_payable'];
        $delta = round($revisedAmount - $originalAmount, 2);

        if (abs($delta) < 0.01) {
            return null; // no material difference for this month
        }

        return PayrollArrears::create([
            'tenant_id' => $structure->tenant_id,
            'user_id' => $employee->id,
            'payroll_revision_log_id' => $revisionLogId,
            'component_id' => optional($arrearsComponent)->id,
            'arrears_type' => 'revision',
            'period_from' => $month,
            'period_to' => $month,
            'original_amount' => $originalAmount,
            'revised_amount' => $revisedAmount,
            'arrears_amount' => $delta,
            'status' => 'pending',
            'target_monthly_payroll_id' => $legacyPayslip->id,
            'created_by' => $structure->created_by,
        ]);
    }
}
