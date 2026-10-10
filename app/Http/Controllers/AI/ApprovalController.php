<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\AI\Concerns\AiScope;
use App\Http\Controllers\Controller;
use App\Models\OffboardingRequest;
use App\Models\Request as WorkRequest;
use App\Services\FeatureService;
use App\Services\RbacService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * GET /api/ai/approvals — everything waiting for the caller to approve, across
 * leave, regularization, WFH / travel, overtime, expenses, loans / salary
 * advances and offboarding. Per module: the module must be on for the company
 * and the caller needs its `approve` permission; the approve scope (team /
 * company) decides whose requests are listed. The caller's own requests are
 * never listed (nobody approves their own).
 *
 * Query: module (leave|regularization|wfh_travel|overtime|expense|loan|offboarding), user_id.
 */
class ApprovalController extends Controller
{
    use AiScope;

    public function view_ai_all(Request $request)
    {
        try {
            $authUser = Auth::user();
            $tenantId = (int) $authUser->tenant_id;
            $features = app(FeatureService::class);
            $rbac = app(RbacService::class);

            // key => [feature, rbac module, loader]
            $modules = [
                'leave' => ['leave_management', 'leave', fn ($ids) => $this->leaves($tenantId, $ids)],
                'regularization' => ['regularization', 'attendance', fn ($ids) => $this->regularizations($tenantId, $ids)],
                'wfh_travel' => ['wfh_travel', 'requests', fn ($ids) => $this->workRequests($tenantId, $ids)],
                'overtime' => ['overtime', 'overtime', fn ($ids) => $this->overtime($tenantId, $ids)],
                'expense' => ['expense_management', 'expenses', fn ($ids) => $this->expenses($tenantId, $ids)],
                'loan' => ['loan_management', 'loans', fn ($ids) => $this->loans($tenantId, $ids)],
                'offboarding' => ['offboarding', 'offboarding', fn ($ids) => $this->offboarding($tenantId, $ids)],
                // Shift swaps / change requests have no RBAC module of their own — attendance approval scope.
                'shift_request' => ['custom_shift', 'attendance', fn ($ids) => $this->shiftRequests($tenantId, $ids)],
            ];
            if ($request->filled('module')) {
                if (! isset($modules[$request->module])) {
                    return response()->json(['success' => false, 'message' => 'Unknown module.'], 422);
                }
                $modules = [$request->module => $modules[$request->module]];
            }

            $data = [];
            $summary = [];
            foreach ($modules as $key => [$feature, $rbacModule, $loader]) {
                if (! $features->enabled($tenantId, $feature)) {
                    continue;
                }
                $scope = $rbac->scopeFor($authUser, $rbacModule, 'approve');
                if ($scope === null || $scope === 'own') {
                    continue; // no approval right for this module
                }
                $ids = $this->narrowToUser($this->visibleUserIds($authUser, $scope), $request->user_id);
                if ($ids !== null) {
                    $ids = array_values(array_diff($ids, [(int) $authUser->id]));
                }
                $items = $loader($ids)->filter(fn ($i) => (int) $i['employee']['id'] !== (int) $authUser->id)->values();
                $data[$key] = $items;
                $summary[$key] = ['count' => $items->count(), 'scope' => $scope];
            }

            return response()->json([
                'success' => true,
                'message' => 'Pending approvals fetched successfully',
                'data' => $data,
                'summary' => [
                    'total_pending' => array_sum(array_column($summary, 'count')),
                    'by_module' => $summary,
                ],
            ], 200);
        } catch (\Throwable $e) {
            return $this->failed('approvals', $e);
        }
    }

    private function scoped($query, string $column, ?array $ids)
    {
        return $ids === null ? $query : $query->whereIn($column, $ids ?: [0]);
    }

    private function emp($row): array
    {
        return ['id' => $row->user_id, 'name' => $row->employee_name, 'employee_id' => $row->employee_code];
    }

    private function shiftRequests(int $tenantId, ?array $ids)
    {
        $q = DB::table('shift_requests as s')->join('users as u', 'u.id', '=', 's.requester_id')
            ->leftJoin('users as c', 'c.id', '=', 's.counterpart_id')
            ->where('s.tenant_id', $tenantId)->where('s.status', 'pending_approval')
            ->select(['s.id', 's.request_no', 's.type', 's.requester_id as user_id', 'u.name as employee_name', 'u.employee_id as employee_code',
                'c.name as counterpart_name', 's.reason', 's.expires_at', 's.created_at']);
        $rows = $this->scoped($q, 's.requester_id', $ids)->orderBy('s.id')->get();
        $dates = DB::table('shift_request_items')->whereIn('shift_request_id', $rows->pluck('id')->all() ?: [0])
            ->selectRaw('shift_request_id, MIN(date) as first_date, MAX(date) as last_date')->groupBy('shift_request_id')->get()->keyBy('shift_request_id');

        return $rows->map(fn ($r) => [
            'id' => $r->id, 'employee' => $this->emp($r), 'request_no' => $r->request_no,
            'type' => $r->type === 'swap' ? 'Shift swap' : 'Shift change', 'swap_with' => $r->counterpart_name,
            'from' => $dates[$r->id]->first_date ?? null, 'to' => $dates[$r->id]->last_date ?? null,
            'reason' => $r->reason, 'decide_by' => $r->expires_at, 'applied_at' => $r->created_at,
        ]);
    }

    private function leaves(int $tenantId, ?array $ids)
    {
        $q = DB::table('leaves as l')->join('users as u', 'u.id', '=', 'l.user_id')
            ->leftJoin('leave_types as t', 't.id', '=', 'l.leave_type')
            ->where('l.tenant_id', $tenantId)->where('l.status', 'pending')
            ->select(['l.id', 'l.user_id', 'u.name as employee_name', 'u.employee_id as employee_code', 't.name as leave_type',
                'l.start_date', 'l.end_date', 'l.total_days', 'l.start_session', 'l.end_session', 'l.reason', 'l.created_at']);

        return $this->scoped($q, 'l.user_id', $ids)->orderBy('l.start_date')->get()->map(fn ($r) => [
            'id' => $r->id, 'employee' => $this->emp($r), 'leave_type' => $r->leave_type,
            'from' => $r->start_date, 'to' => $r->end_date, 'days' => (float) $r->total_days,
            'sessions' => [$r->start_session, $r->end_session], 'reason' => $r->reason, 'applied_at' => $r->created_at,
        ]);
    }

    private function regularizations(int $tenantId, ?array $ids)
    {
        $q = DB::table('attendance_regularizations as r')->join('users as u', 'u.id', '=', 'r.user_id')
            ->where('r.tenant_id', $tenantId)->where('r.status', 'pending')
            ->select(['r.id', 'r.user_id', 'u.name as employee_name', 'u.employee_id as employee_code', 'r.date',
                'r.request_type', 'r.in_time', 'r.out_time', 'r.reason', 'r.created_at']);

        return $this->scoped($q, 'r.user_id', $ids)->orderBy('r.date')->get()->map(fn ($r) => [
            'id' => $r->id, 'employee' => $this->emp($r), 'date' => $r->date, 'type' => $r->request_type,
            'in_time' => $r->in_time, 'out_time' => $r->out_time, 'reason' => $r->reason, 'applied_at' => $r->created_at,
        ]);
    }

    private function workRequests(int $tenantId, ?array $ids)
    {
        $q = DB::table('requests as r')->join('users as u', 'u.id', '=', 'r.user_id')
            ->leftJoin('request_types as t', 't.id', '=', 'r.request_type_id')
            ->where('r.tenant_id', $tenantId)->where('r.status', WorkRequest::STATUS_PENDING)
            ->select(['r.id', 'r.user_id', 'u.name as employee_name', 'u.employee_id as employee_code', 't.type_name',
                'r.start_date', 'r.end_date', 'r.reason', 'r.created_at']);

        return $this->scoped($q, 'r.user_id', $ids)->orderBy('r.start_date')->get()->map(fn ($r) => [
            'id' => $r->id, 'employee' => $this->emp($r), 'type' => $r->type_name,
            'from' => $r->start_date, 'to' => $r->end_date, 'reason' => $r->reason, 'applied_at' => $r->created_at,
        ]);
    }

    private function overtime(int $tenantId, ?array $ids)
    {
        $q = DB::table('overtime_requests as o')->join('users as u', 'u.id', '=', 'o.user_id')
            ->where('o.tenant_id', $tenantId)->where('o.status', 'pending')
            ->select(['o.id', 'o.user_id', 'u.name as employee_name', 'u.employee_id as employee_code', 'o.date',
                'o.overtime_hours', 'o.reason', 'o.created_at']);

        return $this->scoped($q, 'o.user_id', $ids)->orderBy('o.date')->get()->map(fn ($r) => [
            'id' => $r->id, 'employee' => $this->emp($r), 'date' => $r->date, 'hours' => (float) $r->overtime_hours,
            'reason' => $r->reason, 'applied_at' => $r->created_at,
        ]);
    }

    private function expenses(int $tenantId, ?array $ids)
    {
        $q = DB::table('expenses as e')->join('users as u', 'u.id', '=', 'e.user_id')
            ->where('e.tenant_id', $tenantId)->where('e.status', 'pending')->whereNull('e.deleted_at')
            ->select(['e.id', 'e.user_id', 'u.name as employee_name', 'u.employee_id as employee_code', 'e.expense_number',
                'e.requirement_type', 'e.amount', 'e.date', 'e.description', 'e.possible_duplicate_of', 'e.created_at']);

        return $this->scoped($q, 'e.user_id', $ids)->orderBy('e.date')->get()->map(fn ($r) => [
            'id' => $r->id, 'employee' => $this->emp($r), 'expense_number' => $r->expense_number,
            'type' => $r->requirement_type, 'amount' => (float) $r->amount, 'date' => $r->date,
            'description' => $r->description, 'possible_duplicate' => $r->possible_duplicate_of !== null, 'applied_at' => $r->created_at,
        ]);
    }

    private function loans(int $tenantId, ?array $ids)
    {
        $q = DB::table('loans as l')->join('users as u', 'u.id', '=', 'l.user_id')
            ->leftJoin('loan_categories as c', 'c.id', '=', 'l.loan_type_id')
            ->where('l.tenant_id', $tenantId)->where('l.status', 'pending')->whereNull('l.deleted_at')
            ->select(['l.id', 'l.user_id', 'u.name as employee_name', 'u.employee_id as employee_code', 'l.loan_number',
                'l.loan_kind', 'l.advance_month', 'c.name as category', 'l.amount', 'l.tenure_months', 'l.emi_amount', 'l.purpose', 'l.created_at']);

        return $this->scoped($q, 'l.user_id', $ids)->orderBy('l.created_at')->get()->map(fn ($r) => [
            'id' => $r->id, 'employee' => $this->emp($r), 'loan_number' => $r->loan_number,
            'kind' => $r->loan_kind ?? 'loan', 'advance_month' => $r->advance_month, 'category' => $r->category,
            'amount' => (float) $r->amount, 'tenure_months' => $r->tenure_months,
            'emi_amount' => $r->emi_amount !== null ? (float) $r->emi_amount : null, 'purpose' => $r->purpose, 'applied_at' => $r->created_at,
        ]);
    }

    private function offboarding(int $tenantId, ?array $ids)
    {
        $q = DB::table('offboarding_requests as o')->join('users as u', 'u.id', '=', 'o.employee_id')
            ->where('o.tenant_id', $tenantId)->where('o.status', OffboardingRequest::STATUS_PENDING_APPROVAL)->whereNull('o.deleted_at')
            ->select(['o.id', 'o.employee_id as user_id', 'u.name as employee_name', 'u.employee_id as employee_code', 'o.request_code',
                'o.resignation_date', 'o.last_working_date', 'o.reason', 'o.current_stage', 'o.created_at']);

        return $this->scoped($q, 'o.employee_id', $ids)->orderBy('o.last_working_date')->get()->map(fn ($r) => [
            'id' => $r->id, 'employee' => $this->emp($r), 'request_code' => $r->request_code,
            'resignation_date' => $r->resignation_date, 'last_working_date' => $r->last_working_date,
            'reason' => $r->reason, 'stage' => $r->current_stage, 'applied_at' => $r->created_at,
        ]);
    }
}
