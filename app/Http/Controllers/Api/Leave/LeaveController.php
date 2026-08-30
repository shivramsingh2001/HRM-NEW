<?php

namespace App\Http\Controllers\Api\Leave;

use App\Http\Controllers\Controller;
use App\Models\Leave;
use App\Models\LeaveType;
use App\Models\User;
use App\Models\UserJobDetail;
use App\Services\LeaveNotificationService;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Models\LeaveBalance;
use App\Models\LeaveTransaction;
use Illuminate\Support\Facades\Log;

class LeaveController extends Controller
{
    /**
     * @var LeaveNotificationService
     */
    protected $notificationService;

    /**
     * Constructor - Inject the notification service
     */
    public function __construct(LeaveNotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
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
            $baseUrl = env('APP_URL');
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
                    DB::raw("
                        CASE 
                            WHEN leaves.file IS NULL OR leaves.file = '' 
                            THEN NULL
                            ELSE CONCAT('$baseUrl/', leaves.file)
                        END as file_url
                    ")
                )
                ->get();
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
            $isLWP = in_array($leaveTypeId, [3, 7, 13, 16, 21, 22, 23, 24, 27, 28, 32, 33, 36, 42]);
            $dates = [];
            $createdLeaves = [];
    
            /**----------------------------------------------------
             * Generate dates array
             *----------------------------------------------------*/
            while ($start->lte($end)) {
                $dates[] = $start->format('Y-m-d');
                $start->addDay();
            }
    
            $totalLeaveDays = 0; 
            foreach ($dates as $i => $date) {
                $leaveCount = 1;

                if (count($dates) == 1) {
                    if ($request->start_session == $request->end_session) {
                        $leaveCount = ($request->start_session == "fullday") ? 1 : 0.5;
                    } else {
                        $leaveCount = 1;
                    }
                } else {
                    if ($i == 0) {
                        $leaveCount = ($request->start_session == "fullday") ? 1 : 0.5;
                    } elseif ($i == count($dates) - 1) {
                        $leaveCount = ($request->end_session == "fullday") ? 1 : 0.5;
                    } else {
                        $leaveCount = 1;
                    }
                }
                $totalLeaveDays += $leaveCount;
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
            $filePath = null;
            if ($request->hasFile('file')) {
                $file = $request->file('file');
                $extension = strtolower($file->getClientOriginalExtension());
                $filename = time() . '_' . uniqid() . '.' . $extension;
                $destinationPath = public_path('uploads/leave/document');
                
                // Create directory if not exists
                if (!file_exists($destinationPath)) {
                    mkdir($destinationPath, 0755, true);
                }
                
                $file->move($destinationPath, $filename);
                $filePath = 'uploads/leave/document/' . $filename;
            }
    
            $leaveType = LeaveType::find($request->leave_type);
            $totalLeaveUsed = 0;
    
            /**----------------------------------------------------
             * LOOP THROUGH EACH DATE & CREATE LEAVE MODEL
             *----------------------------------------------------*/
            foreach ($dates as $i => $date) {
                $leaveCount = 1; // default full day
                $session = "fullday";
    
                // Single day leave
                if (count($dates) == 1) {
                    if ($request->start_session == $request->end_session) {
                        $leaveCount = ($request->start_session == "fullday") ? 1 : 0.5;
                    } else {
                        $leaveCount = 1;
                    }
                    $session = $request->start_session;
                } else {
                    // Multi-day leaves
                    if ($i == 0) {
                        // First day
                        $session = $request->start_session;
                        $leaveCount = ($session == "fullday") ? 1 : 0.5;
                    } elseif ($i == count($dates) - 1) {
                        // Last day
                        $session = $request->end_session;
                        $leaveCount = ($session == "fullday") ? 1 : 0.5;
                    } else {
                        // Full middle days
                        $session = "fullday";
                        $leaveCount = 1;
                    }
                }
    
                $totalLeaveUsed += $leaveCount;
    
                // 🔥 CREATE EACH LEAVE INDIVIDUALLY TO TRIGGER TRAIT
                $leave = Leave::create([
                    'user_id' => $user->id,
                    'leave_type' => $request->leave_type,
                    'start_date' => $date,
                    'start_session' => $session,
                    'end_date' => $request->end_date,
                    'end_session' => $request->end_session,
                    'leave_count' => $leaveCount,
                    'reason' => $request->reason,
                    'status' => 'pending',
                    'file' => $filePath,
                ]);
                
                $createdLeaves[] = $leave; // Store for potential use
            }
    
            DB::commit();
    
            // Get the first created leave for notification (or you could notify for all)
            $insertedLeave = Leave::where('user_id', $user->id)
                ->with('user')
                ->latest()
                ->first();
    
            // 🔔 SEND NOTIFICATIONS TO REPORTING HEAD, HR, ADMIN
            try {
                if ($insertedLeave) {
                    $this->notificationService->notifyLeaveSubmitted($insertedLeave);
                }
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
            $baseUrl = env('APP_URL');
    
            // Allow admin, hr, and manager to view
            if (!in_array($authUser->role, ['admin', 'hr', 'manager'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access. Only managers, HR, and admins can view this data.'
                ], 403);
            }
            
            $query = Leave::join('users', 'leaves.user_id', '=', 'users.id')
                ->leftJoin('user_job_details', 'user_job_details.user_id', '=', 'users.id')
                ->leftJoin('leave_types', 'leaves.leave_type', '=', 'leave_types.id');
            
            // Role-based filtering
            if ($authUser->role === 'manager') {
                // Managers: Only see their team members' leaves
                $query->where('user_job_details.reporting_head', $authUser->id);
            }
            // Admin and HR: No filtering - see all leaves
            
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
                'leaves.reason',
                'leaves.status',
                'leaves.created_at',
                DB::raw("
                    CASE 
                        WHEN leaves.file IS NULL OR leaves.file = '' 
                        THEN NULL
                        ELSE CONCAT('$baseUrl/', leaves.file)
                    END as file_url
                ")
            )
            ->orderBy('leaves.created_at', 'desc')
            ->get();
    
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

        if (!in_array($authUser->role, ['admin', 'hr', 'manager'])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access.'
            ], 403);
        }

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

            // Check authorization for non-admin/hr
            if (!in_array($authUser->role, ['admin', 'hr'])) {
                if ($leave->reporting_head != $authUser->id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'You are not authorized to update this leave.'
                    ], 403);
                }
            }

            if ($leave->status !== 'pending') {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'This leave has already been processed.'
                ], 400);
            }

            if ($request->status === 'approved') {
                $response = $this->approveLeave($leave, $authUser, $request->remarks);
            } else {
                $response = $this->cancelLeave($leave, $authUser, $request->remarks);
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

            return $response;
            
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Something went wrong',
                
            ], 500);
        }
    }
    
    private function approveLeave($leave, $approver, $remarks)
    {
        $leaveDays = (float) $leave->leave_count;
        $tenantId = $leave->tenant_id;
        $leaveTypeId = $leave->leave_type;
        $userId = $leave->user_id;
        $isLWP = in_array($leaveTypeId, [3, 7, 13, 16, 21, 22, 23, 24, 27, 28, 32, 33, 36]);

        if ($isLWP) {
            // Just update leave status without balance deduction
            Leave::where('id', $leave->id)
                ->update([
                    'status' => 'approved',
                    'status_update_by' => $approver->id,
                    'status_update_remarks' => $remarks
                ]);

            // Create transaction log for LWP
            LeaveTransaction::create([
                'leave_id' => $leave->id ?? null,
                'user_id' => $userId,
                'leave_type' => $leaveTypeId,
                'transaction_type' => 'sub',
                'total_leaves' => $leaveDays,
                'leaves_count' => 0, // No paid days for LWP
                'leave_detail' => 'unpaid',
                'before_leaves' => 0,
                'after_leaves' => 0,
                'transaction_date' => now(),
                'status' => 1,
                'remarks' => "LWP Leave approved: $remarks"
            ]);

            return response()->json([
                'success' => true,
                'message' => 'LWP Leave approved successfully',
            ], 200);
        }

        // For regular leave types with balance
        $balanceRecord = LeaveBalance::where('user_id', $userId)
            ->where('tenant_id', $tenantId)
            ->where('leave_type_id', $leaveTypeId)
            ->first();

        $currentBalance = $balanceRecord ? (float) $balanceRecord->balance : 0.00;
        if ($currentBalance < $leaveDays) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => "Insufficient leave balance. You have {$currentBalance} days available but requested {$leaveDays} days."
            ], 200);
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

        if ($paidDays > 0) {
            LeaveBalance::where('user_id', $userId)
                ->where('tenant_id', $tenantId)
                ->where('leave_type_id', $leaveTypeId)
                ->update(['balance' => $newBalance]);
        }

        Leave::where('id', $leave->id)
            ->update([
                'status' => 'approved',
                'status_update_by' => $approver->id,
                'status_update_remarks' => $remarks
            ]);

        LeaveTransaction::create([
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
        Leave::where('id', $leave->id)
            ->update([
                'status' => 'cancelled',
                'status_update_by' => $approver->id,
                'status_update_remarks' => $remarks
            ]);

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
    
    public function view_ai_leave(Request $request)
    {
        try {
            $authUser = Auth::user();
            $baseUrl = env('APP_URL');
            
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
                    DB::raw("
                        CASE 
                            WHEN leaves.file IS NULL OR leaves.file = '' 
                            THEN NULL
                            ELSE CONCAT('$baseUrl/', leaves.file)
                        END as file_url
                    ")
                );
    
            // Role-based filtering
            switch ($authUser->role) {
                case 'admin':
                case 'hr':
                    break;
                    
                case 'manager':
                    $query->where('user_job_details.reporting_head', $authUser->id);
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
    
            $leaves = $query->get();
    
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