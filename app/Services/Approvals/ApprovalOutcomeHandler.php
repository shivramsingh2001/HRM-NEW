<?php

namespace App\Services\Approvals;

use App\Models\ApprovalRequest;
use App\Models\User;

/**
 * Tier 2 / T2-A — applies the real-world effect of a finalised approval for one
 * request_type. Registered in ApprovalService::HANDLERS.
 */
interface ApprovalOutcomeHandler
{
    public function approved(ApprovalRequest $request, User $finalActor): void;

    public function rejected(ApprovalRequest $request, User $finalActor, ?string $remarks): void;
}
