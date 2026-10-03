<?php

namespace App\Http\Controllers\Api\Leave;

use App\Http\Controllers\Controller;
use App\Models\Leave;
use App\Models\LeaveType;
use App\Models\User;
use App\Models\UserJobDetail;
use App\Services\LeaveNotificationService;
use App\Services\LeaveService;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Models\LeaveBalance;
use App\Models\LeaveTransaction;
use Illuminate\Support\Facades\Log;
use App\Services\RbacService;
use App\Traits\AuthorizesByScope;

class LeaveController extends Controller
{
    use AuthorizesByScope;

    /**
     * @var LeaveNotificationService
     */
    protected $notificationService;
    protected $leaveService;

    /**
     * Constructor - Inject the notification service
     */
    public function __construct(LeaveNotificationService $notificationService, LeaveService $leaveService)
    {
        $this->notificationService = $notificationService;
        $this->leaveService = $leaveService;
    }

    public function fetch_type(Request $request)
    {
        try {
             $userId = Auth::id();
             $leaveTypes = LeaveType::where('status', '1')->get(['id', 'name', 'credit_type', 'credit_value']);
            
             $balances = LeaveBalance::where('user_id', $userId)
                ->get()
                ->keyBy('leave_type_id');

            foreach ($leaveTypes as $type) {
                $type->available_balance = isset($balances[$type->id]) ? (string) $balances[$type->id]->balance : '0';
            }
            return response()->json([
                'success' => true,
                'message' => "Data fetched successfully!!!",
                'data' => $leaveTypes
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "An error occured. Please try again later." ,
            ], 500);
        }
    }
    

    public function view(Request $request)
    {
        try {
            $userId = Auth::id();
            $balanceRow = LeaveBalance::where('user_id', $userId)->first();
            
            $leaves = Leave::where('leaves.user_id', $userId)
                ->leftJoin('leave_types', 'leaves.leave_type', '=', 'leave_types.id')
                ->select(
                    'leaves.id',
                    'leaves.leave_id',
                    'leave_types.name as leave_type',
                    'leaves.reason as reason',
                    'leaves.start_date as date',
                    'leaves.start_session as session',
                    'leaves.status',
                    // Additive: full range + application day-count, now that
                    // leave requests are one row per application rather than
                    // one row per day.
                    'leaves.end_date',
                    'leaves.end_session',
                    'leaves.total_days',
                    'leaves.file as file_url'
                )
                ->get();
            file_storage()->mapUrls($leaves, ['file_url' => 'leave']);
            $allTransactions = LeaveTransaction::with('leaveType')
            ->where('user_id', $userId)
            ->get();
            $balanceByType = LeaveBalance::with('leaveType')
                ->where('user_id', $userId)
                ->get()
                ->map(function ($balance) {
                    return [
                        'id' => $balance->leave_type_id,
                        'type' => $balance->leaveType->name ?? 'Unknown',
                        'balance' => (float) $balance->balance
                    ];
                });

            // Calculate total balance
            $totalBalance = $balanceByType->sum('balance');

            return response()->json([
                'success' => true,
                'message' => "Data fetched successfully!!!",
                'data' => [
                    'leaves' => $leaves,
                    'leave_balance' => (string) $totalBalance ?? "0.00",
                    'type_balance' => $balanceByType ?? []
                ]
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "An error occured. Please try again later.".$e->getMessage(),
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'leave_type' => 'required|exists:leave_types,id',
            'start_date' => 'required|date',
            'start_session' => 'required|in:session1,session2,fullday',
            'end_date' => 'required|date',
            'end_session' => 'required|in:session1,session2,fullday',
            'file' => 'nullable|file|max:2048',
            'reason' => 'required|max:500'
        ]);
    
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ], 200);
        }
    
        DB::beginTransaction();

        try {
            $user = Auth::user();
            $start = Carbon::parse($request->start_date);
            $end = Carbon::parse($request->end_date);
            $leaveTypeId = $request->leave_type;
            $isLWP = $this->leaveService->isLwpId($leaveTypeId);

            if ($end->lt($start)) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'End date cannot be before start date.'
                ], 200);
            }

            // This employee's custom leave rules (Employee 360 → Policies): a
            // type switched off for them, or their own notice / length limit.
            // The leave type's company-wide notice / length rules are not
            // enforced on this endpoint (unchanged).
            if ($leaveTypeModel = LeaveType::find($leaveTypeId)) {
                $refusal = app(\App\Services\EmployeePolicyService::class)
                    ->leaveRefusal((int) $user->tenant_id, (int) $user->id, $leaveTypeModel, $start, $end, customOnly: true);
                if ($refusal) {
                    DB::rollBack();
                    return response()->json(['success' => false, 'message' => $refusal], 200);
                }
            }

            // One request, one row: total_days excludes weekends/holidays,
            // but start_date/end_date still store the full requested range.
            $totalLeaveDays = $this->leaveService->computeLeaveDays(
                $start->copy(),
                $end->copy(),
                $request->start_session,
                $request->end_session,
                $user->tenant_id
            );

            if ($totalLeaveDays <= 0) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'The selected date range has no working days to apply leave for.'
                ], 200);
            }

            /**----------------------------------------------------
             * CHECK AVAILABLE BALANCE FROM leave_balances TABLE
             *----------------------------------------------------*/
            if (!$isLWP) {
                $balanceRecord = LeaveBalance::where('user_id', $user->id)
                    ->where('leave_type_id', $leaveTypeId)
                    ->first();

                $availableBalance = $balanceRecord ? (float) $balanceRecord->balance : 0;

                if ($availableBalance < $totalLeaveDays) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => "Insufficient leave balance. You have {$availableBalance} days available but requested {$totalLeaveDays} days."
                    ], 200);
                }
            }

            /**----------------------------------------------------
             * FILE UPLOAD
             *----------------------------------------------------*/
            $filePath = $request->hasFile('file')
                ? file_storage()->upload($request->file('file'), 'leave', ['tenant' => $user->tenant_id])->path
                : null;

            $insertedLeave = Leave::create([
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

            DB::commit();

            // 🔔 SEND NOTIFICATIONS TO REPORTING HEAD, HR, ADMIN
            try {
                $insertedLeave->load('user');
                $this->notificationService->notifyLeaveSubmitted($insertedLeave);
            } catch (\Exception $e) {
                Log::error('Failed to send leave notifications: ' . $e->getMessage());
            }

            return response()->json([
                'success' => true,
                'message' => 'Leave request submitted successfully',
            ], 200);

        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    public function view_all(Request $request)
    {
        try {
            $authUser = Auth::user();
    
            // Permission-based access (was a fixed role allowlist that
            // silently locked out any custom role holding a real
            // leave:view grant)
            $leaveScope = app(RbacService::class)->scopeFor($authUser, 'leave', 'view');
            if ($leaveScope === null || $leaveScope === 'own') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access. Only managers, HR, and admins can view this data.'
                ], 403);
            }

            $query = Leave::join('users', 'leaves.user_id', '=', 'users.id')
                ->leftJoin('user_job_details', 'user_job_details.user_id', '=', 'users.id')
                ->leftJoin('leave_types', 'leaves.leave_type', '=', 'leave_types.id');

            // Scope-based filtering (any reporting head)
            if ($leaveScope === 'team') {
                $query->whereIn('users.id', function ($q) use ($authUser) {
                    $q->select('user_id')->from('user_reporting_heads')->where('reporting_head_id', $authUser->id);
                });
            }
            // company: no filtering - see all leaves

            $leaves = $query->select(
                'leaves.id',
                'leaves.leave_id',
                'users.name as employee_name',
                'users.role as employee_role', 
                'leave_types.name as leave_type',
                'leaves.start_date as start_date',
                'leaves.end_date as end_date',
                'leaves.start_session as start_session',
                'leaves.end_session as end_session',
                'leaves.total_days',
                'leaves.reason',
                'leaves.status',
                'leaves.created_at',
                'leaves.file as file_url'
            )
            ->orderBy('leaves.created_at', 'desc')
            ->get();
            file_storage()->mapUrls($leaves, ['file_url' => 'leave']);
    
            return response()->json([
                'success' => true,
                'message' => 'Leave data fetched successfully!',
                'data' => $leaves,
                'total_count' => $leaves->count()
            ], 200);
            
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred. Please try again later.',
              
            ], 500);
        }
    }

    public function updateLeaveStatus(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:approved,cancelled',
            'remarks' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 200);
        }

        $authUser = Auth::user();

        DB::beginTransaction();

        try {
            $leave = Leave::join('user_job_details', 'user_job_details.user_id', '=', 'leaves.user_id')
                ->where('leaves.id', $id)
                ->select('leaves.*', 'user_job_details.reporting_head')
                ->first();

            if (!$leave) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Leave not found.'
                ], 404);
            }

            if (!$this->scopeCoversOwner($authUser, 'leave', 'approve', (int) $leave->user_id)) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'You are not authorized to update this leave.'
                ], 403);
            }

            if ($leave->status !== 'pending') {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'This leave has already been processed.'
                ], 400);
            }

            // Route through the generic multi-level approval engine when the
            // tenant has configured a 'leave' workflow (Settings > Approvals
            // — the same engine Overtime/Regularization already use).
            // decide() returns null when no workflow is configured, so this
            // is a zero-risk opt-in: every tenant without one falls straight
            // through to the unchanged direct-approval path below. The
            // handler itself sends the approved/rejected notification, so
            // this path does not duplicate the notification block below.
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

                    return response()->json(['success' => true, 'message' => $message, 'workflow_status' => $ar->status], 200);
                }
            } catch (\App\Exceptions\InsufficientLeaveBalanceException $e) {
                DB::rollBack();
                return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
            } catch (\RuntimeException $e) {
                DB::rollBack();
                return response()->json(['success' => false, 'message' => $e->getMessage()], 403);
            }

            // No workflow configured for this tenant — single source of
            // truth in LeaveService, was previously
            // duplicated here with a real bug: on an insufficient-balance
            // rejection, approveLeave() called DB::rollBack() itself, but
            // execution then fell through to this method's own DB::commit(),
            // which threw ("no active transaction") and was swallowed by the
            // outer catch, turning a clear "insufficient balance" message
            // into a generic 500 "Something went wrong".
            $result = $request->status === 'approved'
                ? $this->leaveService->approvePendingLeave($leave, $authUser, $request->remarks)
                : $this->leaveService->cancelPendingLeave($leave, $authUser, $request->remarks);

            if (!$result['success']) {
                DB::rollBack();
                return response()->json($result, 400);
            }

            DB::commit();

            // 🔔 SEND NOTIFICATION TO EMPLOYEE
            try {
                $leaveWithUser = Leave::with('user')->find($id);
                if ($leaveWithUser) {
                    if ($request->status === 'approved') {
                        $this->notificationService->notifyLeaveApproved($leaveWithUser, $request->remarks);
                    } else {
                        $this->notificationService->notifyLeaveRejected($leaveWithUser, $request->remarks);
                    }
                }
            } catch (\Exception $e) {
                Log::error('Failed to send leave status notification: ' . $e->getMessage());
            }

            return response()->json($result, 200);

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Leave updateLeaveStatus error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Something went wrong',
            ], 500);
        }
    }

    public function view_ai_leave(Request $request)
    {
        try {
            $authUser = Auth::user();
            
            // Base query
            $query = Leave::join('users', 'leaves.user_id', '=', 'users.id')
                ->join('user_job_details', 'user_job_details.user_id', '=', 'users.id')
                ->leftJoin('leave_types', 'leaves.leave_type', '=', 'leave_types.id')
                ->select(
                    'leaves.id',
                    'leaves.leave_id',
                    'users.name as employee_name',
                    'users.id as user_id',
                    'leave_types.name as leave_type',
                    'leaves.start_date as date',
                    'leaves.start_session as session',
                    'leaves.reason',
                    'leaves.status',
                    'leaves.file as file_url'
                );
    
            // Role-based filtering
            switch ($authUser->role) {
                case 'admin':
                case 'hr':
                    break;
                    
                case 'manager':
                    $query->whereIn('users.id', function ($q) use ($authUser) {
                        $q->select('user_id')->from('user_reporting_heads')->where('reporting_head_id', $authUser->id);
                    });
                    break;
                    
                case 'employee':
                    $query->where('leaves.user_id', $authUser->id);
                    break;
                    
                default:
                    return response()->json([
                        'success' => false,
                        'message' => 'Unauthorized access. Invalid role.'
                    ], 200);
            }
    
            $leaves = file_storage()->mapUrls($query->get(), ['file_url' => 'leave']);
    
            return response()->json([
                'success' => true,
                'message' => 'Data fetched successfully!',
                'data' => $leaves,
                
            ], 200);
    
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred. Please try again later.',
            ], 500);
        }
    }
}