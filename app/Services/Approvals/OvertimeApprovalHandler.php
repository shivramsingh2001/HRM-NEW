<?php

namespace App\Services\Approvals;

use App\Models\ApprovalRequest;
use App\Models\OvertimeRequest;
use App\Models\User;

/**
 * Tier 2 / T2-A — a finalised overtime approval. Mirrors the side-effects of
 * OvertimeController::approve / reject so payroll picks up approved_hours.
 */
class OvertimeApprovalHandler implements ApprovalOutcomeHandler
{
    public function approved(ApprovalRequest $request, User $finalActor): void
    {
        $ot = OvertimeRequest::withoutGlobalScopes()->find($request->subject_id);
        if (! $ot) {
            return;
        }

        $ot->update([
            'status' => 'approved',
            'approved_by' => $finalActor->id,
            // Staged by the approver in OvertimeController::approve, else the request amount.
            'approved_hours' => $ot->approved_hours ?? $ot->overtime_hours,
            'approved_at' => now(),
            'rejection_reason' => null,
        ]);

        try {
            app(\App\Services\OvertimeNotificationService::class)->notifyOvertimeApproved($ot->fresh());
        } catch (\Throwable $e) {
            // never block on notification
        }
    }

    public function rejected(ApprovalRequest $request, User $finalActor, ?string $remarks): void
    {
        $ot = OvertimeRequest::withoutGlobalScopes()->find($request->subject_id);
        if (! $ot) {
            return;
        }

        $ot->update([
            'status' => 'rejected',
            'approved_by' => $finalActor->id,
            'rejection_reason' => $remarks ?: 'Rejected via approval workflow',
            'approved_at' => now(),
        ]);

        try {
            app(\App\Services\OvertimeNotificationService::class)->notifyOvertimeRejected($ot->fresh(), $remarks);
        } catch (\Throwable $e) {
            // never block on notification
        }
    }
}
