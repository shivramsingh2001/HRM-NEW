<?php

namespace App\Http\Controllers\Expense;

use App\Exceptions\ExpenseBatchException;
use App\Exceptions\ExpenseException;
use App\Http\Controllers\Concerns\SanitizesCsv;
use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\ExpensePayment;
use App\Models\ExpensePaymentBatch;
use App\Models\ExpenseType;
use App\Models\Project;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Expense\ExpensePaymentService;
use App\Services\ExpensePaymentNotificationService;
use App\Services\RbacService;
use App\Support\Money;
use App\Traits\AuthorizesByScope;
use Barryvdh\DomPDF\Facade\Pdf;
use Closure;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Payment vouchers — pay many approved advances / reimbursements in one go.
 *
 * HTTP edge only: validate -> authorize -> ExpensePaymentService -> map errors -> notify.
 * Every route sits behind `permission:expenses,manage` and `feature:expense_bulk_payment`
 * (a Super Admin can switch the feature off per company).
 *
 * Flow:  payable() lists what can be paid  ->  preview() dry-runs the selection
 *        ->  store() posts it as one voucher (idempotent)  ->  show/csv/pdf/void.
 */
class PaymentBatchController extends Controller
{
    use AuthorizesByScope, SanitizesCsv;

    private const MODES = ['cash', 'bank_transfer', 'cheque', 'upi'];

    public function __construct(
        private ExpensePaymentService $payments,
        private ExpensePaymentNotificationService $notifier,
    ) {
    }

    // ==================== THE PAY-BATCH PAGE ====================

    public function create()
    {
        $authUser = Auth::user();

        return view('client.expense.expense.batch', [
            'employees' => User::where('status', 1)->select('id', 'name', 'employee_id')->orderBy('name')->get(),
            'expenseTypes' => ExpenseType::where('status', 1)->orderBy('name')->get(['id', 'name']),
            'projects' => Project::where('status', '!=', 'cancelled')->orderBy('name')->get(['id', 'name', 'project_code']),
            'paymentModes' => self::MODES,
            'maxLines' => ExpensePaymentService::MAX_BATCH_LINES,
            'userRole' => $authUser->role,
        ]);
    }

    /** JSON: approved advances/reimbursements that still have something left to pay. */
    public function payable(Request $request)
    {
        $authUser = Auth::user();

        $query = Expense::with(['user:id,name,employee_id', 'expenseType:id,name', 'project:id,name'])
            ->payable()
            ->whereIn('requirement_type', [Expense::TYPE_ADVANCE, Expense::TYPE_REIMBURSEMENT]);

        $this->applyScope($query, 'user_id', $authUser, 'expenses', 'manage');

        foreach (['user_id', 'requirement_type', 'expense_type', 'project_id'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->input($filter));
            }
        }
        if ($request->filled('from_date')) {
            $query->whereDate('date', '>=', $request->from_date);
        }
        if ($request->filled('to_date')) {
            $query->whereDate('date', '<=', $request->to_date);
        }
        if ($request->filled('search')) {
            $term = '%' . $request->search . '%';
            $query->where(fn ($q) => $q->where('expense_number', 'like', $term)
                ->orWhere('description', 'like', $term)
                ->orWhereHas('user', fn ($u) => $u->where('name', 'like', $term)->orWhere('employee_id', 'like', $term)));
        }

        $limit = ExpensePaymentService::MAX_BATCH_LINES * 3;
        $total = (clone $query)->count();
        $rows = $query->orderBy('created_at')->orderBy('id')->limit($limit)->get();

        return response()->json([
            'success' => true,
            'truncated' => $total > $limit,
            'total' => $total,
            'data' => $rows->map(fn (Expense $e) => [
                'id' => $e->id,
                'expense_number' => $e->expense_number,
                'user_id' => $e->user_id,
                'employee_name' => $e->user?->name,
                'employee_code' => $e->user?->employee_id,
                'requirement_type' => $e->requirement_type,
                'type_name' => $e->expenseType?->name,
                'project_name' => $e->project?->name,
                'date' => $e->date,
                'description' => $e->description,
                'amount' => (float) $e->amount,
                'paid' => (float) $e->paid_amount,
                'outstanding' => $e->remaining_amount,
            ])->values(),
        ]);
    }

    public function preview(Request $request)
    {
        $lines = $this->validatedLines($request);
        if ($lines instanceof \Illuminate\Http\JsonResponse) {
            return $lines;
        }

        $authUser = Auth::user();

        try {
            return response()->json(['success' => true] + $this->payments->preview($authUser, $lines, $this->can($authUser)));
        } catch (Exception $e) {
            return $this->failure($e, 'preview');
        }
    }

    /** "Pay ₹X to this employee" — suggest lines oldest-first. */
    public function allocate(Request $request)
    {
        $v = Validator::make($request->all(), [
            'user_id' => 'required|integer',
            'amount' => 'required|numeric|gt:0|max:9999999.99',
        ]);
        if ($v->fails()) {
            return response()->json(['success' => false, 'message' => $v->errors()->first()], 422);
        }

        $authUser = Auth::user();

        try {
            $result = $this->payments->allocateFifo($authUser, (int) $request->user_id, $request->amount, $this->can($authUser));
        } catch (ExpenseException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], $e->httpStatus());
        } catch (Exception $e) {
            return $this->failure($e, 'allocate');
        }

        return response()->json(['success' => true] + $result);
    }

    public function store(Request $request)
    {
        $lines = $this->validatedLines($request, withMeta: true);
        if ($lines instanceof \Illuminate\Http\JsonResponse) {
            return $lines;
        }

        $authUser = Auth::user();
        $meta = $request->only(['payment_date', 'payment_mode', 'reference_number', 'bank_name', 'remarks']);

        try {
            $result = $this->payments->payBatch($authUser, $lines, $meta, (string) $request->idempotency_key, $this->can($authUser));
        } catch (ExpenseBatchException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'line_errors' => $e->lineErrors(),
            ], $e->httpStatus());
        } catch (ExpenseException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], $e->httpStatus());
        } catch (Exception $e) {
            return $this->failure($e, 'store');
        }

        /** @var ExpensePaymentBatch $batch */
        $batch = $result['batch'];

        if (! $result['duplicate']) {
            // After the response is sent: FCM pushes are synchronous HTTP calls and must not hold up the user.
            $payments = $result['payments'];
            $expenses = $result['expenses'];
            dispatch(fn () => $this->notifier->notifyBatchPaid($batch, $payments, $expenses, $authUser))->afterResponse();
        }

        return response()->json([
            'success' => true,
            'duplicate' => $result['duplicate'],
            'message' => $result['duplicate']
                ? "This voucher was already posted ({$batch->voucher_number})."
                : "Voucher {$batch->voucher_number} posted — {$batch->line_count} payment(s), ₹" . number_format((float) $batch->total_amount, 2) . '.',
            'batch' => [
                'id' => $batch->id,
                'voucher_number' => $batch->voucher_number,
                'total_amount' => (float) $batch->total_amount,
                'line_count' => $batch->line_count,
                'url' => route('expense.vouchers.show', $batch->id),
                'pdf_url' => route('expense.vouchers.pdf', $batch->id),
                'csv_url' => route('expense.vouchers.csv', $batch->id),
            ],
        ]);
    }

    // ==================== VOUCHERS ====================

    public function index(Request $request)
    {
        $authUser = Auth::user();

        $query = ExpensePaymentBatch::with('creator:id,name');

        // Team-scoped payers only see vouchers they posted; company scope sees all.
        if (app(RbacService::class)->scopeFor($authUser, 'expenses', 'manage') !== 'company') {
            $query->where('created_by', $authUser->id);
        }

        foreach (['status', 'payment_mode'] as $f) {
            if ($request->filled($f)) {
                $query->where($f, $request->input($f));
            }
        }
        if ($request->filled('from_date')) {
            $query->whereDate('payment_date', '>=', $request->from_date);
        }
        if ($request->filled('to_date')) {
            $query->whereDate('payment_date', '<=', $request->to_date);
        }
        if ($request->filled('search')) {
            $term = '%' . $request->search . '%';
            // Voucher no. / reference, or any line's expense code / employee name (Eloquent, so tenant scopes apply).
            $query->where(fn ($q) => $q->where('voucher_number', 'like', $term)
                ->orWhere('reference_number', 'like', $term)
                ->orWhereHas('payments', fn ($p) => $p->whereHas('expense', fn ($e) => $e->where('expense_number', 'like', $term)
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'like', $term)))));
        }

        $stats = (clone $query)->selectRaw("COUNT(*) as c, COALESCE(SUM(CASE WHEN status='posted' THEN total_amount END),0) as posted_total, SUM(status='voided') as voided")->first();

        return view('client.expense.expense.vouchers', [
            'batches' => $query->orderByDesc('id')->paginate(20)->withQueryString(),
            'stats' => $stats,
            'filters' => $request->all(),
            'paymentModes' => self::MODES,
        ]);
    }

    public function show($id)
    {
        $batch = $this->loadAuthorized($id);

        return view('client.expense.expense.voucher-show', [
            'batch' => $batch,
            'lines' => $this->lines($batch),
            'postedTotal' => Money::fromCents($batch->payments->where('status', 'posted')->sum(fn ($p) => Money::toCents($p->amount))),
        ]);
    }

    public function void(Request $request, $id)
    {
        $v = Validator::make($request->all(), ['reason' => 'required|string|min:3|max:500']);
        if ($v->fails()) {
            return response()->json(['success' => false, 'message' => $v->errors()->first()], 422);
        }

        $authUser = Auth::user();

        try {
            $result = $this->payments->voidBatch($authUser, (int) $id, (string) $request->reason, $this->can($authUser));
        } catch (ExpenseException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], $e->httpStatus());
        } catch (Exception $e) {
            return $this->failure($e, 'void');
        }

        $batch = $result['batch'];
        dispatch(function () use ($batch, $result) {
            foreach (ExpensePayment::where('batch_id', $batch->id)->get() as $payment) {
                $expense = $result['expenses'][$payment->expense_id] ?? null;
                if ($expense) {
                    $this->notifier->notifyPaymentDeleted($expense, $payment->amount, "Voucher {$batch->voucher_number} voided");
                }
            }
        })->afterResponse();

        return response()->json([
            'success' => true,
            'message' => "Voucher {$batch->voucher_number} voided — {$result['voided']} payment(s) reversed.",
        ]);
    }

    /** Bank-transfer file: one row per posted line, with the employee's bank details. */
    public function csv($id)
    {
        $batch = $this->loadAuthorized($id);
        $authUser = Auth::user();

        $rows = $this->lines($batch)->where('status', 'posted');

        $handle = fopen('php://temp', 'w+');
        fwrite($handle, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel reads ₹/names correctly

        $this->writeCsvRow($handle, [
            'Voucher', 'Payment Date', 'Mode', 'Reference', 'Employee ID', 'Employee Name', 'Account Number', 'IFSC',
            'Bank', 'Branch', 'Amount', 'Expense No', 'Type', 'Status',
        ]);
        foreach ($rows as $l) {
            $this->writeCsvRow($handle, [
                $batch->voucher_number, $batch->payment_date?->format('Y-m-d'), $batch->payment_mode, $batch->reference_number,
                $l->employee_code, $l->employee_name, $l->account_number, $l->ifsc, $l->bank_name, $l->branch_name,
                number_format((float) $l->amount, 2, '.', ''), $l->expense_number, $l->requirement_type, $l->status,
            ]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        app(AuditLogger::class)->record('tenant_user', $authUser->id, (int) $batch->tenant_id, 'expenses.voucher_exported',
            'ExpensePaymentBatch', (int) $batch->id, [], ['format' => 'csv', 'lines' => $rows->count()]);

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $batch->voucher_number . '.csv"',
        ]);
    }

    public function pdf($id)
    {
        $batch = $this->loadAuthorized($id);
        $authUser = Auth::user();

        $pdf = Pdf::loadView('client.expense.expense.voucher-pdf', [
            'batch' => $batch,
            'lines' => $this->lines($batch),
            'company' => app()->bound('current_tenant') ? app('current_tenant') : null,
        ])->setPaper('a4');

        app(AuditLogger::class)->record('tenant_user', $authUser->id, (int) $batch->tenant_id, 'expenses.voucher_exported',
            'ExpensePaymentBatch', (int) $batch->id, [], ['format' => 'pdf']);

        return $pdf->download($batch->voucher_number . '.pdf');
    }

    // ==================== HELPERS ====================

    private function can(User $user): Closure
    {
        return fn (int $ownerId) => $this->scopeCoversOwner($user, 'expenses', 'manage', $ownerId);
    }

    /**
     * Validate the request shape; returns the normalized lines, or a 422 JSON response.
     *
     * @return array<int, array{expense_id:int, amount:mixed}>|\Illuminate\Http\JsonResponse
     */
    private function validatedLines(Request $request, bool $withMeta = false)
    {
        $rules = [
            'lines' => 'required|array|min:1|max:' . ExpensePaymentService::MAX_BATCH_LINES,
            'lines.*.expense_id' => 'required|integer',
            'lines.*.amount' => 'required|numeric|gt:0|max:9999999.99',
        ];

        if ($withMeta) {
            $rules += [
                'payment_date' => 'required|date',
                'payment_mode' => 'required|in:' . implode(',', self::MODES),
                'reference_number' => 'nullable|string|max:100',
                'bank_name' => 'nullable|string|max:255',
                'remarks' => 'nullable|string|max:500',
                'idempotency_key' => 'required|string|max:64',
            ];
        }

        $v = Validator::make($request->all(), $rules, [
            'lines.required' => 'Select at least one expense to pay.',
            'lines.max' => 'A voucher can hold at most ' . ExpensePaymentService::MAX_BATCH_LINES . ' lines. Split the selection.',
            'lines.*.amount.gt' => 'Every amount must be greater than zero.',
        ]);

        if ($v->fails()) {
            return response()->json(['success' => false, 'message' => $v->errors()->first(), 'errors' => $v->errors()], 422);
        }

        return array_map(fn ($l) => ['expense_id' => (int) $l['expense_id'], 'amount' => $l['amount']], $request->input('lines'));
    }

    /** The voucher, refused unless the caller may manage EVERY employee on it. */
    private function loadAuthorized($id): ExpensePaymentBatch
    {
        $batch = ExpensePaymentBatch::with(['creator:id,name', 'voider:id,name', 'payments'])->findOrFail($id);
        $authUser = Auth::user();

        $ownerIds = Expense::whereIn('id', $batch->payments->pluck('expense_id'))->pluck('user_id')->unique();
        foreach ($ownerIds as $ownerId) {
            abort_unless($this->scopeCoversOwner($authUser, 'expenses', 'manage', (int) $ownerId), 403, 'This voucher includes an employee outside your scope.');
        }

        return $batch;
    }

    /** Voucher lines joined with employee + expense + bank details (one query). */
    private function lines(ExpensePaymentBatch $batch)
    {
        return DB::table('expense_payments as p')
            ->join('expenses as e', 'e.id', '=', 'p.expense_id')
            ->join('users as u', 'u.id', '=', 'e.user_id')
            ->leftJoin('user_bank_details as b', function ($j) {
                $j->on('b.user_id', '=', 'u.id')->whereNull('b.deleted_at');
            })
            ->where('p.batch_id', $batch->id)
            ->orderBy('u.name')->orderBy('p.id')
            ->select('p.id as payment_id', 'p.amount', 'p.status', 'p.void_reason', 'e.id as expense_id', 'e.expense_number',
                'e.requirement_type', 'e.description', 'u.id as user_id', 'u.name as employee_name', 'u.employee_id as employee_code',
                'b.account_number', 'b.ifsc', 'b.bank_name', 'b.branch_name')
            ->get();
    }

    private function failure(Exception $e, string $context)
    {
        Log::error("Expense voucher {$context} failed: " . $e->getMessage(), ['user_id' => Auth::id()]);

        return response()->json(['success' => false, 'message' => 'Something went wrong. Please try again or contact support.'], 500);
    }
}
