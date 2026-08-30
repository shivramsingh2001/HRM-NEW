<?php

namespace App\Http\Controllers\Api\Expense;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\User;
use App\Models\ExpenseStatusHistory;
use App\Models\ExpenseType;
use App\Models\ExpenseTransaction;
use Exception;
use Illuminate\Http\Request;
use App\Models\UserExpenseBalance;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Services\ExpenseNotificationService;
use Illuminate\Support\Facades\Log;

class ExpenseController extends Controller
{
        /**
     * @var ExpenseNotificationService
     */    protected $notificationService;  // Add this property

    /**
     * Constructor - Inject the notification service
     */
    public function __construct(ExpenseNotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    public function fetch_type(Request $request)
    {
        try {
            $types = ExpenseType::where('status', '1')->get(['id', 'name', 'description']);
            return response()->json([
                'success' => true,
                'message' => "Data fetched successfully!!!",
                'data' => $types
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "An error occured. Please try again later.",
            ], 500);
        }
    }

    public function view(Request $request)
    {
        try {
            $userId = Auth::id();
            $baseUrl = env('APP_URL');
            
            // Get all expenses for the user
            $expenses = Expense::where('expenses.user_id', $userId)
                ->leftJoin('expense_types', 'expenses.expense_type', '=', 'expense_types.id')
                ->leftJoin('projects', 'expenses.project_id', '=', 'projects.id')
                ->select([
                    'expenses.id',
                    'expenses.expense_number',
                    'expense_types.name as expense_type',
                    'projects.name as project_name',
                    'expenses.requirement_type',
                    'expenses.date',
                    'expenses.amount',
                    'expenses.status',
                    'expenses.description',
                    DB::raw("
                        CASE 
                            WHEN expenses.file IS NULL OR expenses.file = '' 
                            THEN NULL
                            ELSE CONCAT('$baseUrl', expenses.file)
                        END as file_url
                    ")
                ])
                ->orderBy('expenses.created_at', 'desc')
                ->get();
    
            // Get user's expense balance
            $userBalance = UserExpenseBalance::where('user_id', $userId)->first();
            
            // Calculate statistics
            $advanceExpenses = $expenses->where('requirement_type', 'advance');
            $settlementExpenses = $expenses->where('requirement_type', 'settlement');
            
            // Advance Statistics
            $totalAdvanceCount = $advanceExpenses->count();
            $totalAdvanceAmount = $advanceExpenses->sum('amount');
            $pendingAdvanceCount = $advanceExpenses->where('status', 'pending')->count();
            $pendingAdvanceAmount = $advanceExpenses->where('status', 'pending')->sum('amount');
            $approvedAdvanceCount = $advanceExpenses->where('status', 'approved')->count();
            $approvedAdvanceAmount = $advanceExpenses->where('status', 'approved')->sum('amount');
            $completedAdvanceCount = $advanceExpenses->where('status', 'complete')->count();
            $completedAdvanceAmount = $advanceExpenses->where('status', 'complete')->sum('amount');
            $cancelledAdvanceCount = $advanceExpenses->where('status', 'cancelled')->count();
            $cancelledAdvanceAmount = $advanceExpenses->where('status', 'cancelled')->sum('amount');
            
            // Settlement Statistics
            $totalSettlementCount = $settlementExpenses->count();
            $totalSettlementAmount = $settlementExpenses->sum('amount');
            $pendingSettlementCount = $settlementExpenses->where('status', 'pending')->count();
            $pendingSettlementAmount = $settlementExpenses->where('status', 'pending')->sum('amount');
            $approvedSettlementCount = $settlementExpenses->where('status', 'approved')->count();
            $approvedSettlementAmount = $settlementExpenses->where('status', 'approved')->sum('amount');
            $completedSettlementCount = $settlementExpenses->where('status', 'complete')->count();
            $completedSettlementAmount = $settlementExpenses->where('status', 'complete')->sum('amount');
            $cancelledSettlementCount = $settlementExpenses->where('status', 'cancelled')->count();
            $cancelledSettlementAmount = $settlementExpenses->where('status', 'cancelled')->sum('amount');
            
            // Combined Statistics
            $totalExpenses = $expenses->count();
            $totalAmount = $expenses->sum('amount');
            
            $pendingExpenses = $pendingAdvanceCount + $pendingSettlementCount;
            $pendingAmount = $pendingAdvanceAmount + $pendingSettlementAmount;
            
            $approvedExpenses = $approvedAdvanceCount + $approvedSettlementCount;
            $approvedAmount = $approvedAdvanceAmount + $approvedSettlementAmount;
            
            $completedExpenses = $completedAdvanceCount + $completedSettlementCount;
            $completedAmount = $completedAdvanceAmount + $completedSettlementAmount;
            
            $cancelledExpenses = $cancelledAdvanceCount + $cancelledSettlementCount;
            $cancelledAmount = $cancelledAdvanceAmount + $cancelledSettlementAmount;
            
            // Current Balance
            $currentBalance = $userBalance ? $userBalance->current_balance : 0;
            $totalAdvanceTaken = $userBalance ? $userBalance->advance_balance : 0;
            $totalSettlementDone = $userBalance ? $userBalance->settlement_balance : 0;
            $totalReimbursementDone = $userBalance ? $userBalance->reimbursement_balance : 0;
            
            // Payment Summary (if needed)
            $totalPayments = 0;
            $totalPaymentAmount = 0;
            
            // Get recent transactions
            $recentTransactions = ExpenseTransaction::where('user_id', $userId)
                ->with('expense')
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get();
            
            return response()->json([
                'success' => true,
                'message' => "Data fetched successfully!!!",
                'data' => [
                    'expenses' => $expenses,
                    'statistics' => [
                        // Overall totals
                        'total_expenses' => $totalExpenses,
                        'total_amount' => $totalAmount,
                        
                        // Advance totals
                        // 'advance' => [
                        //     'total_count' => $totalAdvanceCount,
                        //     'total_amount' => $totalAdvanceAmount,
                        //     'pending' => [
                        //         'count' => $pendingAdvanceCount,
                        //         'amount' => $pendingAdvanceAmount
                        //     ],
                        //     'approved' => [
                        //         'count' => $approvedAdvanceCount,
                        //         'amount' => $approvedAdvanceAmount
                        //     ],
                        //     'completed' => [
                        //         'count' => $completedAdvanceCount,
                        //         'amount' => $completedAdvanceAmount
                        //     ],
                        //     'cancelled' => [
                        //         'count' => $cancelledAdvanceCount,
                        //         'amount' => $cancelledAdvanceAmount
                        //     ]
                        // ],
                        
                        // Settlement totals
                        // 'settlement' => [
                        //     'total_count' => $totalSettlementCount,
                        //     'total_amount' => $totalSettlementAmount,
                        //     'pending' => [
                        //         'count' => $pendingSettlementCount,
                        //         'amount' => $pendingSettlementAmount
                        //     ],
                        //     'approved' => [
                        //         'count' => $approvedSettlementCount,
                        //         'amount' => $approvedSettlementAmount
                        //     ],
                        //     'completed' => [
                        //         'count' => $completedSettlementCount,
                        //         'amount' => $completedSettlementAmount
                        //     ],
                        //     'cancelled' => [
                        //         'count' => $cancelledSettlementCount,
                        //         'amount' => $cancelledSettlementAmount
                        //     ]
                        // ],
                        
                        // Combined by status
                        // 'by_status' => [
                        //     'pending' => [
                        //         'count' => $pendingExpenses,
                        //         'amount' => $pendingAmount
                        //     ],
                        //     'approved' => [
                        //         'count' => $approvedExpenses,
                        //         'amount' => $approvedAmount
                        //     ],
                        //     'completed' => [
                        //         'count' => $completedExpenses,
                        //         'amount' => $completedAmount
                        //     ],
                        //     'cancelled' => [
                        //         'count' => $cancelledExpenses,
                        //         'amount' => $cancelledAmount
                        //     ]
                        // ]
                    ],
                    'balance' => [
                        'current_balance' => (string) $currentBalance,
                        'total_advance_taken' => (string) $totalAdvanceTaken,
                        'total_settlement_done' => (string) $totalSettlementDone,
                        'total_reimbursement_done' => (string) $totalReimbursementDone,
                        'total_expenses_done' => (string) ($totalSettlementDone + $totalReimbursementDone),
                    ],
                    'recent_transactions' => $recentTransactions
                ]
            ], 200);
            
        } catch (Exception $e) {
            Log::error('Error in expense view: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => "An error occurred. Please try again later. ",
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'project_id' => 'nullable|exists:projects,id',
            'expense_type' => 'required|exists:expense_types,id',
            'requirement_type' => 'required|in:advance,settlement,reimbursement',
            'date' => 'required|date',
            'file' => 'nullable|file|max:2048',
            'amount' => 'required|numeric',
            'description' => 'nullable|max:255'
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

            // File Upload
            $filePath = null;
            if ($request->hasFile('file')) {
                $file = $request->file('file');
                $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                $path = public_path('uploads/expense/file');

                $file->move($path, $filename);
                $filePath = 'uploads/expense/file/' . $filename;
            }

            $expense = Expense::create([
                'user_id' => $user->id,
                'expense_type' => $request->expense_type,
                'amount' => $request->amount,
                'date' => $request->date,
                'project_id' => $request->project_id,
                'requirement_type' => $request->requirement_type,
                'description' => $request->description,
                'file' => $filePath,
                'status' => 'pending'
            ]);
            ExpenseStatusHistory::create([
                'expense_id' => $expense->id,
                'status' => 'pending',
                'changed_by' => $user->id,
                'remarks' => 'Expense submitted'
            ]);
            DB::commit();
            try {
                $this->notificationService->notifyExpenseSubmitted($expense);
            } catch (Exception $e) {
                Log::error('Failed to send expense notifications: ' . $e->getMessage());
            }
            return response()->json([
                'success' => true,
                'message' => 'Expense submitted successfully',
            ], 200);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => "An error occurred. Please try again later. "
            ], 500);
        }
    }

    public function view_all(Request $request)
    {
    try {
        $authUser = Auth::user();
        $baseUrl = env('APP_URL');

        // Check if user is manager
        if ($authUser->role !== 'manager') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access. Only managers can view this data.'
            ], 403);
        }

        // Get all expenses for team members under this manager
        $expenses = Expense::join('users', 'expenses.user_id', '=', 'users.id')
            ->join('user_job_details', 'user_job_details.user_id', '=', 'users.id')
            ->leftJoin('user_basic_details', 'user_basic_details.user_id', '=', 'users.id')
            ->leftJoin('expense_types', 'expenses.expense_type', '=', 'expense_types.id')
            ->leftJoin('projects', 'expenses.project_id', '=', 'projects.id')
            ->where('user_job_details.reporting_head', $authUser->id)
            ->select(
                'expenses.id',
                'expenses.expense_number',
                'users.employee_id',
                'users.name as employee_name',
                'users.email as employee_email',
                DB::raw("
                    CASE 
                        WHEN user_basic_details.profile_image IS NULL OR user_basic_details.profile_image = '' 
                        THEN NULL
                        ELSE CONCAT('$baseUrl', user_basic_details.profile_image)
                    END as employee_profile_image
                "),
                'expense_types.name as expense_type',
                'expenses.date',
                'projects.name as project_name',
                'projects.project_code as project_code',
                'expenses.requirement_type',
                'expenses.amount',
                'expenses.description',
                'expenses.status',
                'expenses.created_at',
                DB::raw("
                    CASE 
                        WHEN expenses.file IS NULL OR expenses.file = '' 
                        THEN NULL
                        ELSE CONCAT('$baseUrl/', expenses.file)
                    END as file_url
                ")
            )
            ->orderBy('expenses.created_at', 'desc')
            ->get();

        // Get team members list
        $teamMembers = User::join('user_job_details', 'user_job_details.user_id', '=', 'users.id')
            ->where('user_job_details.reporting_head', $authUser->id)
            ->select('users.id', 'users.name', 'users.email', 'users.employee_id')
            ->get();

        // Calculate statistics
        $advanceExpenses = $expenses->where('requirement_type', 'advance');
        $settlementExpenses = $expenses->where('requirement_type', 'settlement');

        // Advance Statistics
        $totalAdvanceCount = $advanceExpenses->count();
        $totalAdvanceAmount = $advanceExpenses->sum('amount');
        $pendingAdvanceCount = $advanceExpenses->where('status', 'pending')->count();
        $pendingAdvanceAmount = $advanceExpenses->where('status', 'pending')->sum('amount');
        $approvedAdvanceCount = $advanceExpenses->where('status', 'approved')->count();
        $approvedAdvanceAmount = $advanceExpenses->where('status', 'approved')->sum('amount');
        $completedAdvanceCount = $advanceExpenses->where('status', 'complete')->count();
        $completedAdvanceAmount = $advanceExpenses->where('status', 'complete')->sum('amount');
        $cancelledAdvanceCount = $advanceExpenses->where('status', 'cancelled')->count();
        $cancelledAdvanceAmount = $advanceExpenses->where('status', 'cancelled')->sum('amount');

        // Settlement Statistics
        $totalSettlementCount = $settlementExpenses->count();
        $totalSettlementAmount = $settlementExpenses->sum('amount');
        $pendingSettlementCount = $settlementExpenses->where('status', 'pending')->count();
        $pendingSettlementAmount = $settlementExpenses->where('status', 'pending')->sum('amount');
        $approvedSettlementCount = $settlementExpenses->where('status', 'approved')->count();
        $approvedSettlementAmount = $settlementExpenses->where('status', 'approved')->sum('amount');
        $completedSettlementCount = $settlementExpenses->where('status', 'complete')->count();
        $completedSettlementAmount = $settlementExpenses->where('status', 'complete')->sum('amount');
        $cancelledSettlementCount = $settlementExpenses->where('status', 'cancelled')->count();
        $cancelledSettlementAmount = $settlementExpenses->where('status', 'cancelled')->sum('amount');

        // Combined Statistics
        $totalExpenses = $expenses->count();
        $totalAmount = $expenses->sum('amount');
        
        $pendingExpenses = $pendingAdvanceCount + $pendingSettlementCount;
        $pendingAmount = $pendingAdvanceAmount + $pendingSettlementAmount;
        
        $approvedExpenses = $approvedAdvanceCount + $approvedSettlementCount;
        $approvedAmount = $approvedAdvanceAmount + $approvedSettlementAmount;
        
        $completedExpenses = $completedAdvanceCount + $completedSettlementCount;
        $completedAmount = $completedAdvanceAmount + $completedSettlementAmount;
        
        $cancelledExpenses = $cancelledAdvanceCount + $cancelledSettlementCount;
        $cancelledAmount = $cancelledAdvanceAmount + $cancelledSettlementAmount;

        // Get team member balances
        $teamBalances = [];
        foreach ($teamMembers as $member) {
            $balance = UserExpenseBalance::where('user_id', $member->id)->first();
            $teamBalances[] = [
                'user_id' => $member->id,
                'user_name' => $member->name,
                'current_balance' => $balance ? $balance->current_balance : 0,
                'total_advance_taken' => $balance ? $balance->advance_balance : 0,
                'total_settlement_done' => $balance ? $balance->settlement_balance : 0
            ];
        }

        return response()->json([
            'success' => true,
            'message' => 'Data fetched successfully!',
            'data' => [
                'expenses' => $expenses,
                'team_members' => $teamMembers,
                'team_balances' => $teamBalances,
                'statistics' => [
                    // Overall totals
                    // 'total' => [
                    //     'count' => $totalExpenses,
                    //     'amount' => $totalAmount
                    // ],
                    
                    // Advance totals
                    // 'advance' => [
                    //     'total' => [
                    //         'count' => $totalAdvanceCount,
                    //         'amount' => $totalAdvanceAmount
                    //     ],
                    //     'pending' => [
                    //         'count' => $pendingAdvanceCount,
                    //         'amount' => $pendingAdvanceAmount
                    //     ],
                    //     'approved' => [
                    //         'count' => $approvedAdvanceCount,
                    //         'amount' => $approvedAdvanceAmount
                    //     ],
                    //     'completed' => [
                    //         'count' => $completedAdvanceCount,
                    //         'amount' => $completedAdvanceAmount
                    //     ],
                    //     'cancelled' => [
                    //         'count' => $cancelledAdvanceCount,
                    //         'amount' => $cancelledAdvanceAmount
                    //     ]
                    // ],
                    
                    // Settlement totals
                    // 'settlement' => [
                    //     'total' => [
                    //         'count' => $totalSettlementCount,
                    //         'amount' => $totalSettlementAmount
                    //     ],
                    //     'pending' => [
                    //         'count' => $pendingSettlementCount,
                    //         'amount' => $pendingSettlementAmount
                    //     ],
                    //     'approved' => [
                    //         'count' => $approvedSettlementCount,
                    //         'amount' => $approvedSettlementAmount
                    //     ],
                    //     'completed' => [
                    //         'count' => $completedSettlementCount,
                    //         'amount' => $completedSettlementAmount
                    //     ],
                    //     'cancelled' => [
                    //         'count' => $cancelledSettlementCount,
                    //         'amount' => $cancelledSettlementAmount
                    //     ]
                    // ],
                    
                    // By status
                    // 'by_status' => [
                    //     'pending' => [
                    //         'count' => $pendingExpenses,
                    //         'amount' => $pendingAmount
                    //     ],
                    //     'approved' => [
                    //         'count' => $approvedExpenses,
                    //         'amount' => $approvedAmount
                    //     ],
                    //     'completed' => [
                    //         'count' => $completedExpenses,
                    //         'amount' => $completedAmount
                    //     ],
                    //     'cancelled' => [
                    //         'count' => $cancelledExpenses,
                    //         'amount' => $cancelledAmount
                    //     ]
                    // ]
                ]
            ]
        ], 200);
        
    } catch (Exception $e) {
        Log::error('Error in view_all manager: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'An error occurred. Please try again later. ',
        ], 500);
    }
}

    public function updateStatus(Request $request)
    {
        $validator = Validator::make($request->all(), [
             'id' => 'required|exists:expenses,id',
            'status' => 'required|in:approved,cancelled,complete',
            'remarks' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 200);
        }

        DB::beginTransaction();

        try {
             $id = $request->id;
            $authUser = Auth::user();

            // Check permission
            if (!in_array($authUser->role, ['admin', 'hr', 'manager'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 200);
            }

            // Get expense with authorization check
            $expense = Expense::where('expenses.id', $id)
                ->leftJoin('user_job_details', 'expenses.user_id', '=', 'user_job_details.user_id')
                ->select('expenses.*', 'user_job_details.reporting_head')
                ->first();

            if (!$expense) {
                return response()->json([
                    'success' => false,
                    'message' => 'Expense not found.'
                ], 200);
            }

            // Check authorization for non-admin/hr
            if (!in_array($authUser->role, ['admin', 'hr', 'manager'])) {
                if ($expense->reporting_head != $authUser->id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'You are not authorized to update this expense.'
                    ], 200);
                }
            }

            if ($expense->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'This expense has already been processed.'
                ], 200);
            }

            if ($request->status === 'approved') {
                if ($expense->requirement_type === 'advance') {
                    // For advance: Just mark as approved, payment will be created separately
                    $expense->update([
                        'status' => 'approved',
                        'approved_by' => $authUser->id,
                        'approved_at' => now(),
                        'approval_remarks' => $request->remarks
                    ]);
                    $message = 'Advance approved. Please create payment to credit balance.';
                } elseif ($expense->requirement_type === 'settlement') {
                  try {
                    $this->handleSettlementApproval($expense, $authUser->id, $request->remarks);
                    $message = 'Settlement approved and balance deducted successfully';
                    } catch (Exception $e) {
                        // Rollback transaction and return specific error
                        DB::rollBack();
                        return response()->json([
                            'success' => false,
                            'message' => $e->getMessage() 
                        ], 200);
                    }
                } elseif ($expense->requirement_type === 'reimbursement') {
                    // ✅ NEW: Just approve it — payment will trigger actual reimbursement
                    $expense->update([
                        'status' => 'approved',
                        'approved_by' => $authUser->id,
                        'approved_at' => now(),
                        'approval_remarks' => $request->remarks
                    ]);
                    $message = 'Reimbursement approved. Please process payment to reimburse the employee.';
                } else {
                    $expense->update([
                        'status' => 'approved',
                        'approved_by' => $authUser->id,
                        'approved_at' => now(),
                        'approval_remarks' => $request->remarks
                    ]);
                    $message = 'Expense approved successfully';
                }
            } elseif ($request->status === 'cancelled') {
                // Handle rejection - no balance changes
                $expense->update([
                    'status' => 'cancelled',
                    'rejected_by' => $authUser->id,
                    'rejected_at' => now(),
                    'rejection_reason' => $request->remarks
                ]);
                $message = 'Expense rejected successfully';
            } else {
                // Handle complete status
                $expense->update([
                    'status' => 'complete',
                    'status_update_by' => $authUser->id,
                    'status_update_remarks' => $request->remarks,
                    'status_update_at' => now(),
                ]);
                $message = 'Expense marked as complete';
            }

            ExpenseStatusHistory::create([
                'expense_id' => $id,
                'status' => $request->status,
                'changed_by' => $authUser->id,
                'remarks' => $request->remarks
            ]);

            DB::commit();
            try {
                if ($request->status === 'approved') {
                    $this->notificationService->notifyExpenseApproved($expense, $request->remarks);
                } elseif ($request->status === 'cancelled') {
                    $this->notificationService->notifyExpenseRejected($expense, $request->remarks);
                }
            } catch (Exception $e) {
                Log::error('Failed to send status update notification: ' . $e->getMessage());
            }
            return response()->json([
                'success' => true,
                'message' => $message
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update status: ' . $e->getMessage()
            ], 500);
        }
    }

    private function handleSettlementApproval($expense, $approvedBy, $remarks)
    {
        // Get user balance
        $balance = UserExpenseBalance::where('user_id', $expense->user_id)->first();

        if (!$balance || $balance->current_balance < $expense->amount) {
            throw new Exception("Insufficient advance balance. Available: " .
                ($balance ? $balance->current_balance : 0) .
                ", Requested: " . $expense->amount);
        }

        $balanceBefore = $balance->current_balance;
        $balanceAfter = $balanceBefore - $expense->amount;

        // Update expense
        $expense->update([
            'status' => 'approved',
            'approved_by' => $approvedBy,
            'approved_at' => now(),
            'approval_remarks' => $remarks
        ]);

        // Update user balance
        $balance->update([
            'current_balance' => $balanceAfter,
            'settlement_balance' => $balance->settlement_balance + $expense->amount
        ]);

        // Create transaction record
        ExpenseTransaction::create([
            'expense_id' => $expense->id,
            'user_id' => $expense->user_id,
            'transaction_type' => 'settlement_debited',
            'amount' => $expense->amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'description' => $remarks ?: 'Settlement approved and balance deducted'
        ]);

        return true;
    }
}
