<?php

namespace App\Services\Expense;

use App\Exceptions\ExpenseException;
use App\Exceptions\InsufficientBalanceException;
use App\Models\Expense;
use App\Models\ExpenseTransaction;
use App\Models\UserExpenseBalance;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * The ONLY code allowed to change `user_expense_balances` or write
 * `expense_transactions` (the employee's expense ledger).
 *
 * Rules enforced here so no caller can forget them:
 *  - Every method must run inside a DB transaction (asserted) — the balance row
 *    is locked FOR UPDATE and must stay locked until the caller commits.
 *  - The balance row is created race-safely (insertOrIgnore + re-select) and then
 *    locked, so two first-ever payments for one employee can't collide on the
 *    unique(user_id) key nor both read a stale balance.
 *  - All arithmetic is in integer paise (App\Support\Money) and amounts are passed
 *    as paise, so callers can't sneak a float in.
 *  - A reversal that would push a balance below zero is refused (HTTP 409) rather
 *    than clamped: clamping hid ledger drift and made balance_after != before - amount.
 *  - The tenant always comes from the expense, never session()/request state, so
 *    this is safe from queues, commands and the mobile API.
 *
 * Balance model (unchanged): current = advance - settlement; reimbursement_balance
 * is a separate running total of reimbursements paid out and is NOT part of current.
 */
class ExpenseLedgerService
{
    /** Race-safe "get or create, then lock" of an employee's balance row. */
    public function lockedBalance(Expense $expense): UserExpenseBalance
    {
        $this->assertInTransaction();

        DB::table('user_expense_balances')->insertOrIgnore([
            'tenant_id' => $expense->tenant_id,
            'user_id' => $expense->user_id,
            'current_balance' => 0,
            'advance_balance' => 0,
            'settlement_balance' => 0,
            'reimbursement_balance' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return UserExpenseBalance::where('user_id', $expense->user_id)->lockForUpdate()->firstOrFail();
    }

    /** An advance payment reaches the employee: current + advance go up. */
    public function creditAdvance(Expense $expense, int $cents, ?string $remarks = null): void
    {
        $this->assertPositive($cents);
        $balance = $this->lockedBalance($expense);

        $before = Money::toCents($balance->current_balance);
        $after = $before + $cents;

        $balance->update([
            'current_balance' => Money::fromCents($after),
            'advance_balance' => Money::fromCents(Money::toCents($balance->advance_balance) + $cents),
        ]);

        $this->record($expense, 'advance_credited', $cents, $before, $after,
            $remarks ?: 'Advance payment of ₹' . Money::format($cents) . ' credited');
    }

    /** Undo (part of) an advance payment. Refused if the employee already spent it. */
    public function reverseAdvance(Expense $expense, int $cents, int $actorId): void
    {
        $this->assertPositive($cents);
        $balance = $this->lockedBalance($expense);

        $before = Money::toCents($balance->current_balance);
        $after = $before - $cents;

        if ($after < 0) {
            throw new ExpenseException(
                'This payment cannot be reduced or deleted because the employee has already used part of this advance '
                . '(current balance ₹' . Money::format($before) . ').',
                409
            );
        }

        $balance->update([
            'current_balance' => Money::fromCents($after),
            'advance_balance' => Money::fromCents(Money::toCents($balance->advance_balance) - $cents),
        ]);

        $this->record($expense, 'advance_credited', -$cents, $before, $after,
            'Advance payment of ₹' . Money::format($cents) . ' reversed | By user_id: ' . $actorId);
    }

    /** A reimbursement is paid out: only reimbursement_balance moves. */
    public function payReimbursement(Expense $expense, int $cents, int $actorId, ?string $remarks = null): void
    {
        $this->assertPositive($cents);
        $balance = $this->lockedBalance($expense);

        $before = Money::toCents($balance->reimbursement_balance);
        $after = $before + $cents;

        $balance->update(['reimbursement_balance' => Money::fromCents($after)]);

        $this->record($expense, 'reimbursement_paid', $cents, $before, $after,
            ($remarks ?: 'Reimbursement paid') . ' | Processed by user_id: ' . $actorId);
    }

    /** Undo (part of) a reimbursement payment. Refused rather than clamped at zero. */
    public function reverseReimbursement(Expense $expense, int $cents, int $actorId): void
    {
        $this->assertPositive($cents);
        $balance = $this->lockedBalance($expense);

        $before = Money::toCents($balance->reimbursement_balance);
        $after = $before - $cents;

        if ($after < 0) {
            throw new ExpenseException(
                'This reimbursement payment cannot be reversed: the recorded reimbursement total is lower than the amount being reversed. '
                . 'Please contact support to reconcile the employee balance.',
                409
            );
        }

        $balance->update(['reimbursement_balance' => Money::fromCents($after)]);

        $this->record($expense, 'reimbursement_paid', -$cents, $before, $after,
            'Reimbursement of ₹' . Money::format($cents) . ' reversed | By user_id: ' . $actorId);
    }

    /** The employee's spendable advance balance (paise), read under the row lock. */
    public function availableCents(Expense $expense): int
    {
        return Money::toCents($this->lockedBalance($expense)->current_balance);
    }

    /**
     * An approved settlement spends the employee's advance: current down, settlement up.
     *
     * @param  int|null  $cents  amount to take from the advance; defaults to the whole settlement. A smaller
     *                           figure is used when the advance only covers PART of it (the rest becomes a
     *                           reimbursement — see ExpenseService::approve()).
     *
     * @throws InsufficientBalanceException when the advance balance is below the amount asked for
     */
    public function debitSettlement(Expense $expense, ?string $remarks = null, ?int $cents = null): void
    {
        $cents ??= Money::toCents($expense->amount);
        $this->assertPositive($cents);
        $balance = $this->lockedBalance($expense);

        $available = Money::toCents($balance->current_balance);

        if ($available < $cents) {
            throw new InsufficientBalanceException($available, $cents);
        }

        $after = $available - $cents;

        $balance->update([
            'current_balance' => Money::fromCents($after),
            'settlement_balance' => Money::fromCents(Money::toCents($balance->settlement_balance) + $cents),
        ]);

        $this->record($expense, 'settlement_debited', $cents, $available, $after,
            $remarks ?: 'Settlement approved and balance deducted');
    }

    // ------------------------------------------------------------------

    private function record(Expense $expense, string $type, int $cents, int $beforeCents, int $afterCents, string $description): void
    {
        ExpenseTransaction::create([
            'tenant_id' => $expense->tenant_id,
            'expense_id' => $expense->id,
            'user_id' => $expense->user_id,
            'transaction_type' => $type,
            'amount' => Money::fromCents($cents),
            'balance_before' => Money::fromCents($beforeCents),
            'balance_after' => Money::fromCents($afterCents),
            'description' => $description,
        ]);
    }

    private function assertPositive(int $cents): void
    {
        if ($cents <= 0) {
            throw new ExpenseException('Amount must be greater than zero.', 422);
        }
    }

    private function assertInTransaction(): void
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException(
                'ExpenseLedgerService must be called inside a DB transaction: the balance row lock is only held until commit.'
            );
        }
    }
}
