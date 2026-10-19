<?php

namespace App\Http\Controllers\Leave;

use App\Http\Controllers\Controller;
use App\Models\FloatingWeekoffDay;
use App\Services\Attendance\FloatingWeekOffRequestService;
use App\Services\RbacService;
use App\Traits\AuthorizesByScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Leave → Floating week-offs: an employee on floating week-offs sees the
 * month's count and plans a day off ahead; approvers (leave approve
 * permission, their scope, never their own) decide. Rules live in
 * FloatingWeekOffRequestService.
 */
class FloatingWeekOffController extends Controller
{
    use AuthorizesByScope;

    public function __construct(private FloatingWeekOffRequestService $planner)
    {
    }

    public function index(Request $request)
    {
        $user = Auth::user();
        $tenantId = (int) $user->tenant_id;
        $canApprove = app(RbacService::class)->can($user, 'leave', 'approve');
        $team = collect();
        if ($canApprove) {
            $query = FloatingWeekoffDay::with(['user:id,name,employee_id', 'decider:id,name'])->where('source', 'planned')->where('user_id', '!=', $user->id)
                ->where('status', $request->status ?: 'pending');
            $team = $this->applyScope($query, 'user_id', $user, 'leave', 'approve')->orderBy('date')->limit(300)->get();
        }

        return view('client.leave.floating-weekoffs', [
            'months' => collect([now(), now()->addMonthNoOverflow()])->map(fn ($m) => ['label' => $m->format('F Y')]
                + $this->planner->monthStatus($tenantId, (int) $user->id, $m->format('Y-m'))),
            'mine' => FloatingWeekoffDay::with('decider:id,name')->where('user_id', $user->id)
                ->where('date', '>=', now()->subMonthNoOverflow()->startOfMonth()->toDateString())->orderByDesc('date')->limit(200)->get(),
            'team' => $team,
            'canApprove' => $canApprove,
            'status' => $request->status,
        ]);
    }

    /** Can this day be planned? (live hint for the date picker) */
    public function check(Request $request)
    {
        $data = $request->validate(['date' => 'required|date_format:Y-m-d']);
        $why = $this->planner->reason((int) Auth::user()->tenant_id, (int) Auth::id(), $data['date']);

        return response()->json(['status' => $why === null, 'message' => $why ?? 'You can ask for this day.']);
    }

    public function store(Request $request)
    {
        $data = $request->validate(['date' => 'required|date_format:Y-m-d', 'reason' => 'nullable|string|max:500']);
        try {
            $this->planner->request(Auth::user(), $data['date'], $data['reason'] ?? null);
        } catch (\RuntimeException $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['status' => true, 'message' => 'Floating week-off requested.']);
    }

    public function decide(Request $request, $id)
    {
        $data = $request->validate(['action' => 'required|in:approved,rejected', 'remarks' => 'nullable|string|max:500']);
        $row = FloatingWeekoffDay::where('source', 'planned')->findOrFail($id);
        if (! $this->scopeCoversOwner(Auth::user(), 'leave', 'approve', (int) $row->user_id)) {
            return response()->json(['status' => false, 'message' => \App\Support\SelfApproval::blocks(Auth::user(), (int) $row->user_id)
                ? \App\Support\SelfApproval::MESSAGE : 'You cannot decide for this employee.'], 403);
        }
        try {
            $row = $this->planner->decide($row, Auth::user(), $data['action'], $data['remarks'] ?? null);
        } catch (\RuntimeException $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['status' => true, 'message' => $row->status === 'pending' ? 'Recorded — waiting for the next approval level.' : "Request {$row->status}."]);
    }

    /** The employee withdraws their own; an approver may cancel an employee's approved day. */
    public function cancel($id)
    {
        $row = FloatingWeekoffDay::findOrFail($id);
        $own = (int) $row->user_id === (int) Auth::id();
        if (! $own && ! (app(RbacService::class)->can(Auth::user(), 'leave', 'approve') && $this->scopeCoversOwner(Auth::user(), 'leave', 'approve', (int) $row->user_id))) {
            return response()->json(['status' => false, 'message' => 'You cannot cancel this.'], 403);
        }
        try {
            $this->planner->cancel($row, Auth::user());
        } catch (\RuntimeException $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['status' => true, 'message' => 'Cancelled.']);
    }
}
