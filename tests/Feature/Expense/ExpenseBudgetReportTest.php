<?php

namespace Tests\Feature\Expense;

use App\Exceptions\ExpenseException;
use App\Models\Expense;
use App\Models\ExpenseBudget;
use App\Models\ExpenseType;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\ExpenseAdvanceReminderNotification;
use App\Services\Expense\ExpenseAgeingService;
use App\Services\Expense\ExpenseBudgetService;
use App\Services\Expense\ExpenseService;
use App\Services\FeatureService;
use App\Services\RbacService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Expense Phase 4 — budgets (fiscal year, scoping, warn/block, usage), outstanding-advance ageing,
 * the three reports + CSV, and the advance-reminder command.
 */
class ExpenseBudgetReportTest extends TestCase
{
    use DatabaseTransactions;

    private int $tenantId;
    private User $hr;
    private User $alice;
    private User $bob;
    private ExpenseType $type;
    private string $fy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantId = (int) DB::table('users')->where('status', '1')->where('role', 'employee')->whereNotNull('tenant_id')
            ->select('tenant_id')->groupBy('tenant_id')->havingRaw('count(*) >= 2')->orderByRaw('count(*) desc')->value('tenant_id');

        $hr = $this->tenantId ? User::withoutGlobalScopes()->where('tenant_id', $this->tenantId)->whereIn('role', ['admin', 'hr'])->where('status', '1')->first() : null;
        $emps = $this->tenantId ? User::withoutGlobalScopes()->where('tenant_id', $this->tenantId)->where('role', 'employee')->where('status', '1')->orderBy('id')->limit(2)->get() : collect();

        if (! $hr || $emps->count() < 2) {
            $this->markTestSkipped('fixture users missing in the dev DB');
        }

        [$this->hr, $this->alice, $this->bob] = [$hr, $emps[0], $emps[1]];
        app()->instance('current_tenant', Tenant::find($this->tenantId));
        Storage::fake('local');

        $this->type = ExpenseType::create(['tenant_id' => $this->tenantId, 'name' => 'PHPUnit-' . uniqid(), 'status' => 1]);
        $this->fy = app(ExpenseBudgetService::class)->fiscalYearFor(now()->subDay());
    }

    // ---------------------------------------------------------------- helpers

    private function as(User $u): static
    {
        return $this->actingAs($u)->withSession(['tenant_id' => $this->tenantId])->withHeaders(['Accept' => 'application/json']);
    }

    private function expense(User $u, array $o = []): Expense
    {
        return Expense::create($o + [
            'tenant_id' => $this->tenantId, 'user_id' => $u->id, 'expense_type' => $this->type->id, 'amount' => 60,
            'date' => now()->subDay()->toDateString(), 'requirement_type' => 'reimbursement', 'status' => 'pending', 'description' => 'phpunit-budget',
        ]);
    }

    /** A budget that only this test's category feeds, so real company data cannot leak into the numbers. */
    private function budget(float $cap, string $mode = 'block', array $o = []): ExpenseBudget
    {
        return ExpenseBudget::create($o + ['tenant_id' => $this->tenantId, 'fiscal_year' => $this->fy, 'expense_type_id' => $this->type->id,
            'allocated_amount' => $cap, 'used_amount' => 0, 'remaining_amount' => $cap, 'enforcement' => $mode]);
    }

    private function svc(): ExpenseBudgetService
    {
        return app(ExpenseBudgetService::class);
    }

    private function ledger(User $u, string $type, float $amount, string $when): void
    {
        // expense_transactions.expense_id is NOT NULL — anchor the row to a throw-away approved advance
        $anchor = $this->expense($u, ['requirement_type' => 'advance', 'status' => 'approved', 'amount' => max(1, abs($amount)), 'description' => 'phpunit-ledger-anchor']);
        DB::table('expense_transactions')->insert(['tenant_id' => $this->tenantId, 'expense_id' => $anchor->id, 'user_id' => $u->id, 'transaction_type' => $type,
            'amount' => $amount, 'balance_before' => 0, 'balance_after' => 0, 'description' => 'phpunit', 'created_at' => $when, 'updated_at' => $when]);
    }

    private function balance(User $u, float $current): void
    {
        DB::table('expense_transactions')->where('user_id', $u->id)->delete();
        DB::table('user_expense_balances')->updateOrInsert(['user_id' => $u->id], ['tenant_id' => $this->tenantId, 'current_balance' => $current,
            'advance_balance' => $current, 'settlement_balance' => 0, 'reimbursement_balance' => 0, 'created_at' => now(), 'updated_at' => now()]);
    }

    // ================================================================ fiscal year

    public function test_fiscal_year_labels_follow_the_company_calendar(): void
    {
        $s = $this->svc();

        $this->assertSame('2025-26', $s->fiscalYearFor(Carbon::parse('2026-03-31')));
        $this->assertSame('2026-27', $s->fiscalYearFor(Carbon::parse('2026-04-01')));
        $this->assertSame('2026-27', $s->fiscalYearFor(Carbon::parse('2027-03-31')));
        $this->assertSame('2099-00', $s->fiscalYearFor(Carbon::parse('2099-06-01')), 'century rollover keeps a two-digit suffix');

        [$start, $end] = $s->range('2026-27');
        $this->assertSame(['2026-04-01', '2027-03-31'], [$start->toDateString(), $end->toDateString()]);

        config(['leave.fiscal_year_start_month' => 1, 'leave.fiscal_year_start_day' => 1]);   // calendar-year company
        $this->assertSame('2026', $s->fiscalYearFor(Carbon::parse('2026-01-01')));
        $this->assertSame('2025', $s->fiscalYearFor(Carbon::parse('2025-12-31')));
        [$start, $end] = $s->range('2026');
        $this->assertSame(['2026-01-01', '2026-12-31'], [$start->toDateString(), $end->toDateString()]);
    }

    // ============================================================ budget engine

    public function test_a_block_budget_refuses_the_approval_that_would_cross_the_cap_and_allows_landing_exactly_on_it(): void
    {
        $b = $this->budget(100, 'block');
        $svc = app(ExpenseService::class);

        $first = $this->expense($this->alice, ['amount' => 60]);
        $r = $svc->decide($first->id, $this->hr, 'approved', null);
        $this->assertSame([], $r['warnings']);

        $tooBig = $this->expense($this->bob, ['amount' => 60]);
        try {
            $svc->decide($tooBig->id, $this->hr, 'approved', null);
            $this->fail('60 + 60 exceeds a cap of 100');
        } catch (ExpenseException $e) {
            $this->assertSame(422, $e->httpStatus());
            $this->assertStringContainsString('Cannot approve', $e->getMessage());
            $this->assertStringContainsString('₹120.00 of ₹100.00', $e->getMessage());
        }
        $this->assertSame('pending', $tooBig->fresh()->status, 'a refused approval changes nothing');

        $exact = $this->expense($this->bob, ['amount' => 40, 'description' => 'exact']);
        $svc->decide($exact->id, $this->hr, 'approved', null);
        $this->assertSame('approved', $exact->fresh()->status);

        // convenience columns are kept in step
        $b->refresh();
        $this->assertEquals(100.00, (float) $b->used_amount);
        $this->assertEquals(0.00, (float) $b->remaining_amount);
    }

    public function test_a_warn_budget_approves_but_reports_the_breach_and_the_ui_payload_carries_it(): void
    {
        $this->budget(50, 'warn');
        $e = $this->expense($this->alice, ['amount' => 80]);

        $r = app(ExpenseService::class)->decide($e->id, $this->hr, 'approved', null);

        $this->assertSame('approved', $e->fresh()->status);
        $this->assertCount(1, $r['warnings']);
        $this->assertStringContainsString('Over budget', $r['warnings'][0]);

        $e2 = $this->expense($this->bob, ['amount' => 10]);
        $res = $this->as($this->hr)->postJson(route('expense.update-status', $e2->id), ['status' => 'approved'])->assertOk();
        $this->assertStringContainsString('Note: Over budget', $res->json('message'), 'the approver is told about the breach');
    }

    public function test_only_spend_counts_advances_children_cancelled_and_other_years_are_ignored(): void
    {
        $b = $this->budget(100, 'block');

        $this->expense($this->alice, ['requirement_type' => 'advance', 'status' => 'approved', 'amount' => 9000]);
        $parent = $this->expense($this->alice, ['requirement_type' => 'settlement', 'status' => 'approved', 'amount' => 30]);
        $this->expense($this->alice, ['status' => 'approved', 'amount' => 900, 'parent_expense_id' => $parent->id]);           // shortfall child
        $this->expense($this->alice, ['status' => 'cancelled', 'amount' => 900]);
        $this->expense($this->alice, ['status' => 'pending', 'amount' => 900]);
        $this->expense($this->alice, ['status' => 'approved', 'amount' => 900, 'date' => now()->subYears(2)->toDateString()]);   // another fiscal year
        $trashed = $this->expense($this->alice, ['status' => 'approved', 'amount' => 900]);
        $trashed->delete();
        $this->expense($this->alice, ['status' => 'complete', 'amount' => 20]);

        $this->assertSame(5000, $this->svc()->usedCents($b), 'settlement 30 + completed reimbursement 20');

        // advances are not evaluated at all
        $adv = $this->expense($this->bob, ['requirement_type' => 'advance', 'amount' => 999999]);
        $this->assertFalse($this->svc()->evaluate($adv)['blocked']);
    }

    public function test_budgets_apply_only_to_matching_dimensions_and_every_applicable_cap_counts(): void
    {
        $dept = DB::table('user_job_details')->where('user_id', $this->alice->id)->value('department');
        $other = $dept ? DB::table('departments')->where('tenant_id', $this->tenantId)->where('id', '!=', $dept)->value('id') : null;

        $anyType = $this->budget(1000, 'block', ['expense_type_id' => null]);                            // company-wide cap — irrelevant here
        $mine = $this->budget(100, 'block');                                                              // this category only
        $otherYear = $this->budget(1, 'block', ['fiscal_year' => '1999-00']);                            // wrong year -> not applicable
        $e = $this->expense($this->alice, ['amount' => 60]);

        $ids = $this->svc()->applicable($e)->pluck('id')->all();
        $this->assertContains($mine->id, $ids);
        $this->assertContains($anyType->id, $ids);
        $this->assertNotContains($otherYear->id, $ids);

        if ($dept && $other) {
            $mineDept = $this->budget(1, 'block', ['department_id' => $dept]);
            $otherDept = $this->budget(1, 'block', ['department_id' => $other]);
            $ids = $this->svc()->applicable($e)->pluck('id')->all();
            $this->assertContains($mineDept->id, $ids, "the claimant's own department");
            $this->assertNotContains($otherDept->id, $ids, "someone else's department");
            $this->assertTrue($this->svc()->evaluate($e)['blocked'], 'the tight department cap blocks even though the category cap has room');
        }

        // a category the claim is NOT in
        $foreignType = ExpenseType::create(['tenant_id' => $this->tenantId, 'name' => 'PHPUnit-B-' . uniqid(), 'status' => 1]);
        $notMine = $this->budget(1, 'block', ['expense_type_id' => $foreignType->id]);
        $this->assertNotContains($notMine->id, $this->svc()->applicable($e)->pluck('id')->all());
    }

    // ================================================================ budgets UI

    public function test_budget_management_needs_manage_and_validates_scope_and_duplicates(): void
    {
        $this->as($this->alice)->get(route('expense.budgets.index'))->assertStatus(403);
        $this->as($this->alice)->postJson(route('expense.budgets.store'), [])->assertStatus(403);

        $this->as($this->hr)->get(route('expense.budgets.index', ['fiscal_year' => $this->fy]))->assertOk();

        $payload = ['fiscal_year' => $this->fy, 'expense_type_id' => $this->type->id, 'allocated_amount' => '5000', 'enforcement' => 'block'];
        $this->as($this->hr)->postJson(route('expense.budgets.store'), $payload)->assertOk()->assertJsonPath('success', true);
        $b = ExpenseBudget::where('expense_type_id', $this->type->id)->where('fiscal_year', $this->fy)->firstOrFail();
        $this->assertSame($this->tenantId, (int) $b->tenant_id);
        $this->assertSame('block', $b->enforcement);

        // exactly-the-same scope is refused (the DB unique key cannot see NULL dimensions)
        $this->as($this->hr)->postJson(route('expense.budgets.store'), $payload)->assertStatus(422);
        // validation
        $this->as($this->hr)->postJson(route('expense.budgets.store'), ['allocated_amount' => '-1', 'enforcement' => 'block', 'fiscal_year' => $this->fy])->assertStatus(422);
        $this->as($this->hr)->postJson(route('expense.budgets.store'), $payload + ['x' => 1, 'enforcement' => 'maybe'])->assertStatus(422);
        $this->as($this->hr)->postJson(route('expense.budgets.store'), ['allocated_amount' => '5', 'enforcement' => 'warn', 'fiscal_year' => 'next year'])->assertStatus(422);
        $this->as($this->hr)->postJson(route('expense.budgets.store'), ['allocated_amount' => '5', 'enforcement' => 'warn', 'fiscal_year' => $this->fy, 'expense_type_id' => 999999999])->assertStatus(422);

        // update changes the cap + mode only, and refreshes the convenience columns
        $this->as($this->hr)->putJson(route('expense.budgets.update', $b->id), ['allocated_amount' => '7500.50', 'enforcement' => 'warn', 'fiscal_year' => '1900'])->assertOk();
        $b->refresh();
        $this->assertEquals(7500.50, (float) $b->allocated_amount);
        $this->assertSame('warn', $b->enforcement);
        $this->assertSame($this->fy, $b->fiscal_year, 'scope and year are fixed');
        $this->assertEquals(7500.50, (float) $b->remaining_amount);

        $this->as($this->hr)->putJson(route('expense.budgets.update', 999999999), ['allocated_amount' => '1', 'enforcement' => 'warn'])->assertStatus(404);
        $this->as($this->hr)->deleteJson(route('expense.budgets.destroy', $b->id))->assertOk();
        $this->assertNull(ExpenseBudget::find($b->id));

        $this->assertGreaterThanOrEqual(3, DB::table('audit_logs')->where('entity_type', 'ExpenseBudget')->where('entity_id', $b->id)->count(), 'create + update + delete are audited');
    }

    // ============================================================== ageing

    public function test_ageing_replays_the_ledger_oldest_advance_is_spent_first(): void
    {
        $svc = app(ExpenseAgeingService::class);
        $ev = fn (string $type, float $amt, string $when) => (object) ['transaction_type' => $type, 'amount' => $amt, 'created_at' => $when];

        // 100 on day -100, 100 on day -10; a settlement of 120 eats the whole old lot and 20 of the newer
        $lots = $svc->unsettledLots(collect([
            $ev('advance_credited', 100, now()->subDays(100)->toDateTimeString()),
            $ev('advance_credited', 100, now()->subDays(10)->toDateTimeString()),
            $ev('settlement_debited', 120, now()->subDays(5)->toDateTimeString()),
        ]));
        $this->assertCount(1, $lots);
        $this->assertSame(8000, $lots[0][1]);
        $this->assertSame(10, (int) $lots[0][0]->startOfDay()->diffInDays(now()->startOfDay()), 'the surviving money is the NEWER lot');

        // a void (negative credit) takes back the most recent money first
        $lots = $svc->unsettledLots(collect([
            $ev('advance_credited', 100, now()->subDays(50)->toDateTimeString()),
            $ev('advance_credited', 40, now()->subDays(2)->toDateTimeString()),
            $ev('advance_credited', -40, now()->subDays(1)->toDateTimeString()),
        ]));
        $this->assertCount(1, $lots);
        $this->assertSame(10000, $lots[0][1]);

        // fully settled -> nothing left
        $this->assertSame([], $svc->unsettledLots(collect([$ev('advance_credited', 10, now()->subDays(9)->toDateTimeString()), $ev('settlement_debited', 10, now()->toDateTimeString())])));
    }

    public function test_ageing_snapshot_buckets_by_age_and_flags_balance_the_ledger_cannot_explain(): void
    {
        $this->balance($this->alice, 300);
        $this->ledger($this->alice, 'advance_credited', 100, now()->subDays(95)->toDateTimeString());
        $this->ledger($this->alice, 'advance_credited', 100, now()->subDays(45)->toDateTimeString());
        $this->ledger($this->alice, 'advance_credited', 50, now()->subDays(3)->toDateTimeString());          // 250 accounted, balance 300 -> 50 unexplained

        $this->balance($this->bob, 10);                                                                       // a balance with NO ledger at all

        $snap = app(ExpenseAgeingService::class)->snapshot($this->tenantId, [$this->alice->id, $this->bob->id]);
        $rows = collect($snap['rows'])->keyBy('user_id');

        $a = $rows[$this->alice->id];
        $this->assertEquals(300.00, $a['balance']);
        $this->assertEquals(100.00, $a['buckets']['90+']);
        $this->assertEquals(100.00, $a['buckets']['31-60']);
        $this->assertEquals(50.00, $a['buckets']['0-30']);
        $this->assertEquals(0.00, $a['buckets']['61-90']);
        $this->assertEquals(50.00, $a['no_ledger']);
        $this->assertSame(95, $a['oldest_days']);

        $b = $rows[$this->bob->id];
        $this->assertEquals(10.00, $b['no_ledger']);
        $this->assertNull($b['oldest_days']);

        $this->assertEquals(310.00, $snap['totals']['balance']);

        // an as-of date in the past re-ages everything
        $past = app(ExpenseAgeingService::class)->snapshot($this->tenantId, [$this->alice->id], now()->subDays(40));
        $this->assertEquals(100.00, $past['rows'][0]['buckets']['31-60'], 'the 95-day-old lot was 55 days old 40 days ago');

        // RBAC restriction: only the ids asked for
        $this->assertSame([$this->bob->id], array_column(app(ExpenseAgeingService::class)->snapshot($this->tenantId, [$this->bob->id])['rows'], 'user_id'));
    }

    // ================================================================ reports

    public function test_reports_are_export_permission_gated(): void
    {
        foreach (['register', 'summary', 'ageing'] as $r) {
            $this->as($this->alice)->get(route('expense.reports.show', $r))->assertStatus(403);
            $this->as($this->alice)->get(route('expense.reports.show', $r) . '?export=csv')->assertStatus(403);
        }
        $this->as($this->hr)->get(route('expense.reports.show', 'nonsense'))->assertStatus(404);
        $this->as($this->hr)->get(route('expense.reports.hub'))->assertRedirect(route('expense.reports.show', 'register'));

        foreach (['register', 'summary', 'ageing'] as $r) {
            $this->as($this->hr)->get(route('expense.reports.show', $r))->assertOk();
        }

        // a manager without the `export` action must not get org data (skip if this tenant has no such user)
        $manager = User::withoutGlobalScopes()->where('tenant_id', $this->tenantId)->where('role', 'manager')->where('status', '1')->get()
            ->first(fn ($u) => app(RbacService::class)->scopeFor($u, 'expenses', 'export') === null);
        if ($manager) {
            $this->as($manager)->get(route('expense.reports.show', 'summary'))->assertStatus(403);
        }
    }

    public function test_expense_and_asset_reports_live_in_tabs_on_the_reports_page_not_in_the_sidebar(): void
    {
        $page = $this->as($this->hr)->get(route('report.attendance.index'))->assertOk()
            ->assertSee('id="expenseReportTab"', false)->assertSee('id="assetReportTab"', false)
            ->assertSee('href="#expenseReportTab"', false)->assertSee('href="#assetReportTab"', false);

        foreach (['register', 'summary', 'ageing'] as $r) {
            $page->assertSee(route('expense.reports.show', $r), false);
        }
        foreach (['register', 'employee-wise', 'summary', 'warranty-expiry'] as $a) {
            $page->assertSee(route("report.asset.$a.index"), false);
        }

        // the stand-alone sidebar entry is gone (the hub route itself still exists as a redirect)
        $this->assertStringNotContainsString('href="' . route('expense.reports.hub') . '"', $page->getContent());

        // the report page itself uses the shared Reports layout and links back to the Expense tab
        $this->as($this->hr)->get(route('expense.reports.show', 'register'))->assertOk()
            ->assertSee('content-area-header', false)
            ->assertSee(route('report.attendance.index') . '#expenseReportTab', false)
            ->assertDontSee('nav-pills', false);

        // a manager without the export action reaches Reports but is not offered the Expense tab
        $manager = User::withoutGlobalScopes()->where('tenant_id', $this->tenantId)->where('role', 'manager')->where('status', '1')->get()
            ->first(fn ($u) => app(RbacService::class)->scopeFor($u, 'expenses', 'export') === null);
        if ($manager) {
            $this->as($manager)->get(route('report.attendance.index'))->assertOk()
                ->assertDontSee('id="expenseReportTab"', false)->assertSee('id="assetReportTab"', false);
        }
    }

    public function test_summary_report_groups_by_category_excludes_shortfall_children_and_exports_csv(): void
    {
        $parent = $this->expense($this->alice, ['requirement_type' => 'settlement', 'status' => 'approved', 'amount' => 1000]);
        $this->expense($this->alice, ['status' => 'approved', 'amount' => 700, 'parent_expense_id' => $parent->id]);
        $this->expense($this->bob, ['status' => 'pending', 'amount' => 25.5]);
        $this->expense($this->bob, ['status' => 'cancelled', 'amount' => 4]);
        $this->expense($this->bob, ['requirement_type' => 'advance', 'status' => 'approved', 'amount' => 5000]);

        $q = ['group' => 'category', 'from_date' => now()->subDays(10)->toDateString(), 'to_date' => now()->toDateString()];
        $page = $this->as($this->hr)->get(route('expense.reports.show', 'summary') . '?' . http_build_query($q))->assertOk();
        $page->assertSee($this->type->name);

        $csv = $this->as($this->hr)->get(route('expense.reports.show', 'summary') . '?' . http_build_query($q + ['export' => 'csv']));
        $csv->assertOk()->assertHeader('Content-Type', 'text/csv; charset=utf-8');
        $this->assertStringContainsString('attachment; filename="expense-summary-', $csv->headers->get('Content-Disposition'));

        $lines = array_map('str_getcsv', array_filter(explode("\n", ltrim($csv->getContent(), "\xEF\xBB\xBF"))));
        $row = collect($lines)->first(fn ($l) => ($l[0] ?? null) === $this->type->name);
        $this->assertNotNull($row, 'the category appears');
        $this->assertSame('3', $row[1], 'settlement + pending + cancelled; the split-off child and the advance are not counted');
        $this->assertSame(['1025.50', '1000.00', '25.50', '4.00'], array_slice($row, 2, 4), 'submitted (excl. cancelled), approved, pending, rejected/withdrawn');
    }

    public function test_register_report_lists_payments_with_voucher_and_export_is_formula_safe(): void
    {
        $e = $this->expense($this->alice, ['requirement_type' => 'advance', 'status' => 'approved', 'amount' => 500]);
        $this->balance($this->alice, 0);
        $paid = app(\App\Services\Expense\ExpensePaymentService::class)->payBatch($this->hr, [['expense_id' => $e->id, 'amount' => '500']], [
            'payment_date' => now()->toDateString(), 'payment_mode' => 'bank_transfer', 'reference_number' => '=HYPERLINK("http://evil")',
        ], null, fn () => true);

        $csv = $this->as($this->hr)->get(route('expense.reports.show', 'register') . '?export=csv&from_date=' . now()->subDay()->toDateString());
        $csv->assertOk();
        $body = ltrim($csv->getContent(), "\xEF\xBB\xBF");

        $this->assertStringContainsString($paid['batch']->voucher_number, $body);
        $this->assertStringContainsString($e->expense_number, $body);
        $this->assertStringContainsString("'=HYPERLINK", $body, 'a leading = is neutralised');
        $this->assertStringNotContainsString(',=HYPERLINK', $body);

        // a voided payment stays in the register, flagged
        app(\App\Services\Expense\ExpensePaymentService::class)->voidBatch($this->hr, $paid['batch']->id, 'entered twice', fn () => true);
        $again = $this->as($this->hr)->get(route('expense.reports.show', 'register') . '?export=csv&status=voided&from_date=' . now()->subDay()->toDateString())->getContent();
        $this->assertStringContainsString('voided: entered twice', $again);
        $posted = $this->as($this->hr)->get(route('expense.reports.show', 'register') . '?export=csv&status=posted&from_date=' . now()->subDay()->toDateString())->getContent();
        $this->assertStringNotContainsString($paid['batch']->voucher_number, $posted);

        $this->assertGreaterThanOrEqual(1, DB::table('audit_logs')->where('action', 'expenses.report_exported')->where('actor_id', $this->hr->id)->count());
    }

    public function test_ageing_report_page_and_csv(): void
    {
        $this->balance($this->alice, 200);
        $this->ledger($this->alice, 'advance_credited', 200, now()->subDays(70)->toDateTimeString());

        $this->as($this->hr)->get(route('expense.reports.show', 'ageing'))->assertOk()->assertSee($this->alice->name);

        $csv = $this->as($this->hr)->get(route('expense.reports.show', 'ageing') . '?export=csv')->getContent();
        $this->assertStringContainsString('61-90', $csv);
        $this->assertStringContainsString('TOTAL', $csv);
        $this->assertStringContainsString($this->alice->name, $csv);
    }

    // ============================================================== reminders

    public function test_advance_reminders_go_to_stale_holders_once_per_window_and_dry_run_sends_nothing(): void
    {
        if (! app(FeatureService::class)->enabled($this->tenantId, 'expense_management')) {
            $this->markTestSkipped('expense_management is off for this fixture tenant');
        }

        $this->balance($this->alice, 400);
        $this->ledger($this->alice, 'advance_credited', 400, now()->subDays(45)->toDateTimeString());
        $this->balance($this->bob, 50);
        $this->ledger($this->bob, 'advance_credited', 50, now()->subDays(5)->toDateTimeString());              // too recent
        $mine = fn (User $u) => DB::table('notifications')->where('type', ExpenseAdvanceReminderNotification::class)->where('notifiable_id', $u->id)->count();
        $before = [$mine($this->alice), $mine($this->bob)];

        $this->artisan('expense:advance-reminders', ['--tenant' => $this->tenantId, '--dry-run' => true])->expectsOutputToContain('would be sent')->assertSuccessful();
        $this->assertSame($before, [$mine($this->alice), $mine($this->bob)], 'a dry run sends nothing');

        $this->artisan('expense:advance-reminders', ['--tenant' => $this->tenantId])->assertSuccessful();
        $this->assertSame($before[0] + 1, $mine($this->alice), 'the 45-day-old advance is reminded');
        $this->assertSame($before[1], $mine($this->bob), 'a 5-day-old advance is not');

        $n = DB::table('notifications')->where('type', ExpenseAdvanceReminderNotification::class)->where('notifiable_id', $this->alice->id)->latest('created_at')->first();
        $this->assertStringContainsString('400.00', $n->data);

        $this->artisan('expense:advance-reminders', ['--tenant' => $this->tenantId])->assertSuccessful();
        $this->assertSame($before[0] + 1, $mine($this->alice), 'not reminded again inside the 7-day window');

        $this->artisan('expense:advance-reminders', ['--tenant' => $this->tenantId, '--days' => 3])->assertSuccessful();
        $this->assertSame($before[0] + 1, $mine($this->alice));
        $this->assertSame($before[1] + 1, $mine($this->bob), 'a lower --days threshold now includes the recent advance');
    }
}
