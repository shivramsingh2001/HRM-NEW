<?php

namespace App\Http\Controllers\Expense;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\ExpensePayment;
use App\Models\ExpenseStatusHistory;
use App\Models\ExpenseTransaction;
use App\Models\User;
use App\Models\UserExpenseBalance;
use App\Models\UserJobDetail;          // ← Bug 4 fix
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use App\Services\ExpensePaymentNotificationService;

class PaymentController extends Controller
{
    protected $notificationService;

    public function __construct(ExpensePaymentNotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }
    public function index(Request $request)
    {
        try {
            $authUser = Auth::user();
            $employees = User::where('status', 1)->get();

            $query = ExpensePayment::with(['expense.user', 'payer'])
                ->join('expenses', 'expense_payments.expense_id', '=', 'expenses.id')
                ->join('users', 'expenses.user_id', '=', 'users.id')
                ->select(
                    'expense_payments.*',
                    'expenses.expense_number',
                    'expenses.amount as expense_amount',
                    'expenses.requirement_type',
                    'users.name as employee_name',
                    'users.employee_id',
                    'users.email as employee_email'
                );

            if (!in_array($authUser->role, ['admin', 'hr'])) {
                $query->where('expenses.user_id', $authUser->id);
            }

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
            if ($request->filled('search')) {
                $searchTerm = '%' . $request->search . '%';
                $query->where(function ($q) use ($searchTerm) {
                    $q->where('users.name', 'LIKE', $searchTerm)
                        ->orWhere('expenses.expense_number', 'LIKE', $searchTerm)
                        ->orWhere('expense_payments.reference_number', 'LIKE', $searchTerm);
                });
            }

            $payments = $query->orderBy('expense_payments.created_at', 'desc')
                ->paginate(15)
                ->withQueryString();

            $statsQuery = ExpensePayment::query()
                ->join('expenses', 'expense_payments.expense_id', '=', 'expenses.id');

            if (!in_array($authUser->role, ['admin', 'hr'])) {
                $statsQuery->where('expenses.user_id', $authUser->id);
            }
            if ($request->filled('user_id')) {
                $statsQuery->where('expenses.user_id', $request->user_id);
            }

            $totalPayments = $statsQuery->count();
            $totalAmount   = $statsQuery->sum('expense_payments.amount');
            $modeStats     = $statsQuery->select(
                'expense_payments.payment_mode',
                DB::raw('count(*) as count'),
                DB::raw('sum(expense_payments.amount) as total')
            )->groupBy('expense_payments.payment_mode')->get();

            // Include both advance AND reimbursement approved expenses
            $approvedAdvance = Expense::with('user')
                ->whereIn('requirement_type', ['advance'])
                ->where('status', 'approved')
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($expense) {
                    $paidAmount = $expense->payments()->sum('amount');
                    $expense->paid_amount      = $paidAmount;
                    $expense->remaining_amount = $expense->amount - $paidAmount;
                    return $expense;
                })
                ->filter(fn($e) => $e->remaining_amount > 0);
            $approvedreimbursement = Expense::with('user')
                ->whereIn('requirement_type', ['reimbursement'])
                ->where('status', 'approved')
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($expense) {
                    $paidAmount = $expense->payments()->sum('amount');
                    $expense->paid_amount      = $paidAmount;
                    $expense->remaining_amount = $expense->amount - $paidAmount;
                    return $expense;
                })
                ->filter(fn($e) => $e->remaining_amount > 0);

            return view('client.expense.expense.payment', [
                'payments'        => $payments,
                'totalPayments'   => $totalPayments,
                'totalAmount'     => $totalAmount,
                'modeStats'       => $modeStats,
                'approvedAdvances' => $approvedAdvance,
                'approvedReimbursements' => $approvedreimbursement,
                'userRole'        => $authUser->role,
                'filters'         => $request->all(),
                'paymentModes'    => ['cash', 'bank_transfer', 'cheque', 'upi'],
                'employees'       => $employees
            ]);
        } catch (Exception $e) {
            Log::error('Error in payment index: ' . $e->getMessage());
            return redirect()->route('expense.view-all')
                ->with('error', 'Failed to load payments: ' . $e->getMessage());
        }
    }

    public function getUserPendingAdvances(Request $request)
    {
        try {
            $authUser = Auth::user();

            if (!in_array($authUser->role, ['admin', 'hr'])) {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
            }

            $userId = $request->get('user_id');

            $query = Expense::with(['user', 'expenseType', 'project'])
                ->whereIn('requirement_type', ['advance', 'reimbursement'])
                ->where('status', 'approved');

            if ($userId) {
                $query->where('user_id', $userId);
            }

            $pendingAdvances = $query->orderBy('created_at', 'asc')
                ->get()
                ->map(function ($expense) {
                    $totalPaid = $expense->payments()->sum('amount');
                    $expense->paid_amount      = $totalPaid;
                    $expense->remaining_amount = $expense->amount - $totalPaid;
                    return $expense;
                })
                ->filter(fn($e) => $e->remaining_amount > 0)
                ->values();

            return response()->json(['success' => true, 'data' => $pendingAdvances]);
        } catch (Exception $e) {
            Log::error('Error getting user pending advances: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to get pending advances: ' . $e->getMessage()], 500);
        }
    }

    public function store(Request $request)
    {
       
        $validator = Validator::make($request->all(), [
            'payment_date'     => 'required|date',
            'amount'           => 'required|numeric|min:0.01',
            'payment_mode'     => 'required|in:cash,bank_transfer,cheque,upi',
            'reference_number' => 'nullable|string|max:100',
            'bank_name'        => 'nullable|string|max:255',
            'paid_to'          => 'nullable|string|max:255',
            'remarks'          => 'nullable|string|max:500',
            'payment_type'     => 'required|in:direct,advance,reimbursement'
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();
        try {
            $authUser = Auth::user();

            if (!in_array($authUser->role, ['admin', 'hr'])) {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
            }

            $paymentType = $request->payment_type;
            $payment     = null;
            $expense     = null;

            // ==================== 1. DIRECT PAYMENT ====================
            if ($paymentType === 'direct') {
                $v2 = Validator::make($request->all(), ['direct_user_id' => 'required|exists:users,id']);
                if ($v2->fails()) {
                    return response()->json(['success' => false, 'errors' => $v2->errors()], 422);
                }

                $expense = Expense::create([
                    'tenant_id'        => session('tenant_id'),
                    'user_id'          => $request->direct_user_id,
                    'expense_type'     => 1,
                    'amount'           => $request->amount,
                    'date'             => $request->payment_date,
                    'project_id'       => 1,
                    'requirement_type' => 'advance',
                    'description'      => 'Direct payment: ' . ($request->remarks ?? 'No remarks'),
                    'status'           => 'complete',
                    'expense_number'   => 'DIRECT-' . time() . '-' . rand(1000, 9999)
                ]);

                $payment = ExpensePayment::create([
                    'tenant_id'        => session('tenant_id'),
                    'expense_id'       => $expense->id,
                    'payment_date'     => $request->payment_date,
                    'amount'           => $request->amount,
                    'payment_mode'     => $request->payment_mode,
                    'reference_number' => $request->reference_number,
                    'bank_name'        => $request->bank_name,
                    'paid_to'          => $request->paid_to,
                    'paid_by'          => $authUser->id,
                    'remarks'          => $request->remarks
                ]);

                $this->creditAdvanceBalance(
                    $expense,
                    $request->amount,
                    $authUser->id,
                    'Direct payment credited: ' . ($request->remarks ?? '')
                );

                ExpenseStatusHistory::create([
                    'tenant_id'  => session('tenant_id'),
                    'expense_id' => $expense->id,
                    'status'     => 'complete',
                    'changed_by' => $authUser->id,
                    'remarks'    => 'Direct payment processed. Amount: ₹' . number_format($request->amount, 2)
                ]);

                $message = 'Direct payment added successfully and credited to employee balance';
                $data = [
                    'id'               => $payment->id,
                    'payment_date'     => $payment->payment_date,
                    'amount'           => $payment->amount,
                    'payment_mode'     => $payment->payment_mode,
                    'reference_number' => $payment->reference_number,
                    'bank_name'        => $payment->bank_name,
                    'paid_to'          => $payment->paid_to,
                    'remarks'          => $payment->remarks,
                    'expense_number'   => $expense->expense_number,
                    'employee_name'    => $expense->user->name,
                    'payer_name'       => $payment->payer ? $payment->payer->name : null,
                    'payment_type'     => 'direct'
                ];

                // ==================== 2. ADVANCE PAYMENT ====================
            } elseif ($paymentType === 'advance') {
                $v2 = Validator::make($request->all(), ['advance_expense_id' => 'required|exists:expenses,id']);
                if ($v2->fails()) {
                    return response()->json(['success' => false, 'errors' => $v2->errors()], 422);
                }

                $expense = Expense::findOrFail($request->expense_id);

                // Verify it's an advance request
                if ($expense->requirement_type !== 'advance') {
                    return response()->json([
                        'success' => false,
                        'message' => 'Selected expense is not an advance request.'
                    ], 400);
                }

                if ($expense->status !== 'approved') {
                    return response()->json([
                        'success' => false,
                        'message' => 'Only approved advances can have payment records.'
                    ], 400);
                }

                $totalPaid = $expense->payments()->sum('amount');
                $remainingAmount = $expense->amount - $totalPaid;

                if ($request->amount > $remainingAmount + 0.01) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Payment amount exceeds remaining balance. Remaining: ₹' . number_format($remainingAmount, 2)
                    ], 400);
                }

                $payment = ExpensePayment::create([
                    'tenant_id'        => session('tenant_id'),
                    'expense_id'       => $expense->id,
                    'payment_date'     => $request->payment_date,
                    'amount'           => $request->amount,
                    'payment_mode'     => $request->payment_mode,
                    'reference_number' => $request->reference_number,
                    'bank_name'        => $request->bank_name,
                    'paid_to'          => $request->paid_to,
                    'paid_by'          => $authUser->id,
                    'remarks'          => $request->remarks
                ]);

                $newTotalPaid = $totalPaid + $request->amount;
                $isFullyPaid = abs($newTotalPaid - $expense->amount) < 0.01;

                // Credit to advance balance
                $this->creditAdvanceBalance($expense, $request->amount, $authUser->id, $request->remarks);

                if ($isFullyPaid && $expense->status !== 'complete') {
                    $expense->update(['status' => 'complete']);
                    ExpenseStatusHistory::create([
                        'tenant_id'  => session('tenant_id'),
                        'expense_id' => $expense->id,
                        'status'     => 'complete',
                        'changed_by' => $authUser->id,
                        'remarks'    => 'Fully paid. Total: ₹' . number_format($newTotalPaid, 2)
                    ]);
                } elseif (!$isFullyPaid) {
                    ExpenseStatusHistory::create([
                        'tenant_id'  => session('tenant_id'),
                        'expense_id' => $expense->id,
                        'status'     => 'approved',
                        'changed_by' => $authUser->id,
                        'remarks'    => 'Partial payment of ₹' . number_format($request->amount, 2)
                            . '. Remaining: ₹' . number_format($remainingAmount - $request->amount, 2)
                    ]);
                }

                $message = 'Advance payment processed successfully';
                $data = [
                    'id'               => $payment->id,
                    'payment_date'     => $payment->payment_date,
                    'amount'           => $payment->amount,
                    'payment_mode'     => $payment->payment_mode,
                    'reference_number' => $payment->reference_number,
                    'bank_name'        => $payment->bank_name,
                    'paid_to'          => $payment->paid_to,
                    'remarks'          => $payment->remarks,
                    'expense_number'   => $expense->expense_number,
                    'employee_name'    => $expense->user->name,
                    'payer_name'       => $payment->payer ? $payment->payer->name : null,
                    'total_paid'       => $newTotalPaid,
                    'remaining_amount' => $expense->amount - $newTotalPaid,
                    'is_fully_paid'    => $isFullyPaid,
                    'payment_type'     => 'advance'
                ];

                // ==================== 3. REIMBURSEMENT PAYMENT ====================
            } elseif ($paymentType === 'reimbursement') {
                $v2 = Validator::make($request->all(), ['reimbursement_expense_id' => 'required|exists:expenses,id']);
                if ($v2->fails()) {
                    return response()->json(['success' => false, 'errors' => $v2->errors()], 422);
                }

                $expense = Expense::findOrFail($request->expense_id);

                // Verify it's a reimbursement request
                if ($expense->requirement_type !== 'reimbursement') {
                    return response()->json([
                        'success' => false,
                        'message' => 'Selected expense is not a reimbursement request.'
                    ], 400);
                }

                if ($expense->status !== 'approved') {
                    return response()->json([
                        'success' => false,
                        'message' => 'Only approved reimbursements can have payment records.'
                    ], 400);
                }

                $totalPaid = $expense->payments()->sum('amount');
                $remainingAmount = $expense->amount - $totalPaid;

                if ($request->amount > $remainingAmount + 0.01) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Payment amount exceeds remaining balance. Remaining: ₹' . number_format($remainingAmount, 2)
                    ], 400);
                }

                $payment = ExpensePayment::create([
                    'tenant_id'        => session('tenant_id'),
                    'expense_id'       => $expense->id,
                    'payment_date'     => $request->payment_date,
                    'amount'           => $request->amount,
                    'payment_mode'     => $request->payment_mode,
                    'reference_number' => $request->reference_number,
                    'bank_name'        => $request->bank_name,
                    'paid_to'          => $request->paid_to,
                    'paid_by'          => $authUser->id,
                    'remarks'          => $request->remarks
                ]);

                $newTotalPaid = $totalPaid + $request->amount;
                $isFullyPaid = abs($newTotalPaid - $expense->amount) < 0.01;

                // Process reimbursement (adds to reimbursement balance)
                $this->processReimbursementPayment($expense, $request->amount, $authUser->id, $request->remarks);

                if ($isFullyPaid && $expense->status !== 'complete') {
                    $expense->update(['status' => 'complete']);
                    ExpenseStatusHistory::create([
                        'tenant_id'  => session('tenant_id'),
                        'expense_id' => $expense->id,
                        'status'     => 'complete',
                        'changed_by' => $authUser->id,
                        'remarks'    => 'Fully reimbursed. Total: ₹' . number_format($newTotalPaid, 2)
                    ]);
                } elseif (!$isFullyPaid) {
                    ExpenseStatusHistory::create([
                        'tenant_id'  => session('tenant_id'),
                        'expense_id' => $expense->id,
                        'status'     => 'approved',
                        'changed_by' => $authUser->id,
                        'remarks'    => 'Partial reimbursement of ₹' . number_format($request->amount, 2)
                            . '. Remaining: ₹' . number_format($remainingAmount - $request->amount, 2)
                    ]);
                }

                $message = 'Reimbursement payment processed successfully';
                $data = [
                    'id'               => $payment->id,
                    'payment_date'     => $payment->payment_date,
                    'amount'           => $payment->amount,
                    'payment_mode'     => $payment->payment_mode,
                    'reference_number' => $payment->reference_number,
                    'bank_name'        => $payment->bank_name,
                    'paid_to'          => $payment->paid_to,
                    'remarks'          => $payment->remarks,
                    'expense_number'   => $expense->expense_number,
                    'employee_name'    => $expense->user->name,
                    'payer_name'       => $payment->payer ? $payment->payer->name : null,
                    'total_paid'       => $newTotalPaid,
                    'remaining_amount' => $expense->amount - $newTotalPaid,
                    'is_fully_paid'    => $isFullyPaid,
                    'payment_type'     => 'reimbursement'
                ];
            }


            if ($payment) {
                $payment->load('payer', 'expense.user');
            }
                        

            DB::commit();
            try{
                $this->notificationService->notifyPaymentCreated($expense, $payment, $request->remarks);
            }catch(Exception $e){
                Log::error('Failed to send expense notifications: ' . $e->getMessage());
            }

            return response()->json(['success' => true, 'message' => $message, 'data' => $data], 200);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error creating payment: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to add payment: ' . $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'payment_date'     => 'required|date',
            'amount'           => 'required|numeric|min:0.01',
            'payment_mode'     => 'required|in:cash,bank_transfer,cheque,upi',
            'reference_number' => 'nullable|string|max:100',
            'bank_name'        => 'nullable|string|max:255',
            'paid_to'          => 'nullable|string|max:255',
            'remarks'          => 'nullable|string|max:500'
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();
        try {
            $authUser = Auth::user();

            if (!in_array($authUser->role, ['admin', 'hr'])) {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
            }

            $payment   = ExpensePayment::with('expense.user')->findOrFail($id);
            $expense   = $payment->expense;
            $oldAmount = $payment->amount;

            $isDirectPayment = $expense->requirement_type === 'advance'
                && $expense->description
                && str_contains($expense->description, 'Direct payment:');

            if (!$isDirectPayment) {
                $otherPaymentsTotal = $expense->payments()->where('id', '!=', $id)->sum('amount');
                $maxAllowedAmount   = $expense->amount - $otherPaymentsTotal;

                if ($request->amount > $maxAllowedAmount + 0.01) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Payment amount exceeds remaining balance. Maximum allowed: ₹' . number_format($maxAllowedAmount, 2)
                    ], 400);
                }
            }

            $payment->update([
                'payment_date'     => $request->payment_date,
                'amount'           => $request->amount,
                'payment_mode'     => $request->payment_mode,
                'reference_number' => $request->reference_number,
                'bank_name'        => $request->bank_name,
                'paid_to'          => $request->paid_to,
                'remarks'          => $request->remarks
            ]);

            // ✅ Bug 1 fix — route balance adjustment by expense type
            $amountDifference = $request->amount - $oldAmount;
            if ($amountDifference != 0) {
                if ($expense->requirement_type === 'reimbursement') {
                    if ($amountDifference > 0) {
                        $this->processReimbursementPayment($expense, $amountDifference, $authUser->id, $request->remarks);
                    } else {
                        $this->reverseReimbursementBalance($expense, abs($amountDifference), $authUser->id);
                    }
                } else {
                    if ($amountDifference > 0) {
                        $this->creditAdvanceBalance($expense, $amountDifference, $authUser->id, $request->remarks);
                    } else {
                        $this->reverseAdvanceBalance($expense, abs($amountDifference), $authUser->id);
                    }
                }
            }

            if (!$isDirectPayment) {
                $otherPaymentsTotal = $expense->payments()->where('id', '!=', $id)->sum('amount');
                $newTotalPaid       = $otherPaymentsTotal + $request->amount;
                $isFullyPaid        = abs($newTotalPaid - $expense->amount) < 0.01;

                if ($isFullyPaid && $expense->status !== 'complete') {
                    $expense->update(['status' => 'complete']);
                    ExpenseStatusHistory::create([
                        'tenant_id'  => session('tenant_id'),
                        'expense_id' => $expense->id,
                        'status'     => 'complete',
                        'changed_by' => $authUser->id,
                        'remarks'    => $expense->requirement_type === 'reimbursement'
                            ? 'Fully reimbursed after update. Total: ₹' . number_format($newTotalPaid, 2)
                            : 'Fully paid after update. Total: ₹' . number_format($newTotalPaid, 2)
                    ]);
                } elseif (!$isFullyPaid && $expense->status === 'complete') {
                    $expense->update(['status' => 'approved']);
                    ExpenseStatusHistory::create([
                        'tenant_id'  => session('tenant_id'),
                        'expense_id' => $expense->id,
                        'status'     => 'approved',
                        'changed_by' => $authUser->id,
                        'remarks'    => $expense->requirement_type === 'reimbursement'
                            ? 'Partial reimbursement after update. Status reverted to approved'
                            : 'Partially paid after update. Status reverted to approved'
                    ]);
                }
            }

            DB::commit();
            try{
                $this->notificationService->notifyPaymentUpdated($expense, $payment, $oldAmount, $request->remarks);
            }catch(Exception $e){
                Log::error('Failed to send expense notifications: ' . $e->getMessage());
            }
            

            return response()->json([
                'success' => true,
                'message' => 'Payment updated successfully',
                'data'    => [
                    'id'               => $payment->id,
                    'payment_date'     => $payment->payment_date,
                    'amount'           => $payment->amount,
                    'payment_mode'     => $payment->payment_mode,
                    'reference_number' => $payment->reference_number,
                    'bank_name'        => $payment->bank_name,
                    'paid_to'          => $payment->paid_to,
                    'remarks'          => $payment->remarks,
                    'payment_type'     => $isDirectPayment ? 'direct' : $expense->requirement_type
                ]
            ], 200);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error updating payment: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to update payment: ' . $e->getMessage()], 500);
        }
    }

    public function destroy($id)
    {
        DB::beginTransaction();
        try {
            $authUser = Auth::user();

            if (!in_array($authUser->role, ['admin', 'hr'])) {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
            }

            $payment       = ExpensePayment::with('expense')->findOrFail($id);
            $expense       = $payment->expense;
            $paymentAmount = $payment->amount;

            // ✅ Bug 2 fix — route reversal by expense type
            if ($expense->requirement_type === 'reimbursement') {
                $this->reverseReimbursementBalance($expense, $paymentAmount, $authUser->id);
            } else {
                $this->reverseAdvanceBalance($expense, $paymentAmount, $authUser->id);
            }

            $payment->delete();

            $totalPaid = $expense->payments()->sum('amount');

            if ($totalPaid < $expense->amount && $expense->status === 'complete') {
                $expense->update(['status' => 'approved']);
                ExpenseStatusHistory::create([
                    'tenant_id'  => session('tenant_id'),
                    'expense_id' => $expense->id,
                    'status'     => 'approved',
                    'changed_by' => $authUser->id,
                    'remarks'    => $totalPaid == 0
                        ? 'All payments deleted - status reverted to approved'
                        : 'Payment deleted - partially paid, status reverted to approved'
                ]);
            }

            DB::commit();
             try{
               $this->notificationService->notifyPaymentDeleted($expense, $paymentAmount, 'Payment deleted');
            }catch(Exception $e){
                Log::error('Failed to send expense notifications: ' . $e->getMessage());
            }

            return response()->json(['success' => true, 'message' => 'Payment deleted successfully'], 200);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error deleting payment: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to delete payment:'], 500);
        }
    }

    public function expensePayments($expenseId)
    {
        try {
            $authUser = Auth::user();

            if (!in_array($authUser->role, ['admin', 'hr', 'manager'])) {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
            }

            $expense = Expense::with(['payments.payer', 'user'])->findOrFail($expenseId);

            if (!in_array($authUser->role, ['admin', 'hr'])) {
                $isReportingHead = UserJobDetail::where('user_id', $expense->user_id)   // ← Bug 4 fix
                    ->where('reporting_head', $authUser->id)
                    ->exists();

                if ($expense->user_id != $authUser->id && !$isReportingHead) {
                    return response()->json(['success' => false, 'message' => 'You are not authorized to view these payments.'], 403);
                }
            }

            $payments        = $expense->payments;
            $totalPaid       = $payments->sum('amount');
            $remainingAmount = $expense->amount - $totalPaid;

            return response()->json([
                'success' => true,
                'data'    => [
                    'expense_id'       => $expense->id,
                    'expense_number'   => $expense->expense_number,
                    'expense_amount'   => $expense->amount,
                    'requirement_type' => $expense->requirement_type,
                    'total_paid'       => $totalPaid,
                    'remaining_amount' => $remainingAmount,
                    'payments'         => $payments->map(fn($p) => [
                        'id'               => $p->id,
                        'payment_date'     => $p->payment_date,
                        'amount'           => $p->amount,
                        'payment_mode'     => $p->payment_mode,
                        'reference_number' => $p->reference_number,
                        'bank_name'        => $p->bank_name,
                        'paid_to'          => $p->paid_to,
                        'remarks'          => $p->remarks,
                        'paid_by'          => $p->paid_by,
                        'payer_name'       => $p->payer ? $p->payer->name : null,
                        'created_at'       => $p->created_at
                    ])
                ]
            ], 200);
        } catch (Exception $e) {
            Log::error('Error fetching expense payments: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to fetch payments: ' . $e->getMessage()], 500);
        }
    }

    public function show($id)
    {
        try {
            $authUser = Auth::user();

            if (!in_array($authUser->role, ['admin', 'hr'])) {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
            }

            $payment = ExpensePayment::with('expense')->findOrFail($id);

            return response()->json([
                'success' => true,
                'data'    => [
                    'id'               => $payment->id,
                    'expense_id'       => $payment->expense_id,
                    'requirement_type' => $payment->expense->requirement_type,
                    'payment_date'     => $payment->payment_date,
                    'amount'           => $payment->amount,
                    'payment_mode'     => $payment->payment_mode,
                    'reference_number' => $payment->reference_number,
                    'bank_name'        => $payment->bank_name,
                    'paid_to'          => $payment->paid_to,
                    'remarks'          => $payment->remarks
                ]
            ], 200);
        } catch (Exception $e) {
            Log::error('Error fetching payment details: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to fetch payment details: ' . $e->getMessage()], 500);
        }
    }

    public function getAdvanceSummary($expenseId)
    {
        try {
            $expense   = Expense::with('payments')->findOrFail($expenseId);
            $totalPaid = $expense->payments->sum('amount');
            $remaining = $expense->amount - $totalPaid;

            return response()->json([
                'success' => true,
                'data'    => [
                    'expense_id'       => $expense->id,
                    'expense_number'   => $expense->expense_number,
                    'requirement_type' => $expense->requirement_type,
                    'total_amount'     => $expense->amount,
                    'total_paid'       => $totalPaid,
                    'remaining_amount' => $remaining,
                    'is_fully_paid'    => $remaining <= 0,
                    'payments'         => $expense->payments
                ]
            ]);
        } catch (Exception $e) {
            Log::error('Error getting advance summary: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to get advance summary: ' . $e->getMessage()], 500);
        }
    }

    public function getPendingAdvances()
    {
        try {
            $authUser = Auth::user();

            if (!in_array($authUser->role, ['admin', 'hr'])) {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
            }

            $pendingAdvances = Expense::with(['user', 'expenseType', 'project'])
                ->whereIn('requirement_type', ['advance', 'reimbursement'])
                ->where('status', 'approved')
                ->orderBy('created_at', 'asc')
                ->get()
                ->map(function ($expense) {
                    $totalPaid = $expense->payments()->sum('amount');
                    $expense->paid_amount      = $totalPaid;
                    $expense->remaining_amount = $expense->amount - $totalPaid;
                    return $expense;
                })
                ->filter(fn($e) => $e->remaining_amount > 0)
                ->values();

            return response()->json(['success' => true, 'data' => $pendingAdvances]);
        } catch (Exception $e) {
            Log::error('Error getting pending advances: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to get pending advances: ' . $e->getMessage()], 500);
        }
    }

    // ==================== PRIVATE BALANCE HELPERS ====================

    private function creditAdvanceBalance($expense, $paymentAmount, $paidBy, $remarks)
    {
        $balance = UserExpenseBalance::firstOrCreate(
            ['user_id' => $expense->user_id],
            ['current_balance' => 0, 'advance_balance' => 0, 'settlement_balance' => 0, 'reimbursement_balance' => 0]
        );

        $balanceBefore = $balance->current_balance;
        $balanceAfter  = $balanceBefore + $paymentAmount;

        $balance->update([
            'current_balance' => $balanceAfter,
            'advance_balance' => $balance->advance_balance + $paymentAmount
        ]);

        ExpenseTransaction::create([
            'tenant_id'        => session('tenant_id'),
            'expense_id'       => $expense->id,
            'user_id'          => $expense->user_id,
            'transaction_type' => 'advance_credited',
            'amount'           => $paymentAmount,
            'balance_before'   => $balanceBefore,
            'balance_after'    => $balanceAfter,
            'description'      => $remarks ?: 'Advance payment of ₹' . number_format($paymentAmount, 2) . ' credited'
        ]);

        return true;
    }

    private function reverseAdvanceBalance($expense, $paymentAmount, $deletedBy)
    {
        $balance = UserExpenseBalance::where('user_id', $expense->user_id)->first();

        if ($balance && $paymentAmount > 0) {
            $balanceBefore = $balance->current_balance;
            $balanceAfter  = $balanceBefore - $paymentAmount;

            $balance->update([
                'current_balance' => $balanceAfter,
                'advance_balance' => $balance->advance_balance - $paymentAmount
            ]);

            ExpenseTransaction::create([
                'tenant_id'        => session('tenant_id'),
                'expense_id'       => $expense->id,
                'user_id'          => $expense->user_id,
                'transaction_type' => 'advance_credited',
                'amount'           => -$paymentAmount,
                'balance_before'   => $balanceBefore,
                'balance_after'    => $balanceAfter,
                'description'      => 'Advance payment of ₹' . number_format($paymentAmount, 2) . ' reversed | By user_id: ' . $deletedBy
            ]);
        }

        return true;
    }

    private function processReimbursementPayment($expense, $paymentAmount, $paidBy, $remarks)
    {
        $balance = UserExpenseBalance::firstOrCreate(
            ['user_id' => $expense->user_id],
            ['current_balance' => 0, 'advance_balance' => 0, 'settlement_balance' => 0, 'reimbursement_balance' => 0]
        );

        $reimbursementBefore = $balance->reimbursement_balance;
        $reimbursementAfter  = $reimbursementBefore + $paymentAmount;

        $balance->update(['reimbursement_balance' => $reimbursementAfter]);

        ExpenseTransaction::create([
            'tenant_id'        => session('tenant_id'),
            'expense_id'       => $expense->id,
            'user_id'          => $expense->user_id,
            'transaction_type' => 'reimbursement_paid',
            'amount'           => $paymentAmount,
            'balance_before'   => $reimbursementBefore,
            'balance_after'    => $reimbursementAfter,
            'description'      => ($remarks ?: 'Reimbursement paid') . ' | Processed by user_id: ' . $paidBy
        ]);

        return true;
    }

       private function reverseReimbursementBalance($expense, $paymentAmount, $deletedBy)
    {
        $balance = UserExpenseBalance::where('user_id', $expense->user_id)->first();

        if ($balance && $paymentAmount > 0) {
            $reimbursementBefore = $balance->reimbursement_balance;
            $reimbursementAfter  = max(0, $reimbursementBefore - $paymentAmount);

            $balance->update(['reimbursement_balance' => $reimbursementAfter]);

            ExpenseTransaction::create([
                'tenant_id'        => session('tenant_id'),
                'expense_id'       => $expense->id,
                'user_id'          => $expense->user_id,
                'transaction_type' => 'reimbursement_paid',
                'amount'           => -$paymentAmount,
                'balance_before'   => $reimbursementBefore,
                'balance_after'    => $reimbursementAfter,
                'description'      => 'Reimbursement of ₹' . number_format($paymentAmount, 2) . ' reversed | By user_id: ' . $deletedBy
            ]);
        }

        return true;
    }
}
