<?php

namespace App\Http\Controllers\Attendance;

use App\Http\Controllers\Controller;
use App\Models\OvertimeRequest;
use App\Models\OvertimeSetting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Services\RbacService;
use App\Services\Attendance\OvertimeApprovalService;
use App\Traits\AuthorizesByScope;

class OvertimeController extends Controller
{
    use AuthorizesByScope;

    /**
     * Display list of overtime requests
     */
    public function index(Request $request)
    {
        $tenantId = auth()->user()->tenant_id ?? null;
        $userId = auth()->user()->id;

        // Build query
        $query = OvertimeRequest::where('tenant_id', $tenantId)
            ->where('user_id', $userId);

        // Apply filters
        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        if ($request->has('from_date') && $request->from_date) {
            $query->whereDate('date', '>=', $request->from_date);
        }

        if ($request->has('to_date') && $request->to_date) {
            $query->whereDate('date', '<=', $request->to_date);
        }

        // Get paginated results
        $requests = $query->orderBy('date', 'desc')->paginate(15);

        // Calculate statistics
        $totalRequests = OvertimeRequest::where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->count();

        $totalHours = OvertimeRequest::where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->sum('overtime_hours');

        $pendingRequests = OvertimeRequest::where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->where('status', 'pending')
            ->count();

        $pendingHours = OvertimeRequest::where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->where('status', 'pending')
            ->sum('overtime_hours');

        $approvedRequests = OvertimeRequest::where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->where('status', 'approved')
            ->count();

        $approvedHours = OvertimeRequest::where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->where('status', 'approved')
            ->sum(DB::raw('COALESCE(approved_hours, overtime_hours)'));

        $rejectedRequests = OvertimeRequest::where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->where('status', 'rejected')
            ->count();

        $rejectedHours = OvertimeRequest::where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->where('status', 'rejected')
            ->sum('overtime_hours');

        return view('client.overtime.request', compact(
            'requests',
            'totalRequests',
            'totalHours',
            'pendingRequests',
            'pendingHours',
            'approvedRequests',
            'approvedHours',
            'rejectedRequests',
            'rejectedHours'
        ));
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'date' => 'required|date|after_or_equal:today',
            'overtime_hours' => 'required|numeric|min:0.5|max:24',
            'reason' => 'required|string|min:3|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $tenantId = auth()->user()->tenant_id ?? null;
        $userId = auth()->user()->id;

        // Check if request already exists for this date
        $existingRequest = OvertimeRequest::where('user_id', $userId)
            ->where('date', $request->date)
            ->first();

        if ($existingRequest) {
            return response()->json([
                'success' => false,
                'message' => 'You have already submitted an overtime request for this date'
            ], 422);
        }

        // Get settings
        $settings = OvertimeSetting::where('tenant_id', $tenantId)
            ->orWhereNull('tenant_id')
            ->first();

        // Validate against max hours per day
        if ($settings && $settings->max_hours_per_day) {
            if ($request->overtime_hours > $settings->max_hours_per_day) {
                return response()->json([
                    'success' => false,
                    'message' => "Overtime hours cannot exceed {$settings->max_hours_per_day} hours per day"
                ], 422);
            }
        }

        // Determine status
        $status = 'pending';
        if ($settings && !$settings->require_approval) {
            $status = 'approved';
        } elseif ($settings && $settings->auto_approve_limit && $request->overtime_hours <= $settings->auto_approve_limit) {
            $status = 'approved';
        }

        // Create request
        $overtimeRequest = OvertimeRequest::create([
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'date' => $request->date,
            'overtime_hours' => $request->overtime_hours,
            'reason' => $request->reason,
            'status' => $status,
            'approved_at' => $status == 'approved' ? now() : null,
        ]);

        $message = $status == 'approved'
            ? 'Overtime request auto-approved successfully'
            : 'Overtime request submitted successfully';

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $overtimeRequest
        ]);
    }

    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'date' => 'required|date|after_or_equal:today',
            'overtime_hours' => 'required|numeric|min:0.5|max:24',
            'reason' => 'required|string|min:3|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $tenantId = auth()->user()->tenant_id ?? null;
        $userId = auth()->user()->id;

        // Find the request
        $overtimeRequest = OvertimeRequest::where('id', $id)
            ->where('user_id', $userId)
            ->where('tenant_id', $tenantId)
            ->first();

        if (!$overtimeRequest) {
            return response()->json([
                'success' => false,
                'message' => 'Overtime request not found'
            ], 404);
        }

        // Only allow update if status is pending
        if ($overtimeRequest->status != 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Only pending requests can be updated'
            ], 422);
        }

        // Check date uniqueness (excluding current request)
        $existingRequest = OvertimeRequest::where('user_id', $userId)
            ->where('date', $request->date)
            ->where('id', '!=', $id)
            ->first();

        if ($existingRequest) {
            return response()->json([
                'success' => false,
                'message' => 'You already have an overtime request for this date'
            ], 422);
        }

        // Get settings for validation
        $settings = OvertimeSetting::where('tenant_id', $tenantId)
            ->orWhereNull('tenant_id')
            ->first();

        if ($settings && $settings->max_hours_per_day) {
            if ($request->overtime_hours > $settings->max_hours_per_day) {
                return response()->json([
                    'success' => false,
                    'message' => "Overtime hours cannot exceed {$settings->max_hours_per_day} hours per day"
                ], 422);
            }
        }

        // Update the request
        $overtimeRequest->update([
            'date' => $request->date,
            'overtime_hours' => $request->overtime_hours,
            'reason' => $request->reason,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Overtime request updated successfully',
            'data' => $overtimeRequest
        ]);
    }

    public function destroy($id)
    {
        $tenantId = auth()->user()->tenant_id ?? null;
        $userId = auth()->user()->id;

        // Find the request
        $overtimeRequest = OvertimeRequest::where('id', $id)
            ->where('user_id', $userId)
            ->where('tenant_id', $tenantId)
            ->first();

        if (!$overtimeRequest) {
            return response()->json([
                'success' => false,
                'message' => 'Overtime request not found'
            ], 404);
        }

        // Only allow deletion if status is pending
        if ($overtimeRequest->status != 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Only pending requests can be cancelled'
            ], 422);
        }

        // Delete the request
        $overtimeRequest->delete();

        return response()->json([
            'success' => true,
            'message' => 'Overtime request cancelled successfully'
        ]);
    }

    public function viewAll(Request $request)
    {
        try {
            $authUser = Auth::user();
            $tenantId = session('tenant_id') ?? $authUser->tenant_id;

            // Get settings
            $settings = OvertimeSetting::where('tenant_id', $tenantId)
                ->orWhereNull('tenant_id')
                ->first();

            // Build the query
            $query = OvertimeRequest::with(['user', 'approver'])
                ->join('users', 'overtime_requests.user_id', '=', 'users.id')
                ->leftJoin('user_job_details', 'overtime_requests.user_id', '=', 'user_job_details.user_id')
                ->select(
                    'overtime_requests.*',
                    'users.name as user_name',
                    'users.email as user_email',
                    'users.employee_id',
                    'user_job_details.department',
                    'user_job_details.designation',
                    'user_job_details.reporting_head'
                );

            // Permission-based access control
            $overtimeScope = app(RbacService::class)->scopeFor($authUser, 'overtime', 'view');
            if ($overtimeScope === null) {
                abort(403, 'You do not have permission to view overtime requests.');
            } elseif ($overtimeScope === 'team') {
                $query->where(function ($q) use ($authUser) {
                    $q->whereIn('users.id', function ($sub) use ($authUser) {
                        $sub->select('user_id')->from('user_reporting_heads')->where('reporting_head_id', $authUser->id);
                    })->orWhere('overtime_requests.user_id', $authUser->id);
                });
            } elseif ($overtimeScope === 'own') {
                $query->where('overtime_requests.user_id', $authUser->id);
            }

            // Apply filters
            if ($request->filled('status')) {
                $query->where('overtime_requests.status', $request->status);
            }

            if ($request->filled('request_type_filter')) {
                // If you have request types (WFH/Travel) - adjust as needed
                // $query->where('request_type', $request->request_type_filter);
            }

            if ($request->filled('from_date')) {
                $query->whereDate('overtime_requests.date', '>=', $request->from_date);
            }

            if ($request->filled('to_date')) {
                $query->whereDate('overtime_requests.date', '<=', $request->to_date);
            }

            if ($request->filled('employee') && $overtimeScope !== 'own') {
                $query->where('overtime_requests.user_id', $request->employee);
            }

            if ($request->filled('search')) {
                $searchTerm = '%' . $request->search . '%';
                $query->where(function ($q) use ($searchTerm) {
                    $q->where('users.name', 'LIKE', $searchTerm)
                        ->orWhere('users.email', 'LIKE', $searchTerm)
                        ->orWhere('users.employee_id', 'LIKE', $searchTerm)
                        ->orWhere('overtime_requests.reason', 'LIKE', $searchTerm);
                });
            }

            // ==================== STATISTICS CALCULATIONS ====================

            // Base query for counts (without pagination)
            $countsQuery = clone $query;

            // Total statistics
            $totalRequests = (clone $countsQuery)->count();
            $totalHours = (clone $countsQuery)->sum('overtime_hours');

            // Pending statistics
            $pendingRequests = (clone $countsQuery)->where('overtime_requests.status', 'pending')->count();
            $pendingHours = (clone $countsQuery)->where('overtime_requests.status', 'pending')->sum('overtime_hours');

            // Approved statistics
            $approvedRequests = (clone $countsQuery)->where('overtime_requests.status', 'approved')->count();
            $approvedHours = (clone $countsQuery)->where('overtime_requests.status', 'approved')
                ->get()
                ->sum(function ($request) {
                    return $request->approved_hours ?? $request->overtime_hours;
                });

            // Rejected statistics
            $rejectedRequests = (clone $countsQuery)->where('overtime_requests.status', 'rejected')->count();
            $rejectedHours = (clone $countsQuery)->where('overtime_requests.status', 'rejected')->sum('overtime_hours');

            // This month statistics
            $thisMonth = now();
            $thisMonthRequests = (clone $countsQuery)
                ->whereYear('overtime_requests.date', $thisMonth->year)
                ->whereMonth('overtime_requests.date', $thisMonth->month)
                ->count();

            $thisMonthHours = (clone $countsQuery)
                ->whereYear('overtime_requests.date', $thisMonth->year)
                ->whereMonth('overtime_requests.date', $thisMonth->month)
                ->sum('overtime_hours');

            // Get paginated results
            $requests = $query->orderBy('overtime_requests.created_at', 'desc')
                ->paginate(15)
                ->withQueryString();

            // Get employees for filter dropdown (role-based)
            $employeesQuery = User::where('status', 1)
                ->select('id', 'name', 'email', 'employee_id');

            if ($overtimeScope === 'team') {
                $employeesQuery->where(function ($q) use ($authUser) {
                    $q->managedBy($authUser->id)->orWhere('id', $authUser->id);
                });
            } elseif ($overtimeScope !== 'company') {
                $employeesQuery->where('id', $authUser->id);
            }

            $employees = $employeesQuery->orderBy('name')->get();


            // Get pending count for badge
            $pendingCount = OvertimeRequest::where('tenant_id', $tenantId)
                ->where('status', 'pending')
                ->count();

            $data = [
                'requests' => $requests,
                'settings' => $settings,
                'employees' => $employees,
                'userRole' => $authUser->role,
                'filters' => $request->all(),

                // Statistics
                'totalRequests' => $totalRequests,
                'totalHours' => number_format($totalHours, 1),
                'pendingRequests' => $pendingRequests,
                'pendingHours' => number_format($pendingHours, 1),
                'approvedRequests' => $approvedRequests,
                'approvedHours' => number_format($approvedHours, 1),
                'rejectedRequests' => $rejectedRequests,
                'rejectedHours' => number_format($rejectedHours, 1),
                'thisMonthRequests' => $thisMonthRequests,
                'thisMonthHours' => number_format($thisMonthHours, 1),
                'pendingCount' => $pendingCount,
            ];

            return view('client.overtime.view-all-request', $data);
        } catch (\Exception $e) {
            \Log::error('Error in overtime viewAll: ' . $e->getMessage());
            return back()->with('error', 'Something went wrong. Please try again.');
        }
    }

    public function show($id)
    {
        try {
            $authUser = Auth::user();
            $tenantId = session('tenant_id') ?? $authUser->tenant_id;

            $request = OvertimeRequest::with(['user', 'approver'])
                ->join('users', 'overtime_requests.user_id', '=', 'users.id')
                ->leftJoin('user_job_details', 'overtime_requests.user_id', '=', 'user_job_details.user_id')
                ->select(
                    'overtime_requests.*',
                    'users.name as user_name',
                    'users.email as user_email',
                    'users.employee_id',
                    'user_job_details.department',
                    'user_job_details.designation'
                )
                ->where('overtime_requests.id', $id)
                ->where('overtime_requests.tenant_id', $tenantId)
                ->first();

            if (!$request) {
                return response()->json([
                    'success' => false,
                    'message' => 'Request not found'
                ], 404);
            }

            // Check authorization
            if (!$this->scopeCoversOwner($authUser, 'overtime', 'view', (int) $request->user_id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            // Add approver name if exists
            if ($request->approver_id) {
                $approver = User::find($request->approver_id);
                $request->approver_name = $approver ? $approver->name : null;
            }

            return response()->json([
                'success' => true,
                'data' => $request
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }
    public function pendingApprovals(Request $request)
    {
        if (!app(RbacService::class)->can(auth()->user(), 'overtime', 'approve')) {
            abort(403, 'Unauthorized access');
        }

        $tenantId = auth()->user()->tenant_id ?? null;

        $query = OvertimeRequest::with('user')
            ->where('tenant_id', $tenantId)
            ->where('status', 'pending');

        // Previously unrestricted beyond the coarse gate above — a manager
        // saw every tenant's pending requests here, not just their team's.
        $query = $this->applyScope($query, 'user_id', auth()->user(), 'overtime', 'approve');

        // Apply filters
        if ($request->has('from_date') && $request->from_date) {
            $query->whereDate('date', '>=', $request->from_date);
        }

        if ($request->has('to_date') && $request->to_date) {
            $query->whereDate('date', '<=', $request->to_date);
        }

        if ($request->has('user_id') && $request->user_id) {
            $query->where('user_id', $request->user_id);
        }

        $pendingRequests = $query->orderBy('date', 'asc')->paginate(20);

        return view('admin.overtime-approvals', compact('pendingRequests'));
    }
    /**
     * A manager may only act on overtime raised by their own reportees.
     * Admin / HR may act on any request in their tenant.
     */
    private function managerMayAct($authUser, OvertimeRequest $overtimeRequest): bool
    {
        return $this->scopeCoversOwner($authUser, 'overtime', 'approve', $overtimeRequest->user_id);
    }

    public function approve(Request $request, $id)
    {
        $authUser = auth()->user();

        if (!app(RbacService::class)->can($authUser, 'overtime', 'approve')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to approve requests'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'approved_hours' => 'nullable|numeric|min:0|max:24',
            'comments' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $tenantId = $authUser->tenant_id ?? null;

        $overtimeRequest = OvertimeRequest::where('id', $id)
            ->where('tenant_id', $tenantId)
            ->first();

        if (!$overtimeRequest) {
            return response()->json([
                'success' => false,
                'message' => 'Request not found'
            ], 404);
        }

        if (!$this->managerMayAct($authUser, $overtimeRequest)) {
            return response()->json([
                'success' => false,
                'message' => 'You can only approve overtime for your own team members.'
            ], 403);
        }

        if ($overtimeRequest->status != 'pending') {
            return response()->json([
                'success' => false,
                'message' => "Request is already {$overtimeRequest->status}"
            ], 422);
        }

        $approvedHours = $request->approved_hours ?? $overtimeRequest->overtime_hours;

        // Validate against settings
        $settings = OvertimeSetting::where('tenant_id', $tenantId)
            ->orWhereNull('tenant_id')
            ->first();

        if ($settings && $settings->max_hours_per_day && $approvedHours > $settings->max_hours_per_day) {
            return response()->json([
                'success' => false,
                'message' => "Approved hours cannot exceed {$settings->max_hours_per_day} hours per day"
            ], 422);
        }

        // Tier 2 / T2-A — route through the approval workflow when configured.
        // Stage the approved hours so the outcome handler's COALESCE picks them up.
        $overtimeRequest->approved_hours = $approvedHours;
        $overtimeRequest->save();
        try {
            $ar = app(\App\Services\Approvals\ApprovalService::class)
                ->decide('overtime', $overtimeRequest, $authUser, 'approved', $request->comments);
            if ($ar !== null) {
                $msg = $ar->status === 'pending'
                    ? 'Recorded. Awaiting the next approval level.'
                    : 'Overtime request ' . $ar->status . ' successfully';

                return response()->json(['success' => true, 'message' => $msg, 'data' => $overtimeRequest->fresh()]);
            }
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 403);
        }

        $overtimeRequest->update([
            'status' => 'approved',
            'approved_by' => auth()->user()->id,
            'approved_hours' => $approvedHours,
            'approved_at' => now(),
            'rejection_reason' => null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Overtime request approved successfully',
            'data' => $overtimeRequest
        ]);
    }

    /**
     * Reject an overtime request (Admin/Manager only)
     */
    public function reject(Request $request, $id)
    {
        $authUser = auth()->user();

        if (!app(RbacService::class)->can($authUser, 'overtime', 'approve')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to reject requests'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'rejection_reason' => 'required|string|min:3|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $tenantId = $authUser->tenant_id ?? null;

        $overtimeRequest = OvertimeRequest::where('id', $id)
            ->where('tenant_id', $tenantId)
            ->first();

        if (!$overtimeRequest) {
            return response()->json([
                'success' => false,
                'message' => 'Request not found'
            ], 404);
        }

        if (!$this->managerMayAct($authUser, $overtimeRequest)) {
            return response()->json([
                'success' => false,
                'message' => 'You can only reject overtime for your own team members.'
            ], 403);
        }

        if ($overtimeRequest->status != 'pending') {
            return response()->json([
                'success' => false,
                'message' => "Request is already {$overtimeRequest->status}"
            ], 422);
        }

        // Tier 2 / T2-A — route through the approval workflow when configured.
        try {
            $ar = app(\App\Services\Approvals\ApprovalService::class)
                ->decide('overtime', $overtimeRequest, $authUser, 'rejected', $request->rejection_reason);
            if ($ar !== null) {
                return response()->json([
                    'success' => true,
                    'message' => 'Overtime request ' . $ar->status,
                    'data' => $overtimeRequest->fresh(),
                ]);
            }
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 403);
        }

        $overtimeRequest->update([
            'status' => 'rejected',
            'approved_by' => auth()->user()->id,
            'rejection_reason' => $request->rejection_reason,
            'approved_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Overtime request rejected',
            'data' => $overtimeRequest
        ]);
    }

    /**
     * Bulk approve multiple requests (Admin/Manager only)
     */
    public function bulkApprove(Request $request, OvertimeApprovalService $overtimeApprovalService)
    {
        $authUser = auth()->user();

        $validator = Validator::make($request->all(), [
            'request_ids' => 'required|array',
            'request_ids.*' => 'exists:overtime_requests,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $tenantId = $authUser->tenant_id ?? null;

        $result = $overtimeApprovalService->bulkApprove($authUser, $tenantId, $request->request_ids);

        if (!$result['authorized']) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to approve requests'
            ], 403);
        }

        return response()->json([
            'success' => true,
            'message' => "{$result['approved_count']} request(s) approved successfully",
            'count' => $result['approved_count']
        ]);
    }

    /**
     * Get stats for dashboard
     */
    public function getStats()
    {
        $tenantId = auth()->user()->tenant_id ?? null;
        $userId = auth()->user()->id;

        $stats = [
            'total_requests' => OvertimeRequest::where('tenant_id', $tenantId)
                ->where('user_id', $userId)
                ->count(),

            'pending_requests' => OvertimeRequest::where('tenant_id', $tenantId)
                ->where('user_id', $userId)
                ->where('status', 'pending')
                ->count(),

            'approved_requests' => OvertimeRequest::where('tenant_id', $tenantId)
                ->where('user_id', $userId)
                ->where('status', 'approved')
                ->count(),

            'rejected_requests' => OvertimeRequest::where('tenant_id', $tenantId)
                ->where('user_id', $userId)
                ->where('status', 'rejected')
                ->count(),

            'total_hours' => OvertimeRequest::where('tenant_id', $tenantId)
                ->where('user_id', $userId)
                ->sum('overtime_hours'),

            'approved_hours' => OvertimeRequest::where('tenant_id', $tenantId)
                ->where('user_id', $userId)
                ->where('status', 'approved')
                ->sum(DB::raw('COALESCE(approved_hours, overtime_hours)')),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }
}
