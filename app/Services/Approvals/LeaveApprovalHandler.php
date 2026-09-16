<?php

namespace App\Services\Approvals;

use App\Exceptions\InsufficientLeaveBalanceException;
use App\Models\ApprovalRequest;
use App\Models\Leave;
use App\Models\User;
use App\Services\LeaveNotificationService;
use App\Services\LeaveService;

/**
 * A finalised leave approval, routed through the same LeaveService the
 * direct (no-workflow) path uses so balance/ledger side-effects — and the
 * audit trail, which LeaveService writes itself — are identical regardless
 * of whether a tenant has configured a multi-level leave approval workflow.
 */
class LeaveApprovalHandler implements ApprovalOutcomeHandler
{
    public function __construct(
        private LeaveService $leaveService,
        private LeaveNotificationService $notifications,
    ) {
    }

    public function approved(ApprovalRequest $request, User $finalActor): void
    {
        $leave = Leave::withoutGlobalScopes()->find($request->subject_id);
        if (! $leave) {
            return;
        }

        $latestRemarks = $request->actions()->where('level', $request->current_level)
            ->where('action', 'approved')->latest('id')->value('remarks');

        $result = $this->leaveService->approvePendingLeave($leave, $finalActor, $latestRemarks);
        if (! $result['success']) {
            // Roll back the whole approval transaction (ApprovalAction +
            // ApprovalRequest status change) — the request stays 'pending'
            // at this level rather than being marked approved while the
            // underlying leave/balance never actually changed.
            throw new InsufficientLeaveBalanceException($result['message']);
        }

        try {
            $this->notifications->notifyLeaveApproved($leave->fresh(), $latestRemarks);
        } catch (\Throwable $e) {
            // never block on notification
        }
    }

    public function rejected(ApprovalRequest $request, User $finalActor, ?string $remarks): void
    {
        $leave = Leave::withoutGlobalScopes()->find($request->subject_id);
        if (! $leave) {
            return;
        }

        $this->leaveService->cancelPendingLeave($leave, $finalActor, $remarks);

        try {
            $this->notifications->notifyLeaveRejected($leave->fresh(), $remarks);
        } catch (\Throwable $e) {
            // never block on notification
        }
    }
}
