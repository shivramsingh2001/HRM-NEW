<?php

namespace Tests\Feature;

use App\Models\MonthlyPayroll;
use App\Models\PayrollAuditLog;
use App\Models\PayrollMaster;
use App\Models\User;
use App\Models\UserPayroll;
use App\Services\Attendance\PeriodLockService;
use App\Services\FeatureService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Tests\TestCase;

/**
 * Payroll Audit — Phase 3 (Consistency of Secondary Surfaces) fixes:
 *  H3 payslip PDF omitted line items/columns the Show page displays
 *  H4 index page's stat tiles only aggregated the current page of results
 *  H5 payroll master names were validated as globally unique instead of per-tenant
 *  M2 a live route rendered a Blade view that doesn't exist (removed)
 *  M3 "Process New Month" calculation-summary preview was dead on every page load
 *  M4 payroll master's <=100% earnings/deductions cap was enforced only in the browser
 *  M6 arrears reconciliation silently no-op'd with no way to tell why
 *  H8 no deliberate, audited path to correct payroll after it's paid
 *
 * Runs against the shared dev DB (no RefreshDatabase, matching the Phase 1/2 convention);
 * each test creates its own throwaway rows and removes them in tearDown.
 */
class PayrollAuditPhase3Test extends TestCase
{
    private array $monthlyPayrollIds = [];
    private array $userPayrollIds = [];
    private array $scratchUserIds = [];
    private array $payrollMasterIds = [];
    private array $auditLogIds = [];
    private array $attendancePolicyLockCleanup = [];
    private ?int $dynamicFlagTenantId = null;
    private $dynamicFlagOriginalValue = null;

    protected function tearDown(): void
    {
        DB::table('payroll_components')->whereIn('monthly_payroll_id', $this->monthlyPayrollIds)->delete();
        DB::table('payroll_audit_logs')
            ->where('auditable_type', MonthlyPayroll::class)
            ->whereIn('auditable_id', $this->monthlyPayrollIds)
            ->delete();
        MonthlyPayroll::whereIn('id', $this->monthlyPayrollIds)->forceDelete();

        UserPayroll::whereIn('id', $this->userPayrollIds)->forceDelete();
        User::whereIn('id', $this->scratchUserIds)->delete();
        PayrollMaster::whereIn('id', $this->payrollMasterIds)->forceDelete();
        PayrollAuditLog::whereIn('id', $this->auditLogIds)->delete();

        foreach ($this->attendancePolicyLockCleanup as [$tenantId, $month]) {
            DB::table('attendance_period_locks')->where('tenant_id', $tenantId)->where('year_month', $month)->delete();
        }

        if ($this->dynamicFlagTenantId !== null) {
            \App\Models\Tenant::withoutGlobalScopes()->find($this->dynamicFlagTenantId)
                ?->forceFill(['payroll_dynamic_ui_enabled' => $this->dynamicFlagOriginalValue])->save();
        }

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

    /**
     * The legacy Payroll Master screens are deliberately blocked
     * (BlocksLegacyPayrollWrites) once a tenant is cut over to the dynamic
     * engine -- the only tenant that resolves reliably over a plain HTTP
     * test request in this dev environment (APP_URL is bound to its
     * subdomain) happens to be cut over. Temporarily clears the flag so
     * these Phase 3 tests can reach PayrollMasterController::store(), and
     * restores the original value in tearDown either way.
     */
    private function temporarilyDisableDynamicFlag(int $tenantId): void
    {
        $tenant = \App\Models\Tenant::withoutGlobalScopes()->findOrFail($tenantId);
        $this->dynamicFlagTenantId = $tenantId;
        $this->dynamicFlagOriginalValue = $tenant->payroll_dynamic_ui_enabled;
        $tenant->forceFill(['payroll_dynamic_ui_enabled' => false])->save();
    }


    private function makeScratchUser(int $tenantId): User
    {
        $user = new User([
            'name' => 'PHPUnit Scratch',
            'email' => 'phpunit-scratch-' . uniqid() . '@example.test',
            'password' => bcrypt('phpunit'),
            'status' => 1,
            'role' => 'employee',
        ]);
        $user->tenant_id = $tenantId;
        $user->save();
        $this->scratchUserIds[] = $user->id;

        return $user;
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
    // H3 — payslip PDF includes what the Show page shows
    // ------------------------------------------------------------------

    public function test_payslip_pdf_includes_special_allowance_overtime_and_dynamic_components(): void
    {
        [$userPayroll, $admin] = $this->tenantFixture();
        $month = now()->subMonths(3)->format('Y-m');
        $mp = $this->makeMonthlyPayroll($userPayroll, $month, [
            'special_allowance' => 1234,
            'overtime_amount' => 555,
        ]);

        DB::table('payroll_components')->insert([
            'tenant_id' => $mp->tenant_id,
            'monthly_payroll_id' => $mp->id,
            'component_name' => 'PHPUnit Bonus',
            'component_type' => 'earning',
            'amount' => 999,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $html = view('client.payroll.monthly-payroll.pdf', [
            'monthlyPayroll' => $mp->fresh('components'),
            'bankDetails' => null,
            'company' => (object) ['company_name' => 'Test Co'],
        ])->render();

        $this->assertStringContainsString('1,234.00', $html, 'special_allowance must be rendered');
        $this->assertStringContainsString('555.00', $html, 'overtime_amount must be rendered');
        $this->assertStringContainsString('PHPUnit Bonus', $html, 'dynamic-engine-only component must be rendered');
        $this->assertStringContainsString('999.00', $html);
    }

    // ------------------------------------------------------------------
    // H4 — index page stat tiles aggregate the full filtered set
    // ------------------------------------------------------------------

    public function test_index_stat_tiles_aggregate_the_full_filtered_set_not_just_one_page(): void
    {
        [, $admin, $tenantId] = $this->tenantFixture();
        $month = '2033-01';
        $payrollMasterId = DB::table('payroll_masters')->where('tenant_id', $tenantId)->value('id');
        if (! $payrollMasterId) {
            $this->markTestSkipped('fixture tenant has no payroll master to attach scratch salaries to');
        }

        $expectedTotalNet = 0.0;
        $expectedPaidNet = 0.0;

        for ($i = 0; $i < 17; $i++) {
            $user = $this->makeScratchUser($tenantId);
            $upId = DB::table('user_payrolls')->insertGetId([
                'tenant_id' => $tenantId, 'user_id' => $user->id,
                'payroll_master_id' => $payrollMasterId,
                'payroll_code' => 'PU' . $i . uniqid(), 'effective_from' => '2033-01-01',
                'is_current' => 1, 'basic_salary' => 10000, 'status' => 1,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $this->userPayrollIds[] = $upId;

            $netPayable = 10000 + $i;
            $status = $i % 3 === 0 ? 'paid' : 'pending';
            $mpId = DB::table('monthly_payrolls')->insertGetId([
                'tenant_id' => $tenantId, 'user_id' => $user->id, 'employee_payroll_id' => $upId,
                'payroll_month' => $month, 'processing_date' => now(),
                'total_working_days' => 31, 'present_days' => 30,
                'basic_salary' => 10000, 'gross_earnings' => $netPayable,
                'total_deductions' => 0, 'net_payable' => $netPayable,
                'payment_status' => $status, 'created_at' => now(), 'updated_at' => now(),
            ]);
            $this->monthlyPayrollIds[] = $mpId;

            $expectedTotalNet += $netPayable;
            if ($status === 'paid') {
                $expectedPaidNet += $netPayable;
            }
        }

        $response = $this->actingAs($admin)->get(route('monthly-payrolls.index', ['month' => $month]));
        $response->assertOk();

        $viewTotalNet = (float) $response->viewData('totalNet');
        $viewPaidAmount = (float) $response->viewData('paidAmount');

        $this->assertEqualsWithDelta($expectedTotalNet, $viewTotalNet, 0.01, 'totalNet must reflect the full filtered set (17 rows), not just the first page of 15');
        $this->assertEqualsWithDelta($expectedPaidNet, $viewPaidAmount, 0.01, 'paidAmount must reflect the full filtered set');
    }

    // ------------------------------------------------------------------
    // H5 — payroll master name uniqueness is per-tenant
    // ------------------------------------------------------------------

    public function test_payroll_master_name_uniqueness_is_scoped_per_tenant(): void
    {
        [, $admin, $tenantId] = $this->tenantFixture();
        $this->temporarilyDisableDynamicFlag($tenantId);
        $name = 'PHPUnit Shared Name ' . uniqid();

        $r1 = $this->actingAs($admin)->post(route('payroll-masters.store'), [
            'name' => $name, 'payroll_calculation_type' => 'day_based',
        ]);
        $r1->assertRedirect(route('payroll-masters.index'));
        $id1 = PayrollMaster::withoutGlobalScope('tenant')->where('tenant_id', $tenantId)->where('name', $name)->value('id');
        $this->assertNotNull($id1, 'tenant must be able to create a master with this name');
        $this->payrollMasterIds[] = $id1;

        // The SAME tenant reusing the name again must be rejected.
        $r2 = $this->actingAs($admin)->post(route('payroll-masters.store'), [
            'name' => $name, 'payroll_calculation_type' => 'day_based',
        ]);
        $r2->assertSessionHasErrors('name');

        // Cross-tenant allowance, verified at the validation-rule level directly
        // (the same Rule::unique()->where('tenant_id', ...) construction
        // PayrollMasterController::store() uses) -- a full HTTP-level
        // cross-tenant check isn't reliable in this dev environment, since
        // APP_URL is bound to one tenant's subdomain and a different tenant's
        // admin can't reach their own tenant context over a plain test request.
        $otherTenantId = $tenantId + 1;
        $crossTenantValidator = Validator::make(['name' => $name], [
            'name' => Rule::unique('payroll_masters', 'name')->where('tenant_id', $otherTenantId),
        ]);
        $this->assertFalse($crossTenantValidator->fails(), 'a different tenant_id must be allowed to reuse the same master name');
    }

    // ------------------------------------------------------------------
    // M2 — dead view-payslip route removed
    // ------------------------------------------------------------------

    public function test_view_payslip_route_no_longer_exists(): void
    {
        $this->expectException(RouteNotFoundException::class);
        route('monthly-payrolls.view-payslip', 1);
    }

    // ------------------------------------------------------------------
    // M4 — server-side percent cap
    // ------------------------------------------------------------------

    public function test_payroll_master_rejects_earnings_over_100_percent_server_side(): void
    {
        [, $admin, $tenantId] = $this->tenantFixture();
        $this->temporarilyDisableDynamicFlag($tenantId);

        $response = $this->actingAs($admin)->post(route('payroll-masters.store'), [
            'name' => 'PHPUnit Over Cap ' . uniqid(),
            'payroll_calculation_type' => 'day_based',
            'hra' => 60, 'conveyence' => 30, 'medical_allowance' => 20, // 110% total
        ]);

        $response->assertSessionHasErrors('hra');
        $this->assertNull(PayrollMaster::withoutGlobalScope('tenant')->where('name', 'like', 'PHPUnit Over Cap%')->first());
    }

    // ------------------------------------------------------------------
    // M6 — arrears reconciliation contract unchanged (regression check)
    // ------------------------------------------------------------------

    public function test_arrears_calculator_still_returns_empty_collection_with_no_processed_history(): void
    {
        [$userPayroll, , $tenantId] = $this->tenantFixture();

        $structure = new \App\Models\PayrollEmployeeStructure([
            'tenant_id' => $tenantId,
            'user_id' => $userPayroll->user_id,
            'effective_from' => now()->subMonths(2)->startOfMonth()->toDateString(),
            'is_current' => true,
            'status' => 'active',
            'ctc' => 100000,
        ]);
        $structure->exists = true; // avoid touching the model event/persist path -- pure input object

        $result = app(\App\Services\Payroll\PayrollArrearsCalculator::class)->computeForRevision($structure);

        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $result);
        $this->assertTrue($result->isEmpty(), 'no processed/paid legacy payslips exist for this synthetic structure -- must still return an empty Collection, not throw or change contract');
    }

    // ------------------------------------------------------------------
    // H8 — reopen for correction
    // ------------------------------------------------------------------

    public function test_reopen_rejects_pending_payroll_and_non_manage_user(): void
    {
        [$userPayroll, $admin, $tenantId] = $this->tenantFixture();
        $month = now()->subMonths(6)->format('Y-m');
        $mp = $this->makeMonthlyPayroll($userPayroll, $month); // pending by default

        $this->actingAs($admin)
            ->post(route('monthly-payrolls.reopen', $mp->id), ['reason' => 'testing reopen on a pending payslip'])
            ->assertRedirect();
        $this->assertSame('pending', $mp->fresh()->payment_status);

        $employee = User::where('tenant_id', $tenantId)->where('role', 'employee')->first();
        if ($employee) {
            $mp->update(['payment_status' => 'paid']);
            $this->actingAs($employee)
                ->post(route('monthly-payrolls.reopen', $mp->id), ['reason' => 'testing reopen as a non-manage user'])
                ->assertRedirect(route('dashboard'));
            $this->assertSame('paid', $mp->fresh()->payment_status);
        }
    }

    public function test_reopen_flips_a_paid_payroll_back_to_pending_and_logs_the_reason(): void
    {
        [$userPayroll, $admin] = $this->tenantFixture();
        $month = now()->subMonths(6)->format('Y-m');
        $mp = $this->makeMonthlyPayroll($userPayroll, $month, ['payment_status' => 'paid']);

        $reason = 'PHPUnit correction reason ' . uniqid();

        $this->actingAs($admin)
            ->post(route('monthly-payrolls.reopen', $mp->id), ['reason' => $reason])
            ->assertRedirect(route('monthly-payrolls.edit', $mp->id));

        $this->assertSame('pending', $mp->fresh()->payment_status);

        $log = PayrollAuditLog::where('auditable_type', MonthlyPayroll::class)
            ->where('auditable_id', $mp->id)
            ->where('action', 'reopened')
            ->latest('id')->first();
        $this->assertNotNull($log, 'an explicit reopened audit log row must be written');
        $this->assertSame($reason, $log->new_values['reason'] ?? null);
        if ($log) {
            $this->auditLogIds[] = $log->id;
        }

        // The now-pending payroll must be normally editable again (regression check on the existing update() flow).
        $this->actingAs($admin)
            ->put(route('monthly-payrolls.update', $mp->id), [
                'payroll_month' => $mp->payroll_month,
                'present_days' => $mp->present_days,
                'overtime_hours' => 0, 'overtime_amount' => 0,
                'basic_salary' => $mp->basic_salary,
                'hra' => 0, 'conveyence' => 0, 'medical_allowance' => 0,
                'provident_fund' => 0, 'esi' => 0, 'professional_tax' => 0,
            ])
            ->assertRedirect();
        $this->assertSame('pending', $mp->fresh()->payment_status);
    }

    public function test_reopen_is_blocked_while_the_period_is_locked(): void
    {
        [$userPayroll, $admin, $tenantId] = $this->tenantFixture();
        $month = now()->subMonths(7)->format('Y-m');
        $mp = $this->makeMonthlyPayroll($userPayroll, $month, ['payment_status' => 'paid']);

        $originalAutolock = config('attendance.period_autolock');
        config(['attendance.period_autolock' => true]);
        app(PeriodLockService::class)->lock($tenantId, $month, $admin->id, 'phpunit lock');
        $this->attendancePolicyLockCleanup[] = [$tenantId, $month];

        try {
            $this->actingAs($admin)
                ->post(route('monthly-payrolls.reopen', $mp->id), ['reason' => 'testing reopen while period is locked'])
                ->assertRedirect();
            $this->assertSame('paid', $mp->fresh()->payment_status, 'reopen must be rejected while the period is locked');
        } finally {
            config(['attendance.period_autolock' => $originalAutolock]);
        }
    }
}
