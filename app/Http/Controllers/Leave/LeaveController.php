<?php

namespace App\Http\Controllers\Leave;

use App\Http\Controllers\Controller;
use App\Models\Leave;
use App\Models\LeaveType;
use App\Models\User;
use App\Models\UserJobDetail;
use App\Models\LeaveBalance;
use App\Services\LeaveService;
use App\Services\RbacService;
use App\Traits\AuthorizesByScope;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class LeaveController extends Controller
{
    use AuthorizesByScope;
    use \App\Http\Controllers\Concerns\RaisesOnBehalf;

    /**
     * Admin / HR applies a leave for an employee (All Leaves → "Apply leave for
     * employee"). Created APPROVED with the balance deducted — LeaveService::applyOnBehalf,
     * shared with Employee 360.
     */
    public function storeOnBehalf(Request $request, LeaveService $leaves)
    {
        $employee = $this->onBehalfEmployee($request);
        $tenantId = (int) $employee->tenant_id;

        $data = Validator::make($request->all(), [
            'leave_type' => ['required', \Illuminate\Validation\Rule::exists('leave_types', 'id')->where('tenant_id', $tenantId)],
            'start_date' => 'required|date',
            'start_session' => 'required|in:session1,session2,fullday',
            'end_date' => 'required|date|after_or_equal:start_date',
            'end_session' => 'required|in:session1,session2,fullday',
            'reason' => 'required|string|max:500',
        ]);
        if ($data->fails()) {
            return response()->json(['success' => false, 'errors' => $data->errors()], 422);
        }

        try {
            $result = $leaves->applyOnBehalf(Auth::user(), $employee, (int) $request->leave_type, $request->start_date,
                $request->start_session, $request->end_date, $request->end_session, $request->reason);
        } catch (\DomainException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Leave on behalf failed: ' . $e->getMessage());

            return response()->json(['success' => false, 'message' => 'Could not apply the leave. Please try again.'], 500);
        }

        return response()->json([
            'success' => true,
            'message' => "{$result['type']->name} applied for {$employee->name} — {$result['days']} day(s), approved.",
        ]);
    }

    public function index(Request $request)
    {
        try {
            $userId = Auth::id();

            // Get leave summary calculations using models
            $balanceByType = LeaveBalance::with('leaveType')
                ->where('user_id', $userId)
                ->get();

            // Calculate total available balance (sum of all leave types)
            $available = $balanceByType->sum('balance');

            // Base query for leave applications using models
            $leaveQuery = Leave::with('leaveType')
                ->where('user_id', $userId);

            // Apply status filter if provided
            if ($request->filled('status')) {
                $leaveQuery->where('status', $request->status);
            }

            // Apply date range filter if provided
            if ($request->filled('from_date')) {
                $leaveQuery->whereDate('start_date', '>=', $request->from_date);
            }

            if ($request->filled('to_date')) {
                $leaveQuery->whereDate('start_date', '<=', $request->to_date);
            }

            if ($request->filled('leave_type')) {
                $leaveQuery->where('leave_type', $request->leave_type);
            }

            // Get filtered leaves
            $leaves = $leaveQuery->orderBy('created_at', 'desc')->get();

            // Transform file URLs
            $leaves->transform(function ($leave) {
                $leave->file = file_url($leave->file, 'leave');
                return $leave;
            });

            // Get counts based on filters — one conditional-SUM query instead
            // of four separate sum() round trips over the same filtered set.
            $statsQuery = Leave::where('user_id', $userId);
            if ($request->filled('status')) {
                $statsQuery->where('status', $request->status);
            }
            if ($request->filled('from_date')) {
                $statsQuery->whereDate('start_date', '>=', $request->from_date);
            }
            if ($request->filled('to_date')) {
                $statsQuery->whereDate('start_date', '<=', $request->to_date);
            }

            if ($request->filled('leave_type')) {
                $statsQuery->where('leave_type', $request->leave_type);
            }

            $stats = $statsQuery->selectRaw("
                COALESCE(SUM(leave_count), 0) as total_leave,
                COALESCE(SUM(CASE WHEN status = 'pending' THEN leave_count ELSE 0 END), 0) as pending_leave,
                COALESCE(SUM(CASE WHEN status = 'approved' THEN leave_count ELSE 0 END), 0) as approved_leave,
                COALESCE(SUM(CASE WHEN status = 'cancelled' THEN leave_count ELSE 0 END), 0) as reject_leave
            ")->first();

            $data = [
                'totalLeave' => $stats->total_leave,
                'pendingLeave' => $stats->pending_leave,
                'approvedLeave' => $stats->approved_leave,
                'balanceLeave' => max(0, $available),
                'unpaidLeave' => $available < 0 ? abs($available) : 0,
                'rejectLeave' => $stats->reject_leave,
                'leaves' => $leaves,
                'leaveTypes' => LeaveType::where('status', 1)->where('tenant_id', Auth::user()->tenant_id)->get(),
                'filters' => [
                    'status' => $request->status,
                    'from_date' => $request->from_date,
                    'to_date' => $request->to_date,
                    'leave_type' => $request->leave_type,
                ]
            ];

            return view('client.leave.leave', $data);
        } catch (Exception $e) {
            return back()->withErrors([
                'success' => false,
                'message' => "An error occurred. Please try again later." . $e->getMessage(),
            ])->withInput();
        }
    }

    public function create()
    {
        $user = Auth::user();
        $userId = $user->id;
        // Explicit tenant_id filter as defense-in-depth alongside the
        // TenantTrait global scope, which silently no-ops outside a request
        // context (console commands/queued jobs).
        $data['leaveTypes'] = LeaveType::where('status', 1)->where('tenant_id', $user->tenant_id)->get();
        $balances = LeaveBalance::where('user_id', $userId)
                    ->get()
                    ->keyBy('leave_type_id');
        
        foreach ($data['leaveTypes'] as $type) {
            $type->available_balance = isset($balances[$type->id]) ? (float) $balances[$type->id]->balance : 0;
        }

        // ?date=YYYY-MM-DD pre-fills the dates (the Employee Dashboard's "Apply Leave" on an absent day).
        $date = (string) request()->query('date', '');
        $data['prefillLeaveDate'] = preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) && strtotime($date) ? $date : null;

        return view('client.leave.apply-leave', $data);
    }

    public function store(Request $request)
    {
        $request->validate([
            'leave_type' => 'required|exists:leave_types,id',
            'start_date' => 'required|date',
            'start_session' => 'required|in:session1,session2,fullday',
            'end_date' => 'required|date',
            'end_session' => 'required|in:session1,session2,fullday',
            'file' => 'nullable|file|max:2048',
            'reason' => 'required|max:500'
        ]);

        try {
            $user = Auth::user();
            $start = Carbon::parse($request->start_date);
            $end = Carbon::parse($request->end_date);
            $leaveTypeId = $request->leave_type;
            $leaveType = LeaveType::find($leaveTypeId);
            $leaveService = app(LeaveService::class);
            $isLWP = $leaveService->isLwpId($leaveTypeId);

            if ($end->lt($start)) {
                return back()
                    ->withInput()
                    ->with('error', 'End date cannot be before start date.');
            }

            // Per-type policy: minimum notice period / max consecutive days
            // (leave_types columns — nullable, so existing types with
            // neither configured are completely unaffected). An employee's
            // custom values (Employee 360 → Policies) win over the type's,
            // and a type switched off for them is refused.
            if ($leaveType) {
                $refusal = app(\App\Services\EmployeePolicyService::class)
                    ->leaveRefusal((int) $user->tenant_id, (int) $user->id, $leaveType, $start, $end);
                if ($refusal) {
                    return back()->withInput()->with('error', $refusal);
                }
            }

            // One request, one row: total_days excludes weekends/holidays,
            // but start_date/end_date still store the full requested range.
            $totalLeaveDays = $leaveService->computeLeaveDays(
                $start->copy(),
                $end->copy(),
                $request->start_session,
                $request->end_session,
                $user->tenant_id
            );

            if ($totalLeaveDays <= 0) {
                return back()
                    ->withInput()
                    ->with('error', 'The selected date range has no working days to apply leave for.');
            }

            // CHECK AVAILABLE BALANCE (SKIP FOR LWP)
            if (!$isLWP) {
                $balanceRecord = LeaveBalance::where('user_id', $user->id)
                    ->where('leave_type_id', $leaveTypeId)
                    ->first();

                $availableBalance = $balanceRecord ? (float) $balanceRecord->balance : 0;

                if ($availableBalance < $totalLeaveDays) {
                    return back()
                        ->withInput()
                        ->with('error', "Insufficient leave balance. You have {$availableBalance} days available but requested {$totalLeaveDays} days.");
                }
            }

            $filePath = $this->handleFileUpload($request);

            $leave = Leave::create([
                'user_id' => $user->id,
                'leave_type' => $leaveTypeId,
                'start_date' => $start->format('Y-m-d'),
                'start_session' => $request->start_session,
                'end_date' => $end->format('Y-m-d'),
                'end_session' => $request->end_session,
                'leave_count' => $totalLeaveDays,
                'total_days' => $totalLeaveDays,
                'reason' => $request->reason,
                'status' => 'pending',
                'file' => $filePath,
            ]);

            // Was mobile-API-only: the web flow submitted leave requests
            // with nobody (reporting head/HR/admin) ever notified.
            try {
                app(\App\Services\LeaveNotificationService::class)->notifyLeaveSubmitted($leave->fresh());
            } catch (\Throwable $e) {
                // never block on notification
            }

            return redirect()
                ->route('leave.view')
                ->with('success', 'Leave request submitted successfully');
        } catch (Exception $e) {
            return back()
                ->withInput()
                ->with('error', 'Something went wrong. Please try again.' . $e->getMessage());
        }
    }

    private function handleFileUpload($request)
    {
        return $request->hasFile('file')
            ? file_storage()->upload($request->file('file'), 'leave')->path
            : null;
    }

    public function update(Request $request, $id)
    {
        try {
            $id = decrypt($id);
            $userId = Auth::id();

            $data['leave'] = Leave::where('id', $id)
                ->where('user_id', $userId)
                ->first();

            if (!$data['leave']) {
                return redirect()->route('leave.view')->with('error', 'Leave application not found.');
            }

            $data['leaveTypes'] = LeaveType::all();
            return view('client.leave.update-leave', $data);
        } catch (Exception $e) {
            return back()->withErrors('error', 'Something went wrong. ' . $e->getMessage());
        }
    }

    public function update_store(Request $request, $id)
    {
        $request->validate([
            'leave_type' => 'required|exists:leave_types,id',
            'start_date' => 'required|date',
            'start_session' => 'required|in:session1,session2,fullday',
            'end_date' => 'required|date',
            'end_session' => 'required|in:session1,session2,fullday',
            'file' => 'nullable|file|max:2048',
            'reason' => 'required|max:500'
        ]);

        try {
            $id = decrypt($id);
            $user = Auth::user();

            $leave = Leave::where('id', $id)
                ->where('user_id', $user->id)
                ->first();

            if (!$leave) {
                return redirect()
                    ->route('leave.view')
                    ->with('error', 'Leave record not found.');
            }

            if ($leave->status != 'pending') {
                return redirect()
                    ->route('leave.view')
                    ->with('error', 'Only pending leaves can be updated.');
            }

            // Was: a private single-session helper (calculateLeaveCount)
            // that ignored weekends/holidays and end_session/end_date
            // entirely, silently producing a wrong leave_count/total_days
            // whenever a multi-day leave was edited. Now uses the same
            // computation store() uses, so apply and edit never drift.
            $start = Carbon::parse($request->start_date);
            $end = Carbon::parse($request->end_date);
            $leaveTypeId = (int) $request->leave_type;
            $leaveService = app(LeaveService::class);
            $isLwp = $leaveService->isLwpId($leaveTypeId);

            if ($end->lt($start)) {
                return back()
                    ->withInput()
                    ->with('error', 'End date cannot be before start date.');
            }

            $totalLeaveDays = $leaveService->computeLeaveDays(
                $start->copy(),
                $end->copy(),
                $request->start_session,
                $request->end_session,
                $user->tenant_id
            );

            if ($totalLeaveDays <= 0) {
                return back()
                    ->withInput()
                    ->with('error', 'The selected date range has no working days to apply leave for.');
            }

            if (!$isLwp) {
                $balanceRecord = LeaveBalance::where('user_id', $user->id)
                    ->where('leave_type_id', $leaveTypeId)
                    ->first();
                $availableBalance = $balanceRecord ? (float) $balanceRecord->balance : 0;

                if ($availableBalance < $totalLeaveDays) {
                    return back()
                        ->withInput()
                        ->with('error', "Insufficient leave balance. You have {$availableBalance} days available but requested {$totalLeaveDays} days.");
                }
            }

            DB::beginTransaction();

            $filePath = $this->handleFileUpload($request);
            if ($filePath) {
                // Replaced document: drop the old one once this update commits.
                file_storage()->deleteAfterCommit($leave->file, 'leave');
            }

            $leave->update([
                'leave_type' => $leaveTypeId,
                'start_date' => $request->start_date,
                'start_session' => $request->start_session,
                'end_date' => $request->end_date,
                'end_session' => $request->end_session,
                'leave_count' => $totalLeaveDays,
                'total_days' => $totalLeaveDays,
                'reason' => $request->reason,
                'file' => $filePath ?? $leave->file,
            ]);

            DB::commit();

            return redirect()
                ->route('leave.view')
                ->with('success', 'Leave request updated successfully.');
        } catch (Exception $e) {
            DB::rollBack();
            return back()
                ->withErrors('error', 'Failed to update leave. Please try again.')
                ->withInput();
        }
    }

    public function delete(Request $request, $id)
    {
        try {
            $id = decrypt($id);
            $userId = Auth::id();

            $leave = Leave::where('id', $id)
                ->where('user_id', $userId)
                ->first();

            if (!$leave) {
                return redirect()
                    ->route('leave.view')
                    ->withErrors(['error' => 'Leave application not found.']);
            }

            if ($leave->status !== 'pending') {
                return redirect()
                    ->route('leave.view')
                    ->withErrors(['error' => 'Only pending leaves can be deleted.']);
            }

            $leave->delete();

            return back()->with('success', 'Leave request deleted successfully.');
        } catch (Exception $e) {
            return back()->withErrors([
                'error' => 'Something went wrong. ' . $e->getMessage()
            ]);
        }
    }

    public function view_all(Request $request)
    {
        $authUser = Auth::user();

        // This is the admin/HR/manager "manage everyone's leave" screen, not
        // the self-service one — an 'own'-scoped grant (or no grant at all)
        // means this screen isn't for them; they use index() instead.
        $scope = app(RbacService::class)->scopeFor($authUser, 'leave', 'view');
        if (!in_array($scope, ['team', 'company'], true)) {
            abort(403, 'You do not have permission to view team leave requests.');
        }
        $isTeamScoped = $scope === 'team';

        // Build query using Eloquent relationships
        $leaveQuery = Leave::with(['user', 'user.jobDetails', 'leaveType'])
            ->join('users', 'leaves.user_id', '=', 'users.id')
            ->leftJoin('user_job_details', 'users.id', '=', 'user_job_details.user_id')
            ->leftJoin('leave_types', 'leaves.leave_type', '=', 'leave_types.id')
            ->select(
                'leaves.*',
                'users.name as user_name',
                'users.email as user_email',
                'users.employee_id as employee_id',
                'leave_types.name as leave_type_name',
                'user_job_details.department',
                'user_job_details.designation',
                'user_job_details.reporting_head'
            );

        // 🔐 Permission-driven scope (was: hardcoded !in_array(role, [admin,hr]))
        if ($isTeamScoped) {
            // Team scope — show only their team's leaves (any reporting head)
            $leaveQuery->whereIn('users.id', function ($q) use ($authUser) {
                $q->select('user_id')->from('user_reporting_heads')->where('reporting_head_id', $authUser->id);
            });
        }
        // Admin and HR can see all leaves

        // 🔍 Filters
        if ($request->filled('status')) {
            $leaveQuery->where('leaves.status', $request->status);
        }

        if ($request->filled('from_date')) {
            $leaveQuery->whereDate('leaves.start_date', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $leaveQuery->whereDate('leaves.start_date', '<=', $request->to_date);
        }

        if ($request->filled('user_id')) {
            $leaveQuery->where('leaves.user_id', $request->user_id);
        }

        if ($request->filled('leave_type')) {
            $leaveQuery->where('leaves.leave_type', $request->leave_type);
        }

        if ($request->filled('search')) {
            $searchTerm = '%' . $request->search . '%';
            $leaveQuery->where(function ($q) use ($searchTerm) {
                $q->where('users.name', 'LIKE', $searchTerm)
                    ->orWhere('users.email', 'LIKE', $searchTerm)
                    ->orWhere('leave_types.name', 'LIKE', $searchTerm)
                    ->orWhere('leaves.reason', 'LIKE', $searchTerm);
            });
        }

        // Get paginated results
        $leaves = $leaveQuery
            ->orderBy('leaves.created_at', 'desc')
            ->paginate(15)
            ->withQueryString();

        // Transform file URLs
        $leaves->getCollection()->transform(function ($leave) {
            $leave->file = file_url($leave->file, 'leave');
            return $leave;
        });

        // Get counts for status cards — one conditional-SUM query instead of
        // four separate count() round trips over the same scoped set.
        $countsQuery = Leave::query()
            ->join('users', 'leaves.user_id', '=', 'users.id')
            ->leftJoin('user_job_details', 'users.id', '=', 'user_job_details.user_id');

        if ($isTeamScoped) {
            $countsQuery->whereIn('users.id', function ($q) use ($authUser) {
                $q->select('user_id')->from('user_reporting_heads')->where('reporting_head_id', $authUser->id);
            });
        }

        $counts = $countsQuery->selectRaw("
            COUNT(*) as total_leaves,
            COALESCE(SUM(CASE WHEN leaves.status = 'pending' THEN 1 ELSE 0 END), 0) as pending_leaves,
            COALESCE(SUM(CASE WHEN leaves.status = 'approved' THEN 1 ELSE 0 END), 0) as approved_leaves,
            COALESCE(SUM(CASE WHEN leaves.status = 'cancelled' THEN 1 ELSE 0 END), 0) as cancelled_leaves
        ")->first();

        $totalLeaves = $counts->total_leaves;
        $pendingLeaves = $counts->pending_leaves;
        $approvedLeaves = $counts->approved_leaves;
        $cancelledLeaves = $counts->cancelled_leaves;

        // Get leave types for filter dropdown using Eloquent
        $leaveTypes = LeaveType::where('status', 1)
            ->orderBy('name')
            ->get(['id', 'name']);

        // Get employees for filter dropdown using Eloquent with proper relationship
        $employeesQuery = User::where('status', 1)
            ->with(['jobDetails']);

        if ($isTeamScoped) {
            $employeesQuery->managedBy($authUser->id);
        }

        $employees = $employeesQuery
            ->select('id', 'name', 'email', 'employee_id')
            ->orderBy('name')
            ->get();

        $data = [
            'leaves' => $leaves,
            'totalLeaves' => $totalLeaves,
            'pendingLeaves' => $pendingLeaves,
            'approvedLeaves' => $approvedLeaves,
            'cancelledLeaves' => $cancelledLeaves,
            'leaveTypes' => $leaveTypes,
            'employees' => $employees,
            'userRole' => $authUser->role,
            'filters' => $request->all()
        ];

        return view('client.leave.view-all-leave', $data);
    }

    public function updateStatus(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:approved,cancelled',
            'remarks' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        DB::beginTransaction();

        try {
            $authUser = Auth::user();

            // Coarse "can this role approve leave at all" is enforced at the
            // route level (permission:leave,approve). What's left here is
            // "does THIS specific leave's owner fall within their granted
            // scope" — company covers anyone, team covers direct reports
            // (+ self), own would only cover their own leave (not
            // meaningful for an approval action, but scopeCoversOwner
            // handles it correctly regardless of which scope a role ends
            // up configured with).
            $leave = Leave::with('user.jobDetails')->find($id);

            if (!$leave) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Leave not found.'
                ], 404);
            }

            if (!$this->scopeCoversOwner($authUser, 'leave', 'approve', $leave->user_id)) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'You are not authorized to update this leave request. Only the reporting head (or an admin/HR user) can process it.'
                ], 403);
            }

            // Revoking an already-approved leave (employee returned early, HR
            // correcting a mistake) is a distinct transition from deciding a
            // still-pending one — it restores the balance deducted at
            // approval time instead of going through the (already finalised)
            // approval workflow again.
            if ($leave->status === 'approved' && $request->status === 'cancelled') {
                $result = app(LeaveService::class)->cancelApprovedLeave($leave, $authUser, $request->remarks);
                if (!$result['success']) {
                    DB::rollBack();
                    return response()->json($result, 400);
                }
                DB::commit();

                try {
                    $leaveWithUser = Leave::with('user')->find($leave->id);
                    if ($leaveWithUser) {
                        // An approved leave being revoked is a cancellation, not a rejection.
                        app(\App\Services\LeaveNotificationService::class)->notifyLeaveCancelled($leaveWithUser, $request->remarks);
                    }
                } catch (\Throwable $e) {
                    // never block on notification
                }

                return response()->json($result);
            }

            // Check if leave is pending
            if ($leave->status !== 'pending') {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Leave request has already been processed.'
                ], 400);
            }

            // Route through the generic multi-level approval engine when the
            // tenant has configured a 'leave' workflow (Settings > Approvals
            // — the same engine Overtime/Regularization already use).
            // decide() returns null when no workflow is configured, so this
            // is a zero-risk opt-in: every tenant without one falls straight
            // through to the unchanged direct-approval path below.
            try {
                // ApprovalService::act() only accepts 'approved'/'rejected';
                // Leave's own status vocabulary uses 'cancelled' for a
                // rejection, so translate before calling decide().
                $workflowAction = $request->status === 'cancelled' ? 'rejected' : $request->status;
                $ar = app(\App\Services\Approvals\ApprovalService::class)
                    ->decide('leave', $leave, $authUser, $workflowAction, $request->remarks);

                if ($ar !== null) {
                    DB::commit();
                    $message = $ar->status === 'pending'
                        ? 'Recorded. Awaiting the next approval level.'
                        : ($ar->status === 'approved' ? 'Leave approved successfully' : 'Leave rejected successfully');

                    return response()->json(['success' => true, 'message' => $message, 'workflow_status' => $ar->status]);
                }
            } catch (\App\Exceptions\InsufficientLeaveBalanceException $e) {
                DB::rollBack();
                return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
            } catch (\RuntimeException $e) {
                DB::rollBack();
                return response()->json(['success' => false, 'message' => $e->getMessage()], 403);
            }

            // No workflow configured for this tenant — single source of
            // truth in LeaveService (was previously duplicated here with a
            // bug: this method ignored approveLeave()'s return value
            // entirely, so an "insufficient balance" rejection still left
            // the transaction committed and reported "success" to the caller).
            $leaveService = app(LeaveService::class);

            $result = $request->status === 'approved'
                ? $leaveService->approvePendingLeave($leave, $authUser, $request->remarks)
                : $leaveService->cancelPendingLeave($leave, $authUser, $request->remarks);

            if (!$result['success']) {
                DB::rollBack();
                return response()->json($result, 400);
            }

            DB::commit();

            // Was mobile-API-only: the web flow's direct (no-workflow)
            // approve/reject never notified the employee.
            try {
                $leaveWithUser = Leave::with('user')->find($leave->id);
                if ($leaveWithUser) {
                    $request->status === 'approved'
                        ? app(\App\Services\LeaveNotificationService::class)->notifyLeaveApproved($leaveWithUser, $request->remarks)
                        : app(\App\Services\LeaveNotificationService::class)->notifyLeaveRejected($leaveWithUser, $request->remarks);
                }
            } catch (\Throwable $e) {
                // never block on notification
            }

            return response()->json($result);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update status: ' . $e->getMessage()
            ], 500);
        }
    }
}
