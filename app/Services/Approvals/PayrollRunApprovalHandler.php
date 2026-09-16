<?php

namespace App\Services\Approvals;

use App\Models\ApprovalRequest;
use App\Models\PayrollRun;
use App\Models\User;

/**
 * Payroll rebuild — Phase 5. Registered now so the payroll_run request_type
 * is wired end-to-end; the actual trigger point (a dynamic-engine payroll
 * run reaching payment_status=processed, gating its move to paid) is
 * Phase 8 cutover, once the dynamic engine is live for a tenant.
 */
class PayrollRunApprovalHandler implements ApprovalOutcomeHandler
{
    public function approved(ApprovalRequest $request, User $finalActor): void
    {
        $run = PayrollRun::withoutGlobalScopes()
            ->where('tenant_id', $request->tenant_id)
            ->find($request->subject_id);
        if (! $run || $run->status !== 'calculated') {
            return;
        }

        $run->update(['status' => 'approved']);
    }

    public function rejected(ApprovalRequest $request, User $finalActor, ?string $remarks): void
    {
        $run = PayrollRun::withoutGlobalScopes()
            ->where('tenant_id', $request->tenant_id)
            ->find($request->subject_id);
        if (! $run || $run->status !== 'calculated') {
            return;
        }

        $run->update(['status' => 'cancelled']);
    }
}
