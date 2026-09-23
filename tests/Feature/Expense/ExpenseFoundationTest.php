<?php

namespace Tests\Feature\Expense;

use App\Exceptions\ExpenseException;
use App\Models\Expense;
use App\Models\ExpensePayment;
use App\Models\ExpenseType;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserExpenseBalance;
use App\Services\Expense\ExpenseLedgerService;
use App\Services\Expense\ExpenseNumberGenerator;
use App\Services\Expense\ExpensePaymentService;
use App\Services\Expense\ExpenseStatsService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Expense Phase 2 foundation: ledger service, payment service (paid_amount kept
 * in sync), code-generated expense numbers, stats service, reconcile command and
 * the migrated-schema contract.
 *
 * Shared dev DB, rolled back via DatabaseTransactions.
 */
class ExpenseFoundationTest extends TestCase
{
    use DatabaseTransactions;

    private int $tenantId;
    private User $hr;
    private User $alice;
    private int $typeId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantId = (int) DB::table('users')
            ->where('status', '1')->where('role', 'employee')->whereNotNull('tenant_id')
            ->select('tenant_id')->groupBy('tenant_id')
            ->havingRaw('count(*) >= 1')->orderByRaw('count(*) desc')->value('tenant_id');

        $hr = $this->tenantId ? User::withoutGlobalScopes()->where('tenant_id', $this->tenantId)->whereIn('role', ['admin', 'hr'])->where('status', '1')->first() : null;
        $alice = $this->tenantId ? User::withoutGlobalScopes()->where('tenant_id', $this->tenantId)->where('role', 'employee')->where('status', '1')->orderBy('id')->first() : null;
        $typeId = $this->tenantId ? ExpenseType::withoutGlobalScopes()->where('tenant_id', $this->tenantId)->where('status', 1)->value('id') : null;

        if (! $hr || ! $alice || ! $typeId) {
            $this->markTestSkipped('fixture users / expense type missing in the dev DB');
        }

        [$this->hr, $this->alice, $this->typeId] = [$hr, $alice, (int) $typeId];
        app()->instance('current_tenant', Tenant::find($this->tenantId));
    }

    // ---------------------------------------------------------------- helpers

    private function makeExpense(array $overrides = []): Expense
    {
        return Expense::create($overrides + [
            'tenant_id' => $this->tenantId, 'user_id' => $this->alice->id, 'expense_type' => $this->typeId,
            'amount' => 1000, 'date' => now()->subDay()->toDateString(), 'requirement_type' => 'advance',
            'status' => 'approved', 'description' => 'phpunit-foundation',
        ]);
    }

    private function zeroBalance(): void
    {
        UserExpenseBalance::updateOrCreate(['user_id' => $this->alice->id], [
            'tenant_id' => $this->tenantId, 'current_balance' => 0, 'advance_balance' => 0,
            'settlement_balance' => 0, 'reimbursement_balance' => 0,
        ]);
        DB::table('expense_transactions')->where('user_id', $this->alice->id)->delete();
    }

    private function balance(): UserExpenseBalance
    {
        return UserExpenseBalance::where('user_id', $this->alice->id)->firstOrFail();
    }

    private function pay(Expense $expense, string $amount, ?string $key = null, string $type = 'advance'): array
    {
        return app(ExpensePaymentService::class)->record($this->hr, $type, [
            'payment_date' => now()->toDateString(), 'amount' => $amount, 'payment_mode' => 'cash', 'expense_id' => $expense->id,
        ], $key, fn () => true);
    }

    // ---------------------------------------------------------- ledger service

    public function test_ledger_advance_credit_and_reversal_stay_consistent_and_refuse_to_go_negative(): void
    {
        $this->zeroBalance();
        $expense = $this->makeExpense();
        $ledger = app(ExpenseLedgerService::class);

        DB::transaction(function () use ($ledger, $expense) {
            $ledger->creditAdvance($expense, 40000);                 // ₹400
            $ledger->reverseAdvance($expense, 15000, $this->hr->id); // -₹150
        });

        $this->assertEquals(250.00, (float) $this->balance()->current_balance);
        $this->assertEquals(250.00, (float) $this->balance()->advance_balance);

        $rows = DB::table('expense_transactions')->where('expense_id', $expense->id)->orderBy('id')->get();
        $this->assertCount(2, $rows);
        $this->assertEquals([0.0, 400.0], [(float) $rows[0]->balance_before, (float) $rows[0]->balance_after]);
        $this->assertEquals(-150.0, (float) $rows[1]->amount);
        $this->assertEquals([400.0, 250.0], [(float) $rows[1]->balance_before, (float) $rows[1]->balance_after]);

        // Reversing more than the employee still holds is refused, not clamped.
        try {
            DB::transaction(fn () => $ledger->reverseAdvance($expense, 30000, $this->hr->id));
            $this->fail('expected a 409');
        } catch (ExpenseException $e) {
            $this->assertSame(409, $e->httpStatus());
        }
        $this->assertEquals(250.00, (float) $this->balance()->current_balance);
        $this->assertSame(2, DB::table('expense_transactions')->where('expense_id', $expense->id)->count(), 'no ledger row for a refused reversal');
    }

    public function test_ledger_reimbursement_pay_and_reverse(): void
    {
        $this->zeroBalance();
        $expense = $this->makeExpense(['requirement_type' => 'reimbursement']);
        $ledger = app(ExpenseLedgerService::class);

        DB::transaction(function () use ($ledger, $expense) {
            $ledger->payReimbursement($expense, 50000, $this->hr->id, 'phpunit');
            $ledger->reverseReimbursement($expense, 20000, $this->hr->id);
        });

        $b = $this->balance();
        $this->assertEquals(300.00, (float) $b->reimbursement_balance);
        $this->assertEquals(0.00, (float) $b->current_balance, 'reimbursements never touch current_balance');

        $this->expectException(ExpenseException::class);
        DB::transaction(fn () => $ledger->reverseReimbursement($expense, 40000, $this->hr->id));
    }

    public function test_ledger_settlement_debit_needs_enough_balance(): void
    {
        $this->zeroBalance();
        $ledger = app(ExpenseLedgerService::class);
        $funding = $this->makeExpense(['amount' => 500]);
        DB::transaction(fn () => $ledger->creditAdvance($funding, 50000));

        $tooBig = $this->makeExpense(['requirement_type' => 'settlement', 'status' => 'pending', 'amount' => 600]);
        try {
            DB::transaction(fn () => $ledger->debitSettlement($tooBig));
            $this->fail('expected insufficient balance');
        } catch (ExpenseException $e) {
            $this->assertStringContainsString('Insufficient advance balance', $e->getMessage());
        }

        $ok = $this->makeExpense(['requirement_type' => 'settlement', 'status' => 'pending', 'amount' => 120.50]);
        DB::transaction(fn () => $ledger->debitSettlement($ok));

        $b = $this->balance();
        $this->assertEquals(379.50, (float) $b->current_balance);
        $this->assertEquals(120.50, (float) $b->settlement_balance);
    }

    public function test_ledger_rejects_non_positive_amounts_and_creates_a_missing_balance_row(): void
    {
        $expense = $this->makeExpense();
        $ledger = app(ExpenseLedgerService::class);

        foreach ([0, -100] as $bad) {
            try {
                DB::transaction(fn () => $ledger->creditAdvance($expense, $bad));
                $this->fail("cents {$bad} should be refused");
            } catch (ExpenseException $e) {
                $this->assertSame(422, $e->httpStatus());
            }
        }

        UserExpenseBalance::where('user_id', $this->alice->id)->delete();
        $first = DB::transaction(fn () => $ledger->lockedBalance($expense));
        $second = DB::transaction(fn () => $ledger->lockedBalance($expense));

        $this->assertSame($first->id, $second->id, 'get-or-create is idempotent (no duplicate row / unique-key error)');
        $this->assertEquals(0.00, (float) $first->current_balance);
    }

    // --------------------------------------------------------- payment service

    public function test_payments_keep_paid_amount_and_status_in_sync(): void
    {
        $this->zeroBalance();
        $expense = $this->makeExpense(['amount' => 1000]);

        $this->pay($expense, '400');
        $expense->refresh();
        $this->assertEquals(400.00, (float) $expense->paid_amount);
        $this->assertSame('approved', $expense->status);
        $this->assertEquals(600.00, (float) $expense->remaining_amount);
        $this->assertFalse($expense->is_fully_paid);

        $this->pay($expense, '600');
        $expense->refresh();
        $this->assertEquals(1000.00, (float) $expense->paid_amount);
        $this->assertSame('complete', $expense->status);
        $this->assertTrue($expense->is_fully_paid);

        $payments = ExpensePayment::where('expense_id', $expense->id)->orderBy('id')->get();
        $service = app(ExpensePaymentService::class);

        // A posted payment's AMOUNT cannot be edited (void + re-post instead) — but its details can.
        try {
            $service->update($this->hr, $payments[0]->id, ['payment_date' => now()->toDateString(), 'amount' => '300', 'payment_mode' => 'upi'], fn () => true);
            $this->fail('changing the amount must be refused');
        } catch (ExpenseException $e) {
            $this->assertStringContainsString('cannot be edited', $e->getMessage());
        }
        $service->update($this->hr, $payments[0]->id, ['payment_date' => now()->toDateString(), 'amount' => '400', 'payment_mode' => 'upi', 'reference_number' => 'UTR1'], fn () => true);
        $this->assertSame('upi', $payments[0]->fresh()->payment_mode);
        $this->assertEquals(1000.00, (float) $expense->fresh()->paid_amount, 'a details-only edit changes no money');

        // Voiding the first payment reopens the expense and shrinks paid_amount + the balance.
        $service->voidPayment($this->hr, $payments[0]->id, 'entered twice by mistake', fn () => true);
        $expense->refresh();
        $this->assertEquals(600.00, (float) $expense->paid_amount);
        $this->assertSame('approved', $expense->status);
        $this->assertEquals(600.00, (float) $this->balance()->current_balance);
        $this->assertSame('voided', $payments[0]->fresh()->status, 'the row is kept, never deleted');
        $this->assertSame('entered twice by mistake', $payments[0]->fresh()->void_reason);

        // Voiding the rest zeroes it again; a voided payment cannot be voided twice.
        $service->voidPayment($this->hr, $payments[1]->id, 'cancelled', fn () => true);
        $expense->refresh();
        $this->assertEquals(0.00, (float) $expense->paid_amount);
        $this->assertEquals(0.00, (float) $this->balance()->current_balance);

        $this->expectException(ExpenseException::class);
        $this->expectExceptionMessage('already voided');
        $service->voidPayment($this->hr, $payments[1]->id, 'again', fn () => true);
    }

    public function test_a_stale_paid_amount_cache_cannot_allow_an_overpayment_and_self_heals(): void
    {
        $this->zeroBalance();
        $expense = $this->makeExpense(['amount' => 1000]);
        $this->pay($expense, '400');

        // Simulate cache drift: the column says nothing was paid.
        DB::table('expenses')->where('id', $expense->id)->update(['paid_amount' => 0]);

        // 700 > the REAL remaining 600 — must be refused even though the cache says 1000 remains.
        try {
            $this->pay($expense, '700');
            $this->fail('overpayment must be refused');
        } catch (ExpenseException $e) {
            $this->assertStringContainsString('exceeds remaining balance', $e->getMessage());
            $this->assertStringContainsString('600.00', $e->getMessage());
        }

        $this->pay($expense, '600'); // legitimate — and rewrites the cache from the payments table
        $this->assertEquals(1000.00, (float) $expense->fresh()->paid_amount);
    }

    public function test_payable_scope_lists_only_approved_expenses_with_something_left_to_pay(): void
    {
        $this->zeroBalance();
        $open = $this->makeExpense(['amount' => 500]);
        $partial = $this->makeExpense(['amount' => 500]);
        $paid = $this->makeExpense(['amount' => 500]);
        $pending = $this->makeExpense(['amount' => 500, 'status' => 'pending']);

        $this->pay($partial, '200');
        $this->pay($paid, '500');

        $ids = Expense::where('description', 'phpunit-foundation')->payable()->pluck('id')->all();

        $this->assertContains($open->id, $ids);
        $this->assertContains($partial->id, $ids);
        $this->assertNotContains($paid->id, $ids, 'fully paid');
        $this->assertNotContains($pending->id, $ids, 'not approved');
    }

    public function test_repeating_an_idempotency_key_records_one_payment(): void
    {
        $this->zeroBalance();
        $expense = $this->makeExpense(['amount' => 1000]);

        $first = $this->pay($expense, '250', 'phpunit-key-A');
        $second = $this->pay($expense, '250', 'phpunit-key-A');

        $this->assertFalse($first['duplicate']);
        $this->assertTrue($second['duplicate']);
        $this->assertSame($first['payment']->id, $second['payment']->id);
        $this->assertSame(1, ExpensePayment::where('expense_id', $expense->id)->count());
        $this->assertEquals(250.00, (float) $expense->fresh()->paid_amount);
    }

    public function test_direct_payment_creates_a_numbered_complete_advance_and_credits_the_balance(): void
    {
        $this->zeroBalance();

        $result = app(ExpensePaymentService::class)->record($this->hr, 'direct', [
            'payment_date' => now()->toDateString(), 'amount' => '750.25', 'payment_mode' => 'bank_transfer',
            'direct_user_id' => $this->alice->id, 'remarks' => 'phpunit',
        ], null, fn () => true);

        $expense = $result['expense']->fresh();
        $this->assertSame(ExpenseNumberGenerator::for($expense->id), $expense->expense_number);
        $this->assertSame('complete', $expense->status);
        $this->assertEquals(750.25, (float) $expense->paid_amount);
        $this->assertEquals(750.25, (float) $this->balance()->current_balance);
    }

    public function test_payment_service_applies_the_owner_scope_predicate(): void
    {
        $expense = $this->makeExpense(['amount' => 100]);

        $this->expectException(ExpenseException::class);
        $this->expectExceptionMessage('not authorized');
        app(ExpensePaymentService::class)->record($this->hr, 'advance', [
            'payment_date' => now()->toDateString(), 'amount' => '10', 'payment_mode' => 'cash', 'expense_id' => $expense->id,
        ], null, fn () => false);
    }

    // ----------------------------------------------------------- expense number

    public function test_expense_numbers_come_from_code_not_a_trigger(): void
    {
        $a = $this->makeExpense();
        $b = $this->makeExpense();

        $this->assertSame('EXP-' . str_pad((string) $a->id, 6, '0', STR_PAD_LEFT), $a->expense_number);
        $this->assertSame(ExpenseNumberGenerator::for($b->id), $b->fresh()->expense_number);
        $this->assertNotSame($a->expense_number, $b->expense_number);
        $this->assertEmpty(DB::select("SHOW TRIGGERS WHERE `Table` = 'expenses'"), 'the generate_expense_number trigger must be gone');
    }

    public function test_an_explicit_number_is_respected_and_duplicates_are_refused_per_tenant(): void
    {
        $a = $this->makeExpense(['expense_number' => 'PHPUNIT-1']);
        $this->assertSame('PHPUNIT-1', $a->fresh()->expense_number);

        $this->expectException(QueryException::class);
        $this->makeExpense(['expense_number' => 'PHPUNIT-1']);
    }

    // -------------------------------------------------------------------- stats

    private function statsFixture(): void
    {
        foreach ([
            ['advance', 'pending', 100], ['advance', 'pending', 100], ['advance', 'approved', 50],
            ['settlement', 'pending', 10], ['settlement', 'approved', 20], ['settlement', 'cancelled', 5],
            ['reimbursement', 'approved', 30], ['reimbursement', 'complete', 40], ['reimbursement', 'cancelled', 7],
        ] as [$type, $status, $amount]) {
            $this->makeExpense(['requirement_type' => $type, 'status' => $status, 'amount' => $amount, 'description' => 'phpunit-stats']);
        }
    }

    public function test_stats_use_settlement_plus_reimbursement_for_the_combined_cards(): void
    {
        $this->statsFixture();
        $stats = app(ExpenseStatsService::class);

        $v = $stats->viewData($stats->matrix(Expense::where('description', 'phpunit-stats')));

        // Per type
        $this->assertSame([3, 250.0], [$v['totalAdvanceCount'], $v['totalAdvanceAmount']]);
        $this->assertSame([3, 35.0], [$v['totalSettlementCount'], $v['totalSettlementAmount']]);
        $this->assertSame([3, 77.0], [$v['totalReimbursementCount'], $v['totalReimbursementAmount']]);
        $this->assertSame([2, 200.0], [$v['pendingAdvanceCount'], $v['pendingAdvanceAmount']]);

        // Combined cards = settlement + reimbursement ONLY (advances excluded)
        $this->assertSame([6, 112.0], [$v['totalExpenses'], $v['totalAmount']]);
        $this->assertSame([1, 10.0], [$v['pendingCount'], $v['pendingAmount']]);
        $this->assertSame([2, 50.0], [$v['approvedCount'], $v['approvedAmount']]);
        $this->assertSame([1, 40.0], [$v['completedCount'], $v['completedAmount']]);
        $this->assertSame([2, 12.0], [$v['cancelledCount'], $v['cancelledAmount']]);
    }

    public function test_stats_match_the_old_php_collection_logic_for_every_per_type_status_card(): void
    {
        $this->statsFixture();
        $stats = app(ExpenseStatsService::class);
        $all = Expense::where('description', 'phpunit-stats')->get();

        $v = $stats->viewData($stats->matrix(Expense::where('description', 'phpunit-stats')));

        $checked = 0;
        foreach (ExpenseStatsService::TYPES as $label => $type) {
            $ofType = $all->where('requirement_type', $type);
            $this->assertEquals($ofType->count(), $v["total{$label}Count"]);
            $this->assertEquals((float) $ofType->sum('amount'), $v["total{$label}Amount"]);

            foreach (ExpenseStatsService::STATUSES as $sLabel => $status) {
                $subset = $ofType->where('status', $status);
                $this->assertEquals($subset->count(), $v["{$sLabel}{$label}Count"], "{$sLabel}{$label}Count");
                $this->assertEquals((float) $subset->sum('amount'), $v["{$sLabel}{$label}Amount"], "{$sLabel}{$label}Amount");
                $checked += 2;
            }
        }

        $this->assertSame(24, $checked);
    }

    public function test_stats_follow_the_filters_and_survive_the_joins_ordering_and_eager_loads_the_screens_use(): void
    {
        $this->statsFixture();
        $stats = app(ExpenseStatsService::class);

        // Exactly the builder shape ExpenseController::index() hands over.
        $query = Expense::where('expenses.user_id', $this->alice->id)->where('expenses.description', 'phpunit-stats')
            ->with(['expenseType', 'project', 'payments'])
            ->leftJoin('expense_types', 'expenses.expense_type', '=', 'expense_types.id')
            ->leftJoin('projects', 'expenses.project_id', '=', 'projects.id')
            ->select(['expenses.*', 'expense_types.name as expense_type_name', 'projects.name as project_name'])
            ->where('expenses.status', 'pending')
            ->orderBy('expenses.created_at', 'desc');
        $query->get(); // the controller executes the same builder before summarising it

        $v = $stats->viewData($stats->matrix($query));

        $this->assertSame(2, $v['pendingAdvanceCount']);
        $this->assertSame(0, $v['approvedAdvanceCount'], 'the status filter is respected');
        $this->assertSame(1, $v['totalExpenses'], 'only the pending settlement counts as spend');
    }

    public function test_my_expenses_page_can_be_filtered_by_status_and_the_cards_reflect_the_filter(): void
    {
        // Regression: a bare `status` in index()'s WHERE was ambiguous against the joined
        // expense_types/projects tables, so every status filter (and every status card link)
        // redirected with "Something went wrong".
        $this->statsFixture();

        foreach (['pending', 'approved', 'complete', 'cancelled'] as $status) {
            $this->actingAs($this->alice)->withSession(['tenant_id' => $this->tenantId])
                ->get(route('expense.index', ['status' => $status]))
                ->assertOk();
        }

        $this->actingAs($this->alice)->withSession(['tenant_id' => $this->tenantId])
            ->get(route('expense.index', ['status' => 'pending', 'requirement_type' => 'advance']))
            ->assertOk()
            ->assertViewHas('pendingAdvanceCount', fn ($n) => $n >= 2)
            ->assertViewHas('approvedAdvanceCount', 0);
    }

    public function test_api_summary_has_the_per_type_and_by_status_shape(): void
    {
        $this->statsFixture();
        $stats = app(ExpenseStatsService::class);

        $s = $stats->apiSummary($stats->matrix(Expense::where('description', 'phpunit-stats')));

        $this->assertSame(['count' => 2, 'amount' => 200.0], $s['advance']['pending']);
        $this->assertSame(['total_count' => 3, 'total_amount' => 250.0], ['total_count' => $s['advance']['total_count'], 'total_amount' => $s['advance']['total_amount']]);
        $this->assertSame(['count' => 6, 'amount' => 112.0], $s['spend']);
        $this->assertSame(['count' => 2, 'amount' => 12.0], $s['by_status']['cancelled']);
    }

    // ---------------------------------------------------------------- reconcile

    public function test_reconcile_detects_and_repairs_a_stale_paid_amount(): void
    {
        $this->zeroBalance();
        $expense = $this->makeExpense(['amount' => 1000]);
        $this->pay($expense, '400');
        DB::table('expenses')->where('id', $expense->id)->update(['paid_amount' => 999]);

        $this->artisan('expense:reconcile-balances', ['--tenant' => $this->tenantId])
            ->expectsOutputToContain('stale paid_amount')
            ->assertFailed();
        $this->assertEquals(999.00, (float) $expense->fresh()->paid_amount, 'report mode changes nothing');

        $this->artisan('expense:reconcile-balances', ['--tenant' => $this->tenantId, '--fix' => true]);
        $this->assertEquals(400.00, (float) $expense->fresh()->paid_amount);
    }

    public function test_reconcile_detects_balance_drift_and_only_overwrites_with_the_explicit_flag(): void
    {
        $this->zeroBalance();
        $expense = $this->makeExpense(['amount' => 1000]);
        $this->pay($expense, '400');                                       // ledger + balance agree at 400
        UserExpenseBalance::where('user_id', $this->alice->id)->update(['advance_balance' => 999, 'current_balance' => 999]);

        $this->artisan('expense:reconcile-balances', ['--tenant' => $this->tenantId, '--fix' => true])
            ->expectsOutputToContain('differ from their ledger');
        $this->assertEquals(999.00, (float) $this->balance()->current_balance, '--fix alone must NOT touch balances');

        $this->artisan('expense:reconcile-balances', ['--tenant' => $this->tenantId, '--fix-balances' => true]);
        $b = $this->balance();
        $this->assertEquals(400.00, (float) $b->current_balance);
        $this->assertEquals(400.00, (float) $b->advance_balance);
    }

    public function test_reconcile_flags_overpaid_and_status_mismatches_without_fixing_them(): void
    {
        $this->zeroBalance();
        $overpaid = $this->makeExpense(['amount' => 100]);
        ExpensePayment::create(['tenant_id' => $this->tenantId, 'expense_id' => $overpaid->id, 'payment_date' => now()->toDateString(),
            'amount' => 150, 'payment_mode' => 'cash', 'paid_by' => $this->hr->id]);

        $this->artisan('expense:reconcile-balances', ['--tenant' => $this->tenantId, '--fix' => true])
            ->expectsOutputToContain('OVERPAID');

        $this->assertSame('approved', $overpaid->fresh()->status, 'manual-review items are never auto-fixed');
    }

    // ------------------------------------------------------------ schema contract

    public function test_the_migrated_schema_matches_what_the_code_relies_on(): void
    {
        $cols = collect(Schema::getColumns('expenses'))->keyBy('name');

        $this->assertSame('decimal(15,2)', $cols['amount']['type']);
        $this->assertSame('date', $cols['date']['type_name']);
        $this->assertSame('decimal(15,2)', $cols['paid_amount']['type']);

        $ddl = (string) ((array) DB::selectOne('SHOW CREATE TABLE expenses'))['Create Table'];
        $this->assertStringContainsString('chk_expenses_amount_positive', $ddl);
        $this->assertTrue(Schema::hasIndex('expenses', 'expenses_tenant_number_unique'));

        foreach (['expense_payments' => 'expense_id', 'expense_status_histories' => 'expense_id'] as $table => $column) {
            $indexed = collect(Schema::getIndexes($table))->contains(fn ($i) => ($i['columns'][0] ?? null) === $column);
            $this->assertTrue($indexed, "{$table}.{$column} must be indexed");
        }

        $this->assertTrue(collect(Schema::getIndexes('expense_attachments'))->contains(fn ($i) => ! empty($i['primary'])), 'expense_attachments needs a primary key');
    }
}
