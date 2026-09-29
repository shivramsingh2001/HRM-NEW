<?php

namespace App\Services\Approvals;

use App\Models\ApprovalRequest;
use App\Models\PayrollEmployeeStructure;
use App\Models\PayrollRevisionLog;
use App\Models\User;
use App\Services\Payroll\PayrollArrearsCalculator;
use Illuminate\Support\Facades\DB;

/**
 * Payroll rebuild — Phase 5.
 *
 * A salary revision (PayrollEmployeeStructure) submitted through a tenant's
 * configured payroll_revision approval workflow. Until approved, the
 * structure sits with is_current=false / status=pending_approval — it does
 * NOT supersede the previously-current structure (so payroll can keep
 * running on the old salary while the revision is under review). Approval
 * flips it live; rejection cancels it and leaves the prior structure as-is.
 */
class PayrollRevisionApprovalHandler implements ApprovalOutcomeHandler
{
    public function approved(ApprovalRequest $request, User $finalActor): void
    {
        $structure = PayrollEmployeeStructure::withoutGlobalScopes()
            ->where('tenant_id', $request->tenant_id)
            ->find($request->subject_id);
        if (! $structure || $structure->status !== 'pending_approval') {
            return;
        }

        DB::transaction(function () use ($structure, $request, $finalActor) {
            $structure->update([
                'is_current' => true,
                'status' => 'active',
                'approved_by' => $finalActor->id,
                'approval_request_id' => $request->id,
            ]);

            $log = PayrollRevisionLog::withoutGlobalScopes()
                ->where('tenant_id', $structure->tenant_id)
                ->where('payroll_employee_structure_id', $structure->id)
                ->first();

            if ($log) {
                $log->update([
                    'approved_by' => $finalActor->id,
                    'approval_request_id' => $request->id,
                ]);
            }

            app(PayrollArrearsCalculator::class)->computeForRevision($structure->fresh(), optional($log)->id);
        });

        DB::afterCommit(fn () => app(\App\Services\PayrollNotificationService::class)->notifyRevisionApplied($structure->fresh()));
    }

    public function rejected(ApprovalRequest $request, User $finalActor, ?string $remarks): void
    {
        $structure = PayrollEmployeeStructure::withoutGlobalScopes()
            ->where('tenant_id', $request->tenant_id)
            ->find($request->subject_id);
        if (! $structure || $structure->status !== 'pending_approval') {
            return;
        }

        $structure->update(['status' => 'cancelled']);
    }
}
