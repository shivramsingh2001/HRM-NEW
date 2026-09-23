<?php

namespace Tests\Feature\Expense;

use App\Models\Expense;
use App\Models\ExpenseBudget;
use App\Models\ExpenseType;
use App\Models\Tenant;
use App\Services\Expense\ExpenseBudgetService;
use App\Services\FeatureService;
use App\Models\User;
use PHPUnit\Framework\Attributes\Group;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * REAL concurrency proof. Each contender is a separate OS process (its own DB
 * connection) released by a shared wall-clock barrier, so the requests genuinely
 * overlap — unlike the sequential "call it twice" tests, which cannot detect a
 * missing lock.
 *
 * Because separate processes must see the data, fixtures are COMMITTED (no
 * DatabaseTransactions) and removed in tearDown, and the employee's real balance
 * row is snapshotted and restored. Slower than the rest (each process boots the
 * framework) — run on its own with:  php artisan test --group=concurrency
 */
#[Group('concurrency')]
class ExpenseConcurrencyTest extends TestCase
{
    private const MARK = 'phpunit-parallel';

    private int $tenantId;
    private User $hr;
    private User $alice;
    private int $typeId;
    private ?array $balanceSnapshot = null;
    private ?array $sequenceSnapshot = null;
    private array $expenseIds = [];
    private array $budgetIds = [];
    private array $extraTypeIds = [];
    private array $overrideKeys = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantId = (int) DB::table('users')->where('status', '1')->where('role', 'employee')->whereNotNull('tenant_id')
            ->select('tenant_id')->groupBy('tenant_id')->orderByRaw('count(*) desc')->value('tenant_id');

        $hr = $this->tenantId ? User::withoutGlobalScopes()->where('tenant_id', $this->tenantId)->whereIn('role', ['admin', 'hr'])->where('status', '1')->first() : null;
        $alice = $this->tenantId ? User::withoutGlobalScopes()->where('tenant_id', $this->tenantId)->where('role', 'employee')->where('status', '1')->orderBy('id')->first() : null;
        $typeId = $this->tenantId ? ExpenseType::withoutGlobalScopes()->where('tenant_id', $this->tenantId)->where('status', 1)->value('id') : null;

        if (! $hr || ! $alice || ! $typeId) {
            $this->markTestSkipped('fixture users / expense type missing in the dev DB');
        }

        [$this->hr, $this->alice, $this->typeId] = [$hr, $alice, (int) $typeId];
        app()->instance('current_tenant', Tenant::find($this->tenantId));

        $existing = DB::table('user_expense_balances')->where('user_id', $this->alice->id)->first();
        $this->balanceSnapshot = $existing ? (array) $existing : null;

        $seq = DB::table('expense_voucher_sequences')->where('tenant_id', $this->tenantId)->first();
        $this->sequenceSnapshot = $seq ? (array) $seq : null;
    }

    protected function tearDown(): void
    {
        if (isset($this->alice)) {
            $ids = $this->expenseIds;

            if ($ids) {
                $batchIds = DB::table('expense_payments')->whereIn('expense_id', $ids)->whereNotNull('batch_id')->pluck('batch_id')->unique()->all();

                $auditIds = DB::table('audit_logs')->where(fn ($q) => $q
                    ->where(fn ($x) => $x->where('entity_type', 'Expense')->whereIn('entity_id', $ids))
                    ->orWhere(fn ($x) => $x->where('entity_type', 'ExpensePaymentBatch')->whereIn('entity_id', $batchIds)))->pluck('id');
                DB::table('sa_audit_signatures')->whereIn('audit_log_id', $auditIds)->delete();
                DB::table('audit_logs')->whereIn('id', $auditIds)->delete();
                DB::table('expense_transactions')->whereIn('expense_id', $ids)->delete();
                DB::table('expense_payments')->whereIn('expense_id', $ids)->delete();
                DB::table('expense_payment_batches')->whereIn('id', $batchIds)->delete();
                DB::table('expense_status_histories')->whereIn('expense_id', $ids)->delete();
                DB::table('expenses')->whereIn('id', $ids)->delete();
            }

            DB::table('expense_payroll_links')->whereIn('expense_id', $this->expenseIds)->delete();
            if ($this->overrideKeys) {
                DB::table('tenant_feature_overrides')->where('tenant_id', $this->tenantId)->whereIn('feature_key', $this->overrideKeys)->delete();
            }

            // budget test fixtures (committed, because the workers run in other processes)
            DB::table('expense_budgets')->whereIn('id', $this->budgetIds)->delete();
            DB::table('expense_types')->whereIn('id', $this->extraTypeIds)->delete();

            // The voucher counter is tenant-wide state: put it back exactly as it was.
            DB::table('expense_voucher_sequences')->where('tenant_id', $this->tenantId)->delete();
            if ($this->sequenceSnapshot) {
                DB::table('expense_voucher_sequences')->insert($this->sequenceSnapshot);
            }

            DB::table('user_expense_balances')->where('user_id', $this->alice->id)->delete();
            if ($this->balanceSnapshot) {
                DB::table('user_expense_balances')->insert($this->balanceSnapshot);
            }
        }

        parent::tearDown();
    }

    // ---------------------------------------------------------------- helpers

    private function setBalance(float $current, float $advance): void
    {
        DB::table('user_expense_balances')->where('user_id', $this->alice->id)->delete();
        DB::table('user_expense_balances')->insert([
            'tenant_id' => $this->tenantId, 'user_id' => $this->alice->id,
            'current_balance' => $current, 'advance_balance' => $advance, 'settlement_balance' => 0, 'reimbursement_balance' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function committedExpense(array $overrides = []): Expense
    {
        $expense = Expense::create($overrides + [
            'tenant_id' => $this->tenantId, 'user_id' => $this->alice->id, 'expense_type' => $this->typeId,
            'amount' => 1000, 'date' => now()->subDay()->toDateString(), 'requirement_type' => 'advance',
            'status' => 'approved', 'description' => self::MARK,
        ]);
        $this->expenseIds[] = $expense->id;

        return $expense;
    }

    /**
     * Launch one process per payload, all released at the same instant.
     *
     * @return array<int, array> decoded result of each contender
     */
    private function race(array $payloads): array
    {
        $script = base_path('tests/Support/expense_concurrency_worker.php');
        $startAt = microtime(true) + 9.0; // generous: every process must finish booting before the barrier

        $processes = [];
        foreach ($payloads as $i => $payload) {
            $p = new Process([PHP_BINARY, $script, json_encode($payload + [
                'tenant_id' => $this->tenantId, 'actor_id' => $this->hr->id, 'start_at' => $startAt,
            ])], base_path());
            $p->setTimeout(60);
            $p->start();
            $processes[$i] = $p;
        }

        $results = [];
        foreach ($processes as $i => $p) {
            $p->wait();
            $this->assertMatchesRegularExpression('/@@RESULT@@(.+)/', $p->getOutput(), "worker {$i} produced no result. stderr: " . $p->getErrorOutput() . ' stdout: ' . $p->getOutput());
            preg_match('/@@RESULT@@(.+)/', $p->getOutput(), $m);
            $results[$i] = json_decode($m[1], true);
            $this->assertArrayNotHasKey('fatal', $results[$i], 'worker crashed: ' . json_encode($results[$i]));
        }

        // Prove the race was real: every contender fired within a small window.
        $fired = array_column($results, 'fired_at');
        $this->assertLessThan(0.75, max($fired) - min($fired), 'contenders did not fire together — the test would prove nothing');

        return $results;
    }

    // -------------------------------------------------------------- scenarios

    public function test_four_simultaneous_approvals_of_one_settlement_deduct_the_balance_exactly_once(): void
    {
        $this->setBalance(500, 500);
        $settlement = $this->committedExpense(['requirement_type' => 'settlement', 'status' => 'pending', 'amount' => 100]);

        $results = $this->race(array_fill(0, 4, ['op' => 'approve', 'expense_id' => $settlement->id]));

        $ok = array_filter($results, fn ($r) => $r['ok']);
        $this->assertCount(1, $ok, 'exactly one approval may win: ' . json_encode($results));
        foreach (array_filter($results, fn ($r) => ! $r['ok']) as $loser) {
            $this->assertSame('This expense has already been processed.', $loser['message']);
        }

        $b = DB::table('user_expense_balances')->where('user_id', $this->alice->id)->first();
        $this->assertEquals(400.00, (float) $b->current_balance, 'deducted once, not 4 times');
        $this->assertEquals(100.00, (float) $b->settlement_balance);
        $this->assertSame(1, DB::table('expense_transactions')->where('expense_id', $settlement->id)->count());
        $this->assertSame('approved', DB::table('expenses')->where('id', $settlement->id)->value('status'));
    }

    public function test_simultaneous_approvals_cannot_both_squeeze_under_one_block_budget(): void
    {
        // A category of its own, so no real company spend leaks into the cap.
        $type = ExpenseType::create(['tenant_id' => $this->tenantId, 'name' => 'phpunit-parallel-budget-' . uniqid(), 'status' => 1]);
        $this->extraTypeIds[] = $type->id;

        $budget = ExpenseBudget::create([
            'tenant_id' => $this->tenantId, 'fiscal_year' => app(ExpenseBudgetService::class)->fiscalYearFor(now()->subDay()),
            'expense_type_id' => $type->id, 'allocated_amount' => 100, 'used_amount' => 0, 'remaining_amount' => 100, 'enforcement' => 'block',
        ]);
        $this->budgetIds[] = $budget->id;

        // Two reimbursements of 60: each fits alone, both together (120) break the cap of 100.
        $a = $this->committedExpense(['requirement_type' => 'reimbursement', 'status' => 'pending', 'amount' => 60, 'expense_type' => $type->id]);
        $b = $this->committedExpense(['requirement_type' => 'reimbursement', 'status' => 'pending', 'amount' => 60, 'expense_type' => $type->id]);

        $results = $this->race([
            ['op' => 'approve', 'expense_id' => $a->id],
            ['op' => 'approve', 'expense_id' => $b->id],
        ]);

        $this->assertCount(1, array_filter($results, fn ($r) => $r['ok']), 'exactly one approval fits the budget: ' . json_encode($results));
        foreach (array_filter($results, fn ($r) => ! $r['ok']) as $loser) {
            $this->assertStringContainsString('Cannot approve', $loser['message']);
            $this->assertSame(422, $loser['status']);
        }

        $this->assertSame(1, DB::table('expenses')->whereIn('id', [$a->id, $b->id])->where('status', 'approved')->count());
        $this->assertSame(1, DB::table('expenses')->whereIn('id', [$a->id, $b->id])->where('status', 'pending')->count());
        $this->assertEquals(60.00, (float) DB::table('expense_budgets')->where('id', $budget->id)->value('used_amount'));
    }

    public function test_two_simultaneous_sends_of_one_reimbursement_to_payroll_route_it_exactly_once(): void
    {
        // The company switch must be ON for the worker processes, so commit an override (removed in tearDown).
        $superAdminId = DB::table('super_admins')->value('id');
        if (! $superAdminId || ! DB::table('tenants')->where('id', $this->tenantId)->value('payroll_dynamic_ui_enabled')
            || ! app(FeatureService::class)->enabled($this->tenantId, 'payroll')
            || ! app(FeatureService::class)->enabled($this->tenantId, 'expense_management')) {
            $this->markTestSkipped('fixture tenant lacks payroll / expense modules / dynamic engine, or no super admin exists');
        }
        DB::table('tenant_feature_overrides')->where('tenant_id', $this->tenantId)->where('feature_key', 'expense_payroll_link')->delete();
        DB::table('tenant_feature_overrides')->insert(['tenant_id' => $this->tenantId, 'feature_key' => 'expense_payroll_link', 'is_enabled' => 1,
            'reason' => 'phpunit-parallel', 'overridden_by' => $superAdminId, 'created_at' => now(), 'updated_at' => now()]);
        $this->overrideKeys[] = 'expense_payroll_link';

        $e = $this->committedExpense(['requirement_type' => 'reimbursement', 'amount' => 250]);

        $results = $this->race(array_fill(0, 3, ['op' => 'send_payroll', 'ids' => [$e->id], 'month' => '2031-03']));

        $this->assertCount(1, array_filter($results, fn ($r) => $r['ok']), 'exactly one send wins: ' . json_encode($results));
        foreach (array_filter($results, fn ($r) => ! $r['ok']) as $loser) {
            $this->assertStringContainsString('Already sent to payroll', $loser['message']);
        }
        $this->assertSame(1, DB::table('expense_payroll_links')->where('expense_id', $e->id)->count());
        $this->assertSame('payroll', DB::table('expenses')->where('id', $e->id)->value('payout_channel'));
    }

    public function test_simultaneous_payments_against_one_advance_cannot_overpay_it(): void
    {
        $this->setBalance(0, 0);
        $advance = $this->committedExpense(['amount' => 1000]);

        // Three different requests (distinct keys), each for 700 of a 1000 advance.
        $results = $this->race([
            ['op' => 'pay', 'expense_id' => $advance->id, 'amount' => '700', 'key' => 'par-1-' . uniqid()],
            ['op' => 'pay', 'expense_id' => $advance->id, 'amount' => '700', 'key' => 'par-2-' . uniqid()],
            ['op' => 'pay', 'expense_id' => $advance->id, 'amount' => '700', 'key' => 'par-3-' . uniqid()],
        ]);

        $this->assertCount(1, array_filter($results, fn ($r) => $r['ok']), 'only one 700 payment fits: ' . json_encode($results));
        foreach (array_filter($results, fn ($r) => ! $r['ok']) as $loser) {
            $this->assertStringContainsString('exceeds remaining balance', $loser['message']);
        }

        $this->assertSame(1, DB::table('expense_payments')->where('expense_id', $advance->id)->count());
        $this->assertEquals(700.00, (float) DB::table('expenses')->where('id', $advance->id)->value('paid_amount'));
        $this->assertEquals(700.00, (float) DB::table('user_expense_balances')->where('user_id', $this->alice->id)->value('current_balance'));
    }

    public function test_simultaneous_requests_with_the_same_idempotency_key_record_one_payment(): void
    {
        $this->setBalance(0, 0);
        $advance = $this->committedExpense(['amount' => 1000]);
        $key = 'same-key-' . uniqid();

        $results = $this->race(array_fill(0, 3, ['op' => 'pay', 'expense_id' => $advance->id, 'amount' => '300', 'key' => $key]));

        $this->assertCount(3, array_filter($results, fn ($r) => $r['ok']), 'a duplicate is not an error: ' . json_encode($results));
        $this->assertCount(1, array_filter($results, fn ($r) => ! $r['duplicate']), 'exactly one request actually posted');

        $this->assertSame(1, DB::table('expense_payments')->where('expense_id', $advance->id)->count());
        $this->assertEquals(300.00, (float) DB::table('user_expense_balances')->where('user_id', $this->alice->id)->value('current_balance'));
    }

    // ---------------------------------------------------------- batch (voucher) races

    private function voucherCounter(): int
    {
        return (int) DB::table('expense_voucher_sequences')->where('tenant_id', $this->tenantId)->value('last_number');
    }

    public function test_two_overlapping_vouchers_cannot_both_overpay_and_the_loser_posts_nothing_at_all(): void
    {
        $this->setBalance(0, 0);
        [$e1, $e2, $e3] = [$this->committedExpense(), $this->committedExpense(), $this->committedExpense()];
        $counter = $this->voucherCounter();

        // Both want 700 of e1's 1000, plus a harmless 100 on their own second expense.
        $results = $this->race([
            ['op' => 'batch', 'key' => 'ov-A-' . uniqid(), 'lines' => [['expense_id' => $e1->id, 'amount' => '700'], ['expense_id' => $e2->id, 'amount' => '100']]],
            ['op' => 'batch', 'key' => 'ov-B-' . uniqid(), 'lines' => [['expense_id' => $e1->id, 'amount' => '700'], ['expense_id' => $e3->id, 'amount' => '100']]],
        ]);

        $this->assertCount(1, array_filter($results, fn ($r) => $r['ok']), 'exactly one voucher wins: ' . json_encode($results));
        $loser = array_values(array_filter($results, fn ($r) => ! $r['ok']))[0];
        $this->assertStringContainsString('exceeds remaining balance', $loser['message']);

        $this->assertEquals(700.00, (float) DB::table('expenses')->where('id', $e1->id)->value('paid_amount'));
        $paidOnSecond = (float) DB::table('expenses')->whereIn('id', [$e2->id, $e3->id])->sum('paid_amount');
        $this->assertEquals(100.00, $paidOnSecond, 'the loser posted NOTHING — not even its harmless second line');
        $this->assertSame(1, DB::table('expense_payment_batches')->whereIn('id', DB::table('expense_payments')->whereIn('expense_id', [$e1->id, $e2->id, $e3->id])->pluck('batch_id'))->count());
        $this->assertEquals(800.00, (float) DB::table('user_expense_balances')->where('user_id', $this->alice->id)->value('current_balance'));
        $this->assertSame($counter + 1, $this->voucherCounter(), 'the loser gave its voucher number back — no gap');
    }

    public function test_vouchers_that_touch_the_same_expenses_in_opposite_order_do_not_deadlock(): void
    {
        $this->setBalance(0, 0);
        [$e1, $e2] = [$this->committedExpense(['amount' => 500]), $this->committedExpense(['amount' => 500])];
        $counter = $this->voucherCounter();

        // Same two expenses, requested in opposite order. Expenses are always locked in ascending id
        // order regardless of the request order, so the two runs queue up instead of deadlocking.
        $results = $this->race([
            ['op' => 'batch', 'key' => 'dl-A-' . uniqid(), 'lines' => [['expense_id' => $e1->id, 'amount' => '250'], ['expense_id' => $e2->id, 'amount' => '250']]],
            ['op' => 'batch', 'key' => 'dl-B-' . uniqid(), 'lines' => [['expense_id' => $e2->id, 'amount' => '250'], ['expense_id' => $e1->id, 'amount' => '250']]],
        ]);

        $this->assertCount(2, array_filter($results, fn ($r) => $r['ok']), 'both must succeed: ' . json_encode($results));
        $this->assertNotSame($results[0]['voucher'], $results[1]['voucher']);

        foreach ([$e1, $e2] as $e) {
            $row = DB::table('expenses')->where('id', $e->id)->first();
            $this->assertEquals(500.00, (float) $row->paid_amount);
            $this->assertSame('complete', $row->status);
        }
        $this->assertEquals(1000.00, (float) DB::table('user_expense_balances')->where('user_id', $this->alice->id)->value('current_balance'));
        $this->assertSame($counter + 2, $this->voucherCounter());
    }

    public function test_identical_voucher_requests_racing_with_one_key_post_one_voucher(): void
    {
        $this->setBalance(0, 0);
        $e = $this->committedExpense();
        $key = 'same-batch-key-' . uniqid();

        $results = $this->race(array_fill(0, 3, ['op' => 'batch', 'key' => $key, 'lines' => [['expense_id' => $e->id, 'amount' => '400']]]));

        $this->assertCount(3, array_filter($results, fn ($r) => $r['ok']), json_encode($results));
        $this->assertCount(1, array_filter($results, fn ($r) => ! $r['duplicate']), 'exactly one request really posted');
        $this->assertCount(1, array_unique(array_column($results, 'batch_id')), 'all three point at the same voucher');
        $this->assertEquals(400.00, (float) DB::table('expenses')->where('id', $e->id)->value('paid_amount'));
        $this->assertEquals(400.00, (float) DB::table('user_expense_balances')->where('user_id', $this->alice->id)->value('current_balance'));
    }

    public function test_a_void_racing_a_new_payment_never_corrupts_the_paid_amount_or_the_balance(): void
    {
        $this->setBalance(0, 0);
        $e = $this->committedExpense(['amount' => 1000]);

        // A committed voucher paying it in full, so there is something to void.
        $setup = $this->race([['op' => 'batch', 'key' => 'vr-0-' . uniqid(), 'lines' => [['expense_id' => $e->id, 'amount' => '1000']]]]);
        $batchId = $setup[0]['batch_id'];

        // Now: void it while someone else tries to pay 600 against the same expense.
        $this->race([
            ['op' => 'void_batch', 'batch_id' => $batchId],
            ['op' => 'batch', 'key' => 'vr-1-' . uniqid(), 'lines' => [['expense_id' => $e->id, 'amount' => '600']]],
        ]);

        // Whichever order they ran in, the books must add up.
        $expense = DB::table('expenses')->where('id', $e->id)->first();
        $postedSum = (float) DB::table('expense_payments')->where('expense_id', $e->id)->where('status', 'posted')->sum('amount');
        $ledger = (float) DB::table('expense_transactions')->where('expense_id', $e->id)->where('transaction_type', 'advance_credited')->sum('amount');

        $this->assertEquals($postedSum, (float) $expense->paid_amount, 'paid_amount == the sum of POSTED payments');
        $this->assertEquals($ledger, (float) DB::table('user_expense_balances')->where('user_id', $this->alice->id)->value('current_balance'), 'balance == the ledger');
        $this->assertLessThanOrEqual(1000.00, $postedSum, 'never overpaid');
        $this->assertSame($postedSum >= 1000.00 ? 'complete' : 'approved', $expense->status);
    }

    public function test_simultaneous_payments_to_different_expenses_of_one_employee_lose_no_balance_update(): void
    {
        // The classic lost-update: each request reads the balance, adds 100, writes it back.
        $this->setBalance(0, 0);
        $expenses = [$this->committedExpense(['amount' => 500]), $this->committedExpense(['amount' => 500]), $this->committedExpense(['amount' => 500])];

        $results = $this->race(array_map(fn ($e) => ['op' => 'pay', 'expense_id' => $e->id, 'amount' => '100', 'key' => 'lu-' . $e->id . uniqid()], $expenses));

        $this->assertCount(3, array_filter($results, fn ($r) => $r['ok']), json_encode($results));

        $b = DB::table('user_expense_balances')->where('user_id', $this->alice->id)->first();
        $this->assertEquals(300.00, (float) $b->current_balance, 'without the balance-row lock this ends at 100 (two updates lost)');
        $this->assertEquals(300.00, (float) $b->advance_balance);

        // The ledger must tell the same story: three rows whose before/after chain is unbroken.
        $rows = DB::table('expense_transactions')->whereIn('expense_id', array_map(fn ($e) => $e->id, $expenses))->orderBy('id')->get();
        $this->assertCount(3, $rows);
        $this->assertEquals([0.0, 100.0, 200.0], $rows->map(fn ($r) => (float) $r->balance_before)->all());
        $this->assertEquals([100.0, 200.0, 300.0], $rows->map(fn ($r) => (float) $r->balance_after)->all());
    }
}
