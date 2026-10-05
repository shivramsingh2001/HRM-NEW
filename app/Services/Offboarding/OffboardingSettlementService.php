<?php

namespace App\Services\Offboarding;

use App\Models\Loan;
use App\Models\OffboardingRequest;
use App\Models\OffboardingSettlementItem;
use App\Models\User;
use App\Models\UserExpenseBalance;
use App\Models\UserPayroll;
use App\Services\AuditLogger;
use App\Services\FeatureService;
use Illuminate\Support\Facades\DB;

/**
 * Builds and maintains the final-settlement worksheet. Kept separate from
 * OffboardingService — this is a distinct integration surface (Payroll/
 * Leave/Loan/Expense) with its own external dependencies, the same
 * reasoning that keeps LeaveService and ExpenseService as separate classes
 * rather than one god-service.
 */
class OffboardingSettlementService
{
    public function __construct(
        protected FeatureService $features,
        protected AuditLogger $audit,
    ) {
    }

    /**
     * Idempotent: regenerating clears prior *computed-only* lines (no
     * override on them) and recomputes; any line HR already overrode is left
     * untouched so their edit survives a recompute (e.g. after a notice
     * override changes the shortfall).
     */
    public function generateWorksheet(OffboardingRequest $request): void
    {
        DB::transaction(function () use ($request) {
            $request->settlementItems()
                ->whereIn('line_type', OffboardingSettlementItem::AUTO_GENERATED_TYPES)
                ->whereNull('override_amount')
                ->delete();

            $tenantId = (int) $request->tenant_id;
            $employeeId = $request->employee_id;

            $this->generatePendingSalary($request, $employeeId, $tenantId);
            $this->generateLeaveEncashment($request, $employeeId, $tenantId);

            if ($this->features->enabled($tenantId, 'loan_management')) {
                $this->generateLoanDeductions($request, $employeeId, $tenantId);
            }
            if ($this->features->enabled($tenantId, 'expense_management')) {
                $this->generateExpenseLines($request, $employeeId, $tenantId);
            }

            $this->generateNoticeShortfall($request, $tenantId);

            $this->recalculateTotals($request->fresh());
        });
    }

    private function perDayRate(?UserPayroll $payroll): float
    {
        if (! $payroll || ! $payroll->net_salary) {
            return 0.0;
        }

        return round(((float) $payroll->net_salary) / max(1, (int) config('offboarding.settlement_per_day_divisor')), 2);
    }

    private function currentPayroll(int $employeeId, int $tenantId): ?UserPayroll
    {
        return UserPayroll::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)->where('user_id', $employeeId)
            ->current()->first();
    }

    private function generatePendingSalary(OffboardingRequest $request, int $employeeId, int $tenantId): void
    {
        $payroll = $this->currentPayroll($employeeId, $tenantId);
        $rate = $this->perDayRate($payroll);

        if (! $payroll || $rate <= 0) {
            $this->upsertLine($request, OffboardingSettlementItem::TYPE_PENDING_SALARY, true, 'Pending salary (payroll data unavailable)', 0);
            return;
        }

        $daysWorked = min(now()->day, now()->daysInMonth);
        $amount = round($rate * $daysWorked, 2);
        $this->upsertLine($request, OffboardingSettlementItem::TYPE_PENDING_SALARY, true, "Pending salary — {$daysWorked} day(s) this month", $amount, 'UserPayroll', $payroll->id);
    }

    private function generateLeaveEncashment(OffboardingRequest $request, int $employeeId, int $tenantId): void
    {
        $balances = DB::table('leave_balances')
            ->join('leave_types', 'leave_types.id', '=', 'leave_balances.leave_type_id')
            ->where('leave_balances.tenant_id', $tenantId)
            ->where('leave_balances.user_id', $employeeId)
            ->where('leave_types.is_encashable', 1)
            ->where('leave_balances.balance', '>', 0)
            ->select('leave_balances.id', 'leave_balances.balance', 'leave_types.name')
            ->get();

        if ($balances->isEmpty()) {
            return;
        }

        $payroll = $this->currentPayroll($employeeId, $tenantId);
        $rate = $this->perDayRate($payroll);

        foreach ($balances as $balance) {
            $amount = round($rate * (float) $balance->balance, 2);
            $this->upsertLine(
                $request, OffboardingSettlementItem::TYPE_LEAVE_ENCASHMENT, true,
                "Leave encashment — {$balance->name} ({$balance->balance} day(s))", $amount,
                'LeaveBalance', $balance->id
            );
        }
    }

    private function generateLoanDeductions(OffboardingRequest $request, int $employeeId, int $tenantId): void
    {
        $loans = Loan::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)->where('user_id', $employeeId)
            ->whereIn('status', ['active', 'approved'])
            ->where('remaining_amount', '>', 0)
            ->get();

        foreach ($loans as $loan) {
            $this->upsertLine(
                $request, OffboardingSettlementItem::TYPE_LOAN_DEDUCTION, false,
                ($loan->loan_kind === \App\Models\Loan::KIND_SALARY_ADVANCE
                    ? 'Outstanding salary advance (' . \Carbon\Carbon::parse($loan->advance_month . '-01')->format('M Y') . ") — {$loan->loan_number}"
                    : "Outstanding loan — {$loan->loan_number}"), (float) $loan->remaining_amount,
                'Loan', $loan->id
            );
        }
    }

    private function generateExpenseLines(OffboardingRequest $request, int $employeeId, int $tenantId): void
    {
        $balance = UserExpenseBalance::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)->where('user_id', $employeeId)->first();

        if (! $balance) {
            return;
        }

        if ((float) $balance->advance_balance > 0) {
            $this->upsertLine($request, OffboardingSettlementItem::TYPE_EXPENSE_ADVANCE_DEDUCTION, false, 'Outstanding expense advance', (float) $balance->advance_balance, 'UserExpenseBalance', $balance->id);
        }
        if ((float) $balance->reimbursement_balance > 0) {
            $this->upsertLine($request, OffboardingSettlementItem::TYPE_EXPENSE_REIMBURSEMENT, true, 'Approved expense reimbursement due', (float) $balance->reimbursement_balance, 'UserExpenseBalance', $balance->id);
        }
    }

    private function generateNoticeShortfall(OffboardingRequest $request, int $tenantId): void
    {
        if (! $request->notice_period_days_required) {
            return;
        }

        $baseDate = $request->resignation_date ?? $request->request_date;
        $requiredDate = \Carbon\Carbon::parse($baseDate)->addDays((int) $request->notice_period_days_required);
        $actualDate = \Carbon\Carbon::parse($request->last_working_date);

        if ($actualDate->greaterThanOrEqualTo($requiredDate)) {
            return; // full notice served, no shortfall
        }

        $hasWaiver = $request->noticeOverrides()
            ->where('type', 'waiver')->where('status', 'approved')->exists();
        if ($hasWaiver) {
            return;
        }

        $shortfallDays = $requiredDate->diffInDays($actualDate);
        $payroll = $this->currentPayroll($request->employee_id, $tenantId);
        $rate = $this->perDayRate($payroll);
        $amount = round($rate * $shortfallDays, 2);

        $this->upsertLine($request, OffboardingSettlementItem::TYPE_NOTICE_SHORTFALL_DEDUCTION, false, "Notice period shortfall — {$shortfallDays} day(s)", $amount);
    }

    /**
     * Called after a notice override is approved while the request is
     * already in the settlement stage — only the shortfall line needs to
     * move, everything else (salary/encashment/loans/expenses) is
     * unaffected by a date change.
     */
    public function recalculateNoticeShortfall(OffboardingRequest $request): void
    {
        DB::transaction(function () use ($request) {
            $request->settlementItems()
                ->where('line_type', OffboardingSettlementItem::TYPE_NOTICE_SHORTFALL_DEDUCTION)
                ->whereNull('override_amount')
                ->delete();

            $this->generateNoticeShortfall($request, (int) $request->tenant_id);
            $this->recalculateTotals($request->fresh());
        });
    }

    private function upsertLine(
        OffboardingRequest $request, string $lineType, bool $isAddition, string $label, float $amount,
        ?string $sourceType = null, ?int $sourceId = null
    ): void {
        $match = [
            'offboarding_request_id' => $request->id,
            'line_type' => $lineType,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
        ];

        $existing = OffboardingSettlementItem::where($match)->first();
        if ($existing && $existing->override_amount !== null) {
            // HR already overrode this line — a regenerate must not clobber it.
            return;
        }

        OffboardingSettlementItem::updateOrCreate($match, [
            'tenant_id' => $request->tenant_id,
            'is_addition' => $isAddition,
            'label' => $label,
            'computed_amount' => $amount,
            'final_amount' => $amount,
        ]);
    }

    public function addManualLine(OffboardingRequest $request, User $actor, string $lineType, bool $isAddition, string $label, float $amount, ?string $notes = null): OffboardingSettlementItem
    {
        $item = OffboardingSettlementItem::create([
            'tenant_id' => $request->tenant_id,
            'offboarding_request_id' => $request->id,
            'line_type' => $lineType,
            'is_addition' => $isAddition,
            'label' => $label,
            'computed_amount' => 0,
            'override_amount' => $amount,
            'final_amount' => $amount,
            'notes' => $notes,
            'created_by' => $actor->id,
        ]);

        $this->audit->record('tenant_user', $actor->id, (int) $request->tenant_id, 'offboarding.settlement_line_added', 'OffboardingSettlementItem', $item->id, [], ['label' => $label, 'amount' => $amount]);
        $this->recalculateTotals($request);

        return $item;
    }

    public function overrideLine(OffboardingSettlementItem $item, User $actor, ?float $amount, ?string $notes): void
    {
        $old = ['override_amount' => $item->override_amount];
        $item->update([
            'override_amount' => $amount,
            'final_amount' => $amount ?? $item->computed_amount,
            'notes' => $notes,
            'updated_by' => $actor->id,
        ]);

        $this->audit->record('tenant_user', $actor->id, (int) $item->tenant_id, 'offboarding.settlement_line_overridden', 'OffboardingSettlementItem', $item->id, $old, ['override_amount' => $amount]);
        $this->recalculateTotals($item->offboardingRequest);
    }

    public function recalculateTotals(OffboardingRequest $request): void
    {
        $items = $request->settlementItems()->get();
        $computed = $items->sum(fn ($i) => $i->is_addition ? (float) $i->computed_amount : -1 * (float) $i->computed_amount);
        $final = $items->sum(fn ($i) => $i->signedAmount());

        $request->update([
            'settlement_computed_total' => $computed,
            'settlement_final_total' => $final,
        ]);
    }

    public function finalize(OffboardingRequest $request, User $actor): void
    {
        $this->recalculateTotals($request);
        $request->update([
            'final_settlement_status' => 'processing',
            'full_final_settlement' => $request->fresh()->settlement_final_total,
            'settlement_finalized_by' => $actor->id,
            'settlement_finalized_at' => now(),
        ]);
        $this->audit->record('tenant_user', $actor->id, (int) $request->tenant_id, 'offboarding.settlement_finalized', 'OffboardingRequest', $request->id);
    }

    public function markPaid(OffboardingRequest $request, User $actor, ?string $reference = null): void
    {
        $request->update([
            'final_settlement_status' => 'paid',
            'settlement_paid_date' => now()->toDateString(),
            'settlement_payment_reference' => $reference,
        ]);
        $this->audit->record('tenant_user', $actor->id, (int) $request->tenant_id, 'offboarding.settlement_paid', 'OffboardingRequest', $request->id, [], ['reference' => $reference]);
    }
}
