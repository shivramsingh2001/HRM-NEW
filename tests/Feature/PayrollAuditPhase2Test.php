<?php

namespace Tests\Feature;

use App\Http\Controllers\Payroll\MonthlyPayrollController;
use App\Models\LeaveType;
use App\Models\PayrollComponentMaster;
use App\Models\PayrollEmployeeStructure;
use App\Models\User;
use App\Models\UserPayroll;
use App\Services\FeatureService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use ReflectionMethod;
use RuntimeException;
use Tests\TestCase;

/**
 * Payroll Audit — Phase 2 (Calculation Accuracy) fixes:
 *  H1 engine could silently compute an earning based on a deduction to ₹0
 *  H2 a backdated structure revision could create an unreachable (inverted) row
 *  C2/H7 payroll's day classification / OT hour detection diverged from the
 *        Attendance module's real, tenant-configurable policy
 *  H6 sandwich rule didn't treat paid-leave-flanked weekoffs as paid
 *  M8 day-based OT rate always divided by calendar days, no tenant option
 *  C7 the new payroll:cutover-tenant on-ramp command
 *
 * Runs against the shared dev DB (no RefreshDatabase, matching
 * PayrollAuditPhase1Test's convention); each test creates its own throwaway
 * rows and removes them in tearDown.
 */
class PayrollAuditPhase2Test extends TestCase
{
    private array $componentMasterIds = [];
    private array $structureIds = [];
    private array $scratchUserIds = [];
    private array $attendancePolicyIds = [];
    private array $leaveIds = [];

    protected function tearDown(): void
    {
        DB::table('payroll_employee_structures')->whereIn('id', $this->structureIds)->delete();
        PayrollComponentMaster::whereIn('id', $this->componentMasterIds)->forceDelete();
        DB::table('leaves')->whereIn('id', $this->leaveIds)->delete();
        DB::table('attendance_policies')->whereIn('id', $this->attendancePolicyIds)->delete();
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

    /** A throwaway employee scoped to a real tenant, so FK-constrained payroll rows have somewhere safe to point without touching any real employee's data. */
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

    private function callPrivate(?object $instance, string $class, string $method, array $args)
    {
        $ref = new ReflectionMethod($class, $method);
        $ref->setAccessible(true);

        return $ref->invokeArgs($instance, $args);
    }

    // ------------------------------------------------------------------
    // H1 — earning based on a deduction is rejected
    // ------------------------------------------------------------------

    public function test_earning_based_on_a_deduction_is_rejected_regardless_of_priority(): void
    {
        [, , $tenantId] = $this->tenantFixture();

        $deduction = PayrollComponentMaster::create([
            'tenant_id' => $tenantId,
            'code' => 'phpunit_ded_' . uniqid(),
            'name' => 'PHPUnit Deduction',
            'component_type' => 'deduction',
            'calculation_method' => 'fixed_amount',
            'calculation_base_type' => 'none',
            'priority' => 10,
            'proration_rule' => 'no_proration',
        ]);
        $this->componentMasterIds[] = $deduction->id;

        $this->expectException(RuntimeException::class);

        // Priority (99) is numerically higher than the deduction's (10), but
        // that alone must not be enough — Pass 1 (earning) can never depend
        // on a Pass 2 (deduction) component regardless of priority ordering.
        PayrollComponentMaster::create([
            'tenant_id' => $tenantId,
            'code' => 'phpunit_earn_' . uniqid(),
            'name' => 'PHPUnit Earning',
            'component_type' => 'earning',
            'calculation_method' => 'percentage',
            'calculation_base_type' => 'component',
            'calculation_base_component_id' => $deduction->id,
            'percentage_value' => 10,
            'priority' => 99,
            'proration_rule' => 'no_proration',
        ]);
    }

    public function test_deduction_based_on_another_deduction_still_saves_fine(): void
    {
        [, , $tenantId] = $this->tenantFixture();

        $base = PayrollComponentMaster::create([
            'tenant_id' => $tenantId,
            'code' => 'phpunit_ded_base_' . uniqid(),
            'name' => 'PHPUnit Base Deduction',
            'component_type' => 'deduction',
            'calculation_method' => 'fixed_amount',
            'calculation_base_type' => 'none',
            'priority' => 10,
            'proration_rule' => 'no_proration',
        ]);
        $this->componentMasterIds[] = $base->id;

        $dependent = PayrollComponentMaster::create([
            'tenant_id' => $tenantId,
            'code' => 'phpunit_ded_dep_' . uniqid(),
            'name' => 'PHPUnit Dependent Deduction',
            'component_type' => 'deduction',
            'calculation_method' => 'percentage',
            'calculation_base_type' => 'component',
            'calculation_base_component_id' => $base->id,
            'percentage_value' => 10,
            'priority' => 20,
            'proration_rule' => 'no_proration',
        ]);
        $this->componentMasterIds[] = $dependent->id;

        $this->assertNotNull($dependent->fresh());
    }

    // ------------------------------------------------------------------
    // H2 — no inverted effective_to range on a backdated revision
    // ------------------------------------------------------------------

    public function test_supersede_others_never_produces_an_inverted_effective_to_range(): void
    {
        [, , $tenantId] = $this->tenantFixture();
        $user = $this->makeScratchUser($tenantId);

        // Starts AFTER the incoming backdated revision -- must be left untouched.
        $laterId = DB::table('payroll_employee_structures')->insertGetId([
            'tenant_id' => $tenantId, 'user_id' => $user->id,
            'effective_from' => '2031-06-01', 'effective_to' => null, 'is_current' => 1,
            'ctc' => 100000, 'revision_type' => 'initial', 'status' => 'active', 'source' => 'manual',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->structureIds[] = $laterId;

        // Genuinely starts BEFORE the incoming revision -- must still be superseded (regression check).
        $earlierId = DB::table('payroll_employee_structures')->insertGetId([
            'tenant_id' => $tenantId, 'user_id' => $user->id,
            'effective_from' => '2030-01-01', 'effective_to' => null, 'is_current' => 1,
            'ctc' => 90000, 'revision_type' => 'initial', 'status' => 'active', 'source' => 'manual',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->structureIds[] = $earlierId;

        $incoming = new PayrollEmployeeStructure([
            'tenant_id' => $tenantId, 'user_id' => $user->id, 'effective_from' => '2031-01-01',
        ]);

        $this->callPrivate(null, PayrollEmployeeStructure::class, 'supersedeOthers', [$incoming]);

        $later = DB::table('payroll_employee_structures')->where('id', $laterId)->first();
        $this->assertEquals(1, $later->is_current, 'a row that starts after the incoming revision must not be superseded');
        $this->assertNull($later->effective_to, 'must never be given an effective_to before its own effective_from');

        $earlier = DB::table('payroll_employee_structures')->where('id', $earlierId)->first();
        $this->assertEquals(0, $earlier->is_current);
        $this->assertSame('superseded', $earlier->status);
        $this->assertSame('2030-12-31', $earlier->effective_to);
    }

    // ------------------------------------------------------------------
    // C2 / H7 — attendance-day classification uses the real tenant policy
    // ------------------------------------------------------------------

    public function test_attendance_status_uses_the_tenants_resolved_policy_not_hardcoded_thresholds(): void
    {
        [, , $tenantId] = $this->tenantFixture();
        $controller = app(MonthlyPayrollController::class);
        $today = now()->toDateString();

        $call = fn ($hours, $attendance = null) => $this->callPrivate(
            $controller, MonthlyPayrollController::class, 'getAttendanceStatusByShift',
            [$hours, $attendance, $tenantId, $today]
        );

        // No shift recorded -- falls back to the resolved policy's hour
        // ladder (default: fallbackPresentHours=8, fallbackHalfHours=4).
        // 6.5h would have been 'Present' under the OLD hardcoded <6h=Present
        // rule -- this locks in the new, policy-driven behavior instead.
        $this->assertSame('Half Day', $call(6.5));
        $this->assertSame('Absent', $call(3.0));
        $this->assertSame('Present', $call(8.5));

        // With a recorded shift -- default policy ratios are 90%/50%, not
        // the old hardcoded 60%/20%.
        $shift = new \stdClass();
        $shift->scheduled_shift_start = '09:00:00';
        $shift->scheduled_shift_end = '18:00:00'; // 9h shift
        // 6h / 9h = 66.7% -- OLD code said 'Present' (>=60%); new default policy says 'Half Day' (50%<=x<90%).
        $this->assertSame('Half Day', $call(6.0, $shift));
        // 8.5h / 9h = 94.4% -- present under both.
        $this->assertSame('Present', $call(8.5, $shift));
    }

    public function test_calculate_attendance_summary_uses_configured_working_hours_not_hardcoded_eight(): void
    {
        [, , $tenantId] = $this->tenantFixture();
        $user = $this->makeScratchUser($tenantId);
        $date = now()->subDays(2)->toDateString();

        DB::table('attendances')->insert([
            'tenant_id' => $tenantId, 'user_id' => $user->id, 'date' => $date,
            'worked_hours' => 10, 'status' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $controller = app(MonthlyPayrollController::class);

        // stdHoursPerDay=9 (tenant's configured working hours) -> 1h overtime, not 2h (would be the old hardcoded-8 result).
        $result = $this->callPrivate(
            $controller, MonthlyPayrollController::class, 'calculateAttendanceSummary',
            [$user->id, $date, $date, $tenantId, 9.0]
        );

        $this->assertEquals(1, $result['full_days']);
        $this->assertEqualsWithDelta(1.0, $result['overtime_hours'], 0.01);
    }

    // ------------------------------------------------------------------
    // H6 — sandwich rule treats paid-leave-flanked weekoffs as paid
    // ------------------------------------------------------------------

    public function test_sandwich_rule_treats_paid_leave_flanked_weekoff_as_paid(): void
    {
        [, , $tenantId] = $this->tenantFixture();
        $user = $this->makeScratchUser($tenantId);

        $this->attendancePolicyIds[] = DB::table('attendance_policies')->insertGetId([
            'tenant_id' => $tenantId, 'effective_from' => '2000-01-01',
            'present_ratio' => 0.90, 'half_day_ratio' => 0.50,
            'fallback_present_hours' => 8, 'fallback_half_hours' => 4,
            'overtime_after_hours' => 9, 'overtime_multiplier' => 1,
            'grace_minutes' => 0, 'rounding_minutes' => 0, 'late_halfday_enabled' => 0,
            'monthly_late_allowance' => 30, 'sandwich_leave' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $paidLeaveTypeId = LeaveType::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)->where('is_unpaid', false)->value('id');
        $this->assertNotNull($paidLeaveTypeId, 'fixture tenant must have a paid leave type configured');

        $controller = app(MonthlyPayrollController::class);

        // Week A: Fri/Mon on approved paid leave, flanking a Sat/Sun weekoff -- should be paid.
        $fridayA = Carbon::parse('2031-01-03'); // a Friday
        foreach ([$fridayA, $fridayA->copy()->addDays(3)] as $d) {
            $this->leaveIds[] = DB::table('leaves')->insertGetId([
                'tenant_id' => $tenantId, 'leave_id' => 'PU-' . substr(uniqid(), -10),
                'user_id' => $user->id, 'leave_type' => $paidLeaveTypeId,
                'start_date' => $d->toDateString(), 'end_date' => $d->toDateString(),
                'total_days' => 1, 'status' => 'approved',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $resultPaid = $this->callPrivate(
            $controller, MonthlyPayrollController::class, 'calculateDayBreakdownWithPriority',
            [
                $user->id, $fridayA->toDateString(), $fridayA->copy()->addDays(3)->toDateString(),
                [], [$fridayA->copy()->addDay()->toDateString(), $fridayA->copy()->addDays(2)->toDateString()],
                $tenantId,
            ]
        );
        $this->assertEquals(2, $resultPaid['paid_weekoffs'], 'weekoff flanked by paid leave on both sides must be paid under the sandwich rule');

        // Week B (no leave inserted -- flanking days are plain absence): weekoff must stay unpaid (regression check).
        $fridayB = $fridayA->copy()->addWeek();
        $resultUnpaid = $this->callPrivate(
            $controller, MonthlyPayrollController::class, 'calculateDayBreakdownWithPriority',
            [
                $user->id, $fridayB->toDateString(), $fridayB->copy()->addDays(3)->toDateString(),
                [], [$fridayB->copy()->addDay()->toDateString(), $fridayB->copy()->addDays(2)->toDateString()],
                $tenantId,
            ]
        );
        $this->assertEquals(0, $resultUnpaid['paid_weekoffs'], 'weekoff flanked by plain absence must still be unpaid');
    }

    // ------------------------------------------------------------------
    // M8 — OT rate divisor mode
    // ------------------------------------------------------------------

    public function test_overtime_hourly_rate_respects_the_divisor_mode(): void
    {
        $controller = app(MonthlyPayrollController::class);
        $call = fn (...$args) => $this->callPrivate($controller, MonthlyPayrollController::class, 'overtimeHourlyRate', $args);

        $default = $call(26000.0, 'day_based', 8.0, 30, 22.0);
        $this->assertEqualsWithDelta(26000 / 30 / 8, $default, 0.001, 'default mode must be unchanged from the original calendar-days behavior');

        $fixed = $call(26000.0, 'day_based', 8.0, 30, 22.0, 'fixed_working_days', 26);
        $this->assertEqualsWithDelta(26000 / 26 / 8, $fixed, 0.001);
        $this->assertGreaterThan($default, $fixed, 'a 26-day fixed divisor must yield a higher hourly rate than 30 calendar days');
    }

    // ------------------------------------------------------------------
    // C7 — payroll:cutover-tenant
    // ------------------------------------------------------------------

    public function test_cutover_tenant_reports_already_dynamic_and_refuses_unknown_tenant(): void
    {
        $exit = Artisan::call('payroll:cutover-tenant', ['tenant' => 7, '--dry-run' => true]);
        $this->assertSame(0, $exit);
        $this->assertStringContainsString('Already on the dynamic engine', Artisan::output());

        $exit = Artisan::call('payroll:cutover-tenant', ['tenant' => 999999, '--dry-run' => true]);
        $this->assertSame(1, $exit);
        $this->assertStringContainsString('not found', Artisan::output());
    }

    public function test_cutover_tenant_dry_run_never_writes(): void
    {
        [, , $tenantId] = $this->tenantFixture();
        // Use a non-dynamic tenant if the fixture one already is; skip if every fixture tenant is already cut over.
        if (DB::table('tenants')->where('id', $tenantId)->value('payroll_dynamic_ui_enabled')) {
            $this->markTestSkipped('fixture tenant is already on the dynamic engine');
        }

        $before = DB::table('tenants')->where('id', $tenantId)->value('payroll_dynamic_ui_enabled');

        $exit = Artisan::call('payroll:cutover-tenant', ['tenant' => $tenantId, '--dry-run' => true]);
        $output = Artisan::output();

        $after = DB::table('tenants')->where('id', $tenantId)->value('payroll_dynamic_ui_enabled');
        $this->assertEquals($before, $after, 'dry-run must never write, regardless of outcome');

        if ($exit === 0) {
            $this->assertStringContainsString('DRY RUN', $output);
        }
    }
}
