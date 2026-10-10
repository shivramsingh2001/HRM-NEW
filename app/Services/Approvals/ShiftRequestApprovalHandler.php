<?php

namespace App\Services\Approvals;

use App\Models\ApprovalRequest;
use App\Models\ShiftRequest;
use App\Models\User;
use App\Services\Shift\ShiftNotificationService;
use App\Services\Shift\ShiftRequestException;
use App\Services\Shift\ShiftRequestService;

/**
 * A finished 'shift_request' approval workflow (swap / change request):
 * applies or rejects it through ShiftRequestService and tells both employees.
 * If the shifts changed since the request was raised (e.g. an SLA
 * auto-approval long after), the request is rejected as stale instead.
 */
class ShiftRequestApprovalHandler implements ApprovalOutcomeHandler
{
    public function approved(ApprovalRequest $request, User $finalActor): void
    {
        $req = ShiftRequest::withoutGlobalScopes()->find($request->subject_id);
        if (! $req || $req->status !== ShiftRequest::STATUS_PENDING_APPROVAL) {
            return;
        }

        $service = app(ShiftRequestService::class);
        try {
            $service->finalizeApproved($req, $finalActor, null);
        } catch (ShiftRequestException $e) {
            // A person deciding on the web / app sees the reason; only the
            // scheduled SLA auto-approval turns a stale request into a rejection.
            if (! $e->stale || ! app()->runningInConsole()) {
                throw $e;
            }
            $service->event($req, 'stale_blocked', $finalActor, $e->getMessage(), [], 'system');
            $service->finalizeRejected($req, $finalActor, $e->getMessage(), 'system');
        }

        app(ShiftNotificationService::class)->decided($req->refresh());
    }

    public function rejected(ApprovalRequest $request, User $finalActor, ?string $remarks): void
    {
        $req = ShiftRequest::withoutGlobalScopes()->find($request->subject_id);
        if (! $req || $req->status !== ShiftRequest::STATUS_PENDING_APPROVAL) {
            return;
        }

        app(ShiftRequestService::class)->finalizeRejected($req, $finalActor, $remarks);
        app(ShiftNotificationService::class)->decided($req->refresh());
    }
}
