<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Payroll\LoanDeductionService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Payroll recovers overdue loan instalments (oldest first), records each
 * payslip's share so undoing one payslip leaves another payslip's
 * part-payment intact, and a loan approved / disbursed late no longer starts
 * with instalments in the past. Dev DB, always rolled back.
 */
class LoanOverdueDeductionTest extends TestCase
{
    private User $admin;
    private User $employee;
    private int $tenantId;
    private LoanDeductionService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::withoutGlobalScopes()->where('role', 'admin')->where('status', 1)->whereNotNull('tenant_id')->orderBy('id')->firstOrFail();
        $this->tenantId = (int) $this->admin->tenant_id;
        $this->employee = User::withoutGlobalScopes()->where('tenant_id', $this->tenantId)->where('role', 'employee')->where('status', 1)->orderBy('id')->firstOrFail();
        $this->svc = app(LoanDeductionService::class);
        DB::beginTransaction();
    }

    protected function tearDown(): void
    {
        while (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
        parent::tearDown();
    }

    private function category(): int
    {
        return DB::table('loan_categories')->insertGetId([
            'tenant_id' => $this->tenantId, 'name' => 'OD test', 'code' => 'ODT' . rand(100, 999), 'max_amount' => 100000,
            'default_interest_rate' => 0, 'max_tenure_months' => 12, 'requires_approval' => 1, 'status' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** Active loan of 10,000 with EMIs in the given months. */
    private function loan(array $months, string $status = 'active'): int
    {
        $loanId = DB::table('loans')->insertGetId([
            'tenant_id' => $this->tenantId, 'user_id' => $this->employee->id, 'loan_type_id' => $this->category(),
            'amount' => 10000, 'processing_fee' => 0, 'total_payable' => 10000, 'interest_rate' => 0,
            'tenure_months' => count($months), 'repayment_type' => 'emi', 'emi_amount' => 3333.33, 'remaining_amount' => 10000,
            'loan_date' => '2026-06-01', 'first_emi_date' => $months[0] . '-07', 'status' => $status, 'purpose' => 'test',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        foreach ($months as $i => $m) {
            $amount = $i === count($months) - 1 ? 3333.34 : 3333.33;
            DB::table('loan_repayments')->insert([
                'tenant_id' => $this->tenantId, 'loan_id' => $loanId, 'repayment_number' => "OD-{$loanId}-" . ($i + 1),
                'installment_number' => $i + 1, 'month' => $m, 'due_date' => $m . '-07', 'emi_amount' => $amount,
                'principal_amount' => $amount, 'interest_amount' => 0, 'total_amount' => $amount, 'paid_amount' => 0,
                'status' => 'pending', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        return $loanId;
    }

    /** A bare payslip row (loan_repayments.monthly_payroll_id has a real FK). */
    private function payslip(string $month): int
    {
        return DB::table('monthly_payrolls')->insertGetId([
            'tenant_id' => $this->tenantId, 'user_id' => $this->employee->id, 'payroll_month' => $month,
            'processing_date' => now()->toDateString(), 'payment_status' => 'pending',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function rep(int $loanId, int $n): object
    {
        return DB::table('loan_repayments')->where('loan_id', $loanId)->where('installment_number', $n)->first();
    }

    public function test_overdue_instalments_are_due_oldest_first_and_future_ones_are_not(): void
    {
        $loan = $this->loan(['2026-06', '2026-07', '2026-08', '2026-11']);

        $items = $this->svc->dueItems($this->employee->id, $this->tenantId, '2026-09')->where('loan_id', $loan)->values();

        $this->assertSame(['2026-06', '2026-07', '2026-08'], $items->pluck('month')->all());
        $this->assertTrue($items->every(fn ($i) => $i['is_overdue']));
        $this->assertEquals(9999.99, round($items->sum('balance_due'), 2));
    }

    public function test_partial_recovery_across_two_payslips_and_undoing_one_keeps_the_other(): void
    {
        $loan = $this->loan(['2026-06', '2026-07', '2026-08']);
        $sep = $this->payslip('2099-09');
        $oct = $this->payslip('2099-10');

        // September payslip can only spare 5,000.
        $this->svc->applyDeduction($this->employee->id, $this->tenantId, '2026-09', 5000, $sep, $this->admin->id);
        $this->assertSame('paid', $this->rep($loan, 1)->status);
        $this->assertSame('partial', $this->rep($loan, 2)->status);
        $this->assertEquals(1666.67, (float) $this->rep($loan, 2)->paid_amount);
        $this->assertEquals(5000, (float) DB::table('loans')->where('id', $loan)->value('remaining_amount'));

        // October payslip collects the rest → loan closed.
        $res = $this->svc->applyDeduction($this->employee->id, $this->tenantId, '2026-10', 5000, $oct, $this->admin->id);
        $this->assertEquals(5000, $res['applied_total']);
        $this->assertSame('paid', $this->rep($loan, 2)->status);
        $this->assertSame('paid', $this->rep($loan, 3)->status);
        $this->assertSame('closed', DB::table('loans')->where('id', $loan)->value('status'));
        $this->assertSame(4, DB::table('loan_repayment_allocations')->where('loan_id', $loan)->count());

        // Undo October only: September's part-payment of instalment 2 stays.
        $this->svc->revokeForPayroll($oct, $this->tenantId);
        $this->assertSame('partial', $this->rep($loan, 2)->status);
        $this->assertEquals(1666.67, (float) $this->rep($loan, 2)->paid_amount);
        $this->assertEquals($sep, $this->rep($loan, 2)->monthly_payroll_id);
        $this->assertSame('pending', $this->rep($loan, 3)->status);
        $loanRow = DB::table('loans')->where('id', $loan)->first();
        $this->assertSame('active', $loanRow->status);
        $this->assertEquals(5000, (float) $loanRow->remaining_amount);

        // Re-saving September (revoke + re-apply) does not double-collect.
        $this->svc->applyDeduction($this->employee->id, $this->tenantId, '2026-09', 5000, $sep, $this->admin->id);
        $this->assertEquals(5000, (float) DB::table('loans')->where('id', $loan)->value('remaining_amount'));
        $this->assertEquals(1666.67, (float) $this->rep($loan, 2)->paid_amount);
    }

    public function test_late_approval_moves_the_first_emi_to_the_next_salary_date(): void
    {
        $loanId = DB::table('loans')->insertGetId([
            'tenant_id' => $this->tenantId, 'user_id' => $this->employee->id, 'loan_type_id' => $this->category(),
            'amount' => 9000, 'processing_fee' => 0, 'interest_rate' => 0, 'tenure_months' => 3, 'repayment_type' => 'emi',
            'emi_amount' => 3000, 'remaining_amount' => 9000, 'loan_date' => '2026-06-01', 'first_emi_date' => '2026-06-07',
            'status' => 'pending', 'purpose' => 'test', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($this->admin)->withSession(['tenant_id' => $this->tenantId])
            ->postJson(route('loan.approvals.approve', $loanId))->assertOk();

        $first = DB::table('loan_repayments')->where('loan_id', $loanId)->min('due_date');
        $this->assertGreaterThanOrEqual(now()->toDateString(), $first);
    }

    public function test_late_disbursal_with_nothing_paid_restarts_the_schedule(): void
    {
        $loan = $this->loan(['2026-06', '2026-07', '2026-08'], 'approved');

        $this->actingAs($this->admin)->withSession(['tenant_id' => $this->tenantId])
            ->postJson(route('loan.approvals.disburse', $loan))->assertOk();

        $this->assertSame('active', DB::table('loans')->where('id', $loan)->value('status'));
        $this->assertSame(3, DB::table('loan_repayments')->where('loan_id', $loan)->count());
        $this->assertGreaterThanOrEqual(now()->toDateString(), DB::table('loan_repayments')->where('loan_id', $loan)->min('due_date'));
    }
}
