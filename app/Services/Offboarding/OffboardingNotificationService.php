<?php

namespace App\Services\Offboarding;

use App\Models\OffboardingNoticeOverride;
use App\Models\OffboardingRequest;
use App\Models\User;
use App\Notifications\CustomNotification;
use Illuminate\Support\Facades\Notification;

/**
 * Covers the offboarding-specific events the generic ApprovalService engine
 * doesn't know about — the engine's own notifyLevel()/notifyRequester()
 * (see ApprovalService::finalise()) already handle "approval needed" and
 * "your request was approved/rejected" for every request_type including
 * offboarding, so this service is intentionally NOT called for those. Every
 * method here mirrors the database-only CustomNotification pattern the
 * engine itself uses, for consistency within one request's lifecycle.
 */
class OffboardingNotificationService
{
    public function notifySubmitted(OffboardingRequest $request): void
    {
        $employee = $request->employee;
        if (! $employee) {
            return;
        }

        $employee->notify(new CustomNotification(
            'Offboarding request submitted',
            "Your {$request->reason_label} request has been submitted and is pending approval.",
            ['type' => 'offboarding_submitted', 'offboarding_request_id' => $request->id]
        ));
    }

    public function notifyCancelled(OffboardingRequest $request): void
    {
        $recipients = collect([$request->employee])->filter();
        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send($recipients, new CustomNotification(
            'Offboarding request cancelled',
            "The offboarding request for {$request->employee?->name} has been cancelled.",
            ['type' => 'offboarding_cancelled', 'offboarding_request_id' => $request->id]
        ));
    }

    public function notifyCompleted(OffboardingRequest $request): void
    {
        $employee = $request->employee;
        if (! $employee) {
            return;
        }

        $employee->notify(new CustomNotification(
            'Offboarding completed',
            'Your offboarding process has been completed. We wish you the best in your next role.',
            ['type' => 'offboarding_completed', 'offboarding_request_id' => $request->id]
        ));
    }

    public function notifyNoticeOverrideDecided(OffboardingNoticeOverride $override, bool $approved): void
    {
        $requester = $override->requestedBy;
        $employee = $override->offboardingRequest?->employee;
        $recipients = collect([$requester, $employee])->filter()->unique('id');
        if ($recipients->isEmpty()) {
            return;
        }

        $label = ucfirst(str_replace('_', ' ', $override->type));
        Notification::send($recipients, new CustomNotification(
            $approved ? "Notice period {$label} approved" : "Notice period {$label} rejected",
            $approved
                ? "The requested last working date change ({$override->requested_last_working_date->toDateString()}) has been approved."
                : "The requested {$label} was not approved.",
            ['type' => 'offboarding_notice_override_decided', 'offboarding_request_id' => $override->offboarding_request_id]
        ));
    }
}
