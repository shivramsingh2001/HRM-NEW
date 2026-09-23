<?php

namespace App\Http\Controllers\Expense;

use App\Exceptions\ExpenseBatchException;
use App\Exceptions\ExpenseException;
use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\ExpensePayrollLink;
use App\Models\User;
use App\Services\Expense\ExpensePaymentService;
use App\Services\Expense\ExpenseReimbursementPayrollService;
use App\Support\ExpenseFeatures;
use App\Traits\AuthorizesByScope;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Finance screen for paying reimbursements THROUGH PAYROLL (see ExpenseReimbursementPayrollService).
 * `expense.payroll.*`, behind permission:expenses,manage.
 *
 *   index    GET   the reimbursements waiting to be routed + everything already routed (with its state)
 *   send     POST  route approved reimbursements to a payroll month   (needs the company feature)
 *   release  POST  hand one back to the voucher route                 (works even when the feature is off,
 *                                                                      so nothing is ever stranded)
 */
class ExpensePayrollRouteController extends Controller
{
    use AuthorizesByScope;

    public function __construct(private ExpenseReimbursementPayrollService $payroll)
    {
    }

    public function index(Request $request)
    {
        $user = Auth::user();
        $enabled = ExpenseFeatures::payrollRouteEnabled((int) $user->tenant_id);

        $routed = ExpensePayrollLink::active()->with(['expense:id,expense_number,user_id,amount,date,description', 'expense.user:id,name,employee_id', 'monthlyPayroll:id,payroll_month,payment_status'])
            ->orderByDesc('id')->limit(300)->get()
            ->filter(fn ($l) => $l->expense && $this->scopeCoversOwner($user, 'expenses', 'manage', (int) $l->expense->user_id));

        // Nothing to route and nothing routed: don't show an empty page for a company without the feature.
        if (! $enabled && $routed->isEmpty()) {
            return redirect()->route('expense.payments.index')->with('error', 'Paying reimbursements through payroll is not enabled for your company.');
        }

        $waiting = collect();
        if ($enabled) {
            $query = Expense::with(['user:id,name,employee_id', 'expenseType:id,name'])->payable()
                ->where('requirement_type', Expense::TYPE_REIMBURSEMENT)->where('paid_amount', 0);
            $this->applyScope($query, 'user_id', $user, 'expenses', 'manage');
            $waiting = $query->orderBy('id')->limit(300)->get();
        }

        return view('client.expense.expense.payroll-route', [
            'enabled' => $enabled,
            'waiting' => $waiting,
            'routed' => $routed,
            'defaultMonth' => Carbon::now()->format('Y-m'),
            'nextMonth' => Carbon::now()->addMonth()->format('Y-m'),
        ]);
    }

    public function send(Request $request)
    {
        $data = $request->validate([
            'expense_ids' => 'required|array|min:1',
            'expense_ids.*' => 'integer',
            'month' => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
        ], ['month.regex' => 'Choose the payroll month.']);

        $user = Auth::user();

        try {
            $r = $this->payroll->sendToPayroll($user, $data['expense_ids'], $data['month'],
                fn (int $ownerId) => $this->scopeCoversOwner($user, 'expenses', 'manage', $ownerId));

            $message = "{$r['sent']} reimbursement(s), ₹{$r['total']}, will be paid with the {$r['month']} salary.";
            if ($r['pending_payslips']) {
                $message .= ' A payslip for that month already exists for ' . implode(', ', $r['pending_payslips'])
                    . ' — open it and save (or regenerate it) to include the reimbursement.';
            }

            return response()->json(['success' => true, 'message' => $message] + $r);
        } catch (ExpenseBatchException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'line_errors' => $e->lineErrors()], $e->httpStatus());
        } catch (ExpenseException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], $e->httpStatus());
        } catch (\Throwable $e) {
            Log::error('Expense send-to-payroll failed: ' . $e->getMessage(), ['user_id' => $user->id]);

            return response()->json(['success' => false, 'message' => 'Something went wrong. Please try again or contact support.'], 500);
        }
    }

    public function release(Request $request, int $id)
    {
        $request->validate(['reason' => 'required|string|min:3|max:255']);
        $user = Auth::user();

        try {
            $expense = $this->payroll->release($user, $id, (string) $request->reason,
                fn (int $ownerId) => $this->scopeCoversOwner($user, 'expenses', 'manage', $ownerId));

            return response()->json(['success' => true, 'message' => "{$expense->expense_number} is back on the voucher route."]);
        } catch (ExpenseException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], $e->httpStatus());
        } catch (\Throwable $e) {
            Log::error('Expense release-from-payroll failed: ' . $e->getMessage(), ['user_id' => $user->id]);

            return response()->json(['success' => false, 'message' => 'Something went wrong. Please try again or contact support.'], 500);
        }
    }
}
