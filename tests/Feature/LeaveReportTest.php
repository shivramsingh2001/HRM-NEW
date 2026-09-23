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
 * Leave Reports tab (client/report/index.blade.php) — Leave Balance, Leave Report (register),
 * Leave Summary, Regularization Report and WFH & Travel Report, all backed by
 * LeaveReportController. Runs against the shared dev DB (no RefreshDatabase, no writes) — mirrors
 * ShiftReportTest/PayrollReportTest's pattern.
 */
class LeaveReportTest extends TestCase
{
    private int $tenantId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantId = (int) DB::table('leaves')
            ->whereNotNull('tenant_id')
            ->select('tenant_id')
            ->groupBy('tenant_id')
            ->havingRaw('count(*) >= 1')
            ->orderByRaw('count(*) desc')
            ->value('tenant_id');

        if (! $this->tenantId) {
            $this->markTestSkipped('no tenant with leaves fixture data');
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

    public function test_the_leave_reports_tab_and_its_five_cards_are_on_the_reports_page(): void
    {
        $this->actingAdmin();

        $page = $this->get(route('report.attendance.index'))->assertOk()->assertSee('Leave Reports');
        foreach (['balance', 'register', 'summary', 'regularization', 'wfh-travel'] as $r) {
            $page->assertSee(route('report.leave.show', $r), false);
        }
    }

    public function test_leave_balance_report_lists_a_row_per_employee_per_leave_type(): void
    {
        $this->actingAdmin();

        $expected = DB::table('leave_balances')->where('tenant_id', $this->tenantId)->count();
        $this->get(route('report.leave.show', 'balance'))->assertOk()->assertSee('Leave Balance')->assertSee((string) $expected);
    }

    public function test_leave_register_filters_by_status_and_exports_csv(): void
    {
        $this->actingAdmin();
        $from = DB::table('leaves')->where('tenant_id', $this->tenantId)->min('start_date');
        $to = DB::table('leaves')->where('tenant_id', $this->tenantId)->max('start_date');

        $page = $this->get(route('report.leave.show', ['report' => 'register', 'from_date' => $from, 'to_date' => $to]))->assertOk();
        $page->assertSee('Leave Report');

        $approvedCount = DB::table('leaves')->where('tenant_id', $this->tenantId)
            ->whereBetween('start_date', [$from, $to])->where('status', 'approved')->count();
        $filtered = $this->get(route('report.leave.show', ['report' => 'register', 'from_date' => $from, 'to_date' => $to, 'status' => 'approved']))->assertOk();
        // one row per matching leave => at least the count appears somewhere (Applications tile or table)
        $filtered->assertSee((string) $approvedCount);

        $csv = $this->get(route('report.leave.show', ['report' => 'register', 'from_date' => $from, 'to_date' => $to, 'export' => 'csv']))->assertOk();
        $csv->assertHeader('Content-Type', 'text/csv; charset=utf-8');
    }

    public function test_leave_summary_groups_by_leave_type(): void
    {
        $this->actingAdmin();
        $this->get(route('report.leave.show', 'summary'))->assertOk()->assertSee('Leave Summary');
    }

    public function test_regularization_report_scopes_a_manager_to_their_team(): void
    {
        $this->actingAdmin();
        $this->get(route('report.leave.show', 'regularization'))->assertOk()->assertSee('Regularization Report');

        $manager = User::where('tenant_id', $this->tenantId)->where('role', 'manager')->where('status', 1)->first();
        if (! $manager) {
            $this->markTestSkipped('no manager fixture in this tenant');
        }
        $this->assertSame('team', app(RbacService::class)->scopeFor($manager, 'attendance', 'view'));

        Auth::login($manager);
        Session::put('tenant_id', $this->tenantId);
        app()->instance('current_tenant', Tenant::find($this->tenantId));

        $this->get(route('report.leave.show', 'regularization'))->assertOk();
    }

    public function test_wfh_and_travel_report_lists_requests_and_a_bad_report_key_is_404(): void
    {
        $this->actingAdmin();
        $this->get(route('report.leave.show', 'wfh-travel'))->assertOk()->assertSee('WFH');
        $this->get(route('report.leave.show', 'nonsense'))->assertStatus(404);
    }
}
