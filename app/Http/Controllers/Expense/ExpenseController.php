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
use App\Exceptions\ExpenseException;
use App\Exceptions\InsufficientBalanceException;
use App\Http\Requests\Expense\DecideExpenseRequest;
use App\Http\Requests\Expense\StoreExpenseRequest;
use App\Http\Requests\Expense\UpdateExpenseRequest;
use App\Services\AuditLogger;
use App\Services\Expense\ExpenseAttachmentService;
use App\Services\Expense\ExpenseService;
use App\Services\Expense\ExpenseStatsService;
use App\Services\ExpenseNotificationService;
use App\Services\RbacService;
use App\Traits\AuthorizesByScope;

class ExpenseController extends Controller
{
    use AuthorizesByScope;
    use \App\Http\Controllers\Concerns\RaisesOnBehalf;

    protected $notificationService;

    public function __construct(
        ExpenseNotificationService $notificationService,
        protected ExpenseAttachmentService $attachments,
        protected ExpenseService $expenseService,
        protected ExpenseStatsService $stats,
    ) {
        $this->notificationService = $notificationService;
    }

    /**
     * Never echo a raw \Throwable message to the browser (it can carry SQL,
     * paths or internals) — log it with context and return a generic one.
     */
    private function genericFailure(\Throwable $e, string $context, array $extra = []): array
    {
        Log::error("Expense {$context} failed: " . $e->getMessage(), $extra + ['user_id' => Auth::id()]);

        return ['success' => false, 'message' => 'Something went wrong. Please try again or contact support.'];
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
                // Project's relation is assigns() (this said assignments(), which doesn't
                // exist, so the page threw for every non-manager/admin user).
                $projects = $projectsQuery->whereHas('assigns', function ($q) use ($authUser) {
                    $q->where('user_id', $authUser->id);
                })->orderBy('name')->get(['id', 'project_code', 'name']);
            }

            // Get expense types for dropdown
            $expenseTypes = ExpenseType::where('status', 1)->orderBy('name')->get();

            // Build the expenses query with filters
            $expenseQuery = Expense::where('expenses.user_id', $userId)
                ->with(['expenseType', 'project', 'payments', 'attachments', 'possibleDuplicateOf:id,expense_number'])
                ->leftJoin('expense_types', 'expenses.expense_type', '=', 'expense_types.id')
                ->leftJoin('projects', 'expenses.project_id', '=', 'projects.id')
                ->select([
                    'expenses.*',
                    'expense_types.name as expense_type_name',
                    'projects.name as project_name',
                ]);

            // Apply filters
            if ($request->filled('status')) {
                // Qualified: expense_types and projects (both joined above) also have a
                // `status` column, so a bare `status` was an ambiguous-column SQL error
                // and every status filter / status summary-card link redirected with an error.
                $expenseQuery->where('expenses.status', $request->status);
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

            // Receipt links (signed for private files, plain URL for legacy ones).
            // `file` keeps the raw stored path so views can read its extension.
            $expenses->transform(function ($expense) {
                $expense->file_url = $this->attachments->url($expense->file, (int) $expense->id);
                $expense->receipt_list = $this->attachments->receipts($expense);   // primary + extra receipts, signed URLs
                return $expense;
            });

            // Summary cards: one grouped query over the FILTERED set (ExpenseStatsService),
            // so the cards keep reflecting the current filter selections. The combined
            // "Total" / pending / approved / completed / cancelled cards mean
            // settlement + reimbursement (actual spend); advance figures are their own cards.
            $stats = $this->stats->viewData($this->stats->matrix($expenseQuery));
            $data = array_merge([
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
            ], $stats);

            return view('client.expense.expense.expense', $data);
        } catch (Exception $e) {
            $this->genericFailure($e, 'index');
            return back()->with('error', 'Something went wrong. Please try again or contact support.');
        }
    }

    /** Fields of a create/update request that ExpenseService accepts. */
    private const FORM_FIELDS = ['expense_type', 'amount', 'date', 'project_id', 'requirement_type', 'description'];

    /**
     * Store a newly created expense
     */
    public function store(StoreExpenseRequest $request)
    {
        try {
            $expense = $this->expenseService->submit(Auth::user(), $request->only(self::FORM_FIELDS), $request->receipts());
        } catch (ExpenseException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], $e->httpStatus());
        } catch (Exception $e) {
            return response()->json($this->genericFailure($e, 'store'), 500);
        }

        try {
            $this->notificationService->notifyExpenseSubmitted($expense);
        } catch (Exception $e) {
            Log::error('Failed to send expense notifications: ' . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => 'Expense Created Successfully!!!'
        ], 200);
    }

    /**
     * Admin / HR raises an expense for an employee (Expenses → "Add expense").
     * Same field rules as the employee's form; saved and approved in one step by
     * the caller (ExpenseService::submitOnBehalf). Payment stays a separate step.
     */
    public function storeOnBehalf(StoreExpenseRequest $request)
    {
        $employee = $this->onBehalfEmployee($request);

        try {
            $result = $this->expenseService->submitOnBehalf(Auth::user(), $employee, $request->only(self::FORM_FIELDS),
                $request->receipts(), $request->boolean('cover_shortfall'));
        } catch (InsufficientBalanceException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()] + $e->toPayload(), $e->httpStatus());
        } catch (ExpenseException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], $e->httpStatus());
        } catch (Exception $e) {
            return response()->json($this->genericFailure($e, 'storeOnBehalf'), 500);
        }

        try {
            $this->notificationService->notifyExpenseApproved($result['expense'], 'Raised by ' . Auth::user()->name);
        } catch (Exception $e) {
            Log::error('Failed to send expense notifications: ' . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => "Expense recorded and approved for {$employee->name}. " . $result['message'],
        ]);
    }

    /**
     * Update an existing expense (owner only, and only while still pending)
     */
    public function update(UpdateExpenseRequest $request, $id)
    {
        try {
            $this->expenseService->update(Auth::user(), (int) $id, $request->only(self::FORM_FIELDS), $request->receipts());
        } catch (ExpenseException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], $e->httpStatus());
        } catch (Exception $e) {
            return response()->json($this->genericFailure($e, 'update', ['expense_id' => $id]), 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Expense Updated Successfully!'
        ], 200);
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
            $expenseQuery = Expense::with(['user', 'expenseType', 'project', 'payments', 'attachments', 'possibleDuplicateOf:id,expense_number'])
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
                        ->orWhereIn('users.id', function ($sub) use ($authUser) {
                            $sub->select('user_id')->from('user_reporting_heads')->where('reporting_head_id', $authUser->id);
                        });
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
                        ->orWhere('expenses.expense_number', 'LIKE', $searchTerm)
                        ->orWhere('expenses.description', 'LIKE', $searchTerm)
                        ->orWhere('expense_types.name', 'LIKE', $searchTerm)
                        ->orWhere('projects.name', 'LIKE', $searchTerm);
                });
            }

            // Summary cards: one grouped query over the FILTERED set (ExpenseStatsService).
            // The combined "Total" / pending / approved / completed / cancelled cards mean
            // settlement + reimbursement (actual spend); advance figures are their own cards.
            $stats = $this->stats->viewData($this->stats->matrix($expenseQuery));
            // Get paginated results
            $expenses = $expenseQuery
                ->orderBy('expenses.date', 'desc')
                ->orderBy('expenses.created_at', 'desc')
                ->paginate(15)
                ->withQueryString();

            // Receipt links (signed for private files, plain URL for legacy ones).
            $expenses->getCollection()->transform(function ($expense) {
                $expense->file_url = $this->attachments->url($expense->file, (int) $expense->id);
                $expense->receipt_list = $this->attachments->receipts($expense);
                return $expense;
            });


            // Get employees for filter dropdown
            $employeesQuery = User::where('status', 1)
                ->with(['jobDetails']);

            if ($needsOwnerFilter) {
                $employeesQuery->where(function ($q) use ($authUser, $userId) {
                    $q->managedBy($authUser->id)->orWhere('id', $authUser->id);
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

            $data = array_merge([
                'expenses' => $expenses,
                'expenseTypes' => $expenseTypes,
                'employees' => $employees,
                'projects' => $projects,
                'userRole' => $authUser->role,
                'filters' => $request->all(),
                // Bulk approve/reject: the company must have the feature AND the user the approve permission.
                'canBulk' => app(\App\Services\FeatureService::class)->enabledForCurrentTenant('expense_bulk_payment')
                    && app(RbacService::class)->can($authUser, 'expenses', 'approve'),
            ], $stats);

            return view('client.expense.expense.view-all-expense', $data);
        } catch (Exception $e) {
          
            $this->genericFailure($e, 'view_all');
            return back()->with('error', 'Something went wrong. Please try again or contact support.');
        }
    }

    /**
     * Approve / reject an expense.
     *
     * Coarse "can this role approve expenses at all" is enforced at the route
     * (permission:expenses,approve); here we check the expense's owner falls
     * inside the caller's granted scope, then ExpenseService performs the
     * locked, re-checked transition (shared with the mobile API).
     */
    public function updateStatus(DecideExpenseRequest $request, $id)
    {
        $authUser = Auth::user();

        $expense = Expense::find($id);
        if (! $expense) {
            return response()->json(['success' => false, 'message' => 'Expense not found.'], 404);
        }

        if (! $this->scopeCoversOwner($authUser, 'expenses', 'approve', (int) $expense->user_id)) {
            return response()->json(['success' => false, 'message' => 'You are not authorized to update this expense.'], 403);
        }

        try {
            $result = $this->expenseService->decide((int) $id, $authUser, $request->status, $request->remarks, $request->boolean('cover_shortfall'));
        } catch (InsufficientBalanceException $e) {
            // Carries the numbers so the approval screen can offer "deduct what the advance covers and
            // turn the rest into a reimbursement" instead of a dead end.
            return response()->json(['success' => false, 'message' => $e->getMessage()] + $e->toPayload(), $e->httpStatus());
        } catch (ExpenseException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], $e->httpStatus());
        } catch (Exception $e) {
            return response()->json($this->genericFailure($e, 'updateStatus', ['expense_id' => $id]), 500);
        }

        try {
            if ($request->status === Expense::STATUS_APPROVED) {
                $this->notificationService->notifyExpenseApproved($result['expense'], $request->remarks);
            } else {
                $this->notificationService->notifyExpenseRejected($result['expense'], $request->remarks);
            }
        } catch (Exception $e) {
            Log::error('Failed to send status update notification: ' . $e->getMessage());
        }

        return response()->json(['success' => true, 'message' => $result['message']]);
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

            // Previously unchecked: any user could read any expense's ledger.
            if (! $this->scopeCoversOwner(Auth::user(), 'expenses', 'view', (int) $expense->user_id)) {
                return response()->json(['success' => false, 'message' => 'You are not authorized to view these transactions.'], 403);
            }

            $transactions = $expense->transactions()->orderBy('created_at', 'desc')->get();

            return response()->json([
                'success' => true,
                'data' => $transactions
            ]);
        } catch (Exception $e) {
            report($e);
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
        try {
            $this->expenseService->delete(Auth::user(), (int) $id);
        } catch (ExpenseException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], $e->httpStatus());
        } catch (Exception $e) {
            return response()->json($this->genericFailure($e, 'destroy', ['expense_id' => $id]), 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Expense deleted successfully.'
        ], 200);
    }

    /**
     * Approve / reject MANY expenses at once (route middleware: permission:expenses,approve).
     *
     * Deliberately NOT all-or-nothing (unlike a payment voucher): each expense is decided in
     * its own locked transaction by ExpenseService::decide() — the same code as the single
     * approve — so one bad row (out of scope, already processed, settlement larger than the
     * employee's balance) never blocks the rest. The response reports every row.
     */
    public function bulkStatus(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ids' => 'required|array|min:1|max:100',
            'ids.*' => 'integer',
            'status' => 'required|in:approved,cancelled',
            'remarks' => 'nullable|string|max:500',
            'cover_shortfall' => 'nullable|boolean',
        ], ['ids.max' => 'You can decide at most 100 expenses at a time.']);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first()], 422);
        }

        $authUser = Auth::user();
        $ids = collect($request->ids)->map(fn ($i) => (int) $i)->unique()->sort()->values();

        $results = [];
        $decided = [];

        foreach ($ids as $id) {
            $expense = Expense::find($id);

            if (! $expense) {
                $results[] = ['id' => $id, 'ok' => false, 'message' => 'Expense not found.'];
                continue;
            }
            if (! $this->scopeCoversOwner($authUser, 'expenses', 'approve', (int) $expense->user_id)) {
                $results[] = ['id' => $id, 'expense_number' => $expense->expense_number, 'ok' => false, 'message' => 'You are not authorized to decide this expense.'];
                continue;
            }

            try {
                $outcome = $this->expenseService->decide($id, $authUser, $request->status, $request->remarks, $request->boolean('cover_shortfall'));
                $results[] = ['id' => $id, 'expense_number' => $expense->expense_number, 'ok' => true, 'message' => $outcome['message']];
                $decided[] = $outcome['expense'];
            } catch (InsufficientBalanceException $e) {
                $results[] = ['id' => $id, 'expense_number' => $expense->expense_number, 'ok' => false, 'message' => $e->getMessage()] + $e->toPayload();
            } catch (ExpenseException $e) {
                $results[] = ['id' => $id, 'expense_number' => $expense->expense_number, 'ok' => false, 'message' => $e->getMessage()];
            } catch (Exception $e) {
                Log::error('Expense bulk decide failed: ' . $e->getMessage(), ['expense_id' => $id, 'user_id' => $authUser->id]);
                $results[] = ['id' => $id, 'expense_number' => $expense->expense_number, 'ok' => false, 'message' => 'Something went wrong for this expense.'];
            }
        }

        // After the response is sent — push notifications are synchronous HTTP calls.
        $status = $request->status;
        $remarks = $request->remarks;
        dispatch(function () use ($decided, $status, $remarks) {
            foreach ($decided as $expense) {
                try {
                    $status === Expense::STATUS_APPROVED
                        ? $this->notificationService->notifyExpenseApproved($expense, $remarks)
                        : $this->notificationService->notifyExpenseRejected($expense, $remarks);
                } catch (Exception $e) {
                    Log::error('Failed to send status update notification: ' . $e->getMessage());
                }
            }
        })->afterResponse();

        $succeeded = count($decided);

        return response()->json([
            'success' => $succeeded > 0,
            'message' => $succeeded === count($results)
                ? "{$succeeded} expense(s) " . ($status === 'approved' ? 'approved' : 'rejected') . '.'
                : "{$succeeded} of " . count($results) . ' expense(s) ' . ($status === 'approved' ? 'approved' : 'rejected') . '; the rest were skipped (see details).',
            'succeeded' => $succeeded,
            'failed' => count($results) - $succeeded,
            'results' => $results,
        ]);
    }

    /**
     * Get payments for an expense (for modal)
     */
    public function getPayments($expenseId)
    {
        try {
            $expense = Expense::with(['payments.payer', 'user'])->findOrFail($expenseId);

            // Previously unchecked: any user could list any expense's payments by id.
            if (! $this->scopeCoversOwner(Auth::user(), 'expenses', 'view', (int) $expense->user_id)) {
                return response()->json(['success' => false, 'message' => 'You are not authorized to view these payments.'], 403);
            }

            return response()->json([
                'success' => true,
                'data' => $expense->payments
            ]);
        } catch (Exception $e) {
            report($e);
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch payments'
            ], 500);
        }
    }

    /**
     * The employee withdraws their OWN claim (pending, or approved-but-completely-unpaid advance /
     * reimbursement). Keeps a visible record — see ExpenseService::withdraw().
     */
    public function withdraw(Request $request, $id)
    {
        $validator = Validator::make($request->all(), ['reason' => 'required|string|min:3|max:500']);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first()], 422);
        }

        try {
            $this->expenseService->withdraw(Auth::user(), (int) $id, (string) $request->reason);
        } catch (ExpenseException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], $e->httpStatus());
        } catch (Exception $e) {
            return response()->json($this->genericFailure($e, 'withdraw', ['expense_id' => $id]), 500);
        }

        return response()->json(['success' => true, 'message' => 'Your claim has been withdrawn.']);
    }

    /** Remove one receipt from a pending claim ({attachment} is an id, or "primary" for the first one). */
    public function removeReceipt($id, $attachment)
    {
        try {
            $this->expenseService->removeReceipt(Auth::user(), (int) $id, $attachment === 'primary' ? null : (int) $attachment);
        } catch (ExpenseException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], $e->httpStatus());
        } catch (Exception $e) {
            return response()->json($this->genericFailure($e, 'removeReceipt', ['expense_id' => $id]), 500);
        }

        return response()->json(['success' => true, 'message' => 'Receipt removed.']);
    }

    /** Stream an EXTRA receipt (expense_attachments) — same signed-URL model as file() below. */
    public function attachment(Request $request, $id)
    {
        $attachment = \App\Models\ExpenseAttachment::withoutGlobalScopes()
            ->where('tenant_id', (int) $request->query('tenant'))
            ->findOrFail($id);

        return $this->attachments->streamAttachment($attachment);
    }

    /**
     * Stream an expense receipt. Reached only through a short-lived signed URL
     * (route middleware `signed`), which ExpenseAttachmentService::url() mints
     * only for records the caller was already allowed to list — so this works
     * for the mobile app without a session while staying non-guessable.
     */
    public function file(Request $request, $id)
    {
        // No tenant context on this route, so scope explicitly by the tenant
        // carried (and signed) in the URL — never an unfiltered global bypass.
        $expense = Expense::withoutGlobalScopes()
            ->where('tenant_id', (int) $request->query('tenant'))
            ->findOrFail($id);

        return $this->attachments->stream($expense);
    }
}
