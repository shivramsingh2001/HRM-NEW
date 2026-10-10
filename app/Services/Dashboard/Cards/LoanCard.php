<?php

namespace App\Services\Dashboard\Cards;

use Carbon\Carbon;

/**
 * Dashboard — Loans card: outstanding loans, salary advances recovered this month, requests waiting.
 * Moved out of DashboardController unchanged (code-quality plan, Phase 2).
 */
class LoanCard
{
    /** Loans card: what is still owed, salary advances recovered from this month's salary, requests waiting. */
    public function forMonth(Carbon $month): array
    {
        $active = \App\Models\Loan::where('status', 'active');
        $advances = \App\Models\Loan::where('loan_kind', 'salary_advance')
            ->where('advance_month', $month->format('Y-m'))
            ->whereIn('status', ['approved', 'active']);

        return [
            'month_label' => $month->format('M Y'),
            'active_count' => (clone $active)->count(),
            'outstanding' => (float) (clone $active)->sum('remaining_amount'),
            'advance_count' => (clone $advances)->count(),
            'advance_amount' => (float) (clone $advances)->sum('amount'),
            'pending' => \App\Models\Loan::where('status', 'pending')->count(),
        ];
    }
}
