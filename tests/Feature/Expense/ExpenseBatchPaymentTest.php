<?php

namespace Tests\Feature\Expense;

use App\Exceptions\ExpenseBatchException;
use App\Exceptions\ExpenseException;
use App\Models\Expense;
use App\Models\ExpensePayment;
use App\Models\ExpensePaymentBatch;
use App\Models\ExpenseType;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserExpenseBalance;
use App\Services\Expense\ExpensePaymentService;
use App\Services\FeatureService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Expense Phase 3 — payment batches (vouchers): posting, all-or-nothing validation,
 * preview, FIFO allocation, voids, direct payments, and the web/API edge
 * (permissions, per-company feature flag, CSV/PDF, bulk approve).
 */
class ExpenseBatchPaymentTest extends TestCase
{
    use DatabaseTransactions;

    private int $tenantId;
    private User $hr;
    private User $alice;
    private User $bob;
    private int $typeId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantId = (int) DB::table('users')->where('status', '1')->where('role', 'employee')->whereNotNull('tenant_id')
            ->select('tenant_id')->groupBy('tenant_id')->havingRaw('count(*) >= 2')->orderByRaw('count(*) desc')->value('tenant_id');

        $hr = $this->tenantId ? User::withoutGlobalScopes()->where('tenant_id', $this->tenantId)->whereIn('role', ['admin', 'hr'])->where('status', '1')->first() : null;
        $emps = $this->tenantId ? User::withoutGlobalScopes()->where('tenant_id', $this->tenantId)->where('role', 'employee')->where('status', '1')->orderBy('id')->limit(2)->get() : collect();
        $typeId = $this->tenantId ? ExpenseType::withoutGlobalScopes()->where('tenant_id', $this->tenantId)->where('status', 1)->orderBy('id')->value('id') : null;

        if (! $hr || $emps->count() < 2 || ! $typeId) {
            $this->markTestSkipped('fixture users / expense type missing in the dev DB');
        }

        [$this->hr, $this->alice, $this->bob, $this->typeId] = [$hr, $emps[0], $emps[1], (int) $typeId];
        app()->instance('current_tenant', Tenant::find($this->tenantId));
    }

    // ---------------------------------------------------------------- helpers

    private function svc(): ExpensePaymentService
    {
        return app(ExpensePaymentService::class);
    }

    private function expense(User $owner, array $o = []): Expense
    {
        return Expense::create($o + [
            'tenant_id' => $this->tenantId, 'user_id' => $owner->id, 'expense_type' => $this->typeId, 'amount' => 1000,
            'date' => now()->subDay()->toDateString(), 'requirement_type' => 'advance', 'status' => 'approved', 'description' => 'phpunit-batch',
        ]);
    }

    private function zero(User $u): void
    {
        UserExpenseBalance::updateOrCreate(['user_id' => $u->id], ['tenant_id' => $this->tenantId, 'current_balance' => 0,
            'advance_balance' => 0, 'settlement_balance' => 0, 'reimbursement_balance' => 0]);
        DB::table('expense_transactions')->where('user_id', $u->id)->delete();
    }

    private function bal(User $u): UserExpenseBalance
    {
        return UserExpenseBalance::where('user_id', $u->id)->firstOrFail();
    }

    private function meta(array $o = []): array
    {
        return $o + ['payment_date' => now()->toDateString(), 'payment_mode' => 'bank_transfer', 'reference_number' => 'UTR-1'];
    }

    private function pay(array $lines, ?string $key = null, ?array $meta = null, ?callable $can = null): array
    {
        return $this->svc()->payBatch($this->hr, $lines, $meta ?? $this->meta(), $key, $can ?? fn () => true);
    }

    private function lastVoucherNumber(): int
    {
        return (int) DB::table('expense_voucher_sequences')->where('tenant_id', $this->tenantId)->value('last_number');
    }

    private function as(User $u): static
    {
        return $this->actingAs($u)->withSession(['tenant_id' => $this->tenantId])->withHeaders(['Accept' => 'application/json']);
    }

    // ------------------------------------------------------------ posting a batch

    public function test_a_batch_posts_one_voucher_with_a_payment_per_line_and_updates_every_ledger(): void
    {
        $this->zero($this->alice);
        $this->zero($this->bob);
        $a1 = $this->expense($this->alice, ['amount' => 500]);
        $a2 = $this->expense($this->alice, ['amount' => 300, 'requirement_type' => 'reimbursement']);
        $b1 = $this->expense($this->bob, ['amount' => 1000]);
        $before = $this->lastVoucherNumber();

        $r = $this->pay([
            ['expense_id' => $a1->id, 'amount' => '500'],      // full
            ['expense_id' => $a2->id, 'amount' => '100'],      // partial reimbursement
            ['expense_id' => $b1->id, 'amount' => '250.50'],   // partial advance
        ]);

        $batch = $r['batch'];
        $this->assertFalse($r['duplicate']);
        $this->assertSame('PV-' . str_pad((string) ($before + 1), 6, '0', STR_PAD_LEFT), $batch->voucher_number);
        $this->assertEquals(850.50, (float) $batch->total_amount);
        $this->assertSame(3, $batch->line_count);
        $this->assertSame('posted', $batch->status);
        $this->assertSame(3, ExpensePayment::where('batch_id', $batch->id)->count());

        $this->assertSame('complete', $a1->fresh()->status);
        $this->assertSame('approved', $a2->fresh()->status);
        $this->assertEquals(100.00, (float) $a2->fresh()->paid_amount);
        $this->assertEquals(250.50, (float) $b1->fresh()->paid_amount);

        $alice = $this->bal($this->alice);
        $this->assertEquals(500.00, (float) $alice->current_balance, 'advance credited, reimbursement not part of current');
        $this->assertEquals(100.00, (float) $alice->reimbursement_balance);
        $this->assertEquals(250.50, (float) $this->bal($this->bob)->current_balance);

        // every ledger row mentions the voucher, and the chain is unbroken per employee
        $tx = DB::table('expense_transactions')->whereIn('expense_id', [$a1->id, $a2->id, $b1->id])->get();
        $this->assertCount(3, $tx);
        foreach ($tx as $t) {
            $this->assertStringContainsString($batch->voucher_number, $t->description);
        }
    }

    public function test_voucher_numbers_are_sequential_per_tenant_and_a_failed_batch_does_not_burn_a_number(): void
    {
        $this->zero($this->alice);
        $e = $this->expense($this->alice, ['amount' => 100]);

        $n0 = $this->lastVoucherNumber();
        $first = $this->pay([['expense_id' => $e->id, 'amount' => '10']]);

        try {
            $this->pay([['expense_id' => $e->id, 'amount' => '99999']]);   // exceeds -> rolled back
            $this->fail('expected a validation failure');
        } catch (ExpenseBatchException) {
        }
        $this->assertSame($n0 + 1, $this->lastVoucherNumber(), 'the rolled-back attempt gave its number back');

        $second = $this->pay([['expense_id' => $e->id, 'amount' => '10']]);
        $this->assertSame((int) substr($first['batch']->voucher_number, 3) + 1, (int) substr($second['batch']->voucher_number, 3), 'gapless');
    }

    public function test_a_batch_is_all_or_nothing_and_reports_every_bad_line_at_once(): void
    {
        $this->zero($this->alice);
        $good = $this->expense($this->alice, ['amount' => 500]);
        $over = $this->expense($this->alice, ['amount' => 100]);
        $pending = $this->expense($this->alice, ['status' => 'pending']);
        $settlement = $this->expense($this->alice, ['requirement_type' => 'settlement']);

        try {
            $this->pay([
                ['expense_id' => $good->id, 'amount' => '500'],
                ['expense_id' => $over->id, 'amount' => '150'],
                ['expense_id' => $pending->id, 'amount' => '10'],
                ['expense_id' => $settlement->id, 'amount' => '10'],
                ['expense_id' => 999999999, 'amount' => '10'],
            ]);
            $this->fail('expected the whole batch to be refused');
        } catch (ExpenseBatchException $e) {
            $this->assertSame(422, $e->httpStatus());
            $errors = collect($e->lineErrors())->keyBy('expense_id');
            $this->assertCount(4, $errors, 'all four bad lines reported together, not one at a time');
            $this->assertStringContainsString('exceeds remaining balance', $errors[$over->id]['message']);
            $this->assertStringContainsString('approved', $errors[$pending->id]['message']);
            $this->assertStringContainsString('Only advances and reimbursements', $errors[$settlement->id]['message']);
            $this->assertSame('Expense not found.', $errors[999999999]['message']);
        }

        // NOTHING was posted — not even the valid first line.
        $this->assertSame('approved', $good->fresh()->status);
        $this->assertEquals(0.00, (float) $good->fresh()->paid_amount);
        $this->assertSame(0, ExpensePayment::where('expense_id', $good->id)->count());
        $this->assertEquals(0.00, (float) $this->bal($this->alice)->current_balance);
    }

    public function test_line_level_form_rules(): void
    {
        $this->zero($this->alice);
        $e = $this->expense($this->alice);

        foreach ([
            'empty selection' => [[], 'Select at least one'],
            'duplicate expense' => [[['expense_id' => $e->id, 'amount' => '1'], ['expense_id' => $e->id, 'amount' => '2']], 'appears twice'],
            'zero amount' => [[['expense_id' => $e->id, 'amount' => '0']], 'greater than zero'],
            'too many lines' => [array_fill(0, ExpensePaymentService::MAX_BATCH_LINES + 1, ['expense_id' => $e->id, 'amount' => '1']), 'at most'],
        ] as $label => [$lines, $needle]) {
            try {
                $this->pay($lines);
                $this->fail("{$label}: expected refusal");
            } catch (ExpenseBatchException $ex) {
                $this->assertStringContainsString($needle, json_encode($ex->lineErrors()) . $ex->getMessage(), $label);
            }
        }
    }

    public function test_the_owner_scope_predicate_is_applied_to_every_line(): void
    {
        $this->zero($this->alice);
        $this->zero($this->bob);
        $a = $this->expense($this->alice);
        $b = $this->expense($this->bob);

        try {
            $this->pay([['expense_id' => $a->id, 'amount' => '10'], ['expense_id' => $b->id, 'amount' => '10']], null, null, fn (int $owner) => $owner === $this->alice->id);
            $this->fail('one out-of-scope line must sink the whole batch');
        } catch (ExpenseBatchException $e) {
            $this->assertSame([$b->id], array_column($e->lineErrors(), 'expense_id'));
            $this->assertStringContainsString('not authorized', $e->lineErrors()[0]['message']);
        }

        $this->assertEquals(0.00, (float) $this->bal($this->alice)->current_balance, 'alice was not paid either');
    }

    public function test_repeating_a_batch_idempotency_key_returns_the_original_voucher(): void
    {
        $this->zero($this->alice);
        $e = $this->expense($this->alice, ['amount' => 1000]);
        $lines = [['expense_id' => $e->id, 'amount' => '300']];

        $first = $this->pay($lines, 'batch-key-1');
        $second = $this->pay($lines, 'batch-key-1');

        $this->assertFalse($first['duplicate']);
        $this->assertTrue($second['duplicate']);
        $this->assertSame($first['batch']->id, $second['batch']->id);
        $this->assertSame(1, ExpensePaymentBatch::where('idempotency_key', 'batch-key-1')->count());
        $this->assertEquals(300.00, (float) $e->fresh()->paid_amount, 'paid once, not twice');
        $this->assertEquals(300.00, (float) $this->bal($this->alice)->current_balance);
    }

    // ------------------------------------------------------------------ preview

    public function test_preview_writes_nothing_and_agrees_with_the_real_posting(): void
    {
        $this->zero($this->alice);
        $this->zero($this->bob);
        $a = $this->expense($this->alice, ['amount' => 200]);
        $b = $this->expense($this->bob, ['amount' => 300]);
        $bad = $this->expense($this->bob, ['amount' => 50]);
        $n0 = $this->lastVoucherNumber();

        $ok = $this->svc()->preview($this->hr, [['expense_id' => $a->id, 'amount' => '200'], ['expense_id' => $b->id, 'amount' => '100']], fn () => true);
        $this->assertTrue($ok['valid']);
        $this->assertSame(2, $ok['count']);
        $this->assertEquals(300.00, $ok['total']);
        $this->assertCount(2, $ok['employees']);
        $this->assertSame([], $ok['errors']);

        $notOk = $this->svc()->preview($this->hr, [['expense_id' => $a->id, 'amount' => '200'], ['expense_id' => $bad->id, 'amount' => '75']], fn () => true);
        $this->assertFalse($notOk['valid']);
        $this->assertSame($bad->id, $notOk['errors'][0]['expense_id']);
        $this->assertSame(1, $notOk['count'], 'the valid line is still listed');

        // read-only
        $this->assertSame($n0, $this->lastVoucherNumber());
        $this->assertSame(0, ExpensePayment::whereIn('expense_id', [$a->id, $b->id, $bad->id])->count());
        $this->assertEquals(0.00, (float) $a->fresh()->paid_amount);

        // and the real posting of the "valid" selection succeeds exactly as previewed
        $r = $this->pay([['expense_id' => $a->id, 'amount' => '200'], ['expense_id' => $b->id, 'amount' => '100']]);
        $this->assertEquals($ok['total'], (float) $r['batch']->total_amount);
    }

    // ------------------------------------------------------------- FIFO allocation

    public function test_fifo_spreads_an_amount_oldest_first_and_reports_what_is_left_over(): void
    {
        $this->zero($this->alice);
        // Alice has real approved expenses in the shared dev DB; park them (rolled back with the test)
        // so only this test's fixtures are payable.
        Expense::where('user_id', $this->alice->id)
            ->where(fn ($q) => $q->whereNull('description')->orWhere('description', '!=', 'phpunit-batch'))
            ->update(['status' => 'pending']);
        $old = $this->expense($this->alice, ['amount' => 100, 'created_at' => now()->subDays(10)]);
        $mid = $this->expense($this->alice, ['amount' => 200, 'created_at' => now()->subDays(5), 'requirement_type' => 'reimbursement']);
        $new = $this->expense($this->alice, ['amount' => 300, 'created_at' => now()->subDay()]);
        $paid = $this->expense($this->alice, ['amount' => 50, 'created_at' => now()->subDays(20)]);
        $this->pay([['expense_id' => $paid->id, 'amount' => '50']]);       // already fully paid -> skipped
        $this->pay([['expense_id' => $mid->id, 'amount' => '50']]);        // partially paid -> only 150 outstanding

        $r = $this->svc()->allocateFifo($this->hr, $this->alice->id, '400', fn () => true);

        $this->assertSame([$old->id, $mid->id, $new->id], array_column($r['lines'], 'expense_id'), 'oldest first, fully-paid skipped');
        $this->assertEquals([100.0, 150.0, 150.0], array_column($r['lines'], 'amount'));
        $this->assertEquals(400.00, $r['allocated']);
        $this->assertEquals(0.00, $r['unallocated']);

        $tooMuch = $this->svc()->allocateFifo($this->hr, $this->alice->id, '9999', fn () => true);
        $this->assertEquals(550.00, $tooMuch['allocated'], '100 + 150 + 300 outstanding');
        $this->assertEquals(9449.00, $tooMuch['unallocated']);

        $this->expectException(ExpenseException::class);
        $this->svc()->allocateFifo($this->hr, $this->alice->id, '10', fn () => false);
    }

    // ------------------------------------------------------------------- voids

    public function test_voiding_one_line_reverses_only_that_line_and_all_lines_voids_the_batch(): void
    {
        $this->zero($this->alice);
        $e1 = $this->expense($this->alice, ['amount' => 100]);
        $e2 = $this->expense($this->alice, ['amount' => 200]);
        $batch = $this->pay([['expense_id' => $e1->id, 'amount' => '100'], ['expense_id' => $e2->id, 'amount' => '200']])['batch'];
        $p1 = ExpensePayment::where('expense_id', $e1->id)->firstOrFail();
        $p2 = ExpensePayment::where('expense_id', $e2->id)->firstOrFail();

        $this->svc()->voidPayment($this->hr, $p1->id, 'wrong employee', fn () => true);

        $this->assertSame('voided', $p1->fresh()->status);
        $this->assertSame('approved', $e1->fresh()->status, 'complete -> reopened');
        $this->assertEquals(0.00, (float) $e1->fresh()->paid_amount);
        $this->assertEquals(200.00, (float) $this->bal($this->alice)->current_balance);
        $this->assertSame('posted', $batch->fresh()->status, 'one line remains posted');
        $this->assertEqualsCanonicalizing([$e1->id], Expense::payable()->where('description', 'phpunit-batch')->pluck('id')->all(), 'the reopened expense is payable again');

        $this->svc()->voidPayment($this->hr, $p2->id, 'and this one', fn () => true);
        $this->assertSame('voided', $batch->fresh()->status, 'no posted line left -> voucher is voided');
        $this->assertEquals(0.00, (float) $this->bal($this->alice)->current_balance);
    }

    public function test_voiding_a_batch_reverses_every_line_together(): void
    {
        $this->zero($this->alice);
        $this->zero($this->bob);
        $a = $this->expense($this->alice, ['amount' => 100]);
        $b = $this->expense($this->bob, ['amount' => 400, 'requirement_type' => 'reimbursement']);
        $batch = $this->pay([['expense_id' => $a->id, 'amount' => '100'], ['expense_id' => $b->id, 'amount' => '400']])['batch'];

        $r = $this->svc()->voidBatch($this->hr, $batch->id, 'bank rejected the file', fn () => true);

        $this->assertSame(2, $r['voided']);
        $batch->refresh();
        $this->assertSame('voided', $batch->status);
        $this->assertSame('bank rejected the file', $batch->void_reason);
        $this->assertSame($this->hr->id, (int) $batch->voided_by);
        $this->assertSame(0, ExpensePayment::where('batch_id', $batch->id)->posted()->count());
        $this->assertSame(2, ExpensePayment::where('batch_id', $batch->id)->count(), 'rows are kept');
        $this->assertEquals(0.00, (float) $a->fresh()->paid_amount);
        $this->assertSame('approved', $a->fresh()->status);
        $this->assertSame('approved', $b->fresh()->status);
        $this->assertEquals(0.00, (float) $this->bal($this->alice)->current_balance);
        $this->assertEquals(0.00, (float) $this->bal($this->bob)->reimbursement_balance);

        try {
            $this->svc()->voidBatch($this->hr, $batch->id, 'again', fn () => true);
            $this->fail('a voucher cannot be voided twice');
        } catch (ExpenseException $e) {
            $this->assertSame(409, $e->httpStatus());
        }
    }

    public function test_voiding_a_batch_is_refused_wholesale_if_any_line_cannot_be_reversed(): void
    {
        $this->zero($this->alice);
        $this->zero($this->bob);
        $a = $this->expense($this->alice, ['amount' => 100]);
        $b = $this->expense($this->bob, ['amount' => 100]);
        $batch = $this->pay([['expense_id' => $a->id, 'amount' => '100'], ['expense_id' => $b->id, 'amount' => '100']])['batch'];

        // Bob has since spent his advance down to nothing.
        UserExpenseBalance::where('user_id', $this->bob->id)->update(['current_balance' => 0]);

        try {
            $this->svc()->voidBatch($this->hr, $batch->id, 'undo', fn () => true);
            $this->fail('expected a refusal');
        } catch (ExpenseException $e) {
            $this->assertSame(409, $e->httpStatus());
            $this->assertStringContainsString($batch->voucher_number, $e->getMessage());
            $this->assertStringContainsString($b->expense_number, $e->getMessage());
        }

        $this->assertSame('posted', $batch->fresh()->status);
        $this->assertSame(2, ExpensePayment::where('batch_id', $batch->id)->posted()->count(), 'alice\'s line was NOT reversed either');
        $this->assertEquals(100.00, (float) $this->bal($this->alice)->current_balance);
    }

    public function test_a_void_needs_a_reason_and_the_owner_scope(): void
    {
        $this->zero($this->alice);
        $e = $this->expense($this->alice, ['amount' => 100]);
        $batch = $this->pay([['expense_id' => $e->id, 'amount' => '100']])['batch'];
        $p = ExpensePayment::where('batch_id', $batch->id)->firstOrFail();

        foreach ([fn () => $this->svc()->voidBatch($this->hr, $batch->id, '  ', fn () => true), fn () => $this->svc()->voidPayment($this->hr, $p->id, 'x', fn () => true)] as $call) {
            try {
                $call();
                $this->fail('a reason of 3+ characters is required');
            } catch (ExpenseException $ex) {
                $this->assertSame(422, $ex->httpStatus());
            }
        }

        $this->expectException(ExpenseException::class);
        $this->expectExceptionMessage('not authorized');
        $this->svc()->voidBatch($this->hr, $batch->id, 'valid reason', fn () => false);
    }

    // --------------------------------------------------------------- direct payment

    public function test_direct_payment_is_a_real_flagged_advance_paid_through_a_voucher(): void
    {
        $this->zero($this->alice);

        $r = $this->svc()->record($this->hr, 'direct', $this->meta(['amount' => '1200', 'direct_user_id' => $this->alice->id, 'remarks' => 'site advance']), null, fn () => true);

        $expense = $r['expense']->fresh();
        $this->assertTrue((bool) $expense->is_direct_payment);
        $this->assertNull($expense->project_id, 'no more hard-coded project_id=1');
        $this->assertSame($this->typeId, (int) $expense->expense_type, "the tenant's first active type, not a hard-coded id 1");
        $this->assertSame('complete', $expense->status);
        $this->assertEquals(1200.00, (float) $expense->paid_amount);
        $this->assertSame($this->hr->id, (int) $expense->approved_by);
        $this->assertNotNull($r['batch']->voucher_number);
        $this->assertEquals(1200.00, (float) $this->bal($this->alice)->current_balance);

        // Voiding it must not leave a payable advance behind.
        $this->svc()->voidBatch($this->hr, $r['batch']->id, 'paid the wrong person', fn () => true);
        $this->assertSame('cancelled', $expense->fresh()->status);
        $this->assertEquals(0.00, (float) $this->bal($this->alice)->current_balance);
        $this->assertNotContains($expense->id, Expense::payable()->pluck('id')->all());
    }

    public function test_a_single_payment_is_a_one_line_voucher(): void
    {
        $this->zero($this->alice);
        $e = $this->expense($this->alice, ['amount' => 100]);

        $r = $this->svc()->record($this->hr, 'advance', $this->meta(['amount' => '100', 'expense_id' => $e->id]), null, fn () => true);

        $this->assertSame(1, $r['batch']->line_count);
        $this->assertSame($r['batch']->voucher_number, $r['data']['voucher_number']);
        $this->assertEquals(100.00, (float) $r['batch']->total_amount);
    }

    public function test_payment_and_voucher_pages_have_search_status_filters_and_find_rows_by_voucher_and_employee(): void
    {
        $this->zero($this->alice);
        $e = $this->expense($this->alice, ['amount' => 100]);
        $r = $this->svc()->record($this->hr, 'advance', $this->meta(['amount' => '100', 'expense_id' => $e->id]), null, fn () => true);
        $voucher = $r['batch']->voucher_number;

        // Payment Management: caption-less filter bar (no "Payment Overview" header) + tiles + Sr. No. column
        $page = $this->as($this->hr)->get(route('expense.payments.index'))->assertOk()
            ->assertDontSee('Payment Overview')->assertDontSee('<label class="filter-label"', false)->assertSee('name="search"', false)->assertSee('name="status"', false)
            ->assertSee('Sr. No.')->assertSee('class="filter-row"', false);
        $this->assertStringContainsString('ex-page', $page->getContent());

        // ...search by voucher number and by employee name find the payment; a stranger term finds nothing
        foreach ([$voucher, $this->alice->name] as $term) {
            $res = $this->as($this->hr)->get(route('expense.payments.index', ['search' => $term]))->assertOk();
            $this->assertContains($e->id, $res->viewData('payments')->pluck('expense_id')->all(), "search '$term' should find the payment");
        }
        $none = $this->as($this->hr)->get(route('expense.payments.index', ['search' => 'zz-no-such-thing-zz']))->assertOk();
        $this->assertCount(0, $none->viewData('payments'));

        // status filter: a posted payment is not in the voided list
        $voided = $this->as($this->hr)->get(route('expense.payments.index', ['search' => $voucher, 'status' => 'voided']))->assertOk();
        $this->assertCount(0, $voided->viewData('payments'));

        // Vouchers page: same layout (the shared filter card, heading "Filter Vouchers"), search by voucher no.
        // and by an employee on one of its lines
        $this->as($this->hr)->get(route('expense.vouchers.index'))->assertOk()
            ->assertDontSee('Voucher Overview')->assertSee('Filter Vouchers')->assertDontSee('<label class="filter-label"', false)
            ->assertSee('name="search"', false)->assertSee('Sr. No.');

        // Pay Batch page: compact single-row filter (auto-applies, so no Apply button), voucher panel intact
        $this->as($this->hr)->get(route('expense.payments.batch'))->assertOk()
            ->assertSee('id="f_search"', false)->assertSee('id="btnPreview"', false)->assertSee('id="btnConfirm"', false)
            ->assertSee('id="alloc_user"', false)->assertDontSee('id="btnApply"', false);
        foreach ([$voucher, $this->alice->name, $e->fresh()->expense_number] as $term) {
            $res = $this->as($this->hr)->get(route('expense.vouchers.index', ['search' => $term]))->assertOk();
            $this->assertContains($r['batch']->id, $res->viewData('batches')->pluck('id')->all(), "search '$term' should find the voucher");
        }
        $this->assertCount(0, $this->as($this->hr)->get(route('expense.vouchers.index', ['search' => 'zz-no-such-thing-zz']))->viewData('batches'));
    }

    // ------------------------------------------------------------------ web / API edge

    public function test_only_payers_can_reach_the_batch_endpoints(): void
    {
        foreach ([
            ['get', route('expense.payments.batch')],
            ['get', route('expense.payments.batch.payable')],
            ['post', route('expense.payments.batch.preview')],
            ['post', route('expense.payments.batch.allocate')],
            ['post', route('expense.payments.batch.store')],
            ['get', route('expense.vouchers.index')],
        ] as [$method, $url]) {
            $this->as($this->alice)->{$method . 'Json'}($url)->assertStatus(403);
        }
    }

    public function test_the_batch_flow_end_to_end_over_http(): void
    {
        $this->zero($this->alice);
        $this->zero($this->bob);
        $a = $this->expense($this->alice, ['amount' => 500, 'description' => 'phpunit-batch =HYPERLINK("x")']);
        $b = $this->expense($this->bob, ['amount' => 300, 'requirement_type' => 'reimbursement']);

        // 0. the page itself renders (filters, table shell, voucher form, the JS endpoints)
        $page = $this->as($this->hr)->get(route('expense.payments.batch'))->assertOk()
            ->assertSee('Pay Batch')->assertSee('Confirm', false)
            // the JS endpoints are emitted with @json(), which escapes slashes ("http:\/\/host\/expense\/…")
            ->assertSee(str_replace('/', '\/', route('expense.payments.batch.store')), false)
            ->assertSee(str_replace('/', '\/', route('expense.payments.batch.payable')), false);
        $this->assertStringContainsString('id="payableTable"', $page->getContent());

        // 1. the payable list
        $list = $this->as($this->hr)->getJson(route('expense.payments.batch.payable', ['search' => 'phpunit-batch']))->assertOk();
        $ids = collect($list->json('data'))->pluck('id')->all();
        $this->assertContains($a->id, $ids);
        $this->assertContains($b->id, $ids);

        // 2. preview
        $lines = [['expense_id' => $a->id, 'amount' => '500'], ['expense_id' => $b->id, 'amount' => '120']];
        $this->as($this->hr)->postJson(route('expense.payments.batch.preview'), ['lines' => $lines])
            ->assertOk()->assertJsonPath('valid', true)->assertJsonPath('total', 620);

        // 3. post — the key and the meta are required
        $this->as($this->hr)->postJson(route('expense.payments.batch.store'), ['lines' => $lines])->assertStatus(422);

        $body = ['lines' => $lines, 'payment_date' => now()->toDateString(), 'payment_mode' => 'bank_transfer', 'reference_number' => 'UTR-999', 'idempotency_key' => 'http-key-1'];
        $res = $this->as($this->hr)->postJson(route('expense.payments.batch.store'), $body)->assertOk()
            ->assertJsonPath('success', true)->assertJsonPath('duplicate', false)->assertJsonPath('batch.line_count', 2);
        $batchId = $res->json('batch.id');

        // a retry with the same key is a no-op
        $this->as($this->hr)->postJson(route('expense.payments.batch.store'), $body)->assertOk()->assertJsonPath('duplicate', true)->assertJsonPath('batch.id', $batchId);
        $this->assertSame(1, ExpensePaymentBatch::where('idempotency_key', 'http-key-1')->count());

        // 4. detail page, list page, exports
        $this->as($this->hr)->get(route('expense.vouchers.show', $batchId))->assertOk()->assertSee($res->json('batch.voucher_number'));
        $this->as($this->hr)->get(route('expense.vouchers.index'))->assertOk()->assertSee($res->json('batch.voucher_number'));

        $csv = $this->as($this->hr)->get(route('expense.vouchers.csv', $batchId))->assertOk();
        $this->assertStringContainsString('text/csv', $csv->headers->get('Content-Type'));
        $this->assertStringContainsString($res->json('batch.voucher_number'), $csv->getContent());
        $this->assertStringContainsString('UTR-999', $csv->getContent());

        $pdf = $this->as($this->hr)->get(route('expense.vouchers.pdf', $batchId))->assertOk();
        $this->assertStringContainsString('application/pdf', $pdf->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $pdf->getContent());

        // 5. void over HTTP: a reason is required, then everything is reversed
        $this->as($this->hr)->postJson(route('expense.vouchers.void', $batchId), ['reason' => 'x'])->assertStatus(422);
        $this->as($this->hr)->postJson(route('expense.vouchers.void', $batchId), ['reason' => 'sent to wrong account'])->assertOk()->assertJsonPath('success', true);
        $this->assertSame('voided', ExpensePaymentBatch::find($batchId)->status);
        $this->assertEquals(0.00, (float) $a->fresh()->paid_amount);

        // 6. the payments page shows the voided rows and links the voucher
        $this->as($this->hr)->get(route('expense.payments.index'))->assertOk()->assertSee('Voided')->assertSee('Pay Batch');
    }

    public function test_batch_validation_failures_come_back_with_per_line_errors(): void
    {
        $this->zero($this->alice);
        $good = $this->expense($this->alice, ['amount' => 100]);
        $bad = $this->expense($this->alice, ['amount' => 100]);

        $res = $this->as($this->hr)->postJson(route('expense.payments.batch.store'), [
            'lines' => [['expense_id' => $good->id, 'amount' => '100'], ['expense_id' => $bad->id, 'amount' => '500']],
            'payment_date' => now()->toDateString(), 'payment_mode' => 'cash', 'idempotency_key' => 'http-key-2',
        ])->assertStatus(422); // a real batch reports 422 (only a one-line voucher keeps a line's own 400/403/404)

        $res->assertJsonPath('success', false)->assertJsonPath('line_errors.0.expense_id', $bad->id);
        $this->assertEquals(0.00, (float) $good->fresh()->paid_amount, 'the valid line was not posted either');
    }

    public function test_the_csv_neutralises_spreadsheet_formulas(): void
    {
        $evil = User::withoutGlobalScopes()->whereKey($this->alice->id)->first();
        $originalName = $evil->name;
        DB::table('users')->where('id', $evil->id)->update(['name' => '=cmd|calc']);

        try {
            $this->zero($this->alice);
            $e = $this->expense($this->alice, ['amount' => 50]);
            $batch = $this->pay([['expense_id' => $e->id, 'amount' => '50']])['batch'];

            $csv = $this->as($this->hr)->get(route('expense.vouchers.csv', $batch->id))->assertOk()->getContent();

            $this->assertStringContainsString("'=cmd|calc", $csv);
            $this->assertStringNotContainsString(',=cmd|calc', $csv);
        } finally {
            DB::table('users')->where('id', $evil->id)->update(['name' => $originalName]);
        }
    }

    public function test_the_single_payment_endpoint_now_creates_a_voucher_and_voiding_replaces_deleting(): void
    {
        $this->zero($this->alice);
        $e = $this->expense($this->alice, ['amount' => 200]);

        $res = $this->as($this->hr)->postJson(route('expense.payments.store'), [
            'payment_type' => 'advance', 'advance_expense_id' => $e->id, 'amount' => '200',
            'payment_date' => now()->toDateString(), 'payment_mode' => 'cash', 'idempotency_key' => 'single-1',
        ])->assertOk();
        $this->assertStringStartsWith('PV-', $res->json('data.voucher_number'));
        $paymentId = $res->json('data.id');

        // DELETE = void: the row stays
        $this->as($this->hr)->deleteJson(route('expense.payments.destroy', $paymentId), ['reason' => 'duplicate entry'])->assertOk()
            ->assertJsonPath('message', 'Payment voided successfully');
        $this->assertSame('voided', ExpensePayment::find($paymentId)->status);
        $this->assertSame('approved', $e->fresh()->status);

        // its amount can no longer be edited through the API
        $this->as($this->hr)->putJson(route('expense.payments.update', $paymentId), [
            'payment_date' => now()->toDateString(), 'amount' => '50', 'payment_mode' => 'cash',
        ])->assertStatus(409); // voided payments are not editable at all
    }

    public function test_the_per_company_feature_flag_switches_the_whole_feature_off(): void
    {
        $this->zero($this->alice);
        $e = $this->expense($this->alice, ['status' => 'pending']);

        $features = app(FeatureService::class);
        $this->assertTrue($features->enabled($this->tenantId, 'expense_bulk_payment'), 'default ON');

        // overridden_by is a FK to the Super Admin panel's super_admins table
        $superAdminId = DB::table('super_admins')->value('id');
        if (! $superAdminId) {
            $this->markTestSkipped('no super_admins row to attribute the override to');
        }

        DB::table('tenant_feature_overrides')->insert([
            'tenant_id' => $this->tenantId, 'feature_key' => 'expense_bulk_payment', 'is_enabled' => 0,
            'reason' => 'phpunit', 'overridden_by' => $superAdminId, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $features->bust($this->tenantId);

        $this->assertFalse($features->enabled($this->tenantId, 'expense_bulk_payment'));

        foreach ([['getJson', route('expense.payments.batch.payable')], ['getJson', route('expense.vouchers.index')]] as [$m, $url]) {
            $this->as($this->hr)->{$m}($url)->assertStatus(403);
        }
        $this->as($this->hr)->postJson(route('expense.bulk-status'), ['ids' => [$e->id], 'status' => 'approved'])->assertStatus(403);
        $this->as($this->hr)->get(route('expense.payments.index'))->assertOk()->assertDontSee('Pay Batch');

        // the normal single-expense flow is untouched by the flag
        $this->as($this->hr)->postJson(route('expense.update-status', $e->id), ['status' => 'approved'])->assertOk();

        DB::table('tenant_feature_overrides')->where('tenant_id', $this->tenantId)->where('feature_key', 'expense_bulk_payment')->delete();
        $features->bust($this->tenantId);
        $this->assertTrue($features->enabled($this->tenantId, 'expense_bulk_payment'));
    }

    public function test_view_all_shows_the_bulk_controls_only_to_approvers_of_companies_that_have_the_feature(): void
    {
        $this->expense($this->alice, ['status' => 'pending', 'description' => 'phpunit-batch']);

        $this->as($this->hr)->get(route('expense.view-all'))->assertOk()
            ->assertSee('id="bulkBar"', false)->assertSee('bulk-row', false)->assertSee('id="bulkModal"', false)
            ->assertViewHas('canBulk', true);

        // A plain employee's view-all (own + team scope) has no approve permission -> no bulk controls.
        $this->as($this->alice)->get(route('expense.view-all'))->assertOk()
            ->assertDontSee('id="bulkBar"', false)->assertViewHas('canBulk', false);
    }

    public function test_team_expense_filters_sit_in_one_row_with_a_search_bar_and_search_finds_the_expense_code(): void
    {
        $mine = $this->expense($this->alice, ['status' => 'pending', 'description' => 'phpunit-batch alpha']);
        $other = $this->expense($this->bob, ['status' => 'pending', 'description' => 'phpunit-batch beta']);

        // Filter bar: search box + every filter with its small caption, incl. all three requirement types
        $this->as($this->hr)->get(route('expense.view-all'))->assertOk()
            ->assertSee('name="search"', false)
            ->assertSee('name="user_id"', false)
            ->assertSee('Expense Overview')
            ->assertSee('All Requirements')
            ->assertSee('value="reimbursement"', false)
            ->assertSee('class="filter-row"', false);

        // Searching by the expense code returns that claim only
        $res = $this->as($this->hr)->get(route('expense.view-all', ['search' => $mine->fresh()->expense_number]))->assertOk();
        $this->assertSame([$mine->id], collect($res->viewData('expenses')->items())->pluck('id')->all());
    }

    // ------------------------------------------------------------------ bulk approve

    public function test_bulk_approve_reports_every_row_and_one_bad_row_does_not_block_the_rest(): void
    {
        $this->zero($this->alice);
        $ok1 = $this->expense($this->alice, ['status' => 'pending', 'amount' => 10]);
        $ok2 = $this->expense($this->bob, ['status' => 'pending', 'amount' => 20, 'requirement_type' => 'reimbursement']);
        $done = $this->expense($this->alice, ['status' => 'approved']);                      // already processed
        $bigSettlement = $this->expense($this->alice, ['status' => 'pending', 'requirement_type' => 'settlement', 'amount' => 999999]);

        $res = $this->as($this->hr)->postJson(route('expense.bulk-status'), [
            'ids' => [$ok1->id, $ok2->id, $done->id, $bigSettlement->id, 987654321], 'status' => 'approved', 'remarks' => 'month-end run',
        ])->assertOk();

        $res->assertJsonPath('success', true)->assertJsonPath('succeeded', 2)->assertJsonPath('failed', 3);
        $byId = collect($res->json('results'))->keyBy('id');

        $this->assertTrue($byId[$ok1->id]['ok']);
        $this->assertTrue($byId[$ok2->id]['ok']);
        $this->assertSame('This expense has already been processed.', $byId[$done->id]['message']);
        $this->assertStringContainsString('Insufficient advance balance', $byId[$bigSettlement->id]['message']);
        $this->assertSame('Expense not found.', $byId[987654321]['message']);

        $this->assertSame('approved', $ok1->fresh()->status);
        $this->assertSame('approved', $ok2->fresh()->status);
        $this->assertSame('pending', $bigSettlement->fresh()->status);
        $this->assertSame('month-end run', $ok1->fresh()->approval_remarks);
    }

    public function test_bulk_reject_and_its_limits_and_permissions(): void
    {
        $e = $this->expense($this->alice, ['status' => 'pending']);

        $this->as($this->hr)->postJson(route('expense.bulk-status'), ['ids' => [$e->id], 'status' => 'cancelled', 'remarks' => 'duplicate claim'])
            ->assertOk()->assertJsonPath('succeeded', 1);
        $this->assertSame('cancelled', $e->fresh()->status);
        $this->assertSame('duplicate claim', $e->fresh()->rejection_reason);

        $this->as($this->hr)->postJson(route('expense.bulk-status'), ['ids' => range(1, 101), 'status' => 'approved'])->assertStatus(422);
        $this->as($this->hr)->postJson(route('expense.bulk-status'), ['ids' => [$e->id], 'status' => 'complete'])->assertStatus(422);
        $this->as($this->alice)->postJson(route('expense.bulk-status'), ['ids' => [$e->id], 'status' => 'approved'])->assertStatus(403);
    }

    public function test_bulk_approve_respects_the_managers_team_scope(): void
    {
        $manager = User::withoutGlobalScopes()->where('tenant_id', $this->tenantId)->where('role', 'manager')->where('status', '1')->first();
        if (! $manager) {
            $this->markTestSkipped('no manager in the fixture tenant');
        }
        $team = User::managedBy($manager->id)->pluck('id');
        $stranger = User::withoutGlobalScopes()->where('tenant_id', $this->tenantId)->where('role', 'employee')->where('status', '1')->whereNotIn('id', $team)->first();
        if (! $stranger) {
            $this->markTestSkipped('every employee reports to the fixture manager');
        }

        $mine = $team->isNotEmpty() ? User::withoutGlobalScopes()->find($team->first()) : null;
        $outside = $this->expense($stranger, ['status' => 'pending', 'amount' => 5]);
        $ids = [$outside->id];
        $inside = null;
        if ($mine) {
            $inside = $this->expense($mine, ['status' => 'pending', 'amount' => 5]);
            $ids[] = $inside->id;
        }

        $res = $this->as($manager)->postJson(route('expense.bulk-status'), ['ids' => $ids, 'status' => 'approved'])->assertOk();
        $byId = collect($res->json('results'))->keyBy('id');

        $this->assertFalse($byId[$outside->id]['ok']);
        $this->assertStringContainsString('not authorized', $byId[$outside->id]['message']);
        $this->assertSame('pending', $outside->fresh()->status);
        if ($inside) {
            $this->assertTrue($byId[$inside->id]['ok']);
        }
    }
}
