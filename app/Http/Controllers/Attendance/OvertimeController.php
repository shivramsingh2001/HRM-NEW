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

class OvertimeController extends Controller
{
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

            // Role-based access control
            if ($authUser->role === 'admin') {
                // Admin can see all requests
                // No additional conditions needed
            } elseif ($authUser->role === 'manager') {
                // Manager can see requests of their team members
                $query->where(function ($q) use ($authUser) {
                    $q->where('user_job_details.reporting_head', $authUser->id)
                        ->orWhere('overtime_requests.user_id', $authUser->id);
                });
            } else {
                // Regular employees can only see their own requests
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

            if ($request->filled('employee') && in_array($authUser->role, ['admin', 'manager', 'hr'])) {
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

            if ($authUser->role === 'manager') {
                // Get team members for manager
                $employeesQuery->where(function ($q) use ($authUser) {
                    $q->whereHas('jobDetails', function ($query) use ($authUser) {
                        $query->where('reporting_head', $authUser->id);
                    })->orWhere('id', $authUser->id);
                });
            } elseif ($authUser->role !== 'admin') {
                // Regular employee - only themselves
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
            dd($e->getMessage());
            \Log::error('Error in overtime viewAll: ' . $e->getMessage());
            return back()->with('error', 'Something went wrong: ' . $e->getMessage());
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
            if (!in_array($authUser->role, ['admin', 'manager']) && $request->user_id != $authUser->id) {
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
        // Check if user is admin or manager
        if (!in_array(auth()->user()->role, ['admin', 'manager'])) {
            abort(403, 'Unauthorized access');
        }

        $tenantId = auth()->user()->tenant_id ?? null;

        $query = OvertimeRequest::with('user')
            ->where('tenant_id', $tenantId)
            ->where('status', 'pending');

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
     * Roles allowed to approve/reject overtime.
     */
    private const APPROVER_ROLES = ['admin', 'hr', 'manager'];

    /**
     * A manager may only act on overtime raised by their own reportees.
     * Admin / HR may act on any request in their tenant.
     */
    private function managerMayAct($authUser, OvertimeRequest $overtimeRequest): bool
    {
        if (in_array($authUser->role, ['admin', 'hr'], true)) {
            return true;
        }

        if ($authUser->role === 'manager') {
            return \App\Models\UserJobDetail::where('user_id', $overtimeRequest->user_id)
                ->where('tenant_id', $authUser->tenant_id)
                ->where('reporting_head', $authUser->id)
                ->exists();
        }

        return false;
    }

    public function approve(Request $request, $id)
    {
        $authUser = auth()->user();

        if (!in_array($authUser->role, self::APPROVER_ROLES, true)) {
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

        if (!in_array($authUser->role, self::APPROVER_ROLES, true)) {
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
    public function bulkApprove(Request $request)
    {
        $authUser = auth()->user();

        if (!in_array($authUser->role, self::APPROVER_ROLES, true)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to approve requests'
            ], 403);
        }

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

        $query = OvertimeRequest::where('tenant_id', $tenantId)
            ->whereIn('id', $request->request_ids)
            ->where('status', 'pending');

        // Managers may only bulk-approve their own reportees.
        if ($authUser->role === 'manager') {
            $query->whereIn('user_id', function ($q) use ($authUser, $tenantId) {
                $q->select('user_id')
                    ->from('user_job_details')
                    ->where('reporting_head', $authUser->id)
                    ->where('tenant_id', $tenantId);
            });
        }

        $updatedCount = $query->update([
            'status' => 'approved',
            'approved_by' => $authUser->id,
            // populate approved_hours so payroll does not fall back to the raw request
            'approved_hours' => DB::raw('COALESCE(approved_hours, overtime_hours)'),
            'approved_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => "{$updatedCount} request(s) approved successfully",
            'count' => $updatedCount
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
