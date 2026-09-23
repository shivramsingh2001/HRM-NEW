<?php

namespace Tests\Feature\Expense;

use App\Models\Expense;
use App\Services\Expense\ExpenseLedgerService;
use LogicException;
use Tests\TestCase;

/**
 * Deliberately WITHOUT DatabaseTransactions: the guard checks that the ledger is
 * called inside a transaction, which a rolled-back test transaction would mask.
 * Nothing is written — the assertion fires before any query.
 */
class ExpenseLedgerTransactionGuardTest extends TestCase
{
    public function test_every_ledger_write_refuses_to_run_outside_a_transaction(): void
    {
        $this->assertSame(0, \DB::transactionLevel(), 'precondition: no ambient transaction in this test');

        $expense = new Expense(['user_id' => 1, 'amount' => 10]);
        $expense->id = 1;
        $ledger = app(ExpenseLedgerService::class);

        foreach ([
            fn () => $ledger->creditAdvance($expense, 1000),
            fn () => $ledger->reverseAdvance($expense, 1000, 1),
            fn () => $ledger->payReimbursement($expense, 1000, 1),
            fn () => $ledger->reverseReimbursement($expense, 1000, 1),
            fn () => $ledger->debitSettlement($expense),
            fn () => $ledger->lockedBalance($expense),
        ] as $i => $call) {
            try {
                $call();
                $this->fail("ledger call #{$i} ran outside a transaction");
            } catch (LogicException $e) {
                $this->assertStringContainsString('inside a DB transaction', $e->getMessage());
            }
        }
    }
}
