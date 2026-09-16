<?php

namespace App\Policies;

use App\Models\MonthlyPayroll;
use App\Models\User;

/**
 * Payroll rebuild — Phase 7.
 *
 * Single source of truth for "can this payslip still be edited/deleted",
 * replacing three separate inline `payment_status !== 'pending'` checks
 * that were previously duplicated across
 * MonthlyPayrollController::edit()/update()/destroy() — any future code
 * path that touches a MonthlyPayroll (a queued job, an API endpoint, a
 * console command) now shares the same rule instead of risking its own,
 * possibly-inconsistent copy.
 */
class MonthlyPayrollPolicy
{
    public function update(User $user, MonthlyPayroll $monthlyPayroll): bool
    {
        return $monthlyPayroll->payment_status === 'pending';
    }

    public function delete(User $user, MonthlyPayroll $monthlyPayroll): bool
    {
        return $monthlyPayroll->payment_status === 'pending';
    }

    /**
     * Payroll Audit Phase 3 — H8. The deliberate, audited path back to
     * 'pending' for a processed/paid payslip that needs correcting — the
     * complement of update()'s rule (a pending payslip is already editable,
     * nothing to reopen). Route-level permission (payroll,manage) is the
     * primary gate; this only encodes the state precondition.
     */
    public function reopen(User $user, MonthlyPayroll $monthlyPayroll): bool
    {
        return $monthlyPayroll->payment_status !== 'pending';
    }
}
