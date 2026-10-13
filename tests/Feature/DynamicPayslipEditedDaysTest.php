<?php

namespace Tests\Feature;

use App\Models\MonthlyPayroll;
use App\Models\User;
use App\Services\Payroll\PayrollCalculationEngine;
use App\Services\Payroll\PayrollEditService;
use App\Services\Payroll\PayslipContext;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * A dynamic payslip whose days were edited (no attendance behind them): Absent
 * follows the edited days, and the salary slip / Edit form recompute from the
 * saved day counts, not raw attendance. Dev DB, always rolled back.
 */
class DynamicPayslipEditedDaysTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        DB::beginTransaction();
    }

    protected function tearDown(): void
    {
        while (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
        parent::tearDown();
    }

    public function test_edited_days_drive_absent_and_the_salary_slip(): void
    {
        $employee = User::withoutGlobalScopes()->where('status', 1)->whereNotNull('tenant_id')
            ->whereHas('currentDynamicPayrollStructure')->orderBy('id')->first();
        if (! $employee) {
            $this->markTestSkipped('Needs an employee with a dynamic salary structure.');
        }
        $tenantId = (int) $employee->tenant_id;
        app()->instance('current_tenant', \App\Models\Tenant::find($tenantId));
        // A month with no attendance: raw attendance alone would pay nothing.
        $month = now()->addMonthsNoOverflow(3)->format('Y-m');
        $days = Carbon::createFromFormat('Y-m', $month)->daysInMonth;
        $engine = app(PayrollCalculationEngine::class);

        $edited = $engine->calculate($employee, $tenantId, $month, false,
            ['present_days' => $days - 4, 'paid_leave_days' => 0, 'week_offs' => 4, 'holidays' => 0]);
        $this->assertEquals($days, $edited['context']['payable_days']);
        $this->assertEquals(0, $edited['context']['absent_days']);
        $earning = collect($edited['line_items'])->where('component_type', 'earning')->firstWhere('amount', '>', 0);
        $this->assertNotNull($earning);

        $slip = MonthlyPayroll::withoutGlobalScopes()->forceCreate([
            'tenant_id' => $tenantId, 'user_id' => $employee->id, 'payroll_month' => $month, 'engine_version' => 'dynamic_v1',
            'total_working_days' => $days, 'payable_days' => $days, 'present_days' => $days - 4, 'week_offs' => 4,
            'holidays' => 0, 'paid_leaves' => 0, 'absent_days' => 0, 'gross_earnings' => $edited['gross_earnings'],
            'net_payable' => $edited['net_payable'], 'payment_status' => 'pending', 'processing_date' => now()->toDateString(),
        ]);

        $resolved = $engine->resolveEmployeeComponents($employee, $tenantId, $month, app(PayrollEditService::class)->storedDayOverrides($slip));
        $this->assertEquals($earning['amount'], collect($resolved['earnings'])->firstWhere('code', $earning['code'])['amount']);

        $html = view('client.payroll.monthly-payroll.pdf', [
            'monthlyPayroll' => MonthlyPayroll::withoutGlobalScopes()->with(['user', 'components', 'payrollMaster'])->find($slip->id),
            'bankDetails' => null,
            'company' => app(PayslipContext::class)->company($tenantId),
        ])->render();
        $this->assertStringContainsString(e($earning['name']), $html);
        $this->assertStringContainsString(number_format($earning['amount'], 2), $html);
    }
}
