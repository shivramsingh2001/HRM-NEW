<?php

namespace App\Services\Approvals;

use App\Models\ApprovalRequest;
use App\Models\CompOffCredit;
use App\Models\User;
use App\Services\Leave\CompOffService;

/**
 * A finalised "comp_off" approval workflow: credit the Comp Off balance, or
 * mark the request rejected — the same effect as CompOffService::decide()
 * has when no workflow is configured.
 */
class CompOffApprovalHandler implements ApprovalOutcomeHandler
{
    public function approved(ApprovalRequest $request, User $finalActor): void
    {
        $credit = CompOffCredit::withoutGlobalScopes()->where('tenant_id', $request->tenant_id)->find($request->subject_id);
        if ($credit && $credit->status === 'pending') {
            app(CompOffService::class)->applyApproved($credit, (int) $finalActor->id);
        }
    }

    public function rejected(ApprovalRequest $request, User $finalActor, ?string $remarks): void
    {
        $credit = CompOffCredit::withoutGlobalScopes()->where('tenant_id', $request->tenant_id)->find($request->subject_id);
        if ($credit && $credit->status === 'pending') {
            app(CompOffService::class)->applyRejected($credit, (int) $finalActor->id, $remarks);
        }
    }
}
