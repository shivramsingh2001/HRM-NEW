<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ApiException;
use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\AttendanceRegularization;
use App\Models\User;
use App\Services\Attendance\AttendanceEntryService;
use App\Traits\AuthorizesByScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Tier 2 / T2-B — list regularization requests and record a decision through
 * the same funnel the web/mobile approval paths use.
 */
class RegularizationV1Controller extends Controller
{
    use AuthorizesByScope;

    public function index(Request $request)
    {
        $data = $request->validate([
            'status' => ['nullable', 'in:pending,approved,rejected'],
            'user_id' => ['nullable', 'integer'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $page = AttendanceRegularization::query()
            ->when($data['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($data['user_id'] ?? null, fn ($q, $u) => $q->where('user_id', $u))
            ->orderByDesc('id')
            ->paginate($data['per_page'] ?? 50);

        return ApiResponse::paginated($page, fn ($r) => [
            'id' => $r->id,
            'user_id' => $r->user_id,
            'date' => (string) $r->date,
            'request_type' => $r->request_type,
            'in_time' => $r->in_time,
            'out_time' => $r->out_time,
            'reason' => $r->reason,
            'status' => $r->status,
            'approved_by' => $r->approved_by,
            'approved_date' => $r->approved_date,
        ]);
    }

    public function decision(Request $request, $id, AttendanceEntryService $entry)
    {
        $data = $request->validate([
            'decision' => ['required', 'in:approved,rejected'],
            'actor_user_id' => ['required', 'integer'],
            'remarks' => ['nullable', 'string', 'max:500'],
        ]);

        $tenantId = (int) app('current_tenant')->id;

        $reg = AttendanceRegularization::where('id', $id)->first();
        if (! $reg) {
            throw new ApiException('regularization.not_found', 'Regularization not found.', 404);
        }
        if ($reg->status !== 'pending') {
            throw new ApiException('regularization.not_pending', "Already {$reg->status}.", 409);
        }

        $actor = User::where('id', $data['actor_user_id'])->where('tenant_id', $tenantId)->first();
        // Was a coarse admin/hr/manager gate with no further per-record
        // check — any manager-actor could decide ANY tenant's regularization,
        // not just their team's. scopeCoversOwner() closes that gap.
        if (! $actor || ! $this->scopeCoversOwner($actor, 'attendance', 'approve', (int) $reg->user_id)) {
            throw new ApiException('actor.not_authorized', 'actor_user_id must be an admin/hr/manager in your tenant.', 403);
        }

        // Tier 2 / T2-A — a configured workflow drives multi-level approval;
        // otherwise apply the decision directly through the funnel.
        try {
            $ar = app(\App\Services\Approvals\ApprovalService::class)
                ->decide('regularization', $reg, $actor, $data['decision'], $data['remarks'] ?? null);
        } catch (\RuntimeException $e) {
            throw new ApiException('approval.not_permitted', $e->getMessage(), 403);
        }

        if ($ar === null) {
            DB::transaction(function () use ($reg, $data, $actor, $entry) {
                $reg->status = $data['decision'];
                $reg->approved_by = $actor->id;
                $reg->approved_date = now();
                $reg->approval_remarks = $data['remarks'] ?? null;
                $reg->save();

                if ($data['decision'] === 'approved') {
                    $entry->applyRegularization($reg, $actor);
                }
            });
        }

        return ApiResponse::ok([
            'id' => $reg->id,
            'status' => $ar->status ?? $reg->fresh()->status,
            'workflow_pending' => $ar !== null && $ar->status === 'pending',
            'decided_by' => $actor->id,
        ]);
    }
}
