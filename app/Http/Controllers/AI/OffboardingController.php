<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\AI\Concerns\AiScope;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * GET /api/ai/offboarding — exit requests with stage, reviews, clearance progress, exit interview
 * and final settlement. Visibility follows offboarding:view (own / team / company).
 */
class OffboardingController extends Controller
{
    use AiScope;

    public function view_ai_all(Request $request)
    {
        try {
            $authUser = Auth::user();
            $tenantId = (int) $authUser->tenant_id;

            $scope = $this->scopeOf($authUser, 'offboarding');
            if ($scope === null) {
                return $this->forbidden();
            }
            $ids = $this->narrowToUser($this->visibleUserIds($authUser, $scope), $request->user_id);

            $query = DB::table('offboarding_requests as o')
                ->join('users as u', 'u.id', '=', 'o.employee_id')
                ->leftJoin('user_job_details as jd', 'jd.user_id', '=', 'u.id')
                ->leftJoin('departments as d', 'd.id', '=', 'jd.department')
                ->leftJoin('designations as g', 'g.id', '=', 'jd.designation')
                ->where('o.tenant_id', $tenantId)
                ->whereNull('o.deleted_at')
                ->select(['o.*', 'u.name as employee_name', 'u.employee_id as employee_code', 'd.name as department', 'g.name as designation']);

            if ($ids !== null) {
                $query->whereIn('o.employee_id', $ids ?: [0]);
            }
            if ($request->filled('status')) {
                $query->where('o.status', $request->status);
            }

            $requests = $query->orderByDesc('o.created_at')->get();
            $requestIds = $requests->pluck('id')->all() ?: [0];

            $clearance = DB::table('offboarding_clearance_tasks')->where('tenant_id', $tenantId)
                ->whereIn('offboarding_request_id', $requestIds)
                ->get(['offboarding_request_id', 'category', 'label', 'status'])->groupBy('offboarding_request_id');
            $interviews = DB::table('exit_interviews')->where('tenant_id', $tenantId)
                ->whereIn('offboarding_request_id', $requestIds)->orderByDesc('id')
                ->get(['offboarding_request_id', 'interview_date', 'status', 'interviewer_id', 'primary_reason'])
                ->unique('offboarding_request_id')->keyBy('offboarding_request_id');
            $people = $this->people($tenantId, $requests->pluck('manager_review_by')->merge($requests->pluck('hr_review_by'))
                ->merge($interviews->pluck('interviewer_id')));

            $today = Carbon::today();
            $data = $requests->map(function ($o) use ($clearance, $interviews, $people, $today) {
                $tasks = $clearance->get($o->id, collect());
                $interview = $interviews->get($o->id);
                $lwd = $o->last_working_date ? Carbon::parse($o->last_working_date) : null;

                return [
                    'id' => $o->id,
                    'request_code' => $o->request_code,
                    'employee' => ['id' => $o->employee_id, 'name' => $o->employee_name, 'employee_id' => $o->employee_code,
                        'department' => $o->department, 'designation' => $o->designation],
                    'reason' => $o->reason,
                    'reason_detail' => $o->reason_detail,
                    'status' => $o->status,
                    'current_stage' => $o->current_stage,
                    'dates' => [
                        'request_date' => $o->request_date,
                        'resignation_date' => $o->resignation_date,
                        'last_working_date' => $o->last_working_date,
                        'original_last_working_date' => $o->original_last_working_date,
                        'days_until_last_working_day' => $lwd && $lwd->gte($today) ? $today->diffInDays($lwd) : null,
                        'completed_at' => $o->completed_at,
                    ],
                    'notice_period_days_required' => $o->notice_period_days_required,
                    'manager_review' => ['status' => $o->manager_review_status, 'by' => $people[$o->manager_review_by] ?? null,
                        'at' => $o->manager_review_at, 'comments' => $o->manager_review_comments],
                    'hr_review' => ['status' => $o->hr_review_status, 'by' => $people[$o->hr_review_by] ?? null,
                        'at' => $o->hr_review_at, 'comments' => $o->hr_review_comments],
                    'knowledge_transfer' => $o->knowledge_transfer_status,
                    'clearance' => [
                        'status' => $o->clearance_status,
                        'asset_return' => $o->asset_return_status,
                        'document_return' => $o->document_return_status,
                        'total' => $tasks->count(),
                        'completed' => $tasks->whereIn('status', ['completed', 'waived', 'not_applicable'])->count(),
                        'pending_items' => $tasks->where('status', 'pending')->map(fn ($t) => ['category' => $t->category, 'label' => $t->label])->values(),
                    ],
                    'exit_interview' => [
                        'status' => $o->exit_interview_status,
                        'skipped' => (bool) $o->exit_interview_skipped,
                        'date' => $interview->interview_date ?? $o->exit_interview_date,
                        'interviewer' => $interview ? ($people[$interview->interviewer_id] ?? null) : null,
                    ],
                    'settlement' => [
                        'status' => $o->final_settlement_status,
                        'computed_total' => $o->settlement_computed_total !== null ? (float) $o->settlement_computed_total : null,
                        'final_total' => $o->settlement_final_total !== null ? (float) $o->settlement_final_total : null,
                        'paid_date' => $o->settlement_paid_date,
                    ],
                    'eligible_for_rehire' => $o->eligible_for_rehire === null ? null : (bool) $o->eligible_for_rehire,
                    'rejected_reason' => $o->rejected_reason,
                    'cancelled_reason' => $o->cancelled_reason,
                ];
            })->values();

            $monthEnd = $today->copy()->endOfMonth();

            return response()->json([
                'success' => true,
                'message' => 'Offboarding data fetched successfully',
                'data' => $data,
                'summary' => [
                    'total_requests' => $data->count(),
                    'by_status' => $data->countBy('status'),
                    'by_stage' => $data->countBy('current_stage'),
                    'pending_approval' => $data->where('status', 'pending_approval')->count(),
                    'leaving_this_month' => $data->filter(fn ($r) => in_array($r['status'], ['approved', 'pending_approval'], true)
                        && $r['dates']['last_working_date'] && Carbon::parse($r['dates']['last_working_date'])->between($today, $monthEnd))->count(),
                    'pending_clearance_items' => $data->sum(fn ($r) => $r['clearance']['pending_items']->count()),
                ],
                'scope' => $scope,
            ], 200);
        } catch (\Throwable $e) {
            return $this->failed('offboarding', $e);
        }
    }
}
