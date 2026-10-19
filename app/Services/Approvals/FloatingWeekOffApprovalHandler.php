<?php

namespace App\Services\Approvals;

use App\Models\ApprovalRequest;
use App\Models\FloatingWeekoffDay;
use App\Models\User;
use App\Services\Attendance\FloatingWeekOffRequestService;

/** A finalised "floating_weekoff" workflow: the planned day becomes a week-off, or is rejected. */
class FloatingWeekOffApprovalHandler implements ApprovalOutcomeHandler
{
    public function approved(ApprovalRequest $request, User $finalActor): void
    {
        $row = FloatingWeekoffDay::withoutGlobalScopes()->where('tenant_id', $request->tenant_id)->find($request->subject_id);
        if ($row && $row->status === 'pending') {
            app(FloatingWeekOffRequestService::class)->applyApproved($row, (int) $finalActor->id);
        }
    }

    public function rejected(ApprovalRequest $request, User $finalActor, ?string $remarks): void
    {
        $row = FloatingWeekoffDay::withoutGlobalScopes()->where('tenant_id', $request->tenant_id)->find($request->subject_id);
        if ($row && $row->status === 'pending') {
            app(FloatingWeekOffRequestService::class)->applyRejected($row, (int) $finalActor->id, $remarks);
        }
    }
}
