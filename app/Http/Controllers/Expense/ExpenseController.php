<?php

namespace App\Http\Controllers\Expense;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\ExpenseType;
use App\Models\ExpenseStatusHistory;
use App\Models\ExpenseTransaction;
use App\Models\User;
use App\Models\Project;
use App\Models\UserExpenseBalance;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use App\Services\ExpenseNotificationService;
use App\Services\RbacService;
use App\Traits\AuthorizesByScope;

class ExpenseController extends Controller
{
    use AuthorizesByScope;

    protected $notificationService;

    public function __construct(ExpenseNotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Display user's own expenses (old method - keep for backward compatibility)
     */
    public function index(Request $request)
    {
        try {
            $userId = Auth::id();
            $baseUrl = config('app.url');
            $authUser = Auth::user();

            $userBalance = UserExpenseBalance::where('user_id', $userId)->first();
            $currentBalance = $userBalance ? $userBalance->current_balance : 0;
            $totalAdvanceTaken = $userBalance ? $userBalance->advance_balance : 0;
            $totalSettlementDone = $userBalance ? $userBalance->settlement_balance : 0;
            $totalReimbursementDone = $userBalance ? $userBalance->reimbursement_balance : 0;

            // Get projects based on role (for dropdown)
            $projectsQuery = Project::query();

            if ($authUser->role === 'manager' || $authUser->role === 'admin') {
                $projects = $projectsQuery->orderBy('name')->get(['id', 'project_code', 'name']);
            } else {
                $projects = $projectsQuery->whereHas('assignments', function ($q) use ($authUser) {
                    $q->where('user_id', $authUser->id);
                })->orderBy('name')->get(['id', 'project_code', 'name']);
            }

            // Get expense types for dropdown
            $expenseTypes = ExpenseType::where('status', 1)->orderBy('name')->get();

            // Build the expenses query with filters
            $expenseQuery = Expense::where('expenses.user_id', $userId)
                ->with(['expenseType', 'project', 'payments'])
                ->leftJoin('expense_types', 'expenses.expense_type', '=', 'expense_types.id')
                ->leftJoin('projects', 'expenses.project_id', '=', 'projects.id')
                ->select([
                    'expenses.*',
                    'expense_types.name as expense_type_name',
                    'projects.name as project_name',
                ]);

            // Apply filters
            if ($request->filled('status')) {
                $expenseQuery->where('status', $request->status);
            }

            if ($request->filled('expense_type')) {
                $expenseQuery->where('expenses.expense_type', $request->expense_type);
            }

            if ($request->filled('requirement_type')) {
                $expenseQuery->where('expenses.requirement_type', $request->requirement_type);
            }

            if ($request->filled('project_id')) {
                $expenseQuery->where('expenses.project_id', $request->project_id);
            }

            if ($request->filled('from_date')) {
                $expenseQuery->whereDate('expenses.date', '>=', $request->from_date);
            }

            if ($request->filled('to_date')) {
                $expenseQuery->whereDate('expenses.date', '<=', $request->to_date);
            }

            if ($request->filled('search')) {
                $searchTerm = '%' . $request->search . '%';
                $expenseQuery->where(function ($q) use ($searchTerm) {
                    $q->where('expenses.description', 'LIKE', $searchTerm)
                        ->orWhere('projects.name', 'LIKE', $searchTerm)
                        ->orWhere('expense_types.name', 'LIKE', $searchTerm);
                });
            }

            // Get filtered expenses
            $expenses = $expenseQuery
                ->orderBy('expenses.created_at', 'desc')
                ->get();

            // Transform file URLs
            $expenses->transform(function ($expense) use ($baseUrl) {
                $expense->file = $expense->file ? asset($expense->file) : null;
                return $expense;
            });

            // ==================== ENHANCED STATISTICS ====================
            // Note: These statistics are calculated on the FILTERED expenses collection
            // This means cards will reflect the current filter selections!

            // 1. TOTAL STATISTICS
            // $totalExpenses = $expenses->count();
            // $totalAmount = $expenses->sum('amount');
             $totalExpenses = $expenses->whereIn('requirement_type', ['settlement','reimbursement'])->count();
            $totalAmount = $expenses->whereIn('requirement_type', ['settlement','reimbursement'])->sum('amount');

            // 2. SEPARATE BY REQUIREMENT TYPE
            $advanceExpenses = $expenses->where('requirement_type', 'advance');
            $settlementExpenses = $expenses->where('requirement_type', 'settlement');
            $reimbursementExpenses = $expenses->where('requirement_type', 'reimbursement');

            // 3. ADVANCE STATISTICS
            $totalAdvanceCount = $advanceExpenses->count();
            $totalAdvanceAmount = $advanceExpenses->sum('amount');

            // 4. SETTLEMENT STATISTICS
            $totalSettlementCount = $settlementExpenses->count();
            $totalSettlementAmount = $settlementExpenses->sum('amount');

            // 5. REIMBURSEMENT STATISTICS
            $totalReimbursementCount = $reimbursementExpenses->count();
            $totalReimbursementAmount = $reimbursementExpenses->sum('amount');

            // 6. PENDING STATISTICS (by type)
            $pendingAdvanceCount = $advanceExpenses->where('status', 'pending')->count();
            $pendingAdvanceAmount = $advanceExpenses->where('status', 'pending')->sum('amount');

            $pendingSettlementCount = $settlementExpenses->where('status', 'pending')->count();
            $pendingSettlementAmount = $settlementExpenses->where('status', 'pending')->sum('amount');

            $pendingReimbursementCount = $reimbursementExpenses->where('status', 'pending')->count();
            $pendingReimbursementAmount = $reimbursementExpenses->where('status', 'pending')->sum('amount');

            // 7. APPROVED STATISTICS (by type)
            $approvedAdvanceCount = $advanceExpenses->where('status', 'approved')->count();
            $approvedAdvanceAmount = $advanceExpenses->where('status', 'approved')->sum('amount');

            $approvedSettlementCount = $settlementExpenses->where('status', 'approved')->count();
            $approvedSettlementAmount = $settlementExpenses->where('status', 'approved')->sum('amount');

            $approvedReimbursementCount = $reimbursementExpenses->where('status', 'approved')->count();
            $approvedReimbursementAmount = $reimbursementExpenses->where('status', 'approved')->sum('amount');

            // 8. COMPLETED STATISTICS (by type)
            $completedAdvanceCount = $advanceExpenses->where('status', 'complete')->count();
            $completedAdvanceAmount = $advanceExpenses->where('status', 'complete')->sum('amount');

            $completedSettlementCount = $settlementExpenses->where('status', 'complete')->count();
            $completedSettlementAmount = $settlementExpenses->where('status', 'complete')->sum('amount');

            $completedReimbursementCount = $reimbursementExpenses->where('status', 'complete')->count();
            $completedReimbursementAmount = $reimbursementExpenses->where('status', 'complete')->sum('amount');

            // 9. CANCELLED STATISTICS (by type)
            $cancelledAdvanceCount = $advanceExpenses->where('status', 'cancelled')->count();
            $cancelledAdvanceAmount = $advanceExpenses->where('status', 'cancelled')->sum('amount');

            $cancelledSettlementCount = $settlementExpenses->where('status', 'cancelled')->count();
            $cancelledSettlementAmount = $settlementExpenses->where('status', 'cancelled')->sum('amount');

            $cancelledReimbursementCount = $reimbursementExpenses->where('status', 'cancelled')->count();
            $cancelledReimbursementAmount = $reimbursementExpenses->where('status', 'cancelled')->sum('amount');

            // 10. OVERALL PENDING/APPROVED/COMPLETED (combined)
            $pendingCount =  $pendingSettlementCount + $pendingReimbursementCount;
            $pendingAmount =  $pendingSettlementAmount + $pendingReimbursementAmount;

            $approvedCount = $approvedSettlementCount + $approvedReimbursementCount;
            $approvedAmount =  $approvedSettlementAmount + $approvedReimbursementAmount;

            $completedCount = $completedSettlementCount + $completedReimbursementCount;
            $completedAmount =  $completedSettlementAmount + $completedReimbursementAmount;

            $cancelledCount =  $cancelledSettlementCount + $cancelledReimbursementCount;
            $cancelledAmount = $cancelledSettlementAmount + $cancelledReimbursementAmount;

            $data = [
                // Basic data
                'projects' => $projects,
                'expenseTypes' => $expenseTypes,
                'expenses' => $expenses,
                'filters' => $request->all(),

                // User balance data (these are NOT filtered - they show overall totals)
                'currentBalance' => $currentBalance,
                'totalAdvanceTaken' => $totalAdvanceTaken,
                'totalSettlementDone' => $totalSettlementDone,
                'totalReimbursementDone' => $totalReimbursementDone,

                // Combined totals (FILTERED)
                'totalExpenses' => $totalExpenses,
                'totalAmount' => $totalAmount,

                // Advance totals (FILTERED)
                'totalAdvanceCount' => $totalAdvanceCount,
                'totalAdvanceAmount' => $totalAdvanceAmount,

                // Settlement totals (FILTERED)
                'totalSettlementCount' => $totalSettlementCount,
                'totalSettlementAmount' => $totalSettlementAmount,

                // Reimbursement totals (FILTERED)
                'totalReimbursementCount' => $totalReimbursementCount,
                'totalReimbursementAmount' => $totalReimbursementAmount,

                // Combined status totals (FILTERED)
                'pendingCount' => $pendingCount,
                'pendingAmount' => $pendingAmount,
                'approvedCount' => $approvedCount,
                'approvedAmount' => $approvedAmount,
                'completedCount' => $completedCount,
                'completedAmount' => $completedAmount,
                'cancelledCount' => $cancelledCount,
                'cancelledAmount' => $cancelledAmount,

                // Advance-specific totals (FILTERED)
                'pendingAdvanceCount' => $pendingAdvanceCount,
                'pendingAdvanceAmount' => $pendingAdvanceAmount,
                'approvedAdvanceCount' => $approvedAdvanceCount,
                'approvedAdvanceAmount' => $approvedAdvanceAmount,
                'completedAdvanceCount' => $completedAdvanceCount,
                'completedAdvanceAmount' => $completedAdvanceAmount,
                'cancelledAdvanceCount' => $cancelledAdvanceCount,
                'cancelledAdvanceAmount' => $cancelledAdvanceAmount,

                // Settlement-specific totals (FILTERED)
                'pendingSettlementCount' => $pendingSettlementCount,
                'pendingSettlementAmount' => $pendingSettlementAmount,
                'approvedSettlementCount' => $approvedSettlementCount,
                'approvedSettlementAmount' => $approvedSettlementAmount,
                'completedSettlementCount' => $completedSettlementCount,
                'completedSettlementAmount' => $completedSettlementAmount,
                'cancelledSettlementCount' => $cancelledSettlementCount,
                'cancelledSettlementAmount' => $cancelledSettlementAmount,

                // Reimbursement-specific totals (FILTERED)
                'pendingReimbursementCount' => $pendingReimbursementCount,
                'pendingReimbursementAmount' => $pendingReimbursementAmount,
                'approvedReimbursementCount' => $approvedReimbursementCount,
                'approvedReimbursementAmount' => $approvedReimbursementAmount,
                'completedReimbursementCount' => $completedReimbursementCount,
                'completedReimbursementAmount' => $completedReimbursementAmount,
                'cancelledReimbursementCount' => $cancelledReimbursementCount,
                'cancelledReimbursementAmount' => $cancelledReimbursementAmount,
            ];

            return view('client.expense.expense.expense', $data);
        } catch (Exception $e) {
            return back()->with('error', 'Something went wrong: ' . $e->getMessage());
        }
    }

    /**
     * Store a newly created expense
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'expense_type' => 'required|exists:expense_types,id',
            'project_id' => 'nullable|exists:projects,id',
            'requirement_type' => 'required|in:advance,settlement,reimbursement',
            'description' => 'nullable|string|max:1000',
            'file' => 'nullable|file|mimes:jpg,jpeg,png,pdf,doc,docx|max:2048',
            'amount' => 'required|numeric|min:0',
            'date' => 'required|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            $userId = Auth::id();

            // Handle file upload
            $filePath = null;
            if ($request->hasFile('file')) {
                $file = $request->file('file');
                $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                $directory = public_path('uploads/expense/file');

                if (!file_exists($directory)) {
                    mkdir($directory, 0755, true);
                }

                $file->move($directory, $filename);
                $filePath = 'uploads/expense/file/' . $filename;
            }

            // Create expense
            $expense = Expense::create([
                'user_id' => $userId,
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
                'changed_by' => $userId,
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
                'message' => 'Expense Created Successfully!!!'
            ], 200);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Something went wrong: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update an existing expense
     */
    public function update(Request $request)
    {
        $id = $request->id;

        $validator = Validator::make($request->all(), [
            'expense_type' => 'required|exists:expense_types,id',
            'project_id' => 'nullable|exists:projects,id',
            'requirement_type' => 'required|in:advance,settlement,reimbursement',
            'description' => 'nullable|string|max:1000',
            'file' => 'nullable|file|mimes:jpg,jpeg,png,pdf,doc,docx|max:2048',
            'amount' => 'required|numeric|min:0',
            'date' => 'required|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            $authUser = Auth::user();
            $expense = Expense::findOrFail($id);

            // Check if expense can be updated
            if (!in_array($expense->status, ['pending'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only pending expenses can be updated.'
                ], 400);
            }

            // Handle file upload
            if ($request->hasFile('file')) {
                // Delete old file if exists
                if ($expense->file && file_exists(public_path($expense->file))) {
                    unlink(public_path($expense->file));
                }

                $file = $request->file('file');
                $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                $directory = public_path('uploads/expense/file');

                if (!file_exists($directory)) {
                    mkdir($directory, 0755, true);
                }

                $file->move($directory, $filename);
                $expense->file = 'uploads/expense/file/' . $filename;
            }

            // Update expense
            $expense->expense_type = $request->expense_type;
            $expense->amount = $request->amount;
            $expense->date = $request->date;
            $expense->project_id = $request->project_id;
            $expense->requirement_type = $request->requirement_type;
            $expense->description = $request->description;
            $expense->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Expense Updated Successfully!'
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Expense not found.'
            ], 404);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Something went wrong: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * View all expenses (for managers/admin/hr)
     */
    public function view_all(Request $request)
    {
        try {
            $authUser = Auth::user();
            $userId = $authUser->id;

            // Unlike some modules' "view all" screens, this one was never
            // role-gated at all — even a plain employee could reach it and
            // simply saw a narrower (own+team) result set. Preserve that:
            // only a genuine "no permission at all" blocks access; 'own'
            // scope still gets in, just filtered narrowly below.
            $scope = app(RbacService::class)->scopeFor($authUser, 'expenses', 'view');
            if ($scope === null) {
                abort(403, 'You do not have permission to view expenses.');
            }
            $needsOwnerFilter = $scope !== 'company';

            // Get expense types
            $expenseTypes = ExpenseType::where('status', 1)
                ->orderBy('name')
                ->get(['id', 'name']);

            // Build the expenses query
            $expenseQuery = Expense::with(['user', 'expenseType', 'project', 'payments'])
                ->join('users', 'expenses.user_id', '=', 'users.id')
                ->leftJoin('expense_types', 'expenses.expense_type', '=', 'expense_types.id')
                ->leftJoin('user_job_details', 'expenses.user_id', '=', 'user_job_details.user_id')
                ->leftJoin('projects', 'expenses.project_id', '=', 'projects.id')
                ->select(
                    'expenses.*',
                    'users.name as user_name',
                    'users.email as user_email',
                    'users.employee_id',
                    'expense_types.name as expense_type_name',
                    'projects.name as project_name',
                    'user_job_details.department',
                    'user_job_details.designation',
                    'user_job_details.reporting_head'
                );

            // Permission-driven scope (was: hardcoded !in_array(role, [admin,hr]))
            if ($needsOwnerFilter) {
                $expenseQuery->where(function ($q) use ($authUser, $userId) {
                    $q->where('expenses.user_id', $userId)
                        ->orWhere('user_job_details.reporting_head', $authUser->id);
                });
            }

            // Apply filters
            if ($request->filled('status')) {
                $expenseQuery->where('expenses.status', $request->status);
            }

            if ($request->filled('from_date')) {
                $expenseQuery->whereDate('expenses.date', '>=', $request->from_date);
            }

            if ($request->filled('to_date')) {
                $expenseQuery->whereDate('expenses.date', '<=', $request->to_date);
            }

            if ($request->filled('expense_type')) {
                $expenseQuery->where('expenses.expense_type', $request->expense_type);
            }
            if ($request->filled('project_id')) {
                $expenseQuery->where('expenses.project_id', $request->project_id);
            }

            // Only keep one requirement_type filter
            if ($request->filled('requirement_type')) {
                $expenseQuery->where('expenses.requirement_type', $request->requirement_type);
            }

            if ($request->filled('user_id') && $scope === 'company') {
                $expenseQuery->where('expenses.user_id', $request->user_id);
            }

            if ($request->filled('search')) {
                $searchTerm = '%' . $request->search . '%';
                $expenseQuery->where(function ($q) use ($searchTerm) {
                    $q->where('users.name', 'LIKE', $searchTerm)
                        ->orWhere('users.email', 'LIKE', $searchTerm)
                        ->orWhere('expenses.description', 'LIKE', $searchTerm)
                        ->orWhere('expense_types.name', 'LIKE', $searchTerm)
                        ->orWhere('projects.name', 'LIKE', $searchTerm);
                });
            }

            // ==================== ENHANCED COUNT STATISTICS ====================

            // Base query for counts (without pagination)
            $countsQuery = $expenseQuery;

            $allExpenses = (clone $countsQuery)->get();

            // Separate by requirement type
            $advanceExpenses = $allExpenses->where('requirement_type', 'advance');
            $settlementExpenses = $allExpenses->where('requirement_type', 'settlement');
            $reimbursementExpenses = $allExpenses->where('requirement_type', 'reimbursement');

            // ==================== TOTAL COUNTS ====================
             $totalExpenses = $allExpenses->whereIn('requirement_type', ['advance','reimbursement'])->count();
            $totalAmount = $allExpenses->whereIn('requirement_type', ['advance','reimbursement'])->sum('amount');

            $totalAdvanceCount = $advanceExpenses->count();
            $totalAdvanceAmount = $advanceExpenses->sum('amount');

            $totalSettlementCount = $settlementExpenses->count();
            $totalSettlementAmount = $settlementExpenses->sum('amount');

            $totalReimbursementCount = $reimbursementExpenses->count();
            $totalReimbursementAmount = $reimbursementExpenses->sum('amount');

            // ==================== PENDING COUNTS ====================
            $pendingAdvanceCount = $advanceExpenses->where('status', 'pending')->count();
            $pendingAdvanceAmount = $advanceExpenses->where('status', 'pending')->sum('amount');

            $pendingSettlementCount = $settlementExpenses->where('status', 'pending')->count();
            $pendingSettlementAmount = $settlementExpenses->where('status', 'pending')->sum('amount');

            $pendingReimbursementCount = $reimbursementExpenses->where('status', 'pending')->count();
            $pendingReimbursementAmount = $reimbursementExpenses->where('status', 'pending')->sum('amount');

            $pendingCount = $pendingAdvanceCount + $pendingReimbursementCount;
            $pendingAmount = $pendingAdvanceAmount + $pendingReimbursementAmount;

            // ==================== APPROVED COUNTS ====================
            $approvedAdvanceCount = $advanceExpenses->where('status', 'approved')->count();
            $approvedAdvanceAmount = $advanceExpenses->where('status', 'approved')->sum('amount');

            $approvedSettlementCount = $settlementExpenses->where('status', 'approved')->count();
            $approvedSettlementAmount = $settlementExpenses->where('status', 'approved')->sum('amount');

            $approvedReimbursementCount = $reimbursementExpenses->where('status', 'approved')->count();
            $approvedReimbursementAmount = $reimbursementExpenses->where('status', 'approved')->sum('amount');

            $approvedCount = $approvedAdvanceCount + $approvedReimbursementCount;
            $approvedAmount = $approvedAdvanceAmount + $approvedReimbursementAmount;

            // ==================== COMPLETED COUNTS ====================
            $completedAdvanceCount = $advanceExpenses->where('status', 'complete')->count();
            $completedAdvanceAmount = $advanceExpenses->where('status', 'complete')->sum('amount');

            $completedSettlementCount = $settlementExpenses->where('status', 'complete')->count();
            $completedSettlementAmount = $settlementExpenses->where('status', 'complete')->sum('amount');

            $completedReimbursementCount = $reimbursementExpenses->where('status', 'complete')->count();
            $completedReimbursementAmount = $reimbursementExpenses->where('status', 'complete')->sum('amount');

            $completedCount = $completedAdvanceCount + $completedReimbursementCount;
            $completedAmount = $completedAdvanceAmount + $completedReimbursementAmount;

            // ==================== CANCELLED COUNTS ====================
            $cancelledAdvanceCount = $advanceExpenses->where('status', 'cancelled')->count();

            $cancelledAdvanceAmount = $advanceExpenses->where('status', 'cancelled')->sum('amount');

            $cancelledSettlementCount = $settlementExpenses->where('status', 'cancelled')->count();
            $cancelledSettlementAmount = $settlementExpenses->where('status', 'cancelled')->sum('amount');

            $cancelledReimbursementCount = $reimbursementExpenses->where('status', 'cancelled')->count();
            $cancelledReimbursementAmount = $reimbursementExpenses->where('status', 'cancelled')->sum('amount');

            $cancelledCount = $cancelledAdvanceCount  + $cancelledReimbursementCount;
            $cancelledAmount = $cancelledAdvanceAmount + $cancelledReimbursementAmount;
            // Get paginated results
            $expenses = $expenseQuery
                ->orderBy('expenses.date', 'desc')
                ->orderBy('expenses.created_at', 'desc')
                ->paginate(15)
                ->withQueryString();

            // Transform file URLs
            $expenses->getCollection()->transform(function ($expense) {
                $expense->file = $expense->file ? asset($expense->file) : null;
                return $expense;
            });


            // Get employees for filter dropdown
            $employeesQuery = User::where('status', 1)
                ->with(['jobDetails']);

            if ($needsOwnerFilter) {
                $employeesQuery->where(function ($q) use ($authUser, $userId) {
                    $q->whereHas('jobDetails', function ($query) use ($authUser) {
                        $query->where('reporting_head', $authUser->id);
                    })->orWhere('id', $authUser->id);
                });
            }

            $employees = $employeesQuery
                ->select('id', 'name', 'email', 'employee_id')
                ->orderBy('name')
                ->get();

            // Get projects for filter
            $projects = Project::where('status', '!=', 'cancelled')
                ->orderBy('name')
                ->get(['id', 'name', 'project_code']);

            $data = [
                'expenses' => $expenses,
                'expenseTypes' => $expenseTypes,
                'employees' => $employees,
                'projects' => $projects,

                // Combined totals
                'totalExpenses' => $totalExpenses,
                'totalAmount' => $totalAmount,

                // Advance specific totals (matching card variable names)
                'totalAdvanceCount' => $totalAdvanceCount,
                'totalAdvanceAmount' => $totalAdvanceAmount,

                // Settlement specific totals (matching card variable names)
                'totalSettlementCount' => $totalSettlementCount,
                'totalSettlementAmount' => $totalSettlementAmount,

                // Reimbursement specific totals (matching card variable names)
                'totalReimbursementCount' => $totalReimbursementCount,
                'totalReimbursementAmount' => $totalReimbursementAmount,

                // Pending with breakdown (matching card variable names)
                'pendingCount' => $pendingCount,
                'pendingAmount' => $pendingAmount,
                'pendingAdvanceCount' => $pendingAdvanceCount,
                'pendingAdvanceAmount' => $pendingAdvanceAmount,
                'pendingSettlementCount' => $pendingSettlementCount,
                'pendingSettlementAmount' => $pendingSettlementAmount,
                'pendingReimbursementCount' => $pendingReimbursementCount,
                'pendingReimbursementAmount' => $pendingReimbursementAmount,

                // Approved with breakdown (matching card variable names)
                'approvedCount' => $approvedCount,
                'approvedAmount' => $approvedAmount,
                'approvedAdvanceCount' => $approvedAdvanceCount,
                'approvedAdvanceAmount' => $approvedAdvanceAmount,
                'approvedSettlementCount' => $approvedSettlementCount,
                'approvedSettlementAmount' => $approvedSettlementAmount,
                'approvedReimbursementCount' => $approvedReimbursementCount,
                'approvedReimbursementAmount' => $approvedReimbursementAmount,

                // Completed with breakdown (matching card variable names)
                'completedCount' => $completedCount,
                'completedAmount' => $completedAmount,
                'completedAdvanceCount' => $completedAdvanceCount,
                'completedAdvanceAmount' => $completedAdvanceAmount,
                'completedSettlementCount' => $completedSettlementCount,
                'completedSettlementAmount' => $completedSettlementAmount,
                'completedReimbursementCount' => $completedReimbursementCount,
                'completedReimbursementAmount' => $completedReimbursementAmount,

                // Cancelled with breakdown (matching card variable names)
                'cancelledCount' => $cancelledCount,
                'cancelledAmount' => $cancelledAmount,
                'cancelledAdvanceCount' => $cancelledAdvanceCount,
                'cancelledAdvanceAmount' => $cancelledAdvanceAmount,
                'cancelledSettlementCount' => $cancelledSettlementCount,
                'cancelledSettlementAmount' => $cancelledSettlementAmount,
                'cancelledReimbursementCount' => $cancelledReimbursementCount,
                'cancelledReimbursementAmount' => $cancelledReimbursementAmount,

                'userRole' => $authUser->role,
                'filters' => $request->all()
            ];

            return view('client.expense.expense.view-all-expense', $data);
        } catch (Exception $e) {
          
            Log::error('Error in view_all: ' . $e->getMessage());
            return back()->with('error', 'Something went wrong: ' . $e->getMessage());
        }
    }

    /**
     * Update expense status (Approve/Reject)
     */
    public function updateStatus(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:approved,cancelled,complete',
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

            // Coarse "can this role approve expenses at all" is enforced at
            // the route level (permission:expenses,approve). Get the
            // expense, then check whether its owner falls within the
            // caller's granted scope.
            //
            // Note: the previous version of this check had a real bug —
            // it repeated the exact same `!in_array(role, [admin,hr,manager])`
            // test that had already caused an early return just above when
            // true, so by the time execution reached the second check it
            // could never be true again, meaning the manager/reporting_head
            // ownership check below it was dead code. In practice this
            // meant any manager could approve ANY expense, not just their
            // own team's. scopeCoversOwner() replaces both checks correctly.
            $expense = Expense::where('expenses.id', $id)
                ->leftJoin('user_job_details', 'expenses.user_id', '=', 'user_job_details.user_id')
                ->select('expenses.*', 'user_job_details.reporting_head')
                ->first();

            if (!$expense) {
                return response()->json([
                    'success' => false,
                    'message' => 'Expense not found.'
                ], 404);
            }

            if (!$this->scopeCoversOwner($authUser, 'expenses', 'approve', $expense->user_id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not authorized to update this expense.'
                ], 403);
            }

            if ($expense->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'This expense has already been processed.'
                ], 400);
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
                            'message' => $e->getMessage() // Return the actual error message
                        ], 400);
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

    /**
     * Get user's expense balance (API endpoint)
     */
    public function getBalance()
    {
        try {
            $userId = Auth::id();
            $balance = UserExpenseBalance::where('user_id', $userId)->first();

            // Get recent transactions
            $recentTransactions = ExpenseTransaction::where('user_id', $userId)
                ->with('expense')
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'current_balance' => $balance ? $balance->current_balance : 0,
                    'total_advance_taken' => $balance ? $balance->advance_balance : 0,
                    'total_settlement_done' => $balance ? $balance->settlement_balance : 0,
                    'recent_transactions' => $recentTransactions
                ]
            ]);
        } catch (Exception $e) {
            Log::error('Error getting balance: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get balance'
            ], 500);
        }
    }

    /**
     * Get expense transactions for an expense
     */
    public function getTransactions($expenseId)
    {
        try {
            $expense = Expense::findOrFail($expenseId);
            $transactions = $expense->transactions()->orderBy('created_at', 'desc')->get();

            return response()->json([
                'success' => true,
                'data' => $transactions
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get transactions'
            ], 500);
        }
    }

    /**
     * Get user's transaction history
     */
    public function getTransactionHistory(Request $request)
    {
        try {
            $userId = Auth::id();
            $limit = $request->get('limit', 50);

            $transactions = ExpenseTransaction::where('user_id', $userId)
                ->with(['expense', 'expense.expenseType', 'expense.project'])
                ->orderBy('created_at', 'desc')
                ->paginate($limit);

            return response()->json([
                'success' => true,
                'data' => $transactions
            ]);
        } catch (Exception $e) {
            Log::error('Error getting transaction history: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get transaction history'
            ], 500);
        }
    }

    /**
     * Get user's expense summary with balances
     */
    public function getSummary()
    {
        try {
            $userId = Auth::id();

            // Get balance
            $balance = UserExpenseBalance::where('user_id', $userId)->first();

            // Get pending requests
            $pendingAdvances = Expense::where('user_id', $userId)
                ->where('requirement_type', 'advance')
                ->where('status', 'pending')
                ->sum('amount');

            $pendingSettlements = Expense::where('user_id', $userId)
                ->where('requirement_type', 'settlement')
                ->where('status', 'pending')
                ->sum('amount');

            // Get approved but not completed
            $approvedAdvances = Expense::where('user_id', $userId)
                ->where('requirement_type', 'advance')
                ->where('status', 'approved')
                ->sum('amount');

            $approvedSettlements = Expense::where('user_id', $userId)
                ->where('requirement_type', 'settlement')
                ->where('status', 'approved')
                ->sum('amount');

            return response()->json([
                'success' => true,
                'data' => [
                    'current_balance' => $balance ? $balance->current_balance : 0,
                    'total_advance_taken' => $balance ? $balance->advance_balance : 0,
                    'total_settlement_done' => $balance ? $balance->settlement_balance : 0,
                    'pending_advances' => $pendingAdvances,
                    'pending_settlements' => $pendingSettlements,
                    'approved_advances' => $approvedAdvances,
                    'approved_settlements' => $approvedSettlements
                ]
            ]);
        } catch (Exception $e) {
            Log::error('Error getting summary: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get summary'
            ], 500);
        }
    }

    /**
     * Delete an expense (only if pending)
     */
    public function destroy($id)
    {
        DB::beginTransaction();

        try {
            $expense = Expense::findOrFail($id);
            $userId = Auth::id();

            // Check ownership
            if ($expense->user_id !== $userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            // Check if can be deleted
            if ($expense->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only pending expenses can be deleted.'
                ], 400);
            }

            // Delete file if exists
            if ($expense->file && file_exists(public_path($expense->file))) {
                unlink(public_path($expense->file));
            }

            $expense->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Expense deleted successfully.'
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Expense not found.'
            ], 404);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete expense: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get payments for an expense (for modal)
     */
    public function getPayments($expenseId)
    {
        try {
            $expense = Expense::with(['payments.payer', 'user'])->findOrFail($expenseId);

            return response()->json([
                'success' => true,
                'data' => $expense->payments
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch payments'
            ], 500);
        }
    }
}
