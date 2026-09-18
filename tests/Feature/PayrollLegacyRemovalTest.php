<?php

namespace Tests\Feature;

use App\Models\MonthlyPayroll;
use App\Models\PayrollComponentMaster;
use App\Models\PayrollEmployeeStructure;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserJobDetail;
use App\Models\UserPayroll;
use App\Services\FeatureService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Payroll legacy-engine removal (2026-09-18): PayrollMasterController /
 * UserPayrollController and their routes/views are deleted; the employee
 * wizard's payroll step writes only to the dynamic engine now.
 *
 * Runs against the shared dev DB (no RefreshDatabase, matching the Payroll
 * Audit test convention); each test creates its own throwaway rows and
 * removes them in tearDown.
 */
class PayrollLegacyRemovalTest extends TestCase
{
    private array $scratchUserIds = [];
    private array $structureIds = [];
    private array $monthlyPayrollIds = [];

    protected function tearDown(): void
    {
        PayrollEmployeeStructure::withoutGlobalScopes()->whereIn('id', $this->structureIds)->forceDelete();
        MonthlyPayroll::withoutGlobalScopes()->whereIn('id', $this->monthlyPayrollIds)->forceDelete();
        User::whereIn('id', $this->scratchUserIds)->delete();

        parent::tearDown();
    }

    /** A cut-over tenant (payroll_dynamic_ui_enabled=1) with a real component catalog. */
    private function dynamicTenantFixture(): array
    {
        $tenant = Tenant::withoutGlobalScopes()->where('payroll_dynamic_ui_enabled', 1)->first();
        if (! $tenant || ! app(FeatureService::class)->enabled($tenant->id, 'payroll')) {
            $this->markTestSkipped('no cut-over payroll tenant fixture available in dev DB');
        }

        $catalogCount = PayrollComponentMaster::withoutGlobalScope('tenant')->where('tenant_id', $tenant->id)->count();
        if ($catalogCount === 0) {
            $this->markTestSkipped('fixture tenant has no component catalog');
        }

        return [$tenant];
    }

    // ------------------------------------------------------------------
    // Legacy routes are gone
    // ------------------------------------------------------------------

    public function test_legacy_payroll_master_and_employee_payroll_routes_no_longer_exist(): void
    {
        $this->assertFalse(Route::has('payroll-masters.index'));
        $this->assertFalse(Route::has('payroll-masters.store'));
        $this->assertFalse(Route::has('employee-payrolls.index'));
        $this->assertFalse(Route::has('employee-payrolls.store'));
    }

    // ------------------------------------------------------------------
    // Wizard payroll step writes only to the dynamic engine
    // ------------------------------------------------------------------

    public function test_save_step6_creates_a_dynamic_structure_and_no_legacy_user_payroll_row(): void
    {
        [$tenant] = $this->dynamicTenantFixture();

        app()->instance('current_tenant', $tenant);

        $admin = User::where('tenant_id', $tenant->id)->where('role', 'admin')->first();
        if (! $admin) {
            $this->markTestSkipped('no admin user for the fixture tenant');
        }
        $this->actingAs($admin);

        $user = User::create([
            'tenant_id' => $tenant->id,
            'employee_id' => 'TESTLR' . uniqid(),
            'name' => 'Legacy Removal Test User',
            'email' => 'legacy-removal-' . uniqid() . '@example.test',
            'password' => bcrypt('secret'),
            'role' => 'employee',
            'status' => 1,
        ]);
        $this->scratchUserIds[] = $user->id;
        UserJobDetail::create(['tenant_id' => $tenant->id, 'user_id' => $user->id]);

        $request = \Illuminate\Http\Request::create('/save-step', 'POST', [
            'annual_ctc' => 600000,
            'salary_effective_date' => now()->toDateString(),
            'basic_salary' => 50000,
            'hra' => 0, 'conveyence' => 0, 'medical_allowance' => 0,
            'provident_fund' => 0, 'employer_provident_fund' => 0,
            'esi' => 0, 'employer_esi' => 0, 'professional_tax' => 0,
            'net_salary' => 50000, 'gross_salary' => 50000,
        ]);

        $controller = app(\App\Http\Controllers\User\UserController::class);
        $ref = new ReflectionMethod($controller, 'saveStep6');
        $ref->setAccessible(true);
        $ref->invoke($controller, $request, $user->employee_id);

        $structure = PayrollEmployeeStructure::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)->where('user_id', $user->id)->where('is_current', 1)->first();
        $this->assertNotNull($structure, 'saveStep6 must create a dynamic payroll structure');
        $this->structureIds[] = $structure->id;

        $legacyRow = UserPayroll::withoutGlobalScopes()->where('user_id', $user->id)->exists();
        $this->assertFalse($legacyRow, 'saveStep6 must not create a legacy user_payrolls row');
    }

    // ------------------------------------------------------------------
    // A dynamic-only employee (no user_payrolls row at all) can be paid
    // ------------------------------------------------------------------

    public function test_monthly_payroll_can_be_created_without_a_legacy_employee_payroll_id(): void
    {
        [$tenant] = $this->dynamicTenantFixture();

        app()->instance('current_tenant', $tenant);

        $user = User::create([
            'tenant_id' => $tenant->id,
            'employee_id' => 'TESTLR' . uniqid(),
            'name' => 'Dynamic Only Test User',
            'email' => 'dynamic-only-' . uniqid() . '@example.test',
            'password' => bcrypt('secret'),
            'role' => 'employee',
            'status' => 1,
        ]);
        $this->scratchUserIds[] = $user->id;

        $this->assertNull(UserPayroll::withoutGlobalScopes()->where('user_id', $user->id)->first());

        $month = now()->subMonths(2)->format('Y-m');
        $mp = MonthlyPayroll::create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'employee_payroll_id' => null,
            'payroll_month' => $month,
            'processing_date' => now(),
            'total_working_days' => 30,
            'present_days' => 30,
            'basic_salary' => 50000,
            'hra' => 0, 'conveyence' => 0, 'medical_allowance' => 0,
            'provident_fund' => 0, 'esi' => 0, 'professional_tax' => 0,
            'overtime_hours' => 0, 'overtime_amount' => 0,
            'gross_earnings' => 50000,
            'total_deductions' => 0,
            'net_payable' => 50000,
            'payment_status' => 'pending',
            'engine_version' => 'dynamic_v1',
        ]);
        $this->monthlyPayrollIds[] = $mp->id;

        $this->assertNull($mp->fresh()->employee_payroll_id);
    }

    // ------------------------------------------------------------------
    // Historical legacy-engine payslips still render after the removal
    // ------------------------------------------------------------------

    public function test_a_historical_legacy_engine_payslip_still_renders(): void
    {
        $legacyPayroll = MonthlyPayroll::withoutGlobalScopes()
            ->where('engine_version', 'legacy_fixed')
            ->orderByDesc('id')
            ->first();

        if (! $legacyPayroll) {
            $this->markTestSkipped('no legacy_fixed monthly_payrolls row available in dev DB');
        }

        $tenantId = (int) $legacyPayroll->tenant_id;
        $admin = User::where('tenant_id', $tenantId)->where('role', 'admin')->first();
        if (! $admin) {
            $this->markTestSkipped('no admin user for the fixture tenant');
        }

        $response = $this->actingAs($admin)->get(route('monthly-payrolls.show', $legacyPayroll->id));
        $response->assertOk();
    }
}
