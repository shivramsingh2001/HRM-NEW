<?php

namespace Tests\Feature;

use App\Models\Loan;
use App\Models\LoanRepayment;
use App\Models\MonthlyPayroll;
use App\Models\PayrollBonus;
use App\Models\PayrollComponent;
use App\Models\PayrollPeriod;
use App\Models\User;
use App\Models\UserPayroll;
use App\Services\Attendance\PeriodLockService;
use App\Services\FeatureService;
use App\Services\Payroll\LoanDeductionService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Payroll Audit — Phase 1 (Critical) fixes:
 *  C1 TDS silently dropped on Edit Payroll save
 *  C3 destroy() never reversed loan deductions
 *  C4 force_reprocess bypassed the edit policy entirely
 *  C5 period lock existed but was never enforced against payroll mutations
 *  C6 9 of 11 payroll route groups had no permission check
 *  C8 payroll_components.component_type enum silently truncated employer_contribution rows
 *
 * Runs against the shared dev DB (no RefreshDatabase, matching
 * ApprovalWorkflowTest/PayrollOvertimeApprovalTest); each test creates its
 * own throwaway rows and removes them in tearDown.
 */
class PayrollAuditPhase1Test extends TestCase
{
    private array $monthlyPayrollIds = [];
    private array $loanIds = [];
    private array $bonusIds = [];
    private ?bool $originalPeriodAutolock = null;

    protected function tearDown(): void
    {
        DB::table('payroll_components')->whereIn('monthly_payroll_id', $this->monthlyPayrollIds)->delete();
        DB::table('payroll_audit_logs')
            ->where('auditable_type', MonthlyPayroll::class)
            ->whereIn('auditable_id', $this->monthlyPayrollIds)
            ->delete();
        MonthlyPayroll::whereIn('id', $this->monthlyPayrollIds)->forceDelete();

        DB::table('loan_repayments')->whereIn('loan_id', $this->loanIds)->delete();
        Loan::whereIn('id', $this->loanIds)->forceDelete();

        PayrollBonus::whereIn('id', $this->bonusIds)->delete();

        if ($this->originalPeriodAutolock !== null) {
            config(['attendance.period_autolock' => $this->originalPeriodAutolock]);
        }

        parent::tearDown();
    }

    /**
     * @param  int  $nth  pick the Nth (0-indexed) matching UserPayroll instead of the first --
     *                    lets tests that each do their own store()/force_reprocess HTTP round
     *                    trip use distinct users, so they never contend on the same
     *                    (user_id, payroll_month) unique key across test methods.
     * @return array{0: UserPayroll, 1: User, 2: int} tenant's day-based UserPayroll fixture, an admin, tenantId
     */
    private function tenantFixture(int $nth = 0): array
    {
        $userPayroll = UserPayroll::with('payrollMaster')
            ->where('is_current', 1)->where('status', 1)
            ->whereHas('payrollMaster', fn ($q) => $q->where('payroll_calculation_type', 'day_based'))
            ->orderBy('id')
            ->skip($nth)->first();
        if (! $userPayroll || ! $userPayroll->payrollMaster) {
            $this->markTestSkipped('no day_based user_payroll fixture available in dev DB');
        }

        $tenantId = (int) $userPayroll->tenant_id;
        if (! app(FeatureService::class)->enabled($tenantId, 'payroll')) {
            $this->markTestSkipped('payroll feature not enabled for the fixture tenant');
        }

        $admin = User::where('tenant_id', $tenantId)->where('role', 'admin')->first();
        if (! $admin) {
            $this->markTestSkipped('no admin user in the fixture tenant');
        }

        return [$userPayroll, $admin, $tenantId];
    }

    private function makeMonthlyPayroll(UserPayroll $userPayroll, string $month, array $overrides = []): MonthlyPayroll
    {
        $mp = MonthlyPayroll::create(array_merge([
            'tenant_id' => $userPayroll->tenant_id,
            'user_id' => $userPayroll->user_id,
            'employee_payroll_id' => $userPayroll->id,
            'payroll_month' => $month,
            'processing_date' => now(),
            'total_working_days' => Carbon::createFromFormat('Y-m', $month)->daysInMonth,
            'present_days' => 30,
            'basic_salary' => $userPayroll->basic_salary,
            'hra' => 0, 'conveyence' => 0, 'medical_allowance' => 0,
            'provident_fund' => 0, 'esi' => 0, 'professional_tax' => 0,
            'overtime_hours' => 0, 'overtime_amount' => 0,
            'gross_earnings' => $userPayroll->basic_salary,
            'total_deductions' => 0,
            'net_payable' => $userPayroll->basic_salary,
            'payment_status' => 'pending',
        ], $overrides));
        $this->monthlyPayrollIds[] = $mp->id;

        return $mp;
    }

    private function basePayload(MonthlyPayroll $mp): array
    {
        return [
            'payroll_month' => $mp->payroll_month,
            'present_days' => $mp->present_days,
            'overtime_hours' => 0,
            'overtime_amount' => 0,
            'basic_salary' => $mp->basic_salary,
            'hra' => $mp->hra,
            'conveyence' => $mp->conveyence,
            'medical_allowance' => $mp->medical_allowance,
            'provident_fund' => $mp->provident_fund,
            'esi' => $mp->esi,
            'professional_tax' => $mp->professional_tax,
        ];
    }

    // ------------------------------------------------------------------
    // C1 — TDS
    // ------------------------------------------------------------------

    public function test_edit_payroll_preserves_tds_in_totals(): void
    {
        [$userPayroll, $admin] = $this->tenantFixture();
        $month = now()->subMonths(3)->format('Y-m');
        $mp = $this->makeMonthlyPayroll($userPayroll, $month);

        $payload = $this->basePayload($mp);
        $payload['tds'] = 2000;

        $this->actingAs($admin)
            ->put(route('monthly-payrolls.update', $mp->id), $payload)
            ->assertRedirect();

        $fresh = $mp->fresh();
        $this->assertEquals(2000, (float) $fresh->tds);
        $this->assertEqualsWithDelta(
            (float) $fresh->provident_fund + (float) $fresh->esi + (float) $fresh->professional_tax
                + (float) $fresh->tds + (float) $fresh->loan_deduction + (float) $fresh->other_deductions,
            (float) $fresh->total_deductions,
            0.01
        );
        $this->assertEqualsWithDelta((float) $fresh->gross_earnings - (float) $fresh->total_deductions, (float) $fresh->net_payable, 0.01);

        $tdsComponent = PayrollComponent::where('monthly_payroll_id', $mp->id)
            ->where('component_name', 'TDS')->first();
        $this->assertNotNull($tdsComponent);
        $this->assertEquals(2000, (float) $tdsComponent->amount);
    }

    // ------------------------------------------------------------------
    // C8 — employer_contribution enum
    // ------------------------------------------------------------------

    public function test_employer_contribution_component_saves_without_truncation(): void
    {
        [$userPayroll, $admin] = $this->tenantFixture();
        $month = now()->subMonths(3)->format('Y-m');
        $mp = $this->makeMonthlyPayroll($userPayroll, $month, [
            'employer_provident_fund' => 1200,
            'employer_esi' => 300,
        ]);

        $payload = $this->basePayload($mp);

        $this->actingAs($admin)
            ->put(route('monthly-payrolls.update', $mp->id), $payload)
            ->assertRedirect();

        $rows = PayrollComponent::where('monthly_payroll_id', $mp->id)
            ->where('component_type', 'employer_contribution')->get();

        $this->assertCount(2, $rows);
        $this->assertTrue($rows->every(fn ($r) => $r->component_type === 'employer_contribution'));
    }

    // ------------------------------------------------------------------
    // C3 — destroy() reverses loan deductions
    // ------------------------------------------------------------------

    public function test_destroy_reverses_the_loan_ledger(): void
    {
        [$userPayroll, $admin, $tenantId] = $this->tenantFixture();
        $month = now()->subMonths(3)->format('Y-m');
        $mp = $this->makeMonthlyPayroll($userPayroll, $month);

        $loan = Loan::create([
            'tenant_id' => $tenantId,
            'user_id' => $userPayroll->user_id,
            'loan_number' => 'PHPUNIT-' . uniqid(),
            'amount' => 10000,
            'tenure_months' => 10,
            'repayment_type' => Loan::REPAYMENT_TYPE_EMI,
            'emi_amount' => 1000,
            'remaining_amount' => 5000,
            'loan_date' => now()->subMonths(6)->toDateString(),
            'status' => Loan::STATUS_ACTIVE,
        ]);
        $this->loanIds[] = $loan->id;

        $repayment = LoanRepayment::create([
            'tenant_id' => $tenantId,
            'loan_id' => $loan->id,
            'installment_number' => 1,
            'month' => $month,
            'due_date' => Carbon::createFromFormat('Y-m', $month)->endOfMonth()->toDateString(),
            'emi_amount' => 1000,
            'principal_amount' => 1000,
            'interest_amount' => 0,
            'total_amount' => 1000,
            'paid_amount' => 0,
            'status' => LoanRepayment::STATUS_PENDING,
        ]);

        app(LoanDeductionService::class)->applyDeduction(
            $userPayroll->user_id, $tenantId, $month, 1000, $mp->id, $admin->id
        );

        $repayment->refresh();
        $this->assertEquals(1000, (float) $repayment->paid_amount);
        $this->assertSame(LoanRepayment::STATUS_PAID, $repayment->status);
        $this->assertEquals(4000, (float) $loan->fresh()->remaining_amount);

        $this->actingAs($admin)
            ->delete(route('monthly-payrolls.destroy', $mp->id))
            ->assertRedirect();

        $repayment->refresh();
        $this->assertEquals(0, (float) $repayment->paid_amount);
        $this->assertSame(LoanRepayment::STATUS_PENDING, $repayment->status);
        $this->assertNull($repayment->monthly_payroll_id);
        $this->assertEquals(5000, (float) $loan->fresh()->remaining_amount);
    }

    // ------------------------------------------------------------------
    // C4 — force_reprocess policy gate
    // ------------------------------------------------------------------

    public function test_force_reprocess_is_blocked_for_an_already_paid_payroll(): void
    {
        [$userPayroll, $admin, $tenantId] = $this->tenantFixture();
        $month = now()->subMonths(4)->format('Y-m');
        $mp = $this->makeMonthlyPayroll($userPayroll, $month, ['payment_status' => 'paid']);

        $this->actingAs($admin)
            ->post(route('monthly-payrolls.store'), [
                'payroll_month' => $month,
                'employee_selection' => 'selected',
                'selected_employees' => [$userPayroll->user_id],
                'force_reprocess' => 1,
            ])
            ->assertRedirect();

        $this->assertNotNull(MonthlyPayroll::find($mp->id));
        $this->assertSame('paid', MonthlyPayroll::find($mp->id)->payment_status);
    }

    public function test_force_reprocess_reverses_the_loan_ledger_for_a_pending_payroll(): void
    {
        // Distinct user from test_force_reprocess_is_blocked_for_an_already_paid_payroll()
        // (both otherwise use the same month) so the two tests never contend on the
        // same monthly_payrolls(user_id, payroll_month) unique key back-to-back.
        [$userPayroll, $admin, $tenantId] = $this->tenantFixture(1);
        $month = now()->subMonths(4)->format('Y-m');
        $mp = $this->makeMonthlyPayroll($userPayroll, $month);

        $loan = Loan::create([
            'tenant_id' => $tenantId,
            'user_id' => $userPayroll->user_id,
            'loan_number' => 'PHPUNIT-' . uniqid(),
            'amount' => 10000,
            'tenure_months' => 20,
            'repayment_type' => Loan::REPAYMENT_TYPE_EMI,
            'emi_amount' => 500,
            'remaining_amount' => 3000,
            'loan_date' => now()->subMonths(6)->toDateString(),
            'status' => Loan::STATUS_ACTIVE,
        ]);
        $this->loanIds[] = $loan->id;

        $repayment = LoanRepayment::create([
            'tenant_id' => $tenantId,
            'loan_id' => $loan->id,
            'installment_number' => 1,
            'month' => $month,
            'due_date' => Carbon::createFromFormat('Y-m', $month)->endOfMonth()->toDateString(),
            'emi_amount' => 500,
            'principal_amount' => 500,
            'interest_amount' => 0,
            'total_amount' => 500,
            'paid_amount' => 0,
            'status' => LoanRepayment::STATUS_PENDING,
        ]);

        app(LoanDeductionService::class)->applyDeduction(
            $userPayroll->user_id, $tenantId, $month, 500, $mp->id, $admin->id
        );
        $this->assertEquals(2500, (float) $loan->fresh()->remaining_amount);

        $this->actingAs($admin)
            ->post(route('monthly-payrolls.store'), [
                'payroll_month' => $month,
                'employee_selection' => 'selected',
                'selected_employees' => [$userPayroll->user_id],
                'force_reprocess' => 1,
            ]);

        // Original row was deleted and replaced by a freshly generated one --
        // track the new id for cleanup instead of the stale $mp->id.
        $replacement = MonthlyPayroll::where('user_id', $userPayroll->user_id)
            ->where('payroll_month', $month)->first();
        if ($replacement) {
            $this->monthlyPayrollIds[] = $replacement->id;
        }

        $this->assertNull(MonthlyPayroll::find($mp->id));
        $this->assertEquals(3000, (float) $loan->fresh()->remaining_amount, 'loan ledger must be restored, not left desynced, once the old payslip is replaced');
    }

    // ------------------------------------------------------------------
    // C5 — period lock enforcement
    // ------------------------------------------------------------------

    public function test_locked_period_blocks_update_and_destroy(): void
    {
        // Distinct user from the force_reprocess tests above (uses store() too), same reason.
        [$userPayroll, $admin, $tenantId] = $this->tenantFixture(2);
        $month = now()->subMonths(5)->format('Y-m');
        $mp = $this->makeMonthlyPayroll($userPayroll, $month);

        $this->originalPeriodAutolock = config('attendance.period_autolock');
        config(['attendance.period_autolock' => true]);
        app(PeriodLockService::class)->lock($tenantId, $month, $admin->id, 'phpunit lock');

        try {
            $this->actingAs($admin)
                ->put(route('monthly-payrolls.update', $mp->id), $this->basePayload($mp))
                ->assertRedirect();
            $this->assertEquals(0, (float) $mp->fresh()->tds, 'update should have been rejected, nothing should change');

            $this->actingAs($admin)
                ->delete(route('monthly-payrolls.destroy', $mp->id))
                ->assertRedirect();
            $this->assertNotNull(MonthlyPayroll::find($mp->id), 'destroy should have been rejected while the period is locked');

            $this->actingAs($admin)
                ->post(route('monthly-payrolls.store'), [
                    'payroll_month' => $month,
                    'employee_selection' => 'selected',
                    'selected_employees' => [$userPayroll->user_id],
                ])
                ->assertRedirect();
        } finally {
            DB::table('attendance_period_locks')->where('tenant_id', $tenantId)->where('year_month', $month)->delete();
        }
    }

    // ------------------------------------------------------------------
    // C6 — permission middleware
    // ------------------------------------------------------------------

    public function test_previously_unguarded_routes_now_reject_a_plain_employee(): void
    {
        [, $admin, $tenantId] = $this->tenantFixture();
        $employee = User::where('tenant_id', $tenantId)->where('role', 'employee')->first();
        if (! $employee) {
            $this->markTestSkipped('no employee-role user in the fixture tenant');
        }

        $this->actingAs($employee)
            ->post(route('payroll-engine-settings.update'), ['enable_dynamic_engine' => 0, 'confirm' => 1])
            ->assertRedirect(route('dashboard'));

        $this->actingAs($employee)
            ->post(route('payroll-components.store'), ['code' => 'phpunit', 'name' => 'PHPUnit test'])
            ->assertRedirect(route('dashboard'));
    }

    public function test_bonus_decide_route_rejects_employee_and_allows_admin(): void
    {
        [$userPayroll, $admin, $tenantId] = $this->tenantFixture();
        $employee = User::where('tenant_id', $tenantId)->where('role', 'employee')->first();
        if (! $employee) {
            $this->markTestSkipped('no employee-role user in the fixture tenant');
        }

        $month = now()->format('Y-m');
        $period = PayrollPeriod::firstOrCreate(['tenant_id' => $tenantId, 'year_month' => $month], ['status' => 'open']);

        $bonus = PayrollBonus::create([
            'tenant_id' => $tenantId,
            'user_id' => $userPayroll->user_id,
            'bonus_type' => 'performance',
            'name' => 'phpunit bonus',
            'amount' => 500,
            'is_taxable' => true,
            'is_recurring' => false,
            'target_payroll_period_id' => $period->id,
            'status' => 'draft',
            'created_by' => $admin->id,
        ]);
        $this->bonusIds[] = $bonus->id;

        $this->actingAs($employee)
            ->post(route('payroll-bonuses.decide', [$bonus->id, 'approve']))
            ->assertRedirect(route('dashboard'));
        $this->assertSame('draft', $bonus->fresh()->status);

        $this->actingAs($admin)
            ->post(route('payroll-bonuses.decide', [$bonus->id, 'approve']))
            ->assertRedirect(route('payroll-bonuses.index'));
        $this->assertSame('approved', $bonus->fresh()->status);
    }
}
