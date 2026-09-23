<?php

namespace App\Http\Controllers\Api\Expense;

use App\Exceptions\ExpenseException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Expense\DecideExpenseRequest;
use App\Http\Requests\Expense\StoreExpenseRequest;
use App\Models\Expense;
use App\Models\ExpensePayment;
use App\Support\Money;
use App\Models\ExpenseStatusHistory;
use App\Models\ExpenseTransaction;
use App\Models\ExpenseType;
use App\Models\User;
use App\Models\UserExpenseBalance;
use App\Services\AuditLogger;
use App\Services\Expense\ExpenseAttachmentService;
use App\Services\Expense\ExpenseService;
use App\Services\Expense\ExpenseStatsService;
use App\Services\ExpenseNotificationService;
use App\Services\RbacService;
use App\Traits\AuthorizesByScope;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Mobile (Flutter) expense API — legacy envelope: business failures return
 * HTTP 200 {success:false, message}, unexpected errors a generic 500
 * (docs/architecture.md "API response conventions").
 *
 * Validation, approval and receipt storage are shared with the web panel via
 * StoreExpenseRequest / DecideExpenseRequest / ExpenseService /
 * ExpenseAttachmentService, so the two surfaces can no longer drift apart
 * (the API used to accept any file type and negative amounts).
 */
class ExpenseController extends Controller
{
    use AuthorizesByScope;

    /**
     * @var ExpenseNotificationService
     */
    protected $notificationService;

    public function __construct(
        ExpenseNotificationService $notificationService,
        protected ExpenseAttachmentService $attachments,
        protected ExpenseService $expenseService,
        protected ExpenseStatsService $stats,
    ) {
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

            // All of the user's expenses. `expenses.file` is selected only so
            // a signed/legacy URL can be built below; the raw stored path is
            // hidden from the response.
            $expenseQuery = Expense::where('expenses.user_id', $userId)
                ->with('attachments')
                ->leftJoin('expense_types', 'expenses.expense_type', '=', 'expense_types.id')
                ->leftJoin('projects', 'expenses.project_id', '=', 'projects.id');

            // Summary numbers for the `statistics` block: one grouped query.
            $matrix = $this->stats->matrix($expenseQuery);

            $expenses = $expenseQuery
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
                    'expenses.file',
                    'expenses.possible_duplicate_of',
                    'expenses.withdrawn_at',
                ])
                ->orderBy('expenses.created_at', 'desc')
                ->get()
                ->each(function ($expense) {
                    $expense->file_url = $this->attachments->url($expense->file, (int) $expense->id);
                    // ALL receipts (the first is what `file_url` already points at). Additive: existing fields unchanged.
                    $expense->receipts = $this->attachments->receipts($expense);
                    $expense->possible_duplicate = $expense->possible_duplicate_of !== null;
                    $expense->makeHidden(['file', 'attachments', 'possible_duplicate_of']);
                });

            $userBalance = UserExpenseBalance::where('user_id', $userId)->first();

            $currentBalance = $userBalance ? $userBalance->current_balance : 0;
            $totalAdvanceTaken = $userBalance ? $userBalance->advance_balance : 0;
            $totalSettlementDone = $userBalance ? $userBalance->settlement_balance : 0;
            $totalReimbursementDone = $userBalance ? $userBalance->reimbursement_balance : 0;

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
                    // total_expenses / total_amount keep their historical meaning (ALL types)
                    // — the mobile app already reads them. The per-type / by_status blocks are
                    // the previously commented-out statistics, now restored from the same query.
                    'statistics' => [
                        'total_expenses' => $expenses->count(),
                        'total_amount' => $expenses->sum('amount'),
                    ] + $this->stats->apiSummary($matrix),
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

    /**
     * "My payments": what has been paid to the signed-in employee, with the voucher each
     * payment belongs to. Read-only. Voided payments are listed (status=voided) so the
     * app can show them struck through, but only POSTED ones count toward the total.
     */
    public function payments(Request $request)
    {
        try {
            $userId = Auth::id();

            $rows = ExpensePayment::query()
                ->join('expenses', 'expenses.id', '=', 'expense_payments.expense_id')
                ->leftJoin('expense_payment_batches as pb', 'pb.id', '=', 'expense_payments.batch_id')
                ->where('expenses.user_id', $userId)
                ->select([
                    'expense_payments.id',
                    'expense_payments.payment_date',
                    'expense_payments.amount',
                    'expense_payments.payment_mode',
                    'expense_payments.reference_number',
                    'expense_payments.status',
                    'pb.voucher_number',
                    'expenses.expense_number',
                    'expenses.requirement_type',
                ])
                ->orderByDesc('expense_payments.payment_date')
                ->orderByDesc('expense_payments.id')
                ->limit(200)
                ->get();

            $posted = $rows->where('status', 'posted');

            return response()->json([
                'success' => true,
                'message' => 'Data fetched successfully!!!',
                'data' => [
                    'payments' => $rows,
                    'total_received' => (string) Money::fromCents($posted->sum(fn ($r) => Money::toCents($r->amount))),
                    'payment_count' => $posted->count(),
                ],
            ], 200);
        } catch (Exception $e) {
            Log::error('Error in expense payments (API): ' . $e->getMessage(), ['user_id' => Auth::id()]);

            return response()->json(['success' => false, 'message' => 'An error occurred. Please try again later.'], 500);
        }
    }

    /**
     * The employee withdraws their own claim (pending, or an approved advance/reimbursement that has not
     * been paid at all). Legacy envelope: business failures are HTTP 200 + success=false.
     */
    public function withdraw(Request $request)
    {
        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'id' => 'required|integer',
            'reason' => 'required|string|min:3|max:500',
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first()], 200);
        }

        try {
            $this->expenseService->withdraw(Auth::user(), (int) $request->id, (string) $request->reason);
        } catch (ExpenseException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 200);
        } catch (Exception $e) {
            Log::error('Error in expense withdraw (API): ' . $e->getMessage(), ['user_id' => Auth::id()]);

            return response()->json(['success' => false, 'message' => 'An error occurred. Please try again later.'], 500);
        }

        return response()->json(['success' => true, 'message' => 'Your claim has been withdrawn.'], 200);
    }

    public function store(StoreExpenseRequest $request)
    {
        $user = Auth::user();

        try {
            $expense = $this->expenseService->submit(
                $user,
                $request->only(['expense_type', 'amount', 'date', 'project_id', 'requirement_type', 'description']),
                $request->receipts(),
                'mobile'
            );
        } catch (ExpenseException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 200);
        } catch (Exception $e) {
            Log::error('Error in expense store (API): ' . $e->getMessage(), ['user_id' => $user->id]);
            return response()->json([
                'success' => false,
                'message' => "An error occurred. Please try again later. "
            ], 500);
        }

        try {
            $this->notificationService->notifyExpenseSubmitted($expense);
        } catch (Exception $e) {
            Log::error('Failed to send expense notifications: ' . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => 'Expense submitted successfully',
        ], 200);
    }

    public function view_all(Request $request)
    {
        try {
            $authUser = Auth::user();

            // Was a hardcoded `role !== 'manager'` check, which also locked out
            // admin/HR. Now permission-driven: team (managers) or company scope.
            $scope = app(RbacService::class)->scopeFor($authUser, 'expenses', 'view');
            if (! in_array($scope, ['team', 'company'], true)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access. Only managers can view this data.'
                ], 403);
            }

            $expenseQuery = Expense::join('users', 'expenses.user_id', '=', 'users.id')
                ->join('user_job_details', 'user_job_details.user_id', '=', 'users.id')
                ->leftJoin('user_basic_details', 'user_basic_details.user_id', '=', 'users.id')
                ->leftJoin('expense_types', 'expenses.expense_type', '=', 'expense_types.id')
                ->leftJoin('projects', 'expenses.project_id', '=', 'projects.id');

            if ($scope === 'team') {
                $expenseQuery->whereIn('users.id', function ($q) use ($authUser) {
                    $q->select('user_id')->from('user_reporting_heads')->where('reporting_head_id', $authUser->id);
                });
            }

            // Summary numbers for the (previously empty) `statistics` block: one grouped query.
            $matrix = $this->stats->matrix($expenseQuery);

            $baseUrl = config('app.url');
            $expenses = $expenseQuery
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
                    'expenses.file'
                )
                ->orderBy('expenses.created_at', 'desc')
                ->get()
                ->each(function ($expense) {
                    $expense->file_url = $this->attachments->url($expense->file, (int) $expense->id);
                    $expense->makeHidden('file');
                });

            $teamMembers = ($scope === 'team'
                ? User::managedBy($authUser->id)
                : User::where('status', 1))
                ->select('users.id', 'users.name', 'users.email', 'users.employee_id')
                ->get();

            // One query for every member's balance (was one query per member).
            $balances = UserExpenseBalance::whereIn('user_id', $teamMembers->pluck('id'))->get()->keyBy('user_id');
            $teamBalances = $teamMembers->map(function ($member) use ($balances) {
                $balance = $balances->get($member->id);

                return [
                    'user_id' => $member->id,
                    'user_name' => $member->name,
                    'current_balance' => $balance ? $balance->current_balance : 0,
                    'total_advance_taken' => $balance ? $balance->advance_balance : 0,
                    'total_settlement_done' => $balance ? $balance->settlement_balance : 0
                ];
            })->values();

            return response()->json([
                'success' => true,
                'message' => 'Data fetched successfully!',
                'data' => [
                    'expenses' => $expenses,
                    'team_members' => $teamMembers,
                    'team_balances' => $teamBalances,
                    'statistics' => $this->stats->apiSummary($matrix)
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

    public function updateStatus(DecideExpenseRequest $request)
    {
        $authUser = Auth::user();

        $expense = Expense::find($request->id);
        if (! $expense) {
            return response()->json(['success' => false, 'message' => 'Expense not found.'], 200);
        }

        // Was two identical admin/hr/manager gates in a row (the second dead
        // code), so a manager could approve ANY tenant's expense.
        if (! $this->scopeCoversOwner($authUser, 'expenses', 'approve', (int) $expense->user_id)) {
            return response()->json(['success' => false, 'message' => 'You are not authorized to update this expense.'], 200);
        }

        try {
            $result = $this->expenseService->decide((int) $expense->id, $authUser, $request->status, $request->remarks, $request->boolean('cover_shortfall'));
        } catch (\App\Exceptions\InsufficientBalanceException $e) {
            // Legacy envelope (HTTP 200 + success=false) plus the numbers, so the app may offer to cover the shortfall.
            return response()->json(['success' => false, 'message' => $e->getMessage()] + $e->toPayload(), 200);
        } catch (ExpenseException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 200);
        } catch (Exception $e) {
            Log::error('Error in expense updateStatus (API): ' . $e->getMessage(), ['user_id' => $authUser->id, 'expense_id' => $expense->id]);
            return response()->json(['success' => false, 'message' => 'An error occurred. Please try again later.'], 500);
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

        return response()->json([
            'success' => true,
            'message' => $result['message']
        ]);
    }
}
