<?php

namespace App\Services\Approvals;

use App\Models\ApprovalRequest;
use App\Models\AttendanceRegularization;
use App\Models\User;
use App\Services\Attendance\AttendanceEntryService;
use Illuminate\Support\Facades\DB;

/**
 * Tier 2 / T2-A — a finalised regularization approval, routed through the same
 * funnel the web/mobile paths use so the side-effects are identical.
 */
class RegularizationApprovalHandler implements ApprovalOutcomeHandler
{
    public function __construct(private AttendanceEntryService $entry)
    {
    }

    public function approved(ApprovalRequest $request, User $finalActor): void
    {
        $reg = AttendanceRegularization::withoutGlobalScopes()->find($request->subject_id);
        if (! $reg) {
            return;
        }

        DB::transaction(function () use ($reg, $finalActor) {
            if ($reg->status !== 'approved') {
                $reg->status = 'approved';
                $reg->approved_by = $finalActor->id;
                $reg->approved_date = now();
                $reg->save();
            }
            $this->entry->applyRegularization($reg, $finalActor);
        });
    }

    public function rejected(ApprovalRequest $request, User $finalActor, ?string $remarks): void
    {
        $reg = AttendanceRegularization::withoutGlobalScopes()->find($request->subject_id);
        if (! $reg) {
            return;
        }

        $reg->update([
            'status' => 'rejected',
            'approved_by' => $finalActor->id,
            'approved_date' => now(),
            'approval_remarks' => $remarks ?: 'Rejected via approval workflow',
        ]);
    }
}
