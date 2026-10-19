<?php

namespace App\Http\Controllers\Leave;

use App\Http\Controllers\Controller;
use App\Models\CompOffCredit;
use App\Services\Leave\CompOffService;
use App\Services\RbacService;
use App\Traits\AuthorizesByScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Leave → Comp-off: an employee claims a worked week-off / holiday as a day off
 * to take later; approvers (leave approve permission, their scope, never their
 * own) decide. Rules live in CompOffService.
 */
class CompOffController extends Controller
{
    use AuthorizesByScope;

    public function __construct(private CompOffService $compOff)
    {
    }

    public function index(Request $request)
    {
        $user = Auth::user();
        $tenantId = (int) $user->tenant_id;
        $canApprove = app(RbacService::class)->can($user, 'leave', 'approve');
        $team = collect();
        if ($canApprove) {
            $query = CompOffCredit::with(['user:id,name,employee_id', 'decider:id,name'])->where('user_id', '!=', $user->id)
                ->when($request->status, fn ($q, $s) => $q->where('status', $s), fn ($q) => $q->where('status', 'pending'));
            $team = $this->applyScope($query, 'user_id', $user, 'leave', 'approve')->orderByDesc('worked_date')->limit(300)->get();
        }

        return view('client.leave.comp-off', [
            'mine' => CompOffCredit::with('decider:id,name')->where('user_id', $user->id)->orderByDesc('worked_date')->limit(200)->get(),
            'balance' => $this->compOff->balance($tenantId, (int) $user->id),
            'claimable' => $this->compOff->claimableDays($tenantId, (int) $user->id, now()->subDays(60)->toDateString(), now()->subDay()->toDateString()),
            'team' => $team,
            'canApprove' => $canApprove,
            'status' => $request->status,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate(['worked_date' => 'required|date_format:Y-m-d', 'reason' => 'nullable|string|max:500']);
        try {
            $credit = $this->compOff->request(Auth::user(), $data['worked_date'], $data['reason'] ?? null);
        } catch (\RuntimeException $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['status' => true, 'message' => "Comp-off requested for {$credit->days} day(s)."]);
    }

    public function decide(Request $request, $id)
    {
        $data = $request->validate(['action' => 'required|in:approved,rejected', 'remarks' => 'nullable|string|max:500']);
        $credit = CompOffCredit::findOrFail($id);
        if (! $this->scopeCoversOwner(Auth::user(), 'leave', 'approve', (int) $credit->user_id)) {
            return response()->json(['status' => false, 'message' => \App\Support\SelfApproval::blocks(Auth::user(), (int) $credit->user_id)
                ? \App\Support\SelfApproval::MESSAGE : 'You cannot decide comp-off for this employee.'], 403);
        }
        try {
            $credit = $this->compOff->decide($credit, Auth::user(), $data['action'], $data['remarks'] ?? null);
        } catch (\RuntimeException $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 422);
        }
        $message = $credit->status === 'pending' ? 'Recorded — waiting for the next approval level.' : "Comp-off {$credit->status}.";

        return response()->json(['status' => true, 'message' => $message]);
    }

    public function cancel($id)
    {
        try {
            $this->compOff->cancel(CompOffCredit::findOrFail($id), Auth::user());
        } catch (\RuntimeException $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['status' => true, 'message' => 'Request cancelled.']);
    }
}
