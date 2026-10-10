<?php

namespace App\Services\Dashboard\Cards;

use App\Models\MonthlyPayroll;
use App\Models\User;
use Carbon\Carbon;

/**
 * Dashboard — Payroll card: payslips of one month by payment status + total net pay.
 * Moved out of DashboardController unchanged (code-quality plan, Phase 2).
 */
class PayrollCard
{
    /**
     * Payroll card: payslips of one month by payment status + total net pay.
     * Payroll usually lags, so a month with no payslips yet falls back to the
     * latest month that has some (`fell_back`).
     */
    public function forMonth(Carbon $month): array
    {
        $key = $month->format('Y-m');
        $fellBack = false;

        if (! MonthlyPayroll::where('payroll_month', $key)->exists()) {
            $latest = MonthlyPayroll::max('payroll_month');
            if (! $latest) {
                return ['has' => false, 'month_label' => $month->format('M Y')];
            }
            $key = (string) $latest;
            $fellBack = true;
        }

        $rows = MonthlyPayroll::where('payroll_month', $key)
            ->selectRaw('payment_status, COUNT(*) as slips, SUM(net_payable) as total')
            ->groupBy('payment_status')
            ->get()
            ->toBase() // plain collection: except() below is by status key, not by model id
            ->keyBy('payment_status');
        $slips = fn (string $status) => (int) ($rows[$status]->slips ?? 0);
        $live = $rows->except('cancelled');

        $withSlip = MonthlyPayroll::where('payroll_month', $key)->where('payment_status', '!=', 'cancelled')
            ->distinct()->count('user_id');
        $activeEmployees = User::where('status', 1)->where('role', '!=', 'admin')->count();

        return [
            'has' => true,
            'fell_back' => $fellBack,
            'month_label' => Carbon::parse(strlen($key) === 7 ? $key.'-01' : $key)->format('M Y'),
            'net' => (float) $live->sum('total'),
            'pending' => $slips('pending'),
            'processed' => $slips('processed'),
            'paid' => $slips('paid'),
            'without' => max(0, $activeEmployees - $withSlip),
        ];
    }
}
