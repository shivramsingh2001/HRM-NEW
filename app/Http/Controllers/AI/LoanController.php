<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\AI\Concerns\AiScope;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * GET /api/ai/loan — loans with category, EMI, amount repaid / outstanding, next instalment
 * and overdue instalments. Visibility follows loans:view (own / team / company).
 */
class LoanController extends Controller
{
    use AiScope;

    public function view_ai_all(Request $request)
    {
        try {
            $authUser = Auth::user();
            $tenantId = (int) $authUser->tenant_id;

            $scope = $this->scopeOf($authUser, 'loans');
            if ($scope === null) {
                return $this->forbidden();
            }
            $ids = $this->narrowToUser($this->visibleUserIds($authUser, $scope), $request->user_id);

            $query = DB::table('loans as l')
                ->leftJoin('loan_categories as c', 'c.id', '=', 'l.loan_type_id')
                ->join('users as u', 'u.id', '=', 'l.user_id')
                ->where('l.tenant_id', $tenantId)
                ->whereNull('l.deleted_at')
                ->select([
                    'l.id', 'l.loan_number', 'l.loan_kind', 'l.advance_month', 'l.user_id', 'u.name as employee_name', 'u.employee_id', 'c.name as category',
                    'l.amount', 'l.processing_fee', 'l.interest_rate', 'l.tenure_months', 'l.total_payable', 'l.repayment_type',
                    'l.emi_amount', 'l.remaining_amount', 'l.loan_date', 'l.first_emi_date', 'l.last_emi_date', 'l.lumpsum_due_date',
                    'l.lumpsum_amount', 'l.closed_date', 'l.status', 'l.purpose', 'l.approved_at', 'l.disbursed_at',
                    'l.rejection_reason', 'l.cancellation_reason', 'l.created_at',
                ]);

            if ($ids !== null) {
                $query->whereIn('l.user_id', $ids ?: [0]);
            }
            if ($request->filled('status')) {
                $query->where('l.status', $request->status);
            }

            $loans = $query->orderByDesc('l.created_at')->get();

            $repayments = DB::table('loan_repayments')->where('tenant_id', $tenantId)
                ->whereIn('loan_id', $loans->pluck('id')->all() ?: [0])->whereNull('deleted_at')
                ->orderBy('installment_number')
                ->get(['loan_id', 'installment_number', 'due_date', 'total_amount', 'paid_amount', 'paid_date', 'status', 'is_auto_deducted'])
                ->groupBy('loan_id');

            $data = $loans->map(function ($l) use ($repayments) {
                $rows = $repayments->get($l->id, collect());
                $paid = (float) $rows->sum('paid_amount');
                $next = $rows->first(fn ($r) => in_array($r->status, ['pending', 'partial', 'overdue'], true));
                $overdue = $rows->where('status', 'overdue');

                return [
                    'id' => $l->id,
                    'loan_number' => $l->loan_number,
                    'kind' => $l->loan_kind ?? 'loan', // loan | salary_advance
                    'advance_month' => $l->advance_month, // salary advance: the payroll month it is deducted from
                    'employee' => ['id' => $l->user_id, 'name' => $l->employee_name, 'employee_id' => $l->employee_id],
                    'category' => $l->category,
                    'purpose' => $l->purpose,
                    'status' => $l->status,
                    'amount' => (float) $l->amount,
                    'processing_fee' => (float) $l->processing_fee,
                    'interest_rate' => (float) $l->interest_rate,
                    'tenure_months' => $l->tenure_months,
                    'total_payable' => (float) $l->total_payable,
                    'repayment_type' => $l->repayment_type,
                    'emi_amount' => $l->emi_amount !== null ? (float) $l->emi_amount : null,
                    'amount_repaid' => round($paid, 2),
                    'outstanding' => $l->remaining_amount !== null ? (float) $l->remaining_amount : round(max(0, (float) $l->total_payable - $paid), 2),
                    'installments' => [
                        'total' => $rows->count(),
                        'paid' => $rows->where('status', 'paid')->count(),
                        'pending' => $rows->whereIn('status', ['pending', 'partial'])->count(),
                        'overdue' => $overdue->count(),
                        'overdue_amount' => round($overdue->sum(fn ($r) => (float) $r->total_amount - (float) $r->paid_amount), 2),
                    ],
                    'next_installment' => $next ? [
                        'number' => $next->installment_number,
                        'due_date' => $next->due_date,
                        'amount' => (float) $next->total_amount - (float) $next->paid_amount,
                        'status' => $next->status,
                        'auto_deducted_from_salary' => (bool) $next->is_auto_deducted,
                    ] : null,
                    'dates' => [
                        'applied' => $l->created_at,
                        'loan_date' => $l->loan_date,
                        'approved_at' => $l->approved_at,
                        'disbursed_at' => $l->disbursed_at,
                        'first_emi_date' => $l->first_emi_date,
                        'last_emi_date' => $l->last_emi_date,
                        'lumpsum_due_date' => $l->lumpsum_due_date,
                        'closed_date' => $l->closed_date,
                    ],
                    'rejection_reason' => $l->rejection_reason,
                    'cancellation_reason' => $l->cancellation_reason,
                ];
            })->values();

            $running = $data->whereIn('status', ['approved', 'active']);

            return response()->json([
                'success' => true,
                'message' => 'Loan data fetched successfully',
                'data' => $data,
                'summary' => [
                    'total_loans' => $data->count(),
                    'by_status' => $data->countBy('status'),
                    'running_loans' => $running->count(),
                    'total_borrowed' => round($running->sum('amount'), 2),
                    'total_outstanding' => round($running->sum('outstanding'), 2),
                    'monthly_emi_total' => round($running->sum('emi_amount'), 2),
                    'overdue_installments' => $data->sum(fn ($l) => $l['installments']['overdue']),
                    'pending_approval' => $data->where('status', 'pending')->count(),
                    'salary_advances_outstanding' => round($running->where('kind', 'salary_advance')->sum('outstanding'), 2),
                ],
                'scope' => $scope,
            ], 200);
        } catch (\Throwable $e) {
            return $this->failed('loans', $e);
        }
    }
}
