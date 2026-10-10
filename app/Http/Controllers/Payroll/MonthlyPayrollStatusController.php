<?php

namespace App\Http\Controllers\Payroll;

use App\Http\Controllers\Controller;
use App\Models\MonthlyPayroll;
use App\Models\PayrollAuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Payslip status — processed / paid / cancelled (one or in bulk) and
 * reopening a processed / paid payslip for correction; locks the attendance
 * month once payroll is processed. Moved out of MonthlyPayrollController
 * unchanged (code-quality plan, Phase 1); route names are the same.
 */
class MonthlyPayrollStatusController extends Controller
{
    /**
     * Payroll Audit Phase 3 — H8. Deliberate, audited alternative to the
     * unrestricted force_reprocess bypass (C4) for correcting a
     * processed/paid payslip: flips payment_status back to 'pending' so the
     * normal, already-policy-gated edit()/update() flow becomes available
     * again. Nothing else needs to change here -- update() already
     * idempotently resyncs the loan ledger and re-validates any OT
     * auto-approval on every save, so the actual correction is handled
     * correctly by the existing edit flow once this unlocks it.
     */
    public function reopen(Request $request, $id)
    {
        $request->validate([
            'reason' => 'required|string|min:5|max:500',
        ]);

        try {
            $monthlyPayroll = MonthlyPayroll::findOrFail($id);

            if (auth()->user()->cannot('reopen', $monthlyPayroll)) {
                return redirect()->back()->with(
                    'error',
                    $monthlyPayroll->payment_status === 'pending'
                        ? 'This payroll is already pending -- nothing to reopen.'
                        : 'You do not have permission to reopen this payroll for correction.'
                );
            }

            $tenantId = $monthlyPayroll->tenant_id;
            if ($tenantId && app(\App\Services\Attendance\PeriodLockService::class)->isLocked($tenantId, $monthlyPayroll->payroll_month)) {
                return redirect()->back()
                    ->with('error', $monthlyPayroll->payroll_month.' is locked. Reopen the period before reopening this payroll for correction.');
            }

            $previousStatus = $monthlyPayroll->payment_status;

            DB::beginTransaction();
            $monthlyPayroll->update(['payment_status' => 'pending']);
            // A paid payslip's reimbursement payments are reversed so the payslip can be corrected and paid again.
            app(\App\Services\Expense\ExpenseReimbursementPayrollService::class)->onPayrollStatusChange($monthlyPayroll, $previousStatus, 'pending', Auth::user());

            PayrollAuditLog::create([
                'tenant_id' => $tenantId,
                'auditable_type' => MonthlyPayroll::class,
                'auditable_id' => $monthlyPayroll->id,
                'action' => 'reopened',
                'actor_id' => Auth::id(),
                'old_values' => ['payment_status' => $previousStatus],
                'new_values' => ['payment_status' => 'pending', 'reason' => $request->reason],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
            DB::commit();

            app(\App\Services\PayrollNotificationService::class)->notifyStatusChange($monthlyPayroll, $previousStatus, 'pending');

            return redirect()->route('monthly-payrolls.edit', $id)
                ->with('success', 'Payroll reopened for correction -- make your changes and save.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Reopen payroll error: '.$e->getMessage());

            return redirect()->back()->with('error', 'Failed to reopen payroll: '.$e->getMessage());
        }
    }

    // =========================================================================

    public function updateStatus(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'payment_status' => 'required|in:pending,processed,paid,cancelled',
            'payment_date' => 'required_if:payment_status,paid|nullable|date',
            'payment_mode' => 'nullable|string|max:50',
            'transaction_reference' => 'nullable|string|max:100',
            'remarks' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        try {
            $monthlyPayroll = MonthlyPayroll::findOrFail($id);

            $updateData = [
                'payment_status' => $request->payment_status,
                'remarks' => $request->remarks
                    ? $monthlyPayroll->remarks.' | '.$request->remarks
                    : $monthlyPayroll->remarks,
            ];

            if ($request->payment_status === 'paid') {
                $updateData['payment_date'] = $request->payment_date ?? now();
                $updateData['payment_mode'] = $request->payment_mode;
                $updateData['transaction_reference'] = $request->transaction_reference;
            }

            $previousStatus = $monthlyPayroll->payment_status;

            // One transaction: if a reimbursement on this payslip can no longer be paid, the payslip is NOT marked paid.
            DB::transaction(function () use ($monthlyPayroll, $updateData, $previousStatus, $request) {
                $monthlyPayroll->update($updateData);
                app(\App\Services\Expense\ExpenseReimbursementPayrollService::class)->onPayrollStatusChange($monthlyPayroll, $previousStatus, $request->payment_status, Auth::user());
            });

            $this->maybeLockPeriod($monthlyPayroll->tenant_id, $monthlyPayroll->payroll_month, $request->payment_status);

            app(\App\Services\PayrollNotificationService::class)->notifyStatusChange($monthlyPayroll->fresh(), $previousStatus, $request->payment_status);

            return redirect()->back()->with('success', 'Payment status updated successfully.');
        } catch (\App\Exceptions\ExpenseException $e) {
            return redirect()->back()->with('error', 'Cannot change the status: '.$e->getMessage());
        } catch (\Exception $e) {
            Log::error('Update status error: '.$e->getMessage());

            return redirect()->back()->with('error', 'Failed to update status: '.$e->getMessage());
        }
    }

    /**
     * Tier 2 / T2-E — freeze a tenant's attendance for a month once its payroll
     * is processed or paid, so later edits require an explicit override.
     */
    private function maybeLockPeriod(?int $tenantId, ?string $payrollMonth, string $status): void
    {
        if (! config('attendance.period_autolock') || ! $tenantId || ! $payrollMonth) {
            return;
        }
        if (! in_array($status, ['processed', 'paid'], true)) {
            return;
        }

        try {
            app(\App\Services\Attendance\PeriodLockService::class)
                ->lock((int) $tenantId, $payrollMonth, \Illuminate\Support\Facades\Auth::id(), 'Payroll '.$status);
            event(new \App\Events\AttendanceDomainEvent('attendance.month_finalised', (int) $tenantId, [
                'year_month' => $payrollMonth,
                'reason' => 'payroll '.$status,
            ]));
        } catch (\Throwable $e) {
            Log::warning('maybeLockPeriod failed: '.$e->getMessage());
        }
    }

    // STATUS MANAGEMENT

    public function bulkUpdate(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:monthly_payrolls,id',
            'payment_status' => 'required|in:pending,processed,paid,cancelled',
        ]);

        try {
            DB::beginTransaction();

            $updateData = [
                'payment_status' => $request->payment_status,
                'remarks' => DB::raw("CONCAT(COALESCE(remarks,''), ' | Bulk updated on ".now()->toDateTimeString()."')"),
            ];

            if ($request->payment_status === 'paid') {
                $updateData['payment_date'] = now();
            }

            $affected = MonthlyPayroll::whereIn('id', $request->ids)
                ->get(['tenant_id', 'payroll_month'])->unique(fn ($r) => $r->tenant_id.$r->payroll_month);

            // The bulk update below is a query-builder update, so model events never fire - remember each
            // payslip's previous status and drive the reimbursement settlement explicitly.
            $before = MonthlyPayroll::whereIn('id', $request->ids)->orderBy('id')->get()->keyBy('id');
            $previousStatus = $before->map->payment_status->all();

            MonthlyPayroll::whereIn('id', $request->ids)->update($updateData);

            $expensePayroll = app(\App\Services\Expense\ExpenseReimbursementPayrollService::class);
            foreach ($before as $slipId => $slip) {
                $slip->refresh();
                $expensePayroll->onPayrollStatusChange($slip, $previousStatus[$slipId], $request->payment_status, Auth::user());
            }

            DB::commit();

            foreach ($affected as $row) {
                $this->maybeLockPeriod($row->tenant_id, $row->payroll_month, $request->payment_status);
            }

            // One push per employee can take a while for a big batch - send them after the response.
            $ids = $before->keys()->all();
            $newStatus = $request->payment_status;
            dispatch(function () use ($ids, $previousStatus, $newStatus) {
                $notifier = app(\App\Services\PayrollNotificationService::class);
                foreach (MonthlyPayroll::withoutGlobalScopes()->whereIn('id', $ids)->get() as $slip) {
                    $notifier->notifyStatusChange($slip, $previousStatus[$slip->id] ?? null, $newStatus);
                }
            })->afterResponse();

            return redirect()->back()->with('success', count($request->ids).' payroll records updated successfully.');
        } catch (\App\Exceptions\ExpenseException $e) {
            DB::rollBack();

            return redirect()->back()->with('error', 'Nothing was changed - a reimbursement on one of the payslips cannot be paid: '.$e->getMessage());
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Bulk update error: '.$e->getMessage());

            return redirect()->back()->with('error', 'Failed to update: '.$e->getMessage());
        }
    }
}
