<?php

namespace App\Services\Approvals;

use App\Models\ApprovalRequest;
use App\Models\OffboardingRequest;
use App\Models\User;
use App\Services\AuditLogger;

/**
 * Registered in ApprovalService::HANDLERS under both 'offboarding' (2-level
 * Manager->HR) and 'offboarding_termination' (1-level HR-only) request
 * types — same handler serves both, only the seeded workflow differs (see
 * OffboardingService::ensureDefaultWorkflow()).
 *
 * The generic engine's own notifyLevel()/notifyRequester() (called by
 * ApprovalService::finalise() right after this handler runs) already cover
 * "approval needed" / "your request was approved/rejected" notifications
 * for every request_type — this handler only applies offboarding's state
 * transition, it does not duplicate those notifications.
 */
class OffboardingApprovalHandler implements ApprovalOutcomeHandler
{
    public function __construct(
        private AuditLogger $audit,
    ) {
    }

    public function approved(ApprovalRequest $request, User $finalActor): void
    {
        $offboarding = OffboardingRequest::withoutGlobalScopes()->find($request->subject_id);
        if (! $offboarding) {
            return;
        }

        $offboarding->update([
            'status' => OffboardingRequest::STATUS_APPROVED,
            'approved_by' => $finalActor->id,
            'approved_at' => now(),
            'current_stage' => OffboardingRequest::STAGE_KNOWLEDGE_TRANSFER,
        ]);

        $this->audit->record('tenant_user', $finalActor->id, (int) $offboarding->tenant_id, 'offboarding.approved', 'OffboardingRequest', $offboarding->id);
    }

    public function rejected(ApprovalRequest $request, User $finalActor, ?string $remarks): void
    {
        $offboarding = OffboardingRequest::withoutGlobalScopes()->find($request->subject_id);
        if (! $offboarding) {
            return;
        }

        $offboarding->update([
            'status' => OffboardingRequest::STATUS_REJECTED,
            'current_stage' => OffboardingRequest::STAGE_REJECTED,
            'rejected_by' => $finalActor->id,
            'rejected_at' => now(),
            'rejected_reason' => $remarks,
        ]);

        $this->audit->record('tenant_user', $finalActor->id, (int) $offboarding->tenant_id, 'offboarding.rejected', 'OffboardingRequest', $offboarding->id, [], ['reason' => $remarks]);
    }
}
