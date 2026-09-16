<?php

namespace Tests\Feature;

use App\Models\MonthlyPayroll;
use App\Models\OvertimeRequest;
use App\Models\User;
use App\Models\UserPayroll;
use App\Services\Attendance\OvertimeApprovalService;
use App\Services\FeatureService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Edit Payroll "include non-approved overtime": OvertimeApprovalService (shared
 * with OvertimeController::bulkApprove()) and the MonthlyPayrollController
 * update() flow that auto-approves pending overtime on save and folds the
 * server-computed amount into the payslip.
 *
 * Runs against the shared dev DB (no RefreshDatabase, matching
 * ApprovalWorkflowTest/FieldTrackingTest); each test creates its own
 * throwaway rows and removes them in tearDown.
 */
class PayrollOvertimeApprovalTest extends TestCase
{
    private array $overtimeIds = [];
    private array $monthlyPayrollIds = [];

    protected function tearDown(): void
    {
        OvertimeRequest::whereIn('id', $this->overtimeIds)->forceDelete();
        DB::table('payroll_components')->whereIn('monthly_payroll_id', $this->monthlyPayrollIds)->delete();
        DB::table('payroll_audit_logs')
            ->where('auditable_type', MonthlyPayroll::class)
            ->whereIn('auditable_id', $this->monthlyPayrollIds)
            ->delete();
        MonthlyPayroll::whereIn('id', $this->monthlyPayrollIds)->forceDelete();

        parent::tearDown();
    }

    /** @return array{0: MonthlyPayroll, 1: UserPayroll, 2: User, 3: string} */
    private function fixture(): array
    {
        $userPayroll = UserPayroll::with('payrollMaster')
            ->where('is_current', 1)->where('status', 1)
            ->whereHas('payrollMaster', fn ($q) => $q->where('payroll_calculation_type', 'day_based'))
            ->first();
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

        $month = now()->subMonths(2)->format('Y-m');

        $monthlyPayroll = MonthlyPayroll::create([
            'tenant_id' => $tenantId,
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
        ]);
        $this->monthlyPayrollIds[] = $monthlyPayroll->id;

        return [$monthlyPayroll, $userPayroll, $admin, $month];
    }

    private function pendingOvertime(int $tenantId, int $userId, string $month, float $hours = 3.0): OvertimeRequest
    {
        $ot = OvertimeRequest::create([
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'date' => Carbon::createFromFormat('Y-m', $month)->startOfMonth()->addDays(2)->toDateString(),
            'overtime_hours' => $hours,
            'reason' => 'phpunit',
            'status' => 'pending',
        ]);
        $this->overtimeIds[] = $ot->id;

        return $ot;
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
    // Service-level: OvertimeApprovalService (shared by bulkApprove() too)
    // ------------------------------------------------------------------

    public function test_service_denies_a_user_without_overtime_approve_permission(): void
    {
        $employee = User::where('role', 'employee')->whereNotNull('tenant_id')->first();
        if (! $employee) {
            $this->markTestSkipped('no employee-role user available');
        }
        $tenantId = (int) $employee->tenant_id;

        $ot = $this->pendingOvertime($tenantId, $employee->id, now()->format('Y-m'));

        $result = app(OvertimeApprovalService::class)->bulkApprove($employee, $tenantId, [$ot->id]);

        $this->assertFalse($result['authorized']);
        $this->assertSame(0, $result['approved_count']);
        $this->assertSame('pending', $ot->fresh()->status);
    }

    public function test_service_bulk_approves_as_admin(): void
    {
        $admin = User::where('role', 'admin')->first();
        if (! $admin) {
            $this->markTestSkipped('no admin user available');
        }
        $tenantId = (int) $admin->tenant_id;

        $ot = $this->pendingOvertime($tenantId, $admin->id, now()->format('Y-m'), 4.5);

        $result = app(OvertimeApprovalService::class)->bulkApprove($admin, $tenantId, [$ot->id]);

        $this->assertTrue($result['authorized']);
        $this->assertSame(1, $result['approved_count']);

        $ot->refresh();
        $this->assertSame('approved', $ot->status);
        $this->assertSame($admin->id, $ot->approved_by);
        $this->assertEquals(4.5, (float) $ot->approved_hours);
        $this->assertNotNull($ot->approved_at);
    }

    // ------------------------------------------------------------------
    // HTTP end-to-end: MonthlyPayrollController::update()
    // ------------------------------------------------------------------

    public function test_edit_payroll_leaves_pending_overtime_untouched_when_checkbox_is_off(): void
    {
        [$monthlyPayroll, $userPayroll, $admin, $month] = $this->fixture();
        $ot = $this->pendingOvertime((int) $userPayroll->tenant_id, $userPayroll->user_id, $month);

        $this->actingAs($admin)
            ->put(route('monthly-payrolls.update', $monthlyPayroll->id), $this->basePayload($monthlyPayroll))
            ->assertRedirect();

        $this->assertSame('pending', $ot->fresh()->status);
        $this->assertEquals(0.0, (float) $monthlyPayroll->fresh()->overtime_hours);
        $this->assertEquals(0.0, (float) $monthlyPayroll->fresh()->overtime_amount);
    }

    public function test_edit_payroll_auto_approves_and_folds_in_pending_overtime_when_checkbox_is_on(): void
    {
        [$monthlyPayroll, $userPayroll, $admin, $month] = $this->fixture();
        $ot = $this->pendingOvertime((int) $userPayroll->tenant_id, $userPayroll->user_id, $month, 3.0);

        $payload = $this->basePayload($monthlyPayroll);
        $payload['include_pending_overtime'] = 1;
        $payload['pending_overtime_request_ids'] = [$ot->id];

        $this->actingAs($admin)
            ->put(route('monthly-payrolls.update', $monthlyPayroll->id), $payload)
            ->assertRedirect();

        $ot->refresh();
        $this->assertSame('approved', $ot->status);
        $this->assertSame($admin->id, $ot->approved_by);
        $this->assertEquals(3.0, (float) $ot->approved_hours);

        // Independently reproduce the server's day-based formula:
        // rate = (basic_salary / calendarDaysInMonth) / working_hours_per_day * tenant_rate_multiplier
        $calendarDays = Carbon::createFromFormat('Y-m', $month)->daysInMonth;
        $workingHoursPerDay = (float) $userPayroll->payrollMaster->working_hours_per_day;
        $rateMultiplier = (float) (
            DB::table('overtime_settings')->where('tenant_id', $userPayroll->tenant_id)->value('rate_multiplier')
            ?? DB::table('overtime_settings')->whereNull('tenant_id')->value('rate_multiplier')
            ?? 1.5
        );
        $hourlyRate = ((float) $userPayroll->basic_salary / $calendarDays) / $workingHoursPerDay;
        $expectedRate = round($hourlyRate * $rateMultiplier, 2);
        $expectedAmount = round(3.0 * $expectedRate, 2);

        $fresh = $monthlyPayroll->fresh();
        $this->assertEquals(3.0, (float) $fresh->overtime_hours);
        $this->assertEquals($expectedAmount, (float) $fresh->overtime_amount);
    }

    public function test_edit_payroll_rejects_a_tampered_include_flag_from_a_non_approver(): void
    {
        [$monthlyPayroll, $userPayroll, , $month] = $this->fixture();
        $ot = $this->pendingOvertime((int) $userPayroll->tenant_id, $userPayroll->user_id, $month);

        $nonApprover = User::where('tenant_id', $userPayroll->tenant_id)->where('role', 'employee')->first();
        if (! $nonApprover) {
            $this->markTestSkipped('no employee-role user in the fixture tenant');
        }

        $payload = $this->basePayload($monthlyPayroll);
        $payload['include_pending_overtime'] = 1;
        $payload['pending_overtime_request_ids'] = [$ot->id];

        // permission:payroll,edit gates the route itself, so a plain employee
        // is expected to be blocked before even reaching the controller body --
        // this asserts the overtime request is left alone either way.
        $this->actingAs($nonApprover)
            ->put(route('monthly-payrolls.update', $monthlyPayroll->id), $payload);

        $this->assertSame('pending', $ot->fresh()->status);
    }
}
