<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\FeatureService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Admin / HR raising Loan, Overtime, Expense, Leave and Regularization for an
 * employee: saved approved with the module's side effects, created_by + an
 * audit_logs entry, and refused for a plain employee. Runs on the dev DB in a
 * transaction that is always rolled back.
 */
class OnBehalfRequestTest extends TestCase
{
    private User $admin;
    private User $employee;
    private int $tenantId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::withoutGlobalScopes()->where('role', 'admin')->where('status', 1)->whereNotNull('tenant_id')->orderBy('id')->firstOrFail();
        $this->tenantId = (int) $this->admin->tenant_id;
        $employee = User::withoutGlobalScopes()->where('tenant_id', $this->tenantId)->where('role', 'employee')->where('status', 1)->orderBy('id')->first();
        if (! $employee) {
            $this->markTestSkipped('Needs an active employee.');
        }
        $this->employee = $employee;

        DB::beginTransaction();
        $sa = DB::table('super_admins')->value('id');
        foreach (['loan_management', 'overtime', 'expense_management', 'leave_management', 'regularization'] as $f) {
            DB::table('tenant_feature_overrides')->where('tenant_id', $this->tenantId)->where('feature_key', $f)->delete();
            DB::table('tenant_feature_overrides')->insert(['tenant_id' => $this->tenantId, 'feature_key' => $f, 'is_enabled' => 1,
                'reason' => 'on-behalf test', 'overridden_by' => $sa, 'created_at' => now(), 'updated_at' => now()]);
        }
        app(FeatureService::class)->bust($this->tenantId);
        DB::table('employee_policy_overrides')->where('user_id', $this->employee->id)->delete();

        $this->actingAs($this->admin)->withSession(['tenant_id' => $this->tenantId]);
    }

    protected function tearDown(): void
    {
        while (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
        app(FeatureService::class)->bust($this->tenantId ?? 0);

        parent::tearDown();
    }

    private function audit(string $action, string $type, int $id): ?object
    {
        return DB::table('audit_logs')->where('action', $action)->where('entity_type', $type)->where('entity_id', $id)->first();
    }

    public function test_loan_is_created_approved_with_schedule_and_logged(): void
    {
        $category = DB::table('loan_categories')->insertGetId([
            'tenant_id' => $this->tenantId, 'name' => 'OB test', 'code' => 'OBT' . rand(100, 999), 'max_amount' => 100000,
            'default_interest_rate' => 0, 'max_tenure_months' => 12, 'requires_approval' => 1, 'status' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $res = $this->postJson(route('loan.approvals.on-behalf'), [
            'user_id' => $this->employee->id, 'loan_type_id' => $category, 'repayment_type' => 'emi',
            'amount' => 12000, 'tenure_months' => 6, 'purpose' => 'Medical',
        ]);

        $res->assertStatus(201)->assertJson(['success' => true]);
        $loan = DB::table('loans')->where('user_id', $this->employee->id)->where('loan_type_id', $category)->first();
        $this->assertSame('approved', $loan->status);
        $this->assertEquals($this->admin->id, $loan->created_by);
        $this->assertEquals($this->admin->id, $loan->approved_by);
        $this->assertSame(6, DB::table('loan_repayments')->where('loan_id', $loan->id)->count());
        $log = $this->audit('loan.created_on_behalf', 'Loan', $loan->id);
        $this->assertNotNull($log);
        $this->assertEquals($this->employee->id, json_decode($log->new_values, true)['on_behalf_of']);
    }

    public function test_overtime_is_recorded_approved_for_a_past_date(): void
    {
        $date = now()->subDays(3)->toDateString();
        DB::table('overtime_requests')->where('user_id', $this->employee->id)->where('date', $date)->delete();

        $this->postJson(route('overtime.on-behalf'), [
            'user_id' => $this->employee->id, 'date' => $date, 'overtime_hours' => 2, 'reason' => 'Release night',
        ])->assertOk()->assertJson(['success' => true]);

        $ot = DB::table('overtime_requests')->where('user_id', $this->employee->id)->where('date', $date)->first();
        $this->assertSame('approved', $ot->status);
        $this->assertEquals(2, (float) $ot->approved_hours);
        $this->assertEquals($this->admin->id, $ot->created_by);
        $this->assertNotNull($this->audit('overtime.created_on_behalf', 'OvertimeRequest', $ot->id));

        // Second one for the same date is refused.
        $this->postJson(route('overtime.on-behalf'), [
            'user_id' => $this->employee->id, 'date' => $date, 'overtime_hours' => 1, 'reason' => 'Again',
        ])->assertStatus(422);
    }

    public function test_leave_is_applied_approved_and_balance_deducted(): void
    {
        $type = DB::table('leave_types')->where('tenant_id', $this->tenantId)->where('status', 1)->where('is_unpaid', 0)->whereNull('code')->orderBy('id')->first();
        if (! $type) {
            $this->markTestSkipped('Needs a paid leave type.');
        }
        DB::table('leave_balances')->updateOrInsert(
            ['tenant_id' => $this->tenantId, 'user_id' => $this->employee->id, 'leave_type_id' => $type->id],
            ['balance' => 10, 'updated_at' => now(), 'created_at' => now()]
        );

        $this->postJson(route('leave.on-behalf'), [
            'user_id' => $this->employee->id, 'leave_type' => $type->id,
            'start_date' => '2031-03-03', 'start_session' => 'fullday', 'end_date' => '2031-03-04', 'end_session' => 'fullday',
            'reason' => 'ob leave test',
        ])->assertOk()->assertJson(['success' => true]);

        $leave = DB::table('leaves')->where('user_id', $this->employee->id)->where('reason', 'ob leave test')->first();
        $this->assertSame('approved', $leave->status);
        $this->assertSame('on_behalf', $leave->source);
        $this->assertEquals($this->admin->id, $leave->applied_by);
        $this->assertEquals(8, (float) DB::table('leave_balances')->where('user_id', $this->employee->id)->where('leave_type_id', $type->id)->value('balance'));
        $this->assertNotNull($this->audit('leave.applied_on_behalf', 'Leave', $leave->id));
    }

    public function test_regularization_is_approved_and_written_to_attendance(): void
    {
        $date = null;
        for ($i = 20; $i < 60; $i++) {
            $d = now()->subDays($i)->toDateString();
            if (! DB::table('attendance_regularizations')->where('user_id', $this->employee->id)->where('date', $d)->exists()) {
                $date = $d;
                break;
            }
        }

        $res = $this->postJson(route('attendance-regularization.on-behalf'), [
            'user_id' => $this->employee->id, 'date' => $date, 'request_type' => 'both',
            'in_time' => '09:30', 'out_time' => '18:30', 'reason' => 'Biometric was down that day',
        ]);
        $res->assertOk()->assertJson(['success' => true]);

        $reg = DB::table('attendance_regularizations')->where('user_id', $this->employee->id)->where('date', $date)->first();
        $this->assertSame('approved', $reg->status);
        $this->assertEquals($this->admin->id, $reg->created_by);
        $att = DB::table('attendances')->where('user_id', $this->employee->id)->where('date', $date)->first();
        $this->assertNotNull($att);
        $this->assertStringContainsString('09:30', (string) $att->clock_in);
        $this->assertNotNull($this->audit('regularization.created_on_behalf', 'AttendanceRegularization', $reg->id));

        // Future dates are refused.
        $this->postJson(route('attendance-regularization.on-behalf'), [
            'user_id' => $this->employee->id, 'date' => now()->addDay()->toDateString(), 'request_type' => 'full_day',
            'reason' => 'Future date should fail',
        ])->assertStatus(422);
    }

    public function test_expense_is_saved_approved_with_history_and_logged(): void
    {
        $type = DB::table('expense_types')->insertGetId([
            'tenant_id' => $this->tenantId, 'name' => 'OB test type ' . uniqid(), 'status' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $res = $this->postJson(route('expense.on-behalf'), [
            'user_id' => $this->employee->id, 'expense_type' => $type, 'requirement_type' => 'reimbursement',
            'amount' => '250.50', 'date' => now()->toDateString(), 'description' => 'Cab to client',
        ]);
        $res->assertOk()->assertJson(['success' => true]);

        $expense = DB::table('expenses')->where('user_id', $this->employee->id)->where('expense_type', $type)->first();
        $this->assertSame('approved', $expense->status);
        $this->assertEquals($this->admin->id, $expense->created_by);
        $this->assertEquals($this->admin->id, $expense->approved_by);
        $history = DB::table('expense_status_histories')->where('expense_id', $expense->id)->orderBy('id')->pluck('status')->all();
        $this->assertSame(['pending', 'approved'], $history);
        $this->assertNotNull($this->audit('expenses.created_on_behalf', 'Expense', $expense->id));
        $this->assertNotNull($this->audit('expenses.approved', 'Expense', $expense->id));
    }

    public function test_employee_cannot_raise_for_someone_else_and_other_tenant_user_is_rejected(): void
    {
        $this->actingAs($this->employee)->withSession(['tenant_id' => $this->tenantId]);
        $this->postJson(route('overtime.on-behalf'), [
            'user_id' => $this->admin->id, 'date' => now()->toDateString(), 'overtime_hours' => 1, 'reason' => 'Nope',
        ])->assertStatus(403);

        $this->actingAs($this->admin)->withSession(['tenant_id' => $this->tenantId]);
        $other = User::withoutGlobalScopes()->where('tenant_id', '!=', $this->tenantId)->whereNotNull('tenant_id')->value('id');
        if ($other) {
            $this->postJson(route('overtime.on-behalf'), [
                'user_id' => $other, 'date' => now()->toDateString(), 'overtime_hours' => 1, 'reason' => 'Other company',
            ])->assertStatus(422)->assertJsonValidationErrors('user_id');
        }
    }

    public function test_admin_pages_show_the_create_buttons(): void
    {
        $this->get(route('loan.approvals.pending'))->assertOk()->assertSee('Create loan request');
        $this->get(route('overtime.view-all'))->assertOk()->assertSee('Add overtime');
        $this->get(route('expense.view-all'))->assertOk()->assertSee('Add expense');
        $this->get(route('leave.view-all'))->assertOk()->assertSee('Apply leave for employee');
        $this->get(route('attendance-regularization.manage'))->assertOk()->assertSee('Add regularization');
    }
}
