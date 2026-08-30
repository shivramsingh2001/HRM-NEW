<?php

namespace App\Http\Controllers\Leave;

use App\Http\Controllers\Controller;
use App\Models\Leave;
use App\Models\LeaveTransaction;
use App\Models\LeaveType;
use App\Models\User;
use App\Models\UserJobDetail;
use App\Models\LeaveBalance;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class LeaveController extends Controller
{
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

            // Get filtered leaves
            $leaves = $leaveQuery->orderBy('created_at', 'desc')->get();

            // Transform file URLs
            $leaves->transform(function ($leave) {
                $leave->file = $leave->file ? asset($leave->file) : null;
                return $leave;
            });

            // Get counts based on filters
            $totalLeaveQuery = Leave::where('user_id', $userId);
            $pendingLeaveQuery = Leave::where('user_id', $userId)->where('status', 'pending');
            $approvedLeaveQuery = Leave::where('user_id', $userId)->where('status', 'approved');
            $rejectLeaveQuery = Leave::where('user_id', $userId)->where('status', 'cancelled');

            // Apply same filters to count queries if they exist
            if ($request->filled('status')) {
                $totalLeaveQuery->where('status', $request->status);
                $pendingLeaveQuery->where('status', $request->status);
                $approvedLeaveQuery->where('status', $request->status);
                $rejectLeaveQuery->where('status', $request->status);
            }

            if ($request->filled('from_date')) {
                $totalLeaveQuery->whereDate('start_date', '>=', $request->from_date);
                $pendingLeaveQuery->whereDate('start_date', '>=', $request->from_date);
                $approvedLeaveQuery->whereDate('start_date', '>=', $request->from_date);
                $rejectLeaveQuery->whereDate('start_date', '>=', $request->from_date);
            }

            if ($request->filled('to_date')) {
                $totalLeaveQuery->whereDate('start_date', '<=', $request->to_date);
                $pendingLeaveQuery->whereDate('start_date', '<=', $request->to_date);
                $approvedLeaveQuery->whereDate('start_date', '<=', $request->to_date);
                $rejectLeaveQuery->whereDate('start_date', '<=', $request->to_date);
            }

            $data = [
                'totalLeave' => $totalLeaveQuery->sum('leave_count'),
                'pendingLeave' => $pendingLeaveQuery->sum('leave_count'),
                'approvedLeave' => $approvedLeaveQuery->sum('leave_count'),
                'balanceLeave' => max(0, $available),
                'unpaidLeave' => $available < 0 ? abs($available) : 0,
                'rejectLeave' => $rejectLeaveQuery->sum('leave_count'),
                'leaves' => $leaves,
                'filters' => [
                    'status' => $request->status,
                    'from_date' => $request->from_date,
                    'to_date' => $request->to_date,
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
        $userId = Auth::id();
        $data['leaveTypes'] = LeaveType::where('status', 1)->get();
        $balances = LeaveBalance::where('user_id', $userId)
                    ->get()
                    ->keyBy('leave_type_id');
        
        foreach ($data['leaveTypes'] as $type) {
            $type->available_balance = isset($balances[$type->id]) ? (float) $balances[$type->id]->balance : 0;
        }
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
            $leaveTypeId = $request->leave_type;
            $end = Carbon::parse($request->end_date);
            $isLWP = ($leaveTypeId == 3);

            if ($end->lt($start)) {
                return back()
                    ->withInput()
                    ->with('error', 'End date cannot be before start date.');
            }

            $dates = [];
            $totalLeaveDays = 0;
            while ($start->lte($end)) {
                $dates[] = $start->format('Y-m-d');
                $start->addDay();
            }
            foreach ($dates as $i => $date) {
                $leaveCount = $this->calculateLeaveCountForDate($i, count($dates), $request);
                $totalLeaveDays += $leaveCount;
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
            $leaveRecords = $this->prepareLeaveRecords($user->id, $request, $dates, $filePath);

            // Bulk insert using model
            foreach ($leaveRecords as $record) {
                Leave::create($record);
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
        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $directory = public_path('uploads/leave');
            if (!file_exists($directory)) {
                mkdir($directory, 0755, true);
            }
            $file->move($directory, $filename);
            return 'uploads/leave/' . $filename;
        }
        return null;
    }

    private function prepareLeaveRecords($userId, $request, $dates, $filePath)
    {
        $leaveRecords = [];
        $totalDays = count($dates);

        foreach ($dates as $i => $date) {
            $session = $this->determineSession($i, $totalDays, $request);
            $leaveCount = $this->calculateLeaveCount($session);

            $leaveRecords[] = [
                'user_id' => $userId,
                'leave_type' => $request->leave_type,
                'start_date' => $date,
                'start_session' => $session,
                'leave_count' => $leaveCount,
                'reason' => $request->reason,
                'status' => 'pending',
                'file' => $filePath,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        return $leaveRecords;
    }

    private function determineSession($index, $totalDays, $request)
    {
        // Single day
        if ($totalDays == 1) {
            // If sessions are different, it's a full day
            if ($this->areSessionsDifferent($request->start_session, $request->end_session)) {
                return 'fullday';
            }
            return $request->start_session;
        }

        // Multiple days
        if ($index == 0) {
            return $request->start_session;
        } elseif ($index == $totalDays - 1) {
            return $request->end_session;
        }

        // Middle days
        return 'fullday';
    }

    private function areSessionsDifferent($startSession, $endSession)
    {
        // If both are not fullday and they're different
        if ($startSession != 'fullday' && $endSession != 'fullday' && $startSession != $endSession) {
            return true;
        }

        // If one is fullday and other is not
        if (
            ($startSession == 'fullday' && $endSession != 'fullday') ||
            ($startSession != 'fullday' && $endSession == 'fullday')
        ) {
            return true;
        }

        return false;
    }

    private function calculateLeaveCount($session): float|int
    {
        return $session == 'fullday' ? 1 : 0.5;
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

            DB::beginTransaction();

            $leaveCount = $this->calculateLeaveCount($request->start_session);
            $filePath = $this->handleFileUpload($request);

            $leave->update([
                'leave_type' => $request->leave_type,
                'start_date' => $request->start_date,
                'start_session' => $request->start_session,
                'leave_count' => $leaveCount,
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

        // 🔐 Role-based access
        if (!in_array($authUser->role, ['admin', 'hr'])) {
            // For managers - show only their team's leaves
            $leaveQuery->where('user_job_details.reporting_head', $authUser->id);
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
            $leave->file = $leave->file ? asset($leave->file) : null;
            return $leave;
        });

        // Get counts for status cards based on user role using Eloquent
        $countsQuery = Leave::query()
            ->join('users', 'leaves.user_id', '=', 'users.id')
            ->leftJoin('user_job_details', 'users.id', '=', 'user_job_details.user_id');

        if (!in_array($authUser->role, ['admin', 'hr'])) {
            $countsQuery->where('user_job_details.reporting_head', $authUser->id);
        }

        $totalLeaves = $countsQuery->count();
        $pendingLeaves = (clone $countsQuery)->where('leaves.status', 'pending')->count();
        $approvedLeaves = (clone $countsQuery)->where('leaves.status', 'approved')->count();
        $cancelledLeaves = (clone $countsQuery)->where('leaves.status', 'cancelled')->count();

        // Get leave types for filter dropdown using Eloquent
        $leaveTypes = LeaveType::where('status', 1)
            ->orderBy('name')
            ->get(['id', 'name']);

        // Get employees for filter dropdown using Eloquent with proper relationship
        $employeesQuery = User::where('status', 1)
            ->with(['jobDetails']);

        if (!in_array($authUser->role, ['admin', 'hr'])) {
            $employeesQuery->whereHas('jobDetails', function ($q) use ($authUser) {
                $q->where('reporting_head', $authUser->id);
            });
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

            // Check if user has required role
            if (!in_array($authUser->role, ['admin', 'hr', 'manager'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access. Only admin, HR, or managers can update leave status.'
                ], 403);
            }

            // Get the leave with user details
            $leave = Leave::with('user.jobDetails')->find($id);

            if (!$leave) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Leave not found.'
                ], 404);
            }

            // Check if leave is pending
            if ($leave->status !== 'pending') {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Leave request has already been processed.'
                ], 400);
            }

            // ===== ROLE-BASED AUTHORIZATION =====

            // CASE 1: ADMIN or HR - Can approve/reject any leave
            if (in_array($authUser->role, ['admin', 'hr'])) {
                // Admin and HR have full access - proceed
                // No additional checks needed
            }

            // CASE 2: MANAGER - Must be the reporting head
            elseif ($authUser->role === 'manager') {
                $reportingHeadId = $leave->user->jobDetails->reporting_head ?? null;

                // Check if user has a reporting head
                if (!$reportingHeadId) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => 'User does not have a reporting head assigned.'
                    ], 400);
                }

                // Verify this manager is the reporting head
                if ($reportingHeadId != $authUser->id) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => 'You are not authorized to update this leave request. Only the reporting head can process it.'
                    ], 403);
                }
            }

            // Process the leave based on status
            if ($request->status === 'approved') {
                $this->approveLeave($leave, $authUser, $request->remarks);
            } else {
                $this->cancelLeave($leave, $authUser, $request->remarks);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Leave status updated successfully'
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update status: '
            ], 500);
        }
    }

    private function approveLeave($leave, $approver, $remarks)
    {
        $leaveDays = (float) $leave->leave_count;
        $tenantId = $leave->tenant_id;
        $leaveTypeId = $leave->leave_type;
        $userId = $leave->user_id;
        $isLWP = ($leaveTypeId == 3);

        if ($isLWP) {
            Leave::where('id', $leave->id)->update([
                'status' => 'approved',
                'status_update_by' => $approver->id,
                'status_update_remarks' => $remarks
            ]);

            LeaveTransaction::create([
                 'leave_id' => $leave->id ?? null,
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'leave_type' => $leaveTypeId,
                'transaction_type' => 'sub',
                'total_leaves' => $leaveDays,
                'leaves_count' => 0,
                'leave_detail' => 'unpaid',
                'before_leaves' => 0,
                'after_leaves' => 0,
                'transaction_date' => now(),
                'status' => 1,
                'remarks' => "LWP Leave approved: $remarks"
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Leave approved successfully',
            ], 200);
        }
        $balanceRecord = LeaveBalance::where('user_id', $userId)
            ->where('leave_type_id', $leaveTypeId)
            ->first();

        $currentBalance = $balanceRecord ? (float) $balanceRecord->balance : 0;
         if ($currentBalance < $leaveDays) {
             return response()->json([
                'success' => true,
                'message' => "Insufficient leave balance. You have {$currentBalance} days available but requested {$leaveDays} days.",
            ], 400);
        }
        $paidDays = min($currentBalance, $leaveDays);
        $unpaidDays = $leaveDays - $paidDays;
        $newBalance = $currentBalance - $paidDays;

        if ($paidDays == $leaveDays) {
            $leaveDetail = 'paid';
        } elseif ($paidDays == 0) {
            $leaveDetail = 'unpaid';
        } else {
            $leaveDetail = 'mixed';
        }

        if ($paidDays > 0 && $balanceRecord) {
            $balanceRecord->update(['balance' => $newBalance]);
        }

        Leave::where('id', $leave->id)->update([
            'status' => 'approved',
            'status_update_by' => $approver->id,
            'status_update_remarks' => $remarks
        ]);

        LeaveTransaction::create([
            'tenant_id' => $tenantId,
            'leave_id' => $leave->id ?? null,
            'user_id' => $userId,
            'leave_type' => $leaveTypeId,
            'transaction_type' => 'sub',
            'total_leaves' => $leaveDays,
            'leaves_count' => $paidDays,
            'leave_detail' => $leaveDetail,
            'before_leaves' => $currentBalance,
            'after_leaves' => $newBalance,
            'transaction_date' => now(),
            'status' => 1,
            'remarks' => "Leave approved: $remarks"
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Leave approved successfully',
        ], 200);
    }

    private function cancelLeave($leave, $approver, $remarks)
    {
        // Update leave using model
        Leave::where('id', $leave->id)->update([
            'status' => 'cancelled',
            'status_update_by' => $approver->id,
            'status_update_remarks' => $remarks
        ]);

        // Create transaction record for rejected leave using model
        // LeaveTransaction::create([
        //     'user_id' => $leave->user_id,
        //     'leave_type' => $leave->leave_type,
        //     'transaction_type' => 'sub',
        //     'total_leaves' => (float) $leave->leave_count,
        //     'leaves_count' => 0,
        //     'leave_detail' => 'unpaid',
        //     'before_leaves' => 0,
        //     'after_leaves' => 0,
        //     'transaction_date' => now(),
        //     'status' => 0,
        //     'remarks' => "Leave rejected: $remarks"
        // ]);

        return response()->json([
            'success' => true,
            'message' => 'Leave rejected successfully',
        ], 200);
    }
    private function calculateLeaveCountForDate($index, $totalDays, $request)
    {
        if ($totalDays == 1) {
            if ($request->start_session == $request->end_session) {
                return ($request->start_session == "fullday") ? 1 : 0.5;
            } else {
                return 1;
            }
        } else {
            if ($index == 0) {
                return ($request->start_session == "fullday") ? 1 : 0.5;
            } elseif ($index == $totalDays - 1) {
                return ($request->end_session == "fullday") ? 1 : 0.5;
            } else {
                return 1;
            }
        }
    }
}
