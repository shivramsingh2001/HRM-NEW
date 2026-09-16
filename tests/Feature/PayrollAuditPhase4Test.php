<?php

namespace Tests\Feature;

use App\Models\MonthlyPayroll;
use App\Models\PayrollComponent;
use App\Models\User;
use App\Models\UserPayroll;
use App\Services\FeatureService;
use App\Traits\ResolvesCurrentTenant;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Payroll Audit — Phase 4 (Hygiene & Hardening) fixes:
 *  L1 payroll_masters money columns normalized from double to decimal(15,2)
 *  L2 no uniqueness guard on payroll run numbering
 *  L3 statutory rate/slab tables allowed duplicate/overlapping effective-date rows
 *  L6 zero/negative components were dropped from the line-item breakdown
 *  L7 no row locking on concurrent payroll edits
 *  M9 Show page's action buttons were commented out
 *  M10 mobile API had its own inline tenant-resolution instead of the shared helper
 *
 * Runs against the shared dev DB (no RefreshDatabase, matching the Phase 1-3 convention);
 * each test creates its own throwaway rows and removes them in tearDown.
 */
class PayrollAuditPhase4Test extends TestCase
{
    private array $monthlyPayrollIds = [];
    private array $scratchUserIds = [];
    private array $payrollRunIds = [];

    protected function tearDown(): void
    {
        DB::table('payroll_components')->whereIn('monthly_payroll_id', $this->monthlyPayrollIds)->delete();
        DB::table('payroll_audit_logs')
            ->where('auditable_type', MonthlyPayroll::class)
            ->whereIn('auditable_id', $this->monthlyPayrollIds)
            ->delete();
        MonthlyPayroll::whereIn('id', $this->monthlyPayrollIds)->forceDelete();
        DB::table('payroll_runs')->whereIn('id', $this->payrollRunIds)->delete();
        User::whereIn('id', $this->scratchUserIds)->delete();

        parent::tearDown();
    }

    /** @return array{0: UserPayroll, 1: User, 2: int} */
    private function tenantFixture(): array
    {
        $userPayroll = UserPayroll::with('payrollMaster')
            ->where('is_current', 1)->where('status', 1)
            ->whereHas('payrollMaster', fn ($q) => $q->where('payroll_calculation_type', 'day_based'))
            ->orderBy('id')->first();
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

    // ------------------------------------------------------------------
    // L1 — payroll_masters money columns
    // ------------------------------------------------------------------

    public function test_payroll_masters_money_columns_are_decimal_not_double(): void
    {
        $columns = DB::select("SHOW COLUMNS FROM payroll_masters WHERE Field IN "
            . "('hra','conveyence','medical_allowance','children_allowance','post_allowance',"
            . "'leave_travel_allowance','monthly_incentive','provident_fund',"
            . "'employer_provident_fund','esi','employer_esi','pt')");

        $this->assertCount(12, $columns);
        foreach ($columns as $col) {
            $this->assertStringStartsWith('decimal(15,2)', $col->Type, "{$col->Field} must be decimal(15,2), not double");
        }
    }

    // ------------------------------------------------------------------
    // L2 — payroll_runs uniqueness
    // ------------------------------------------------------------------

    public function test_payroll_runs_rejects_a_duplicate_run_number_for_the_same_period(): void
    {
        [, , $tenantId] = $this->tenantFixture();
        $period = DB::table('payroll_periods')->where('tenant_id', $tenantId)->first()
            ?? (function () use ($tenantId) {
                $id = DB::table('payroll_periods')->insertGetId([
                    'tenant_id' => $tenantId, 'year_month' => '2033-02', 'status' => 'open',
                    'created_at' => now(), 'updated_at' => now(),
                ]);

                return DB::table('payroll_periods')->find($id);
            })();

        $runId = DB::table('payroll_runs')->insertGetId([
            'tenant_id' => $tenantId, 'payroll_period_id' => $period->id, 'run_number' => 9999,
            'status' => 'draft', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->payrollRunIds[] = $runId;

        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::table('payroll_runs')->insert([
            'tenant_id' => $tenantId, 'payroll_period_id' => $period->id, 'run_number' => 9999,
            'status' => 'draft', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    // ------------------------------------------------------------------
    // L3 — statutory table uniqueness
    // ------------------------------------------------------------------

    public function test_statutory_rate_configs_rejects_a_duplicate_version(): void
    {
        [, , $tenantId] = $this->tenantFixture();

        $insert = fn () => DB::table('statutory_rate_configs')->insert([
            'tenant_id' => $tenantId, 'statutory_type' => 'lwf',
            'effective_from' => '2033-01-01', 'config' => json_encode(['employee_rate' => 1]),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $insert();
        $cleanupWhere = ['tenant_id' => $tenantId, 'statutory_type' => 'lwf', 'effective_from' => '2033-01-01'];

        try {
            $this->expectException(\Illuminate\Database\QueryException::class);
            $insert();
        } finally {
            DB::table('statutory_rate_configs')->where($cleanupWhere)->delete();
        }
    }

    // ------------------------------------------------------------------
    // L6 — zero/negative components are not dropped
    // ------------------------------------------------------------------

    public function test_negative_earning_component_still_gets_a_line_item_row(): void
    {
        [$userPayroll, , $tenantId] = $this->tenantFixture();
        $month = now()->subMonths(3)->format('Y-m');
        $mp = $this->makeMonthlyPayroll($userPayroll, $month);

        // savePayrollComponents() relies on PayrollComponent's TenantTrait
        // auto-filling tenant_id from app('current_tenant') -- present during
        // any real request, but not bound by default in a PHPUnit process.
        $previouslyBound = app()->bound('current_tenant') ? app('current_tenant') : null;
        app()->instance('current_tenant', \App\Models\Tenant::withoutGlobalScopes()->find($tenantId));

        $controller = app(\App\Http\Controllers\Payroll\MonthlyPayrollController::class);
        $ref = new ReflectionMethod($controller, 'savePayrollComponents');
        $ref->setAccessible(true);
        try {
            $ref->invoke($controller, $mp->id, ['special' => -500], [], []);
        } finally {
            if ($previouslyBound) {
                app()->instance('current_tenant', $previouslyBound);
            }
        }

        $row = PayrollComponent::where('monthly_payroll_id', $mp->id)->where('component_type', 'earning')->first();
        $this->assertNotNull($row, 'a negative-amount component must still produce a line-item row');
        $this->assertEquals(-500, (float) $row->amount);
    }

    // ------------------------------------------------------------------
    // L7 — row locking on update()
    // ------------------------------------------------------------------

    public function test_update_locks_the_row_for_update(): void
    {
        [$userPayroll, $admin] = $this->tenantFixture();
        $month = now()->subMonths(3)->format('Y-m');
        $mp = $this->makeMonthlyPayroll($userPayroll, $month);

        $sawLockForUpdate = false;
        DB::listen(function ($query) use (&$sawLockForUpdate) {
            if (str_contains($query->sql, 'for update')) {
                $sawLockForUpdate = true;
            }
        });

        $this->actingAs($admin)->put(route('monthly-payrolls.update', $mp->id), [
            'payroll_month' => $mp->payroll_month,
            'present_days' => $mp->present_days,
            'overtime_hours' => 0, 'overtime_amount' => 0,
            'basic_salary' => $mp->basic_salary,
            'hra' => 0, 'conveyence' => 0, 'medical_allowance' => 0,
            'provident_fund' => 0, 'esi' => 0, 'professional_tax' => 0,
        ]);

        DB::flushQueryLog();
        $this->assertTrue($sawLockForUpdate, 'update() must SELECT ... FOR UPDATE the payroll row before mutating it');
    }

    // ------------------------------------------------------------------
    // M9 — Show page action buttons restored
    // ------------------------------------------------------------------

    public function test_show_page_renders_the_restored_action_buttons(): void
    {
        [$userPayroll, $admin] = $this->tenantFixture();
        $month = now()->subMonths(3)->format('Y-m');
        $mp = $this->makeMonthlyPayroll($userPayroll, $month);

        $response = $this->actingAs($admin)->get(route('monthly-payrolls.show', $mp->id));

        $response->assertOk();
        $response->assertSee('Generate Payslip');
        $response->assertSee('Update Status');
        $response->assertSee('Edit Payroll');
        $response->assertSee('Back to List');
    }

    // ------------------------------------------------------------------
    // M10 — shared tenant-resolution trait
    // ------------------------------------------------------------------

    public function test_resolves_current_tenant_trait_precedence(): void
    {
        $probe = new class {
            use ResolvesCurrentTenant;

            public function call(?User $user = null): ?int
            {
                return $this->currentTenantId($user);
            }
        };

        // No container binding, no user, no session -> null.
        $this->assertNull($probe->call());

        // No container binding, a user with tenant_id -> the user's tenant_id
        // (this is the behavior the mobile API's inline `$user->tenant_id ??
        // session(...)` used to implement directly).
        $user = new User();
        $user->tenant_id = 424242;
        $this->assertSame(424242, $probe->call($user));
    }
}
