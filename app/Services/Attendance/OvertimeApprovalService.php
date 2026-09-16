<?php

namespace App\Services\Attendance;

use App\Models\OvertimeRequest;
use App\Models\User;
use App\Services\RbacService;
use Illuminate\Support\Facades\DB;

/**
 * Single source of truth for "bulk-approve these pending overtime requests as
 * this actor" — extracted from OvertimeController::bulkApprove() so the same
 * permission/scope rules and status-flip logic are shared between the
 * Overtime approvals screen and Edit Payroll's "include non-approved
 * overtime" checkbox. Deliberately mirrors bulkApprove()'s behavior exactly:
 * a direct status flip, not routed through the multi-level ApprovalService
 * workflow (that workflow requires the actor to be the specific request's
 * configured approver, which doesn't fit an HR/admin acting in bulk).
 */
class OvertimeApprovalService
{
    public function __construct(private RbacService $rbac)
    {
    }

    /**
     * @param  int[]  $requestIds
     * @return array{authorized: bool, approved_count: int, approved_ids: int[]}
     */
    public function bulkApprove(User $actor, int $tenantId, array $requestIds): array
    {
        if (! $this->rbac->can($actor, 'overtime', 'approve')) {
            return ['authorized' => false, 'approved_count' => 0, 'approved_ids' => []];
        }

        if (empty($requestIds)) {
            return ['authorized' => true, 'approved_count' => 0, 'approved_ids' => []];
        }

        $query = OvertimeRequest::where('tenant_id', $tenantId)
            ->whereIn('id', $requestIds)
            ->where('status', 'pending');

        // Managers may only bulk-approve their own reportees.
        if ($this->rbac->scopeFor($actor, 'overtime', 'approve') === 'team') {
            $query->whereIn('user_id', function ($q) use ($actor, $tenantId) {
                $q->select('user_id')
                    ->from('user_job_details')
                    ->where('reporting_head', $actor->id)
                    ->where('tenant_id', $tenantId);
            });
        }

        $approvedIds = $query->pluck('id')->all();

        $updatedCount = empty($approvedIds) ? 0 : OvertimeRequest::whereIn('id', $approvedIds)->update([
            'status' => 'approved',
            'approved_by' => $actor->id,
            // populate approved_hours so payroll does not fall back to the raw request
            'approved_hours' => DB::raw('COALESCE(approved_hours, overtime_hours)'),
            'approved_at' => now(),
        ]);

        return ['authorized' => true, 'approved_count' => $updatedCount, 'approved_ids' => $approvedIds];
    }
}
