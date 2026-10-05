<?php

namespace Tests\Feature;

use App\Models\Loan;
use App\Models\User;
use App\Services\Loan\SalaryAdvanceService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Salary Advance (Loans & Advances): validation, lifecycle (create / approve /
 * disburse / cancel, month moved when its payroll is already processed),
 * payroll deduction as its own payslip line BEFORE loan EMIs, idempotent
 * regeneration, mobile API. Dev DB, always rolled back.
 */
class SalaryAdvanceTest extends TestCase
{
    private User $admin;
    private User $employee; // has a salary structure
    private int $tenantId;
    private int $categoryId;
    private SalaryAdvanceService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::withoutGlobalScopes()->where('role', 'admin')->where('status', 1)->whereNotNull('tenant_id')->orderBy('id')->firstOrFail();
        $this->tenantId = (int) $this->admin->tenant_id;
        $this->svc = app(SalaryAdvanceService::class);

        $employee = User::withoutGlobalScopes()->where('tenant_id', $this->tenantId)->where('status', 1)->where('role', '!=', 'admin')
            ->whereHas('currentDynamicPayrollStructure')->orderBy('id')->get()
            ->first(fn ($u) => ($this->svc->monthlyGross($u) ?? 0) > 0);
        if (! $employee) {
            $this->markTestSkipped('Needs an employee with a salary structure.');
        }
        $this->employee = $employee;

        DB::beginTransaction();
        $sa = DB::table('super_admins')->value('id');
        foreach (['loan_management', 'payroll'] as $f) {
            DB::table('tenant_feature_overrides')->where('tenant_id', $this->tenantId)->where('feature_key', $f)->delete();
            DB::table('tenant_feature_overrides')->insert(['tenant_id' => $this->tenantId, 'feature_key' => $f, 'is_enabled' => 1,
                'reason' => 'salary advance test', 'overridden_by' => $sa, 'created_at' => now(), 'updated_at' => now()]);
        }
        app(\App\Services\FeatureService::class)->bust($this->tenantId);

        $this->categoryId = DB::table('loan_categories')->insertGetId([
            'tenant_id' => $this->tenantId, 'name' => 'SA test', 'code' => 'SAT' . rand(100, 999), 'kind' => 'salary_advance',
            'max_amount' => 100000, 'max_percent_of_gross' => 50, 'default_interest_rate' => 0, 'max_tenure_months' => 1,
            'requires_approval' => 1, 'status' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        while (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
        app(\App\Services\FeatureService::class)->bust($this->tenantId ?? 0);
        parent::tearDown();
    }

    private function category(): \App\Models\LoanCategory
    {
        return \App\Models\LoanCategory::withoutGlobalScopes()->findOrFail($this->categoryId);
    }

    private function asAdmin(): self
    {
        return $this->actingAs($this->admin)->withSession(['tenant_id' => $this->tenantId]);
    }

    public function test_limits_and_month_window_are_validated(): void
    {
        $month = now()->format('Y-m');
        $gross = $this->svc->monthlyGross($this->employee);
        $half = round($gross * 0.5, 2);

        $this->assertNull($this->svc->validate($this->employee, $this->category(), $half, $month));
        $this->assertStringContainsString('most that can be advanced', $this->svc->validate($this->employee, $this->category(), $half + 1, $month));
        $this->assertStringContainsString('can be taken for', $this->svc->validate($this->employee, $this->category(), 100, now()->addMonths(5)->format('Y-m')));
        $this->assertStringContainsString('can be taken for', $this->svc->validate($this->employee, $this->category(), 100, now()->subMonth()->format('Y-m')));

        // A second advance for the same month only gets what is left of the % cap.
        $this->svc->create($this->employee, $this->category(), round($half - 100, 2), $month, 'first', null);
        $this->assertNull($this->svc->validate($this->employee, $this->category(), 100, $month));
        $this->assertNotNull($this->svc->validate($this->employee, $this->category(), 101, $month));

        // A regular loan category is refused for an advance, and vice versa through the web route.
        $loanCat = DB::table('loan_categories')->insertGetId(['tenant_id' => $this->tenantId, 'name' => 'Plain', 'code' => 'PL' . rand(100, 999),
            'kind' => 'loan', 'status' => 1, 'requires_approval' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($this->employee)->withSession(['tenant_id' => $this->tenantId])
            ->postJson(route('loan.requests.store'), ['loan_kind' => 'salary_advance', 'loan_type_id' => $loanCat,
                'advance_month' => $month, 'amount' => 100, 'purpose' => 'x'])->assertStatus(422);
    }

    public function test_processed_month_is_refused_and_approval_moves_the_month(): void
    {
        $month = now()->format('Y-m');
        $loan = $this->svc->create($this->employee, $this->category(), 100, $month, 'medical', null);
        $this->assertSame(Loan::STATUS_PENDING, $loan->status);
        $this->assertSame(0, DB::table('loan_repayments')->where('loan_id', $loan->id)->count());

        // That month's payslip gets processed before the approval.
        DB::table('monthly_payrolls')->where('user_id', $this->employee->id)->where('payroll_month', $month)->delete();
        DB::table('monthly_payrolls')->insert(['tenant_id' => $this->tenantId, 'user_id' => $this->employee->id, 'payroll_month' => $month,
            'processing_date' => now()->toDateString(), 'payment_status' => 'processed', 'created_at' => now(), 'updated_at' => now()]);
        $this->assertStringContainsString('already processed', $this->svc->validate($this->employee, $this->category(), 100, $month));

        $res = $this->asAdmin()->postJson(route('loan.approvals.approve', $loan->id))->assertOk();
        $this->assertStringContainsString('instead', (string) $res->json('notice'));

        $loan->refresh();
        $next = now()->startOfMonth()->addMonthNoOverflow()->format('Y-m');
        $this->assertSame($next, $loan->advance_month);
        $row = DB::table('loan_repayments')->where('loan_id', $loan->id)->first();
        $this->assertSame($next, $row->month);
        $this->assertEquals(100, (float) $row->total_amount);
        $this->assertTrue(DB::table('audit_logs')->where('entity_type', 'Loan')->where('entity_id', $loan->id)->where('action', 'salary_advance.month_moved')->exists());
        $this->assertTrue(DB::table('audit_logs')->where('entity_type', 'Loan')->where('entity_id', $loan->id)->where('action', 'salary_advance.approved')->exists());
    }

    public function test_cancel_drops_the_schedule(): void
    {
        $loan = $this->svc->create($this->employee, $this->category(), 100, now()->format('Y-m'), 'x', null, $this->admin, true);
        $this->assertSame(Loan::STATUS_APPROVED, $loan->status);
        $this->assertSame(1, DB::table('loan_repayments')->where('loan_id', $loan->id)->count());

        $this->asAdmin()->postJson(route('loan.requests.cancel', $loan->id), ['cancellation_reason' => 'not needed'])->assertOk();
        $this->assertSame(Loan::STATUS_CANCELLED, $loan->fresh()->status);
        $this->assertSame(0, DB::table('loan_repayments')->where('loan_id', $loan->id)->count());
    }

    public function test_payroll_deducts_the_advance_first_as_its_own_line_and_regeneration_is_idempotent(): void
    {
        // A pending payslip month for this employee to regenerate (generated earlier, still pending).
        $slip = DB::table('monthly_payrolls')->where('tenant_id', $this->tenantId)->where('user_id', $this->employee->id)
            ->where('payment_status', 'pending')->where('engine_version', 'dynamic_v1')->orderByDesc('payroll_month')->first();
        if (! $slip) {
            $this->markTestSkipped('Needs a pending dynamic payslip to regenerate.');
        }
        $month = $slip->payroll_month;

        // Advance for that month (inserted directly: the request window is current month onward), paid out.
        $loan = Loan::create([
            'tenant_id' => $this->tenantId, 'user_id' => $this->employee->id, 'loan_type_id' => $this->categoryId,
            'loan_kind' => 'salary_advance', 'advance_month' => $month, 'repayment_type' => 'lumpsum', 'amount' => 1000,
            'processing_fee' => 0, 'total_payable' => 1000, 'interest_rate' => 0, 'tenure_months' => 1, 'emi_amount' => 0,
            'remaining_amount' => 1000, 'loan_date' => now()->toDateString(), 'lumpsum_amount' => 1000, 'purpose' => 'test',
            'status' => 'active', 'approved_by' => $this->admin->id, 'approved_at' => now(), 'disbursed_at' => now(),
        ]);
        $loan->refresh();
        $this->svc->scheduleRow($loan);

        $generate = fn () => $this->asAdmin()->post(route('monthly-payrolls.store'), [
            'payroll_month' => $month, 'employee_selection' => 'selected', 'selected_employees' => [$this->employee->id],
            'include_overtime' => 1, 'include_loan_deductions' => 1, 'force_reprocess' => 1,
        ]);

        $generate()->assertRedirect();
        $p = DB::table('monthly_payrolls')->where('user_id', $this->employee->id)->where('payroll_month', $month)->first();
        $gross = (float) $p->gross_earnings;
        $expectedAdvance = min(1000.0, $gross);
        $this->assertEquals($expectedAdvance, (float) $p->salary_advance_deduction);
        $this->assertEquals(1000, (float) $p->salary_advance_deduction_computed);
        // Advance first: loans only get what is left.
        $this->assertLessThanOrEqual(round($gross - $expectedAdvance, 2) + 0.01, (float) $p->loan_deduction);
        $this->assertGreaterThanOrEqual(-0.01, (float) $p->net_payable);
        $this->assertTrue(DB::table('payroll_components')->where('monthly_payroll_id', $p->id)->where('component_name', 'Salary Advance Deduction')->exists());
        if ($expectedAdvance >= 1000) {
            $this->assertSame('closed', $loan->fresh()->status);
        }

        // Regenerate again: still collected once.
        $generate()->assertRedirect();
        $this->assertEquals(round(1000 - $expectedAdvance, 2), (float) $loan->fresh()->remaining_amount);
        $p2 = DB::table('monthly_payrolls')->where('user_id', $this->employee->id)->where('payroll_month', $month)->first();
        $this->assertSame(1, DB::table('loan_repayment_allocations')->where('loan_id', $loan->id)->where('monthly_payroll_id', $p2->id)->count());

        // Deleting the payslip puts the advance back as due.
        $this->asAdmin()->delete(route('monthly-payrolls.destroy', $p2->id));
        $this->assertEquals(1000, (float) $loan->fresh()->remaining_amount);
        $this->assertSame('active', $loan->fresh()->status);
    }

    public function test_on_behalf_and_mobile_api(): void
    {
        $month = now()->format('Y-m');
        $this->asAdmin()->postJson(route('loan.approvals.on-behalf'), [
            'user_id' => $this->employee->id, 'loan_kind' => 'salary_advance', 'loan_type_id' => $this->categoryId,
            'advance_month' => $month, 'amount' => 50, 'purpose' => 'urgent',
        ])->assertStatus(201)->assertJson(['success' => true]);
        $loan = Loan::withoutGlobalScopes()->where('user_id', $this->employee->id)->where('loan_type_id', $this->categoryId)->latest('id')->first();
        $this->assertSame('approved', $loan->status);
        $this->assertEquals($this->admin->id, $loan->created_by);
        $this->assertTrue(DB::table('audit_logs')->where('entity_id', $loan->id)->where('action', 'salary_advance.created_on_behalf')->exists());

        // Mobile API
        $device = Str::random(40);
        DB::table('users')->where('id', $this->employee->id)->update(['last_login_token' => $device]);
        $this->employee->last_login_token = $device;
        $headers = ['Authorization' => 'Bearer ' . auth('api')->login($this->employee), 'Device-Token' => $device, 'Accept' => 'application/json'];

        $limit = $this->withHeaders($headers)->getJson('/api/loan/advance-limit?category=' . $this->categoryId)->assertOk();
        $this->assertNotEmpty($limit->json('data.open_months'));
        $this->assertNotNull($limit->json('data.limit.available'));

        $this->withHeaders($headers)->postJson('/api/loan/store', [
            'loan_kind' => 'salary_advance', 'loan_type_id' => $this->categoryId, 'advance_month' => $month, 'amount' => 25, 'purpose' => 'api',
        ])->assertOk()->assertJson(['success' => true])->assertJsonPath('data.loan_kind', 'salary_advance');

        $this->withHeaders($headers)->postJson('/api/loan/store', [
            'loan_kind' => 'salary_advance', 'loan_type_id' => $this->categoryId, 'advance_month' => $month, 'amount' => 99999999, 'purpose' => 'too much',
        ])->assertOk()->assertJson(['success' => false]);
    }

    public function test_category_type_and_pages_render(): void
    {
        $res = $this->asAdmin()->postJson(route('loan.categories.store'), [
            'name' => 'Festival advance', 'kind' => 'salary_advance', 'max_percent_of_gross' => 40, 'default_interest_rate' => 12,
            'requires_approval' => 1, 'status' => 1,
        ])->assertStatus(201);
        $cat = DB::table('loan_categories')->where('id', $res->json('data.id'))->first();
        $this->assertSame('salary_advance', $cat->kind);
        $this->assertEquals(0, (float) $cat->default_interest_rate);
        $this->assertEquals(40, (float) $cat->max_percent_of_gross);

        $this->asAdmin()->get(route('loan.categories.index'))->assertOk()->assertSee('Salary Advance');
        $this->asAdmin()->get(route('loan.approvals.pending'))->assertOk()->assertSee('Salary advances only');
        $this->actingAs($this->employee)->withSession(['tenant_id' => $this->tenantId])
            ->get(route('loan.requests.index'))->assertOk()->assertSee('Request salary advance');
    }
}
