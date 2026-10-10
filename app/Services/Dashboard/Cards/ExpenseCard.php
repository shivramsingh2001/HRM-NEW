<?php

namespace App\Services\Dashboard\Cards;

use App\Models\Expense;
use App\Models\User;
use App\Models\UserExpenseBalance;
use App\Services\RbacService;
use Illuminate\Support\Facades\Auth;

/**
 * Dashboard — Expense KPI / chart numbers.
 * Moved out of DashboardController unchanged (code-quality plan, Phase 2).
 */
class ExpenseCard
{
    /** @param array|null $dates [Y-m-d, Y-m-d] — only expenses dated in that period (balances stay "now") */
    public function statistics(?array $dates = null)
    {
        $authUser = Auth::user();
        $userId = $authUser->id;

        $currentBalance = UserExpenseBalance::sum('current_balance');
        $AdvanceBalance = UserExpenseBalance::sum('advance_balance');
        $SettlementBalance = UserExpenseBalance::sum('settlement_balance');
        $ReimbursementBalance = UserExpenseBalance::sum('reimbursement_balance');

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

        // Permission-based access
        if (app(RbacService::class)->scopeFor($authUser, 'expenses', 'view') !== 'company') {
            $expenseQuery->where(function ($q) use ($authUser, $userId) {
                $q->where('expenses.user_id', $userId)
                    ->orWhereIn('users.id', function ($sub) use ($authUser) {
                        $sub->select('user_id')->from('user_reporting_heads')->where('reporting_head_id', $authUser->id);
                    });
            });
        }
        if ($dates) {
            $expenseQuery->whereBetween('expenses.date', $dates);
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
        $totalExpenses = $allExpenses->whereIn('requirement_type', ['advance', 'reimbursement'])->count();
        $totalAmount = $allExpenses->whereIn('requirement_type', ['advance', 'reimbursement'])->sum('amount');
        // $totalExpenses = $advanceExpenses->count() + $reimbursementExpenses->count();
        // $totalAmount = $advanceExpenses->sum('amount') + $reimbursementExpenses->sum('amount');

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

        $cancelledCount = $cancelledAdvanceCount + $cancelledReimbursementCount;
        $cancelledAmount = $cancelledAdvanceAmount + $cancelledReimbursementAmount;
        // Get paginated results
        $expenses = $expenseQuery
            ->orderBy('expenses.date', 'desc')
            ->orderBy('expenses.created_at', 'desc')
            ->paginate(15)
            ->withQueryString();

        // Transform file URLs
        $expenses->getCollection()->transform(function ($expense) {
            $expense->file = app(\App\Services\Expense\ExpenseAttachmentService::class)->url($expense->file, (int) $expense->id);

            return $expense;
        });

        // Get employees for filter dropdown
        $employeesQuery = User::where('status', 1)
            ->with(['jobDetails']);

        if (app(RbacService::class)->scopeFor($authUser, 'expenses', 'view') !== 'company') {
            $employeesQuery->where(function ($q) use ($authUser) {
                $q->managedBy($authUser->id)->orWhere('id', $authUser->id);
            });
        }

        return [

            'currentBalance' => $currentBalance,
            'totalAdvanceTaken' => $AdvanceBalance,
            'totalSettlementDone' => $SettlementBalance,
            'totalReimbursementDone' => $ReimbursementBalance,
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
        ];
    }
}
