<?php

namespace Tests\Feature;

use App\Models\MonthlyPayroll;
use App\Models\User;
use App\Services\Payroll\PayrollRunService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Code-quality plan, Phase 1: every payroll endpoint still answers after the
 * MonthlyPayrollController split (status / payslip PDFs / export moved to
 * their own controllers, work moved to services). Route names unchanged.
 * Dev DB, everything rolled back.
 */
class PayrollControllersSmokeTest extends TestCase
{
    private User $admin;

    private int $tenantId;

    private MonthlyPayroll $slip;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([\App\Http\Middleware\EnsureFeatureEnabled::class]);
        $this->admin = User::withoutGlobalScopes()->where('role', 'admin')->where('status', 1)->whereNotNull('tenant_id')->orderBy('id')->firstOrFail();
        $this->tenantId = (int) $this->admin->tenant_id;
        app()->instance('current_tenant', \App\Models\Tenant::find($this->tenantId));

        $employee = User::withoutGlobalScopes()->where('tenant_id', $this->tenantId)->where('status', 1)
            ->whereHas('userPayrolls', fn ($q) => $q->where('is_current', true))->orderBy('id')->first();
        if (! $employee) {
            $this->markTestSkipped('Needs an employee with a legacy salary structure.');
        }

        DB::beginTransaction();
        $month = Carbon::today()->addMonthsNoOverflow(4);
        DB::table('monthly_payrolls')->where('user_id', $employee->id)->where('payroll_month', $month->format('Y-m'))->delete();
        $this->actingAs($this->admin)->withSession(['tenant_id' => $this->tenantId]);
        $this->slip = app(PayrollRunService::class)->processLegacy($employee, $this->tenantId, $month->format('Y-m'),
            $month->copy()->startOfMonth()->toDateString(), $month->copy()->endOfMonth()->toDateString(), true, false);
    }

    protected function tearDown(): void
    {
        while (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
        parent::tearDown();
    }

    public function test_list_show_edit_and_estimates(): void
    {
        $this->get(route('monthly-payrolls.index'))->assertOk();
        $this->get(route('monthly-payrolls.show', $this->slip->id))->assertOk()->assertSee(number_format((float) $this->slip->net_payable, 2));
        $this->get(route('monthly-payrolls.edit', $this->slip->id))->assertOk()->assertViewHas('loanDueTotal')->assertViewHas('isDynamic', false);

        $this->postJson(route('monthly-payrolls.calculate-estimates'), [
            'employee_ids' => [$this->slip->user_id], 'payroll_month' => $this->slip->payroll_month,
        ])->assertOk()->assertJson(['success' => true]);
    }

    public function test_admin_payslip_pdf_and_csv_export(): void
    {
        $pdf = $this->get(route('monthly-payrolls.payslip', $this->slip->id))->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $pdf->headers->get('Content-Type'));

        $csv = $this->post(route('monthly-payrolls.export'), ['ids' => json_encode([$this->slip->id])])->assertOk();
        $this->assertStringContainsString('"Employee ID","Employee Name",Month', $csv->getContent());
        $this->assertStringContainsString((string) $this->slip->net_payable, $csv->getContent());
    }

    public function test_status_bulk_status_reopen_and_employee_salary_slip(): void
    {
        $this->patch(route('monthly-payrolls.status', $this->slip->id), ['payment_status' => 'processed'])
            ->assertRedirect()->assertSessionHas('success');
        $this->assertSame('processed', $this->slip->fresh()->payment_status);

        // The employee can now see and download it.
        $employee = User::withoutGlobalScopes()->find($this->slip->user_id);
        $this->actingAs($employee)->get(route('my-payroll.my-salary-slips'))->assertOk();
        $download = $this->actingAs($employee)->get(route('my-payroll.download-salary-slip', $this->slip->id))->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $download->headers->get('Content-Type'));

        $this->actingAs($this->admin)->post(route('monthly-payrolls.reopen', $this->slip->id), ['reason' => 'Correct the HRA'])
            ->assertRedirect(route('monthly-payrolls.edit', $this->slip->id));
        $this->assertSame('pending', $this->slip->fresh()->payment_status);

        $this->actingAs($this->admin)->post(route('monthly-payrolls.bulk-update'), ['ids' => [$this->slip->id], 'payment_status' => 'processed'])
            ->assertRedirect()->assertSessionHas('success');
        $this->assertSame('processed', $this->slip->fresh()->payment_status);
    }
}
