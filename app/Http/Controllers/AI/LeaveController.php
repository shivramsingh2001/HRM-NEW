<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Models\Leave;
use App\Models\LeaveBalance;
use App\Models\LeaveTransaction;
use App\Models\LeaveType;
use App\Models\User;
use App\Models\UserJobDetail;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LeaveController extends Controller
{
    public function view_ai_all(Request $request)
    {
        try {
            $authUser = Auth::user();
            $baseUrl = env('APP_URL');
            $currentYear = date('Y');

            // Base query for leaves
            $query = Leave::join('users', 'leaves.user_id', '=', 'users.id')
                ->join('user_job_details', 'user_job_details.user_id', '=', 'users.id')
                ->leftJoin('leave_types', 'leaves.leave_type', '=', 'leave_types.id')
                ->select(
                    'leaves.id',
                    'leaves.leave_id',
                    'users.name as employee_name',
                    'users.id as user_id',
                    'users.employee_id',
                    'users.role as user_role',
                    'users.status as user_status',
                    'leave_types.id as leave_type_id',
                    'leave_types.name as leave_type',
                    'leave_types.credit_type',
                    'leave_types.credit_value',
                    'leaves.start_date',
                    'leaves.end_date',
                    'leaves.start_session',
                    'leaves.end_session',
                    'leaves.leave_count',
                    'leaves.reason',
                    'leaves.status',
                    'leaves.created_at',
                    'leaves.updated_at',
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
                    // Admin/HR: See all leaves
                    break;

                case 'manager':
                    // Manager: See team members' leaves + their own leaves
                    $query->where(function ($q) use ($authUser) {
                        $q->where('user_job_details.reporting_head', $authUser->id)
                            ->orWhere('leaves.user_id', $authUser->id);
                    });
                    break;

                case 'employee':
                    // Employee: See only their own leaves
                    $query->where('leaves.user_id', $authUser->id);
                    break;

                default:
                    return response()->json([
                        'success' => false,
                        'message' => 'Unauthorized access. Invalid role.'
                    ], 403);
            }

            // Apply filters
            if ($request->has('from_date') && $request->has('to_date')) {
                $query->whereBetween('leaves.start_date', [$request->from_date, $request->to_date]);
            }

            if ($request->has('status')) {
                $query->where('leaves.status', $request->status);
            }

            if ($request->has('user_id')) {
                $query->where('leaves.user_id', $request->user_id);
            }

            if ($request->has('leave_type')) {
                $query->where('leaves.leave_type', $request->leave_type);
            }

            // Optional: Filter by year
            if ($request->has('year')) {
                $year = $request->year;
                $query->whereYear('leaves.start_date', $year);
            } else {
                // Default to current year if no filter
                $query->whereYear('leaves.start_date', $currentYear);
            }

            $leaves = $query->orderBy('leaves.created_at', 'desc')->get();

            // Get all user IDs from leaves
            $userIds = $leaves->pluck('user_id')->unique()->toArray();

            // If no leaves found but we need to show balances, get all users based on role
            if (empty($userIds) && in_array($authUser->role, ['admin', 'hr'])) {
                $users = User::where('status', 1)->pluck('id')->toArray();
                $userIds = $users;
            } elseif (empty($userIds) && $authUser->role === 'manager') {
                // Get manager's team + self
                $teamIds = UserJobDetail::where('reporting_head', $authUser->id)
                    ->pluck('user_id')
                    ->toArray();
                $teamIds[] = $authUser->id;
                $userIds = $teamIds;
            } elseif (empty($userIds) && $authUser->role === 'employee') {
                $userIds = [$authUser->id];
            }

            // Fetch all leave types
            $leaveTypes = LeaveType::where('status', 1)->get()->keyBy('id');

            // Fetch leave balances for these users
            $leaveBalances = LeaveBalance::whereIn('user_id', $userIds)
                ->get()
                ->keyBy('user_id');

            // Fetch leave transactions summary for current year
            $year = $request->year ?? $currentYear;
            $leaveTransactions = LeaveTransaction::whereIn('user_id', $userIds)
                ->whereYear('transaction_date', $year)
                ->select(
                    'user_id',
                    'leave_type',
                    DB::raw('SUM(CASE WHEN transaction_type = "add" THEN leaves_count ELSE 0 END) as total_credited'),
                    DB::raw('SUM(CASE WHEN transaction_type = "sub" THEN leaves_count ELSE 0 END) as total_used')
                )
                ->groupBy('user_id', 'leave_type')
                ->get()
                ->groupBy('user_id');

            // Get all pending leaves summary
            $pendingLeaves = Leave::whereIn('user_id', $userIds)
                ->where('status', 'pending')
                ->whereYear('start_date', $year)
                ->select('user_id', 'leave_type', DB::raw('SUM(leave_count) as pending_days'))
                ->groupBy('user_id', 'leave_type')
                ->get()
                ->groupBy('user_id');

            // Get user details for all users
            $users = User::whereIn('id', $userIds)
                ->get()
                ->keyBy('id');

            // Build complete leave data with balances
            $leaveData = [];

            foreach ($userIds as $userId) {
                $user = $users->get($userId);
                if (!$user) continue;

                // Get user's leaves
                $userLeaves = $leaves->where('user_id', $userId);
                
                // Get user's balance
                $balance = $leaveBalances->get($userId);
                
                // Get user's transactions
                $userTransactions = $leaveTransactions->get($userId, collect());
                
                // Get user's pending leaves summary
                $userPending = $pendingLeaves->get($userId, collect());

                // Calculate balance per leave type
                $balanceDetails = [];
                $totalCredited = 0;
                $totalUsed = 0;
                $totalPending = 0;

                foreach ($leaveTypes as $typeId => $type) {
                    $typeTransactions = $userTransactions->where('leave_type', $typeId)->first();
                    
                    $credited = (float) ($typeTransactions->total_credited ?? 0);
                    $used = (float) ($typeTransactions->total_used ?? 0);
                    
                    $pendingDays = (float) ($userPending->where('leave_type', $typeId)->first()->pending_days ?? 0);
                    
                    $available = $credited - $used;
                    
                    $balanceDetails[] = [
                        'leave_type_id' => $typeId,
                        'leave_type' => $type->name,
                        'credit_type' => $type->credit_type,
                        'annual_quota' => (float) $type->credit_value,
                        'credited' => $credited,
                        'used' => $used,
                        'pending' => $pendingDays,
                        'available' => $available,
                        'balance' => $available
                    ];
                    
                    $totalCredited += $credited;
                    $totalUsed += $used;
                    $totalPending += $pendingDays;
                }

                // Format leaves for this user
                $formattedLeaves = $userLeaves->map(function ($leave) use ($balance, $userTransactions, $leaveTypes) {
                    
                    // Calculate total days including sessions
                    $totalDays = $this->calculateLeaveDays(
                        Carbon::parse($leave->start_date),
                        $leave->end_date ? Carbon::parse($leave->end_date) : Carbon::parse($leave->start_date),
                        $leave->start_session,
                        $leave->end_session
                    );
                    
                    // Get leave type info
                    $leaveType = $leaveTypes->get($leave->leave_type_id);
                    
                    // Get transactions for this leave type
                    $typeTransactions = $userTransactions->where('leave_type', $leave->leave_type_id)->first();
                    
                    // Determine if paid or unpaid
                    $isPaid = ($leave->leave_count > 0 && $totalDays <= ($balance->balance ?? 0));
                    
                    return [
                        'id' => $leave->id,
                        'leave_id' => $leave->leave_id,
                        'leave_type' => $leave->leave_type,
                        'period' => [
                            'start_date' => $leave->start_date,
                            'end_date' => $leave->end_date,
                            'start_session' => $leave->start_session,
                            'end_session' => $leave->end_session,
                            'total_days' => $totalDays,
                            'leave_count' => $leave->leave_count
                        ],
                        'reason' => $leave->reason,
                        'status' => $leave->status,
                        'file_url' => $leave->file_url,
                        'submitted_at' => $leave->created_at,
                        'payment_status' => $isPaid ? 'paid' : ($leave->leave_count > 0 ? 'paid' : 'unpaid')
                    ];
                });

                // Add to leave data
                $leaveData[] = [
                    'employee' => [
                        'id' => $user->id,
                        'employee_id' => $user->employee_id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'role' => $user->role
                    ],
                    'balance_summary' => [
                        'current_balance' => $balance ? (float) $balance->balance : 0,
                        'total_credited' => $totalCredited,
                        'total_used' => $totalUsed,
                        'total_pending' => $totalPending,
                        'total_available' => $totalCredited - $totalUsed,
                        'year' => (int) $year
                    ],
                    'balance_details' => $balanceDetails,
                    'leaves' => $formattedLeaves,
                    'total_leaves' => $formattedLeaves->count()
                ];
            }

            // Overall summary statistics
            $overallSummary = [
                'total_employees' => count($leaveData),
                'total_leave_requests' => $leaves->count(),
                'total_pending_requests' => $leaves->where('status', 'pending')->count(),
                'total_approved_requests' => $leaves->where('status', 'approved')->count(),
                'total_cancelled_requests' => $leaves->where('status', 'cancelled')->count(),
                'total_days_requested' => $leaves->sum('leave_count'),
                'year' => (int) $year
            ];

            return response()->json([
                'success' => true,
                'message' => 'Leave data with balances fetched successfully',
                'data' => $leaveData,
                'summary' => $overallSummary,
                'user_role' => $authUser->role,
                'filters_applied' => [
                    'year' => $year,
                    'from_date' => $request->from_date,
                    'to_date' => $request->to_date,
                    'status' => $request->status,
                    'user_id' => $request->user_id
                ]
            ], 200);
            
        } catch (Exception $e) {
            Log::error('View AI Leave Error: ' . $e->getMessage(), [
                'user_id' => Auth::id(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred. Please try again later.'
            ], 500);
        }
    }

    /**
     * Calculate leave days considering sessions
     */
    private function calculateLeaveDays($startDate, $endDate, $startSession, $endSession)
    {
        if ($startDate->eq($endDate)) {
            // Same day leave
            if ($startSession == 'fullday' || ($startSession == 'session1' && $endSession == 'session2')) {
                return 1;
            } elseif (in_array($startSession, ['session1', 'session2'])) {
                return 0.5;
            }
            return 1;
        } else {
            // Multi-day leave
            $totalDays = $startDate->diffInDays($endDate) + 1;

            // Adjust for first day session
            if ($startSession == 'session2') {
                $totalDays -= 0.5;
            }

            // Adjust for last day session
            if ($endSession == 'session1') {
                $totalDays -= 0.5;
            }

            return $totalDays;
        }
    }
}