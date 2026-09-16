<?php

namespace App\Services\Approvals;

use App\Models\ApprovalRequest;
use App\Models\PayrollBonus;
use App\Models\User;

/**
 * Payroll rebuild — Phase 5. Registered now so the payroll_bonus
 * request_type is wired end-to-end; the actual bonus-entry UI that submits
 * these requests is Phase 6.
 */
class PayrollBonusApprovalHandler implements ApprovalOutcomeHandler
{
    public function approved(ApprovalRequest $request, User $finalActor): void
    {
        // Tenant-scoped defensively even though subject_id always belongs to
        // the request's own tenant in practice — a handler should never rely
        // solely on the caller having gotten that right.
        $bonus = PayrollBonus::withoutGlobalScopes()
            ->where('tenant_id', $request->tenant_id)
            ->find($request->subject_id);
        if (! $bonus || $bonus->status !== 'draft') {
            return;
        }

        $bonus->update([
            'status' => 'approved',
            'approved_by' => $finalActor->id,
            'approval_request_id' => $request->id,
        ]);
    }

    public function rejected(ApprovalRequest $request, User $finalActor, ?string $remarks): void
    {
        // Tenant-scoped defensively even though subject_id always belongs to
        // the request's own tenant in practice — a handler should never rely
        // solely on the caller having gotten that right.
        $bonus = PayrollBonus::withoutGlobalScopes()
            ->where('tenant_id', $request->tenant_id)
            ->find($request->subject_id);
        if (! $bonus || $bonus->status !== 'draft') {
            return;
        }

        $bonus->update(['status' => 'cancelled']);
    }
}
