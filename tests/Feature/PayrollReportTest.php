<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Services\RbacService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Tests\TestCase;

/**
 * Payroll Reports tab (client/report/index.blade.php) — Monthly Payroll, Employee Payroll
 * Structure, Loan and Payroll Summary, backed by PayrollReportController. Money-sensitive: the
 * tab and every report require `payroll,view` to resolve to company scope, except the Loan Report
 * which follows `loans,view` (company for admin/hr, team for a manager). Runs against the shared
 * dev DB (no RefreshDatabase, no writes) — mirrors ShiftReportTest's pattern.
 */
class PayrollReportTest extends TestCase
{
    private int $tenantId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantId = (int) DB::table('monthly_payrolls')
            ->whereNotNull('tenant_id')
            ->select('tenant_id')
            ->groupBy('tenant_id')
            ->havingRaw('count(*) >= 1')
            ->orderByRaw('count(*) desc')
            ->value('tenant_id');

        if (! $this->tenantId) {
            $this->markTestSkipped('no tenant with monthly_payrolls fixture data');
        }
    }

    protected function tearDown(): void
    {
        Auth::logout();
        parent::tearDown();
    }

    private function actingAdmin(): User
    {
        $admin = User::where('tenant_id', $this->tenantId)->where('role', 'admin')->first();
        Auth::login($admin);
        Session::put('tenant_id', $this->tenantId);
        app()->instance('current_tenant', Tenant::find($this->tenantId));

        return $admin;
    }

    public function test_the_payroll_reports_tab_is_offered_only_to_company_scope_payroll_viewers(): void
    {
        $admin = $this->actingAdmin();
        $this->assertSame('company', app(RbacService::class)->scopeFor($admin, 'payroll', 'view'));

        $this->get(route('report.attendance.index'))->assertOk()
            ->assertSee('Payroll Reports')
            ->assertSee(route('report.payroll.show', 'monthly'), false);
    }

    public function test_a_manager_with_own_only_payroll_scope_cannot_reach_the_payroll_reports(): void
    {
        $manager = User::where('tenant_id', $this->tenantId)->where('role', 'manager')->where('status', 1)->first();
        if (! $manager) {
            $this->markTestSkipped('no manager fixture in this tenant');
        }
        $this->assertSame('own', app(RbacService::class)->scopeFor($manager, 'payroll', 'view'), 'precondition: manager payroll scope is own, per config/rbac.php');

        Auth::login($manager);
        Session::put('tenant_id', $this->tenantId);
        app()->instance('current_tenant', Tenant::find($this->tenantId));

        $this->get(route('report.attendance.index'))->assertOk()->assertDontSee('Payroll Reports');
        $this->get(route('report.payroll.show', 'monthly'))->assertStatus(403);
    }

    public function test_monthly_payroll_report_lists_the_months_payslips_with_totals_and_exports_csv(): void
    {
        $this->actingAdmin();
        $month = (string) DB::table('monthly_payrolls')->where('tenant_id', $this->tenantId)->max('payroll_month');

        $page = $this->get(route('report.payroll.show', ['report' => 'monthly', 'month' => $month]))->assertOk();
        $page->assertSee('Monthly Payroll');

        $expectedCount = DB::table('monthly_payrolls')->where('tenant_id', $this->tenantId)->where('payroll_month', $month)->count();
        $page->assertSee((string) $expectedCount);

        $csv = $this->get(route('report.payroll.show', ['report' => 'monthly', 'month' => $month, 'export' => 'csv']))->assertOk();
        $csv->assertHeader('Content-Type', 'text/csv; charset=utf-8');
        $this->assertStringContainsString('Employee ID', $csv->getContent());
    }

    public function test_employee_payroll_structure_report_shows_current_structures_only(): void
    {
        $this->actingAdmin();

        $page = $this->get(route('report.payroll.show', 'structure'))->assertOk();
        $expectedCount = DB::table('payroll_employee_structures')->where('tenant_id', $this->tenantId)
            ->where('is_current', 1)->whereNull('deleted_at')->count();
        $page->assertSee((string) $expectedCount);
    }

    public function test_loan_report_is_reachable_by_a_team_scope_manager_too(): void
    {
        $this->actingAdmin();
        $this->get(route('report.payroll.show', 'loan'))->assertOk()->assertSee('Loan Report');

        $manager = User::where('tenant_id', $this->tenantId)->where('role', 'manager')->where('status', 1)->first();
        if (! $manager) {
            $this->markTestSkipped('no manager fixture in this tenant');
        }
        Auth::login($manager);
        Session::put('tenant_id', $this->tenantId);
        app()->instance('current_tenant', Tenant::find($this->tenantId));

        // loans,view team scope: reachable (unlike the payroll-scoped reports above)
        $this->get(route('report.payroll.show', 'loan'))->assertOk();
    }

    public function test_payroll_summary_report_rolls_up_by_department_with_a_totals_row(): void
    {
        $this->actingAdmin();
        $month = (string) DB::table('monthly_payrolls')->where('tenant_id', $this->tenantId)->max('payroll_month');

        $this->get(route('report.payroll.show', ['report' => 'summary', 'month' => $month]))->assertOk()
            ->assertSee('TOTAL');
    }

    public function test_an_unknown_report_key_is_a_404(): void
    {
        $this->actingAdmin();
        $this->get(route('report.payroll.show', 'nonsense'))->assertStatus(404);
    }
}
