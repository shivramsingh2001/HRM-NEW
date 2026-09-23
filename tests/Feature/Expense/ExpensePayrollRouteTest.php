<?php

namespace Tests\Feature\Expense;

use App\Exceptions\ExpenseBatchException;
use App\Exceptions\ExpenseException;
use App\Models\Expense;
use App\Models\ExpenseType;
use App\Models\ExpensePayrollLink;
use App\Models\MonthlyPayroll;
use App\Models\PayrollComponent;
use App\Models\PayrollEmployeeStructure;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Expense\ExpensePaymentService;
use App\Services\Expense\ExpenseReimbursementPayrollService;
use App\Services\FeatureService;
use App\Services\Payroll\PayrollCalculationEngine;
use App\Support\ExpenseFeatures;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Expense Phase 5 — paying approved reimbursements THROUGH PAYROLL, per company via the
 * `expense_payroll_link` feature: routing, the payslip link lifecycle (queued -> linked -> paid ->
 * reopened), the voucher route staying closed for routed claims, and the feature / dependency switches.
 */
class ExpensePayrollRouteTest extends TestCase
{
    use DatabaseTransactions;

    private const MONTH = '2031-03';   // far from any real payslip

    private int $tenantId;
    private User $admin;
    private User $alice;
    private User $bob;
    private ExpenseType $type;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantId = (int) DB::table('users')->where('status', '1')->where('role', 'employee')->whereNotNull('tenant_id')
            ->select('tenant_id')->groupBy('tenant_id')->havingRaw('count(*) >= 2')->orderByRaw('count(*) desc')->value('tenant_id');

        $admin = $this->tenantId ? User::withoutGlobalScopes()->where('tenant_id', $this->tenantId)->whereIn('role', ['admin', 'hr'])->where('status', '1')->orderByRaw("role = 'admin' desc")->first() : null;
        $emps = $this->tenantId ? User::withoutGlobalScopes()->where('tenant_id', $this->tenantId)->where('role', 'employee')->where('status', '1')->orderBy('id')->limit(2)->get() : collect();

        if (! $admin || $emps->count() < 2) {
            $this->markTestSkipped('fixture users missing in the dev DB');
        }

        [$this->admin, $this->alice, $this->bob] = [$admin, $emps[0], $emps[1]];
        app()->instance('current_tenant', Tenant::find($this->tenantId));
        Storage::fake('local');

        $this->type = ExpenseType::create(['tenant_id' => $this->tenantId, 'name' => 'PHPUnit-' . uniqid(), 'status' => 1]);

        // The company must run the dynamic engine and have payroll + expense modules for the route to exist at all.
        DB::table('tenants')->where('id', $this->tenantId)->update(['payroll_dynamic_ui_enabled' => 1]);
        foreach (['payroll', 'expense_management'] as $key) {
            $this->override($key, true);
        }
        $this->override('expense_payroll_link', true);
    }

    // ---------------------------------------------------------------- helpers

    private function override(string $key, bool $on, ?int $tenantId = null): void
    {
        $tenantId ??= $this->tenantId;
        $superAdminId = DB::table('super_admins')->value('id');
        if (! $superAdminId) {
            $this->markTestSkipped('no super_admins row to attribute the override to');
        }

        DB::table('tenant_feature_overrides')->where('tenant_id', $tenantId)->where('feature_key', $key)->delete();
        DB::table('tenant_feature_overrides')->insert([
            'tenant_id' => $tenantId, 'feature_key' => $key, 'is_enabled' => $on ? 1 : 0,
            'reason' => 'phpunit', 'overridden_by' => $superAdminId, 'created_at' => now(), 'updated_at' => now(),
        ]);
        app(FeatureService::class)->bust($tenantId);
    }

    private function svc(): ExpenseReimbursementPayrollService
    {
        return app(ExpenseReimbursementPayrollService::class);
    }

    private function as(User $u): static
    {
        return $this->actingAs($u)->withSession(['tenant_id' => $this->tenantId])->withHeaders(['Accept' => 'application/json']);
    }

    private function reimb(User $u, float $amount = 250, array $o = []): Expense
    {
        return Expense::create($o + [
            'tenant_id' => $this->tenantId, 'user_id' => $u->id, 'expense_type' => $this->type->id, 'amount' => $amount,
            'date' => now()->subDay()->toDateString(), 'requirement_type' => 'reimbursement', 'status' => 'approved', 'description' => 'phpunit-payroll',
        ]);
    }

    private function slip(User $u, array $o = []): MonthlyPayroll
    {
        return MonthlyPayroll::create($o + [
            'tenant_id' => $this->tenantId, 'user_id' => $u->id, 'payroll_month' => self::MONTH, 'processing_date' => now()->toDateString(),
            'gross_earnings' => 50000, 'total_deductions' => 5000, 'net_payable' => 45000, 'payment_status' => 'pending', 'processed_by' => $this->admin->id,
        ]);
    }

    private function send(array $expenses, string $month = self::MONTH, ?callable $can = null): array
    {
        return $this->svc()->sendToPayroll($this->admin, array_map(fn ($e) => $e->id, $expenses), $month, $can ?? fn () => true);
    }

    private function link(Expense $e): ?ExpensePayrollLink
    {
        return ExpensePayrollLink::where('expense_id', $e->id)->active()->first();
    }

    /** Route expenses and attach exactly these to a payslip, as generating the payroll would. */
    private function linked(array|Expense $expenses, MonthlyPayroll $slip): void
    {
        $expenses = is_array($expenses) ? $expenses : [$expenses];
        $this->send($expenses);
        $this->svc()->applyToPayroll($slip->id, $this->tenantId, array_map(fn ($e) => $e->id, $expenses));
    }

    private function balance(User $u): float
    {
        return (float) DB::table('user_expense_balances')->where('user_id', $u->id)->value('reimbursement_balance');
    }

    // ================================================================ feature switch

    public function test_the_route_needs_the_company_switch_and_every_dependency(): void
    {
        $this->assertTrue(ExpenseFeatures::payrollRouteEnabled($this->tenantId));

        $this->override('expense_payroll_link', false);
        $this->assertFalse(ExpenseFeatures::payrollRouteEnabled($this->tenantId), 'the company switch is off');
        $this->override('expense_payroll_link', true);

        $this->override('payroll', false);
        $this->assertFalse(ExpenseFeatures::payrollRouteEnabled($this->tenantId), 'no payroll module');
        $this->override('payroll', true);

        $this->override('expense_management', false);
        $this->assertFalse(ExpenseFeatures::payrollRouteEnabled($this->tenantId), 'no expense module');
        $this->override('expense_management', true);

        DB::table('tenants')->where('id', $this->tenantId)->update(['payroll_dynamic_ui_enabled' => 0]);
        $this->assertFalse(ExpenseFeatures::payrollRouteEnabled($this->tenantId), 'only the dynamic engine can add the line to a payslip');
        DB::table('tenants')->where('id', $this->tenantId)->update(['payroll_dynamic_ui_enabled' => 1]);

        $this->assertTrue(ExpenseFeatures::payrollRouteEnabled($this->tenantId));
        $this->assertFalse(ExpenseFeatures::payrollRouteEnabled(null));
    }

    public function test_the_default_is_off_and_one_company_switching_it_on_does_not_touch_another(): void
    {
        DB::table('tenant_feature_overrides')->where('tenant_id', $this->tenantId)->where('feature_key', 'expense_payroll_link')->delete();
        app(FeatureService::class)->bust($this->tenantId);
        $this->assertFalse(config('features.expense_payroll_link.default'), 'off by default: existing companies are unaffected');
        $this->assertFalse(ExpenseFeatures::payrollRouteEnabled($this->tenantId));

        $this->override('expense_payroll_link', true);
        $other = (int) DB::table('tenants')->where('id', '!=', $this->tenantId)->value('id');
        if ($other) {
            $this->assertFalse(app(FeatureService::class)->enabled($other, 'expense_payroll_link'), 'company B is unaffected by company A');
        }
    }

    public function test_sending_is_refused_when_the_switch_is_off_over_http_and_in_the_service(): void
    {
        $e = $this->reimb($this->alice);
        $this->override('expense_payroll_link', false);

        try {
            $this->send([$e]);
            $this->fail('must be refused');
        } catch (ExpenseException $ex) {
            $this->assertSame(403, $ex->httpStatus());
        }
        $this->as($this->admin)->postJson(route('expense.payroll.send'), ['expense_ids' => [$e->id], 'month' => self::MONTH])->assertStatus(403);
        $this->assertNull($e->fresh()->payout_channel);
    }

    // ================================================================ routing

    public function test_sending_routes_the_reimbursement_and_closes_the_voucher_route_for_it(): void
    {
        $e = $this->reimb($this->alice, 250.50);

        $r = $this->send([$e]);

        $this->assertSame(1, $r['sent']);
        $this->assertSame('250.50', $r['total']);
        $e->refresh();
        $this->assertSame('payroll', $e->payout_channel);
        $this->assertSame(self::MONTH, $e->payroll_target_month);
        $this->assertTrue($e->isRoutedThroughPayroll());

        $link = $this->link($e);
        $this->assertSame('queued', $link->status);
        $this->assertEquals(250.50, (float) $link->amount);
        $this->assertSame($this->alice->id, (int) $link->user_id);
        $this->assertSame(1, DB::table('audit_logs')->where('entity_type', 'Expense')->where('entity_id', $e->id)->where('action', 'expenses.sent_to_payroll')->count());
        $this->assertStringContainsString('Sent to payroll', DB::table('expense_status_histories')->where('expense_id', $e->id)->orderByDesc('id')->value('remarks'));

        // not payable by voucher any more — neither listed nor postable
        $this->assertNotContains($e->id, Expense::payable()->pluck('id')->all());
        $pay = fn () => app(ExpensePaymentService::class)->payBatch($this->admin, [['expense_id' => $e->id, 'amount' => '250.50']],
            ['payment_date' => now()->toDateString(), 'payment_mode' => 'cash'], null, fn () => true);
        try {
            $pay();
            $this->fail('a voucher must not pay a payroll-routed reimbursement');
        } catch (ExpenseBatchException $ex) {
            $this->assertStringContainsString('paid through payroll', $ex->getMessage());
            $this->assertSame(409, $ex->httpStatus());
        }
        $this->assertSame(0, DB::table('expense_payments')->where('expense_id', $e->id)->count());

        // the employee cannot withdraw it from under finance
        try {
            app(\App\Services\Expense\ExpenseService::class)->withdraw($this->alice, $e->id, 'changed my mind');
            $this->fail('withdraw refused while routed');
        } catch (ExpenseException $ex) {
            $this->assertStringContainsString('sent to payroll', $ex->getMessage());
        }
    }

    public function test_only_approved_unpaid_reimbursements_can_be_sent_and_a_bad_line_sends_nothing(): void
    {
        $good = $this->reimb($this->alice);
        $advance = $this->reimb($this->alice, 100, ['requirement_type' => 'advance']);
        $pending = $this->reimb($this->alice, 100, ['status' => 'pending']);
        $partly = $this->reimb($this->bob, 300);
        app(ExpensePaymentService::class)->payBatch($this->admin, [['expense_id' => $partly->id, 'amount' => '100']],
            ['payment_date' => now()->toDateString(), 'payment_mode' => 'cash'], null, fn () => true);

        try {
            $this->send([$good, $advance, $pending, $partly]);
            $this->fail('three lines are invalid');
        } catch (ExpenseBatchException $ex) {
            $this->assertCount(3, $ex->lineErrors());
            $this->assertSame(422, $ex->httpStatus());
            $joined = implode(' | ', array_column($ex->lineErrors(), 'message'));
            $this->assertStringContainsString('advances are paid by voucher', $joined);
            $this->assertStringContainsString('Only approved', $joined);
            $this->assertStringContainsString('already been paid by voucher', $joined);
        }
        $this->assertNull($good->fresh()->payout_channel, 'all-or-nothing: the good line was not sent either');
        $this->assertSame(0, ExpensePayrollLink::where('tenant_id', $this->tenantId)->whereIn('expense_id', [$good->id, $advance->id, $pending->id, $partly->id])->count());

        $this->send([$good]);
        try {
            $this->send([$good]);
            $this->fail('cannot send twice');
        } catch (ExpenseException $ex) {
            $this->assertStringContainsString('Already sent', $ex->getMessage());
        }

        foreach (['2031-13', '2031-3', 'March', ''] as $bad) {
            try {
                $this->send([$this->reimb($this->alice, 5)], $bad);
                $this->fail("month {$bad}");
            } catch (ExpenseException $ex) {
                $this->assertSame(422, $ex->httpStatus());
            }
        }
        try {
            $this->send([$this->reimb($this->alice, 5)], self::MONTH, fn () => false);
            $this->fail('out of scope');
        } catch (ExpenseException $ex) {
            $this->assertSame(403, $ex->httpStatus());
        }
    }

    public function test_an_existing_payslip_decides_whether_the_month_is_still_open(): void
    {
        $e = $this->reimb($this->alice);
        $slip = $this->slip($this->alice, ['payment_status' => 'processed']);

        try {
            $this->send([$e]);
            $this->fail('the month is already processed');
        } catch (ExpenseBatchException $ex) {
            $this->assertStringContainsString('already processed', $ex->getMessage());
            $this->assertStringContainsString('choose a later month', $ex->getMessage());
        }

        $slip->update(['payment_status' => 'pending']);
        $r = $this->send([$e]);
        $this->assertSame([$this->alice->name], $r['pending_payslips'], 'a pending payslip is allowed, and the caller is told to re-save it');
    }

    public function test_the_database_itself_allows_one_active_link_per_expense(): void
    {
        $e = $this->reimb($this->alice);
        $this->send([$e]);

        $this->expectException(QueryException::class);
        ExpensePayrollLink::create(['tenant_id' => $this->tenantId, 'expense_id' => $e->id, 'user_id' => $this->alice->id, 'target_month' => self::MONTH, 'amount' => 250, 'status' => 'queued']);
    }

    public function test_release_before_payroll_returns_it_to_vouchers_and_it_can_be_sent_again(): void
    {
        $e = $this->reimb($this->alice);
        $this->send([$e]);

        $this->svc()->release($this->admin, $e->id, 'finance prefers a voucher', fn () => true);

        $e->refresh();
        $this->assertNull($e->payout_channel);
        $this->assertNull($e->payroll_target_month);
        $this->assertNull($this->link($e));
        $this->assertSame('released', ExpensePayrollLink::where('expense_id', $e->id)->value('status'));
        $this->assertContains($e->id, Expense::payable()->pluck('id')->all());

        $paid = app(ExpensePaymentService::class)->payBatch($this->admin, [['expense_id' => $e->id, 'amount' => '250']],
            ['payment_date' => now()->toDateString(), 'payment_mode' => 'upi'], null, fn () => true);
        $this->assertSame('complete', $e->fresh()->status);
        $this->assertNotNull($paid['batch']->voucher_number);

        // a released claim that is still unpaid can go to payroll again (history rows stay)
        $again = $this->reimb($this->bob);
        $this->send([$again]);
        $this->svc()->release($this->admin, $again->id, 'oops wrong month', fn () => true);
        $this->send([$again], '2031-04');
        $this->assertSame(2, ExpensePayrollLink::where('expense_id', $again->id)->count());
        $this->assertSame('2031-04', $this->link($again)->target_month);

        try {
            $this->svc()->release($this->admin, $e->id, 'nothing to release', fn () => true);
            $this->fail('not routed');
        } catch (ExpenseException $ex) {
            $this->assertSame(409, $ex->httpStatus());
        }
        try {
            $this->svc()->release($this->admin, $again->id, 'x', fn () => true);
            $this->fail('reason too short');
        } catch (ExpenseException $ex) {
            $this->assertSame(422, $ex->httpStatus());
        }
    }

    // ================================================================ payroll engine side

    public function test_lines_for_a_month_carry_forward_only_queued_items_whose_month_has_arrived(): void
    {
        $now = $this->reimb($this->alice, 100);
        $later = $this->reimb($this->alice, 200);
        $other = $this->reimb($this->bob, 300);
        $this->send([$now], '2031-03');
        $this->send([$later], '2031-06');
        $this->send([$other], '2031-03');

        $numbers = fn (string $month) => $this->svc()->linesFor($this->tenantId, $this->alice->id, $month)->pluck('expense_number')->all();

        $this->assertSame([], $numbers('2031-02'), 'target month not reached');
        $this->assertSame([$now->expense_number], $numbers('2031-03'));
        $this->assertSame([$now->expense_number], $numbers('2031-05'), 'an unpaid earlier one carries forward');
        $this->assertEqualsCanonicalizing([$now->expense_number, $later->expense_number], $numbers('2031-06'));

        $line = $this->svc()->linesFor($this->tenantId, $this->alice->id, '2031-03')->first();
        $this->assertEquals(100.00, $line->amount);
        $this->assertSame($now->id, $line->expense_id);

        // a claim that is no longer approved (e.g. cancelled) is not paid
        $now->update(['status' => 'cancelled']);
        $this->assertSame([], $numbers('2031-03'));
    }

    public function test_switching_the_feature_off_stops_new_pickups_but_keeps_what_a_payslip_already_owns(): void
    {
        $queued = $this->reimb($this->alice, 100);
        $owned = $this->reimb($this->alice, 200);
        $slip = $this->slip($this->alice);
        $this->send([$queued]);
        $this->linked($owned, $slip);

        $this->override('expense_payroll_link', false);

        $lines = $this->svc()->linesFor($this->tenantId, $this->alice->id, self::MONTH);
        $this->assertSame([$owned->expense_number], $lines->pluck('expense_number')->all(),
            'the payslip keeps its line; the merely-queued one is not picked up any more');

        // and finance can still hand both back
        $this->svc()->release($this->admin, $queued->id, 'feature turned off', fn () => true);
        $this->svc()->release($this->admin, $owned->id, 'feature turned off', fn () => true);
        $this->assertNull($queued->fresh()->payout_channel);
        $this->assertNull($owned->fresh()->payout_channel);
    }

    public function test_applying_to_a_payslip_is_idempotent_and_lets_go_of_dropped_lines(): void
    {
        [$e1, $e2] = [$this->reimb($this->alice, 100), $this->reimb($this->alice, 200)];
        $foreign = $this->reimb($this->bob, 50);
        $slip = $this->slip($this->alice);
        $this->send([$e1, $e2, $foreign]);

        $status = fn (Expense $e) => $this->link($e)->status;

        $this->svc()->applyToPayroll($slip->id, $this->tenantId, [$e1->id, $e2->id, $foreign->id]);
        $this->assertSame(['linked', 'linked', 'queued'], [$status($e1), $status($e2), $status($foreign)], "another employee's expense is never linked to this payslip");
        $this->assertSame($slip->id, (int) $this->link($e1)->monthly_payroll_id);

        $this->svc()->applyToPayroll($slip->id, $this->tenantId, [$e1->id, $e2->id]);      // same again: nothing changes
        $this->assertSame(['linked', 'linked'], [$status($e1), $status($e2)]);

        $this->svc()->applyToPayroll($slip->id, $this->tenantId, [$e1->id]);                // recalculated without e2
        $this->assertSame(['linked', 'queued'], [$status($e1), $status($e2)]);
        $this->assertNull($this->link($e2)->monthly_payroll_id);

        $this->svc()->applyToPayroll($slip->id, $this->tenantId, []);
        $this->assertSame(['queued', 'queued'], [$status($e1), $status($e2)]);

        $this->svc()->applyToPayroll($slip->id, $this->tenantId, [$e1->id]);
        $this->assertSame(1, $this->svc()->revokeForPayroll($slip->id, $this->tenantId), 'deleting the payslip hands its lines back');
        $this->assertSame('queued', $status($e1));
    }

    public function test_the_engine_adds_one_non_taxable_earning_per_reimbursement_and_only_while_routed(): void
    {
        $structure = PayrollEmployeeStructure::withoutGlobalScope('tenant')->where('tenant_id', $this->tenantId)
            ->whereDate('effective_from', '<=', '2031-03-31')->where(fn ($q) => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', '2031-03-31'))->first();
        $employee = $structure ? User::withoutGlobalScopes()->where('tenant_id', $this->tenantId)->where('status', 1)->find($structure->user_id) : null;
        if (! $employee) {
            $this->markTestSkipped('no employee with a dynamic payroll structure in the fixture tenant');
        }

        $engine = app(PayrollCalculationEngine::class);
        $code = ExpenseReimbursementPayrollService::CODE;
        $base = $engine->calculate($employee, $this->tenantId, self::MONTH);
        $this->assertSame([], array_values(array_filter($base['line_items'], fn ($l) => $l['code'] === $code)), 'nothing routed, nothing added');

        $e = $this->reimb($employee, 1234.56);
        $this->send([$e]);
        $with = $engine->calculate($employee, $this->tenantId, self::MONTH);

        $lines = array_values(array_filter($with['line_items'], fn ($l) => $l['code'] === $code));
        $this->assertCount(1, $lines);
        $this->assertSame('earning', $lines[0]['component_type'], 'only earning/deduction feed gross and net');
        $this->assertFalse($lines[0]['is_taxable']);
        $this->assertEquals(1234.56, $lines[0]['amount']);
        $this->assertSame($e->id, $lines[0]['source_id']);
        $this->assertSame(ExpenseReimbursementPayrollService::lineName($e->expense_number), $lines[0]['name']);
        $this->assertEqualsWithDelta($base['gross_earnings'] + 1234.56, $with['gross_earnings'], 0.005);
        $this->assertEqualsWithDelta($base['net_payable'] + 1234.56, $with['net_payable'], 0.005, 'it reaches take-home pay');
        $this->assertEqualsWithDelta($base['total_deductions'], $with['total_deductions'], 0.005, 'and changes no statutory deduction');

        $this->override('expense_payroll_link', false);
        $off = $engine->calculate($employee, $this->tenantId, self::MONTH);
        $this->assertEqualsWithDelta($base['net_payable'], $off['net_payable'], 0.005, 'feature off: an unlinked claim is not added');
    }

    // ================================================================ paying with the salary

    public function test_marking_the_payslip_paid_pays_the_reimbursements_through_the_normal_ledger(): void
    {
        [$e1, $e2] = [$this->reimb($this->alice, 100.25), $this->reimb($this->alice, 200)];
        $slip = $this->slip($this->alice);
        $this->linked([$e1, $e2], $slip);
        $before = $this->balance($this->alice);

        $slip->update(['payment_status' => 'paid', 'payment_date' => '2031-03-31']);
        $this->svc()->onPayrollStatusChange($slip->fresh(), 'pending', 'paid', $this->admin);

        foreach ([$e1, $e2] as $e) {
            $e->refresh();
            $this->assertSame('complete', $e->status);
            $this->assertEquals((float) $e->amount, (float) $e->paid_amount);
            $p = $e->payments()->first();
            $this->assertSame('payroll', $p->payment_mode);
            $this->assertSame('PAYROLL-' . $slip->id, $p->reference_number);
            $this->assertSame('2031-03-31', $p->payment_date->toDateString());
            $this->assertSame('paid', $this->paidLink($e)->status);
            $this->assertSame($p->id, (int) $this->paidLink($e)->expense_payment_id);
        }
        $this->assertEquals($before + 300.25, $this->balance($this->alice), 'the reimbursement ledger was credited');
        $this->assertSame(1, DB::table('expense_payment_batches')->where('id', $e1->payments()->value('batch_id'))->where('payment_mode', 'payroll')->count(), 'one payroll voucher per payslip');
        $this->assertSame($e1->payments()->value('batch_id'), $e2->payments()->value('batch_id'));

        // marking paid again changes nothing
        $this->svc()->onPayrollStatusChange($slip->fresh(), 'paid', 'paid', $this->admin);
        $this->assertSame(1, $e1->payments()->count());
    }

    private function paidLink(Expense $e): ?ExpensePayrollLink
    {
        return ExpensePayrollLink::where('expense_id', $e->id)->where('status', 'paid')->first();
    }

    public function test_a_payslip_is_not_paid_when_one_of_its_reimbursements_can_no_longer_be_paid(): void
    {
        [$ok, $gone] = [$this->reimb($this->alice, 100), $this->reimb($this->alice, 200)];
        $slip = $this->slip($this->alice);
        $this->linked([$ok, $gone], $slip);
        $gone->update(['status' => 'cancelled']);

        try {
            $this->svc()->onPayrollStatusChange($slip->fresh(), 'pending', 'paid', $this->admin);
            $this->fail('the cancelled claim cannot be paid');
        } catch (ExpenseException $e) {
            $this->assertStringContainsString('Only approved', $e->getMessage());
        }
        $this->assertSame(0, DB::table('expense_payments')->whereIn('expense_id', [$ok->id, $gone->id])->count(), 'all-or-nothing: the good one was not paid either');
        $this->assertSame('linked', ExpensePayrollLink::where('expense_id', $ok->id)->value('status'));
    }

    public function test_reopening_a_paid_payslip_reverses_the_payment_and_paying_again_works(): void
    {
        $e = $this->reimb($this->alice, 500);
        $slip = $this->slip($this->alice);
        $this->linked($e, $slip);
        $before = $this->balance($this->alice);
        $this->svc()->onPayrollStatusChange($slip->fresh(), 'pending', 'paid', $this->admin);
        $this->assertSame('complete', $e->fresh()->status);

        // a voucher screen cannot undo a payroll payment behind payroll's back
        $paymentId = $e->payments()->value('id');
        $batchId = $e->payments()->value('batch_id');
        foreach ([
            fn () => app(ExpensePaymentService::class)->voidBatch($this->admin, $batchId, 'wrong', fn () => true),
            fn () => app(ExpensePaymentService::class)->voidPayment($this->admin, $paymentId, 'wrong', fn () => true),
            fn () => app(ExpensePaymentService::class)->update($this->admin, $paymentId, ['payment_date' => now()->toDateString(), 'payment_mode' => 'cash', 'amount' => '500'], fn () => true),
        ] as $attempt) {
            try {
                $attempt();
                $this->fail('payroll payments are managed from the payslip');
            } catch (ExpenseException $ex) {
                $this->assertSame(409, $ex->httpStatus());
                $this->assertStringContainsString('payroll', $ex->getMessage());
            }
        }
        $this->as($this->admin)->postJson(route('expense.vouchers.void', $batchId), ['reason' => 'trying'])->assertStatus(409);

        // reopening the payslip (paid -> pending) reverses it
        $this->svc()->onPayrollStatusChange($slip->fresh(), 'paid', 'pending', $this->admin);

        $e->refresh();
        $this->assertSame('approved', $e->status);
        $this->assertEquals(0.0, (float) $e->paid_amount);
        $this->assertEquals($before, $this->balance($this->alice), 'the reimbursement ledger is back where it was');
        $this->assertSame('voided', DB::table('expense_payments')->where('id', $paymentId)->value('status'));
        $this->assertSame('voided', DB::table('expense_payment_batches')->where('id', $batchId)->value('status'));
        $link = $this->link($e);
        $this->assertSame('linked', $link->status, 'it stays on the payslip, ready for the corrected payslip to be paid');
        $this->assertSame($slip->id, (int) $link->monthly_payroll_id);
        $this->assertNull($link->expense_payment_id);
        $this->assertSame('payroll', $e->payout_channel, 'and is still closed to vouchers');

        $this->svc()->onPayrollStatusChange($slip->fresh(), 'pending', 'paid', $this->admin);
        $this->assertSame('complete', $e->fresh()->status);
        $this->assertSame(1, $e->payments()->count(), 'the voided one no longer counts; exactly one live payment');
        $this->assertSame(2, $e->allPayments()->count());
    }

    public function test_cancelling_a_payslip_hands_its_reimbursements_back_to_the_queue(): void
    {
        $e = $this->reimb($this->alice);
        $slip = $this->slip($this->alice);
        $this->linked($e, $slip);

        $this->svc()->onPayrollStatusChange($slip->fresh(), 'pending', 'cancelled', $this->admin);

        $this->assertSame('queued', $this->link($e)->status);
        $this->assertNull($this->link($e)->monthly_payroll_id);
        $this->assertSame('payroll', $e->fresh()->payout_channel, 'still going to payroll, just not on that slip');
    }

    public function test_releasing_from_a_pending_payslip_removes_its_line_and_corrects_the_totals(): void
    {
        $e = $this->reimb($this->alice, 400);
        $slip = $this->slip($this->alice, ['gross_earnings' => 50400, 'net_payable' => 45400]);
        PayrollComponent::create(['monthly_payroll_id' => $slip->id, 'component_name' => ExpenseReimbursementPayrollService::lineName($e->expense_number), 'component_type' => 'earning', 'amount' => 400, 'is_taxable' => false]);
        $this->linked($e, $slip);

        $this->svc()->release($this->admin, $e->id, 'pay it by voucher instead', fn () => true);

        $slip->refresh();
        $this->assertEquals(50000.00, (float) $slip->gross_earnings);
        $this->assertEquals(45000.00, (float) $slip->net_payable);
        $this->assertSame(0, PayrollComponent::where('monthly_payroll_id', $slip->id)->count());
        $this->assertSame(1, DB::table('payroll_audit_logs')->where('auditable_id', $slip->id)->where('action', 'updated')->where('new_values', 'like', '%expense_reimbursement_released%')->count());
        $this->assertNull($e->fresh()->payout_channel);
    }

    public function test_release_is_refused_once_the_payslip_is_processed_or_the_money_was_paid(): void
    {
        $e = $this->reimb($this->alice);
        $slip = $this->slip($this->alice);
        $this->linked($e, $slip);

        $slip->update(['payment_status' => 'processed']);
        try {
            $this->svc()->release($this->admin, $e->id, 'too late', fn () => true);
            $this->fail('payslip is processed');
        } catch (ExpenseException $ex) {
            $this->assertStringContainsString('Reopen that payslip first', $ex->getMessage());
        }

        $slip->update(['payment_status' => 'pending']);
        $this->svc()->onPayrollStatusChange($slip->fresh(), 'pending', 'paid', $this->admin);
        try {
            $this->svc()->release($this->admin, $e->id, 'too late', fn () => true);
            $this->fail('already paid');
        } catch (ExpenseException $ex) {
            $this->assertStringContainsString('already paid', $ex->getMessage());
        }
        $this->assertSame('payroll', $e->fresh()->payout_channel);
    }

    // ================================================================ through the payroll screens

    public function test_the_real_payroll_status_actions_settle_reopen_and_delete_reimbursements(): void
    {
        $e1 = $this->reimb($this->alice, 111);
        $e2 = $this->reimb($this->bob, 222);
        $slipA = $this->slip($this->alice);
        $slipB = $this->slip($this->bob);
        $this->linked($e1, $slipA);
        $this->linked($e2, $slipB);

        // single status change to paid
        $this->as($this->admin)->patch(route('monthly-payrolls.status', $slipA->id), ['payment_status' => 'paid', 'payment_date' => '2031-03-31'])->assertSessionHasNoErrors();
        $this->assertSame('paid', $slipA->fresh()->payment_status);
        $this->assertSame('complete', $e1->fresh()->status);
        $this->assertSame('payroll', $e1->payments()->value('payment_mode'));

        // bulk (a query-builder update — no model events) still settles
        $this->as($this->admin)->post(route('monthly-payrolls.bulk-update'), ['ids' => [$slipB->id], 'payment_status' => 'paid'])->assertSessionHasNoErrors();
        $this->assertSame('complete', $e2->fresh()->status, 'bulk-paid settles too');

        // reopen (paid -> pending) reverses
        $this->as($this->admin)->post(route('monthly-payrolls.reopen', $slipA->id), ['reason' => 'wrong deduction, redo'])->assertSessionHasNoErrors();
        $this->assertSame('pending', $slipA->fresh()->payment_status);
        $this->assertSame('approved', $e1->fresh()->status);

        // deleting the pending payslip returns the reimbursement to the queue — it is not lost
        $this->as($this->admin)->delete(route('monthly-payrolls.destroy', $slipA->id))->assertSessionHasNoErrors();
        $this->assertNull(MonthlyPayroll::find($slipA->id));
        $this->assertSame('queued', $this->link($e1)->status);
        $this->assertNotNull($this->link($e1), 'not orphaned');
        $this->assertSame('payroll', $e1->fresh()->payout_channel);
    }

    public function test_a_payslip_that_cannot_settle_its_reimbursements_is_not_marked_paid(): void
    {
        $e = $this->reimb($this->alice, 90);
        $slip = $this->slip($this->alice);
        $this->linked($e, $slip);
        $e->update(['status' => 'cancelled']);

        $this->as($this->admin)->patch(route('monthly-payrolls.status', $slip->id), ['payment_status' => 'paid', 'payment_date' => '2031-03-31'])
            ->assertSessionHas('error');

        $this->assertSame('pending', $slip->fresh()->payment_status, 'the status change rolled back with the failed settlement');
    }

    // ================================================================ finance screen

    public function test_the_finance_screen_lists_sends_releases_and_is_permission_gated(): void
    {
        $e = $this->reimb($this->alice, 321);

        $this->as($this->alice)->get(route('expense.payroll.index'))->assertStatus(403);
        $this->as($this->alice)->postJson(route('expense.payroll.send'), ['expense_ids' => [$e->id], 'month' => self::MONTH])->assertStatus(403);

        $this->as($this->admin)->get(route('expense.payroll.index'))->assertOk()->assertSee($e->expense_number)->assertSee('Send selected to payroll');

        $this->as($this->admin)->postJson(route('expense.payroll.send'), ['expense_ids' => [$e->id], 'month' => 'nope'])->assertStatus(422);
        $res = $this->as($this->admin)->postJson(route('expense.payroll.send'), ['expense_ids' => [$e->id], 'month' => self::MONTH])->assertOk();
        $res->assertJsonPath('success', true);
        $this->assertStringContainsString('₹321.00', $res->json('message'));

        // now listed as routed, no longer as waiting
        $page = $this->as($this->admin)->get(route('expense.payroll.index'))->assertOk();
        $page->assertSee('Waiting for the ' . self::MONTH);
        $page->assertSee('Release to voucher');

        // a second attempt reports the reason
        $this->as($this->admin)->postJson(route('expense.payroll.send'), ['expense_ids' => [$e->id], 'month' => self::MONTH])->assertStatus(409);

        $this->as($this->admin)->postJson(route('expense.payroll.release', $e->id), ['reason' => 'x'])->assertStatus(422);
        $this->as($this->admin)->postJson(route('expense.payroll.release', $e->id), ['reason' => 'voucher please'])->assertOk()->assertJsonPath('success', true);
        $this->assertNull($e->fresh()->payout_channel);
    }

    public function test_with_the_switch_off_the_screen_still_lets_finance_release_what_is_already_routed(): void
    {
        $e = $this->reimb($this->alice);
        $this->send([$e]);
        $this->override('expense_payroll_link', false);

        $page = $this->as($this->admin)->get(route('expense.payroll.index'))->assertOk();
        $page->assertSee('switched off')->assertSee('Release to voucher')->assertDontSee('Send selected to payroll');

        $this->as($this->admin)->postJson(route('expense.payroll.release', $e->id), ['reason' => 'switch is off'])->assertOk();
        $this->assertNull($e->fresh()->payout_channel);

        // nothing routed + feature off: the page is not offered at all
        $this->as($this->admin)->get(route('expense.payroll.index'))->assertRedirect(route('expense.payments.index'));
    }

    public function test_the_voucher_screens_no_longer_offer_a_routed_reimbursement(): void
    {
        $routed = $this->reimb($this->alice, 77);
        $free = $this->reimb($this->bob, 88);
        $this->send([$routed]);

        $rows = $this->as($this->admin)->getJson(route('expense.payments.batch.payable', ['search' => 'phpunit-payroll']))->assertOk()->json('data');
        $ids = array_column($rows, 'id');
        $this->assertContains($free->id, $ids);
        $this->assertNotContains($routed->id, $ids);

        // the sidebar entry appears only when the route is enabled
        $this->as($this->admin)->get(route('expense.payments.index'))->assertOk()->assertSee('Reimbursements via Payroll');
        $this->override('expense_payroll_link', false);
        $this->as($this->admin)->get(route('expense.payments.index'))->assertOk()->assertDontSee('Reimbursements via Payroll');
    }
}
