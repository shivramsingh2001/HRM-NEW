<?php

namespace App\Http\Controllers\Expense;

use App\Exceptions\ExpenseException;
use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\ExpensePayment;
use App\Models\User;
use App\Services\Expense\ExpensePaymentService;
use App\Services\ExpensePaymentNotificationService;
use App\Services\RbacService;
use App\Support\Money;
use App\Traits\AuthorizesByScope;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Expense payments — HTTP edge only: validate, authorize, call
 * ExpensePaymentService (which owns the locking, ledger and paid_amount rules),
 * map ExpenseException to JSON, send notifications after commit.
 *
 * No balance math or lockForUpdate lives here any more (see
 * App\Services\Expense\ExpensePaymentService / ExpenseLedgerService).
 */
class PaymentController extends Controller
{
    use AuthorizesByScope;

    protected $notificationService;

    public function __construct(
        ExpensePaymentNotificationService $notificationService,
        protected ExpensePaymentService $payments,
    ) {
        $this->notificationService = $notificationService;
    }

    // ==================== LISTING ====================

    public function index(Request $request)
    {
        try {
            $authUser = Auth::user();
            $rbac = app(RbacService::class);
            $canManage = $rbac->can($authUser, 'expenses', 'manage');

            // Only people who can record payments need the employee picker /
            // payable lists. (Previously every viewer got ALL users and ALL
            // approved advances/reimbursements in the company.)
            $employees = $canManage
                ? User::where('status', 1)->select('id', 'name', 'employee_id', 'email')->orderBy('name')->get()
                : collect();

            $query = ExpensePayment::with(['expense.user', 'payer'])
                ->join('expenses', 'expense_payments.expense_id', '=', 'expenses.id')
                ->join('users', 'expenses.user_id', '=', 'users.id')
                ->leftJoin('expense_payment_batches as pb', 'pb.id', '=', 'expense_payments.batch_id')
                ->select(
                    'expense_payments.*',
                    'pb.voucher_number',
                    'expenses.expense_number',
                    'expenses.amount as expense_amount',
                    'expenses.requirement_type',
                    'users.name as employee_name',
                    'users.employee_id',
                    'users.email as employee_email'
                );

            // Permission-driven scope (own / team / company), not "company or self".
            $this->applyScope($query, 'expenses.user_id', $authUser, 'expenses', 'view');

            if ($request->filled('payment_mode')) {
                $query->where('expense_payments.payment_mode', $request->payment_mode);
            }
            if ($request->filled('from_date')) {
                $query->whereDate('expense_payments.payment_date', '>=', $request->from_date);
            }
            if ($request->filled('to_date')) {
                $query->whereDate('expense_payments.payment_date', '<=', $request->to_date);
            }
            if ($request->filled('user_id')) {
                $query->where('expenses.user_id', $request->user_id);
            }
            if (in_array($request->input('status'), ['posted', 'voided'], true)) {
                $query->where('expense_payments.status', $request->input('status'));
            }
            if ($request->filled('search')) {
                $searchTerm = '%' . $request->search . '%';
                $query->where(function ($q) use ($searchTerm) {
                    $q->where('users.name', 'LIKE', $searchTerm)
                        ->orWhere('users.email', 'LIKE', $searchTerm)
                        ->orWhere('users.employee_id', 'LIKE', $searchTerm)
                        ->orWhere('expenses.expense_number', 'LIKE', $searchTerm)
                        ->orWhere('expense_payments.reference_number', 'LIKE', $searchTerm)
                        ->orWhere('pb.voucher_number', 'LIKE', $searchTerm);
                });
            }

            $payments = $query->orderBy('expense_payments.created_at', 'desc')
                ->paginate(15)
                ->withQueryString();

            // The list shows voided payments (struck through) for audit, but the totals
            // and per-mode figures count POSTED payments only.
            $statsQuery = ExpensePayment::query()->posted()
                ->join('expenses', 'expense_payments.expense_id', '=', 'expenses.id');

            $this->applyScope($statsQuery, 'expenses.user_id', $authUser, 'expenses', 'view');

            if ($request->filled('user_id')) {
                $statsQuery->where('expenses.user_id', $request->user_id);
            }

            $totalPayments = $statsQuery->count();
            $totalAmount = $statsQuery->sum('expense_payments.amount');
            $modeStats = $statsQuery->select(
                'expense_payments.payment_mode',
                DB::raw('count(*) as count'),
                DB::raw('sum(expense_payments.amount) as total')
            )->groupBy('expense_payments.payment_mode')->get();

            return view('client.expense.expense.payment', [
                'payments' => $payments,
                'totalPayments' => $totalPayments,
                'totalAmount' => $totalAmount,
                'modeStats' => $modeStats,
                'approvedAdvances' => $canManage ? $this->payableExpenses($authUser, ['advance']) : collect(),
                'approvedReimbursements' => $canManage ? $this->payableExpenses($authUser, ['reimbursement']) : collect(),
                'userRole' => $authUser->role,
                'filters' => $request->all(),
                'paymentModes' => ['cash', 'bank_transfer', 'cheque', 'upi'],
                'employees' => $employees,
                'canManage' => $canManage,
                // Per-company switch (Super Admin): shows the Pay Batch / Vouchers buttons.
                'bulkEnabled' => app(\App\Services\FeatureService::class)->enabledForCurrentTenant('expense_bulk_payment'),
            ]);
        } catch (Exception $e) {
            if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) {
                throw $e; // 403 from applyScope must stay a 403
            }
            Log::error('Error in payment index: ' . $e->getMessage(), ['user_id' => Auth::id()]);

            return redirect()->route('expense.view-all')
                ->with('error', 'Failed to load payments. Please try again or contact support.');
        }
    }

    public function getUserPendingAdvances(Request $request)
    {
        try {
            $authUser = Auth::user();

            if (! app(RbacService::class)->can($authUser, 'expenses', 'manage')) {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
            }

            $data = $this->payableExpenses($authUser, ['advance', 'reimbursement'], $request->get('user_id'), 'asc');

            return response()->json(['success' => true, 'data' => $data]);
        } catch (Exception $e) {
            return $this->failure($e, 'getUserPendingAdvances');
        }
    }

    public function getPendingAdvances()
    {
        try {
            $authUser = Auth::user();

            if (! app(RbacService::class)->can($authUser, 'expenses', 'manage')) {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
            }

            $data = $this->payableExpenses($authUser, ['advance', 'reimbursement'], null, 'asc');

            return response()->json(['success' => true, 'data' => $data]);
        } catch (Exception $e) {
            return $this->failure($e, 'getPendingAdvances');
        }
    }

    // ==================== WRITE (thin — see ExpensePaymentService) ====================

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'payment_date' => 'required|date',
            'amount' => 'required|numeric|min:0.01|max:9999999.99',
            'payment_mode' => 'required|in:cash,bank_transfer,cheque,upi',
            'reference_number' => 'nullable|string|max:100',
            'bank_name' => 'nullable|string|max:255',
            'paid_to' => 'nullable|string|max:255',
            'remarks' => 'nullable|string|max:500',
            'payment_type' => 'required|in:direct,advance,reimbursement',
            'idempotency_key' => 'nullable|string|max:64',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $authUser = Auth::user();

        if (! app(RbacService::class)->can($authUser, 'expenses', 'manage')) {
            return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
        }

        $type = $request->payment_type;

        // The form posts a different field per payment type; validate the one that applies.
        $idField = ['direct' => 'direct_user_id', 'advance' => 'advance_expense_id', 'reimbursement' => 'reimbursement_expense_id'][$type];
        $idCheck = Validator::make($request->all(), [$idField => 'required|integer']);
        if ($idCheck->fails()) {
            return response()->json(['success' => false, 'message' => $idCheck->errors()->first()], 422);
        }

        $data = $request->only(['payment_date', 'amount', 'payment_mode', 'reference_number', 'bank_name', 'paid_to', 'remarks']);
        $data[$type === 'direct' ? 'direct_user_id' : 'expense_id'] = $request->input($idField);

        try {
            $result = $this->payments->record(
                $authUser,
                $type,
                $data,
                $request->filled('idempotency_key') ? (string) $request->idempotency_key : null,
                fn (int $ownerId) => $this->scopeCoversOwner($authUser, 'expenses', 'manage', $ownerId)
            );
        } catch (ExpenseException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], $e->httpStatus());
        } catch (Exception $e) {
            return $this->failure($e, 'store');
        }

        if ($result['duplicate']) {
            return response()->json([
                'success' => true,
                'message' => 'This payment was already recorded.',
                'data' => ['id' => $result['payment']->id, 'amount' => $result['payment']->amount, 'duplicate' => true],
            ], 200);
        }

        try {
            $this->notificationService->notifyPaymentCreated($result['expense'], $result['payment'], $request->remarks);
        } catch (Exception $e) {
            Log::error('Failed to send expense notifications: ' . $e->getMessage());
        }

        return response()->json(['success' => true, 'message' => $result['message'], 'data' => $result['data']], 200);
    }

    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'payment_date' => 'required|date',
            'amount' => 'required|numeric|min:0.01|max:9999999.99',
            'payment_mode' => 'required|in:cash,bank_transfer,cheque,upi',
            'reference_number' => 'nullable|string|max:100',
            'bank_name' => 'nullable|string|max:255',
            'paid_to' => 'nullable|string|max:255',
            'remarks' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $authUser = Auth::user();

        if (! app(RbacService::class)->can($authUser, 'expenses', 'manage')) {
            return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
        }

        try {
            $result = $this->payments->update(
                $authUser,
                (int) $id,
                $request->only(['payment_date', 'amount', 'payment_mode', 'reference_number', 'bank_name', 'paid_to', 'remarks']),
                fn (int $ownerId) => $this->scopeCoversOwner($authUser, 'expenses', 'manage', $ownerId)
            );
        } catch (ExpenseException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], $e->httpStatus());
        } catch (Exception $e) {
            return $this->failure($e, 'update', ['payment_id' => $id]);
        }

        /** @var Expense $expense @var ExpensePayment $payment */
        ['expense' => $expense, 'payment' => $payment] = $result;

        try {
            $this->notificationService->notifyPaymentUpdated($expense, $payment, $result['oldAmount'], $request->remarks);
        } catch (Exception $e) {
            Log::error('Failed to send expense notifications: ' . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => 'Payment updated successfully',
            'data' => [
                'id' => $payment->id,
                'payment_date' => $payment->payment_date,
                'amount' => $payment->amount,
                'payment_mode' => $payment->payment_mode,
                'reference_number' => $payment->reference_number,
                'bank_name' => $payment->bank_name,
                'paid_to' => $payment->paid_to,
                'remarks' => $payment->remarks,
                'payment_type' => $result['isDirect'] ? 'direct' : $expense->requirement_type,
            ],
        ], 200);
    }

    public function destroy(Request $request, $id)
    {
        $authUser = Auth::user();

        if (! app(RbacService::class)->can($authUser, 'expenses', 'manage')) {
            return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
        }

        // Payments are never hard-deleted any more: "delete" VOIDS the payment (reverses the
        // ledger, keeps the row with who/when/why). A reason is recorded; the UI's plain delete
        // button sends none, so a sensible default is used.
        $reason = trim((string) $request->input('reason', '')) ?: ('Voided by ' . $authUser->name);

        try {
            $result = $this->payments->voidPayment(
                $authUser,
                (int) $id,
                $reason,
                fn (int $ownerId) => $this->scopeCoversOwner($authUser, 'expenses', 'manage', $ownerId)
            );
        } catch (ExpenseException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], $e->httpStatus());
        } catch (Exception $e) {
            return $this->failure($e, 'destroy', ['payment_id' => $id]);
        }

        try {
            $this->notificationService->notifyPaymentDeleted($result['expense'], $result['amount'], 'Payment voided');
        } catch (Exception $e) {
            Log::error('Failed to send expense notifications: ' . $e->getMessage());
        }

        return response()->json(['success' => true, 'message' => 'Payment voided successfully'], 200);
    }

    // ==================== READ ====================

    public function expensePayments($expenseId)
    {
        try {
            $authUser = Auth::user();

            $expense = Expense::with(['payments.payer', 'user'])->findOrFail($expenseId);

            if (! $this->scopeCoversOwner($authUser, 'expenses', 'view', (int) $expense->user_id)) {
                return response()->json(['success' => false, 'message' => 'You are not authorized to view these payments.'], 403);
            }

            $payments = $expense->payments;
            $totalPaid = $payments->sum('amount');
            $remainingAmount = $expense->amount - $totalPaid;

            return response()->json([
                'success' => true,
                'data' => [
                    'expense_id' => $expense->id,
                    'expense_number' => $expense->expense_number,
                    'expense_amount' => $expense->amount,
                    'requirement_type' => $expense->requirement_type,
                    'total_paid' => $totalPaid,
                    'remaining_amount' => $remainingAmount,
                    'payments' => $payments->map(fn ($p) => [
                        'id' => $p->id,
                        'payment_date' => $p->payment_date,
                        'amount' => $p->amount,
                        'payment_mode' => $p->payment_mode,
                        'reference_number' => $p->reference_number,
                        'bank_name' => $p->bank_name,
                        'paid_to' => $p->paid_to,
                        'remarks' => $p->remarks,
                        'paid_by' => $p->paid_by,
                        'payer_name' => $p->payer ? $p->payer->name : null,
                        'created_at' => $p->created_at,
                    ]),
                ],
            ], 200);
        } catch (Exception $e) {
            return $this->failure($e, 'expensePayments', ['expense_id' => $expenseId]);
        }
    }

    public function show($id)
    {
        try {
            $authUser = Auth::user();

            if (! app(RbacService::class)->can($authUser, 'expenses', 'manage')) {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
            }

            $payment = ExpensePayment::with('expense')->findOrFail($id);

            if (! $this->scopeCoversOwner($authUser, 'expenses', 'manage', (int) $payment->expense->user_id)) {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $payment->id,
                    'expense_id' => $payment->expense_id,
                    'requirement_type' => $payment->expense->requirement_type,
                    'payment_date' => $payment->payment_date,
                    'amount' => $payment->amount,
                    'payment_mode' => $payment->payment_mode,
                    'reference_number' => $payment->reference_number,
                    'bank_name' => $payment->bank_name,
                    'paid_to' => $payment->paid_to,
                    'remarks' => $payment->remarks,
                ],
            ], 200);
        } catch (Exception $e) {
            return $this->failure($e, 'show', ['payment_id' => $id]);
        }
    }

    public function getAdvanceSummary($expenseId)
    {
        try {
            $expense = Expense::with('payments')->findOrFail($expenseId);

            // Previously unchecked: any user could read any expense's payment summary.
            if (! $this->scopeCoversOwner(Auth::user(), 'expenses', 'view', (int) $expense->user_id)) {
                return response()->json(['success' => false, 'message' => 'You are not authorized to view this summary.'], 403);
            }

            $totalPaid = $expense->payments->sum('amount');
            $remaining = $expense->amount - $totalPaid;

            return response()->json([
                'success' => true,
                'data' => [
                    'expense_id' => $expense->id,
                    'expense_number' => $expense->expense_number,
                    'requirement_type' => $expense->requirement_type,
                    'total_amount' => $expense->amount,
                    'total_paid' => $totalPaid,
                    'remaining_amount' => $remaining,
                    'is_fully_paid' => Money::toCents($remaining) <= 0,
                    'payments' => $expense->payments,
                ],
            ]);
        } catch (Exception $e) {
            return $this->failure($e, 'getAdvanceSummary', ['expense_id' => $expenseId]);
        }
    }

    // ==================== HELPERS ====================

    /**
     * Approved advances/reimbursements that still have an unpaid amount, limited to
     * the employees the caller may pay. The "still unpaid" filter is pushed into SQL
     * (Expense::payable() on the cached paid_amount) — no per-row queries, no PHP loop.
     * `paid_amount` / `remaining_amount` are the model's own column/accessor.
     *
     * @param  string[]  $types
     */
    private function payableExpenses(User $authUser, array $types, $userId = null, string $order = 'desc')
    {
        $query = Expense::with(['user', 'expenseType', 'project'])
            ->payable()
            ->whereIn('requirement_type', $types);

        $this->applyScope($query, 'user_id', $authUser, 'expenses', 'manage');

        if ($userId) {
            $query->where('user_id', $userId);
        }

        return $query->orderBy('created_at', $order)->get()->each->append('remaining_amount');
    }

    private function failure(Exception $e, string $context, array $extra = [])
    {
        Log::error("Expense payment {$context} failed: " . $e->getMessage(), $extra + ['user_id' => Auth::id()]);

        return response()->json(['success' => false, 'message' => 'Something went wrong. Please try again or contact support.'], 500);
    }
}
