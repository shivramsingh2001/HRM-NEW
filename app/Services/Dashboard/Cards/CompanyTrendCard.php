<?php

namespace App\Services\Dashboard\Cards;

use App\Models\Expense;
use App\Models\LoanRepayment;
use App\Models\MonthlyPayroll;
use Carbon\Carbon;

/**
 * Dashboard — Six-month company overview trend.
 * Moved out of DashboardController unchanged (code-quality plan, Phase 2).
 */
class CompanyTrendCard
{
    /**
     * Company overview trend for the last $months months: monthly expense,
     * monthly payroll cost, cumulative headcount, attendance rate %, and
     * tasks completed. Used by the "Company Overview Trend" chart.
     */
    public function build($months = 6)
    {
        $monthDates = collect(range($months - 1, 0))->map(function ($i) {
            return Carbon::now()->subMonths($i)->startOfMonth();
        })->values();

        $monthKeys = $monthDates->map(fn ($m) => $m->format('Y-m'));
        $labels = $monthDates->map(fn ($m) => $m->format('M Y'));

        $rangeStart = $monthDates->first()->copy()->startOfMonth();
        $rangeEnd = $monthDates->last()->copy()->endOfMonth();

        $expenseByMonth = Expense::whereBetween('date', [$rangeStart->format('Y-m-d'), $rangeEnd->format('Y-m-d')])
            ->whereIn('status', ['approved', 'complete'])
            ->selectRaw("DATE_FORMAT(date, '%Y-%m') as ym, SUM(amount) as total")
            ->groupBy('ym')
            ->pluck('total', 'ym');

        $payrollByMonth = MonthlyPayroll::whereIn('payroll_month', $monthKeys->all())
            ->selectRaw('payroll_month, SUM(net_payable) as total')
            ->groupBy('payroll_month')
            ->pluck('total', 'payroll_month');

        // Loan repayments / other money-related transactions collected that month
        $loanByMonth = LoanRepayment::whereIn('salary_month', $monthKeys->all())
            ->selectRaw('salary_month, SUM(paid_amount) as total')
            ->groupBy('salary_month')
            ->pluck('total', 'salary_month');

        $expense = [];
        $payroll = [];
        $loanOther = [];

        foreach ($monthDates as $monthDate) {
            $key = $monthDate->format('Y-m');

            $expense[] = round((float) ($expenseByMonth[$key] ?? 0), 2);
            $payroll[] = round((float) ($payrollByMonth[$key] ?? 0), 2);
            $loanOther[] = round((float) ($loanByMonth[$key] ?? 0), 2);
        }

        return [
            'labels' => $labels->all(),
            'expense' => $expense,
            'payroll' => $payroll,
            'loan_other' => $loanOther,
        ];
    }
}
