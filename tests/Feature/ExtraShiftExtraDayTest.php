<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureFeatureEnabled;
use App\Models\MonthlyPayroll;
use App\Models\User;
use App\Services\Attendance\ExtraShiftOvertime;
use App\Services\Attendance\OvertimePolicyService;
use App\Services\Payroll\ExtraWorkDayCalculator;
use App\Services\Payroll\PayrollCalculationEngine;
use App\Services\Payroll\PayslipContext;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Company Policies → "Additional shift pay": an assigned 2nd shift paid as
 * overtime hours (default) or as extra working days at one day's salary —
 * both payroll engines, Edit Payroll, the salary slip and the policy form.
 * Dev DB, always rolled back.
 */
class ExtraShiftExtraDayTest extends TestCase
{
    private User $admin;
    private int $tenantId;
    private string $month;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(EnsureFeatureEnabled::class);
        $this->admin = User::withoutGlobalScopes()->where('role', 'admin')->where('status', 1)->whereNotNull('tenant_id')->orderBy('id')->firstOrFail();
        $this->tenantId = (int) $this->admin->tenant_id;
        app()->instance('current_tenant', \App\Models\Tenant::find($this->tenantId));
        // A month nobody has attendance or payslips in yet.
        $this->month = now()->addMonthsNoOverflow(3)->format('Y-m');
        DB::beginTransaction();
    }

    protected function tearDown(): void
    {
        while (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
        app(OvertimePolicyService::class)->forget();
        parent::tearDown();
    }

    private function payExtraShiftAs(string $mode): void
    {
        DB::table('overtime_settings')->updateOrInsert(['tenant_id' => $this->tenantId],
            ['extra_shift_pay_mode' => $mode, 'updated_at' => now()]);
        app(OvertimePolicyService::class)->forget();
    }

    private function employee(): User
    {
        return User::withoutGlobalScopes()->forceCreate([
            'name' => 'PHPUnit XD ' . uniqid(), 'email' => 'xd.' . uniqid() . '@phpunit.test',
            'employee_id' => 'XD' . random_int(100000, 999999), 'password' => bcrypt('secret'),
            'role' => 'employee', 'status' => 1, 'tenant_id' => $this->tenantId,
        ]);
    }

    /** A worked day: primary shift 8 h, plus an additional shift of $extraMinutes (of 480 scheduled) when given. */
    private function day(int $userId, int $d, ?int $extraMinutes): void
    {
        $date = $this->month . '-' . str_pad((string) $d, 2, '0', STR_PAD_LEFT);
        $id = DB::table('attendances')->insertGetId([
            'tenant_id' => $this->tenantId, 'user_id' => $userId, 'date' => $date,
            'clock_in' => $date . ' 06:00:00', 'worked_hours' => 8 + ($extraMinutes ?? 0) / 60,
            'attendance_status' => 'present', 'effective_status' => 'present', 'attendance_type' => 'manual',
            'extra_shift_minutes' => $extraMinutes ?? 0, 'shift_count' => $extraMinutes ? 2 : 1,
            'status' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        foreach (array_filter([[false, 480], $extraMinutes ? [true, $extraMinutes] : null]) as [$additional, $minutes]) {
            DB::table('attendance_shift_segments')->insert(['tenant_id' => $this->tenantId, 'attendance_id' => $id, 'user_id' => $userId,
                'date' => $date, 'is_additional' => $additional, 'is_extra' => $additional, 'worked_minutes' => $minutes, 'expected_minutes' => 480,
                'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function test_one_day_per_extra_shift_half_when_less_than_half_worked(): void
    {
        $this->payExtraShiftAs('extra_day');
        $user = $this->employee();
        $this->day($user->id, 1, 480);   // full extra shift   → 1
        $this->day($user->id, 2, 180);   // 3 h of 8 h         → ½
        $this->day($user->id, 3, 240);   // exactly half       → 1
        $this->day($user->id, 4, null);  // primary shift only → 0

        $calc = app(ExtraWorkDayCalculator::class);
        $this->assertSame(2.5, $calc->days($this->tenantId, $user->id, $this->month)['days']);
        $start = $this->month . '-01';
        $end = Carbon::createFromFormat('Y-m', $this->month)->endOfMonth()->toDateString();
        $this->assertSame(2.5, $calc->daysByUser($this->tenantId, $start, $end)[$user->id]);

        $r = $calc->calculate($this->tenantId, $user->id, $this->month, 30000.0);
        $days = Carbon::createFromFormat('Y-m', $this->month)->daysInMonth;
        $this->assertSame(round(30000 / $days, 2), $r['rate']);
        $this->assertSame(round(2.5 * round(30000 / $days, 2), 2), $r['amount']);
        $this->assertSame('Extra Working Days (2.5 days)', $r['label']);
        $this->assertSame('Extra Working Days (1 day)', ExtraWorkDayCalculator::label(1.0));
    }

    public function test_the_mode_decides_overtime_hours_or_days_never_both(): void
    {
        $user = $this->employee();
        $this->day($user->id, 1, 480);
        $end = Carbon::createFromFormat('Y-m', $this->month)->endOfMonth()->toDateString();

        $this->payExtraShiftAs('overtime');
        $this->assertSame(8.0, app(ExtraShiftOvertime::class)->forPeriod($user->id, $this->tenantId, $this->month . '-01', $end)['total_hours']);
        $this->assertSame(0.0, app(ExtraWorkDayCalculator::class)->days($this->tenantId, $user->id, $this->month)['days']);

        $this->payExtraShiftAs('extra_day');
        $this->assertSame(0.0, app(ExtraShiftOvertime::class)->forPeriod($user->id, $this->tenantId, $this->month . '-01', $end)['total_hours']);
        $this->assertSame(1.0, app(ExtraWorkDayCalculator::class)->days($this->tenantId, $user->id, $this->month)['days']);
        $legacy = app(\App\Services\Payroll\Legacy\LegacyPayrollCalculator::class)->getApprovedOvertimeHours($user->id, $this->month, $this->tenantId);
        $this->assertSame(0.0, (float) $legacy['extra_shift_hours']);
    }

    private function legacyEmployee(): ?User
    {
        $month = Carbon::createFromFormat('Y-m', $this->month);

        return User::withoutGlobalScopes()->where('tenant_id', $this->tenantId)->where('status', 1)->orderBy('id')->get()
            ->first(fn ($u) => \App\Models\UserPayroll::withoutGlobalScopes()->where('user_id', $u->id)->where('tenant_id', $this->tenantId)
                ->where('is_current', 1)->where('status', 1)
                ->where('effective_from', '<=', $month->copy()->endOfMonth())->whereHas('payrollMaster')->exists());
    }

    private function legacySlip(User $employee): MonthlyPayroll
    {
        $month = Carbon::createFromFormat('Y-m', $this->month);
        DB::table('monthly_payrolls')->where('user_id', $employee->id)->where('payroll_month', $this->month)->delete();
        $this->actingAs($this->admin)->withSession(['tenant_id' => $this->tenantId]);

        return app(\App\Services\Payroll\PayrollRunService::class)->processLegacy($employee, $this->tenantId, $this->month,
            $month->copy()->startOfMonth()->toDateString(), $month->copy()->endOfMonth()->toDateString(), true, false);
    }

    public function test_legacy_payslip_pays_one_days_salary_per_extra_day_and_shows_it_on_the_slip(): void
    {
        $employee = $this->legacyEmployee();
        if (! $employee) {
            $this->markTestSkipped('Needs an employee with a legacy salary structure.');
        }
        $this->payExtraShiftAs('extra_day');
        $this->day($employee->id, 1, 480);
        $this->day($employee->id, 2, 120);

        $slip = $this->legacySlip($employee);

        $userPayroll = \App\Models\UserPayroll::withoutGlobalScopes()->find($slip->employee_payroll_id);
        $rate = ExtraWorkDayCalculator::dayRate(app(\App\Services\Payroll\Legacy\LegacyPayrollCalculator::class)->monthlyGross($userPayroll),
            Carbon::createFromFormat('Y-m', $this->month)->daysInMonth);
        $this->assertEquals(1.5, (float) $slip->extra_work_days);
        $this->assertEquals($rate, (float) $slip->extra_work_day_rate);
        $this->assertEquals(round(1.5 * $rate, 2), (float) $slip->extra_work_day_amount);
        // Not also paid as overtime, not added to payable days.
        $this->assertEquals(0, (float) $slip->overtime_amount);
        $this->assertLessThanOrEqual((float) $slip->total_working_days, (float) $slip->payable_days);

        $component = DB::table('payroll_components')->where('monthly_payroll_id', $slip->id)->where('component_name', 'Extra Working Days (1.5 days)')->first();
        $this->assertNotNull($component);
        $this->assertEquals(round(1.5 * $rate, 2), (float) $component->amount);
        $this->assertEquals(round((float) DB::table('payroll_components')->where('monthly_payroll_id', $slip->id)->where('component_type', 'earning')->sum('amount'), 2),
            round((float) $slip->gross_earnings, 2));

        $html = view('client.payroll.monthly-payroll.pdf', [
            'monthlyPayroll' => MonthlyPayroll::with(['user', 'components', 'payrollMaster'])->find($slip->id),
            'bankDetails' => null,
            'company' => app(PayslipContext::class)->company($this->tenantId),
        ])->render();
        $this->assertStringContainsString('Extra Working Days :', $html);
        $this->assertStringContainsString('Extra Working Days (1.5 days)', $html);
    }

    public function test_legacy_edit_saves_the_typed_extra_days_at_one_days_salary(): void
    {
        $employee = $this->legacyEmployee();
        if (! $employee) {
            $this->markTestSkipped('Needs an employee with a legacy salary structure.');
        }
        $this->payExtraShiftAs('extra_day');
        $this->day($employee->id, 1, 480);
        $slip = $this->legacySlip($employee);
        $rate = (float) $slip->extra_work_day_rate;
        $this->assertGreaterThan(0, $rate);

        $this->get(route('monthly-payrolls.edit', $slip->id))->assertOk()
            ->assertSee('Extra Working Days')->assertSee('name="extra_work_days"', false);

        $fields = ['basic_salary', 'hra', 'conveyence', 'medical_allowance', 'children_allowance', 'post_allowance', 'leave_travel_allowance',
            'monthly_incentive', 'special_allowance', 'overtime_amount', 'shift_allowance_amount', 'provident_fund', 'esi', 'professional_tax', 'tds', 'other_deductions'];
        $data = collect($fields)->mapWithKeys(fn ($f) => [$f => (float) ($slip->{$f} ?? 0)])->all() + [
            'payroll_month' => $this->month, 'present_days' => (float) $slip->present_days, 'paid_leaves' => (float) $slip->paid_leaves,
            'loan_deduction_enabled' => 0, 'salary_advance_deduction_enabled' => 0, 'confirm_negative_net_payable' => 1,
            'extra_work_days' => 2.5,
        ];
        $grossBefore = (float) $slip->gross_earnings - (float) $slip->extra_work_day_amount;

        $this->put(route('monthly-payrolls.update', $slip->id), $data)->assertRedirect(route('monthly-payrolls.show', $slip->id));

        $slip->refresh();
        $this->assertEquals(2.5, (float) $slip->extra_work_days);
        $this->assertEquals(round(2.5 * $rate, 2), (float) $slip->extra_work_day_amount);
        $this->assertEquals(round($grossBefore + 2.5 * $rate, 2), round((float) $slip->gross_earnings, 2));
        $this->assertNotNull(DB::table('payroll_components')->where('monthly_payroll_id', $slip->id)->where('component_name', 'Extra Working Days (2.5 days)')->first());
    }

    public function test_dynamic_engine_adds_the_line_and_edit_can_override_the_days(): void
    {
        $employee = User::withoutGlobalScopes()->where('tenant_id', $this->tenantId)->where('status', 1)
            ->whereHas('currentDynamicPayrollStructure')->orderBy('id')->first();
        if (! $employee) {
            $this->markTestSkipped('Needs an employee with a dynamic salary structure.');
        }
        $this->payExtraShiftAs('extra_day');
        $this->day($employee->id, 1, 480);
        $this->day($employee->id, 2, 480);

        $engine = app(PayrollCalculationEngine::class);
        $rate = $engine->extraWorkDayRate($employee, $this->tenantId, $this->month);
        $this->assertGreaterThan(0, $rate);

        $result = $engine->calculate($employee, $this->tenantId, $this->month, false);
        $line = collect($result['line_items'])->firstWhere('code', 'extra_work_days');
        $this->assertNotNull($line);
        $this->assertSame('earning', $line['component_type']);
        $this->assertSame('Extra Working Days (2 days)', $line['name']);
        $this->assertSame(round(2 * $rate, 2), (float) $line['amount']);
        $this->assertSame(2.0, $result['extra_work_days']);
        // The 2nd-shift hours are not overtime in this mode.
        $this->assertSame(0.0, (float) $result['context']['extra_shift_overtime_hours']);

        // Edit Payroll override.
        $edited = $engine->calculate($employee, $this->tenantId, $this->month, false, ['extra_work_days' => 3]);
        $this->assertSame(3.0, $edited['extra_work_days']);
        $this->assertSame(round(3 * $rate, 2), $edited['extra_work_day_amount']);
        $this->assertSame(round($result['gross_earnings'] + $rate, 2), round($edited['gross_earnings'], 2));
    }

    public function test_company_policies_form_saves_the_mode(): void
    {
        $this->actingAs($this->admin)->withSession(['tenant_id' => $this->tenantId]);
        $base = ['section' => 'limits', 'rate_type' => 'multiplier', 'rate_multiplier' => 1.5];

        $this->put(route('overtime-policy-settings.update'), $base + ['extra_shift_pay_mode' => 'extra_day'])->assertRedirect();
        $this->assertSame('extra_day', DB::table('overtime_settings')->where('tenant_id', $this->tenantId)->value('extra_shift_pay_mode'));

        $this->get(route('workforce-settings.index'))->assertOk()->assertSee('Paid as extra working day');

        $this->put(route('overtime-policy-settings.update'), $base + ['extra_shift_pay_mode' => 'overtime'])->assertRedirect();
        $this->assertSame('overtime', DB::table('overtime_settings')->where('tenant_id', $this->tenantId)->value('extra_shift_pay_mode'));

        $this->put(route('overtime-policy-settings.update'), $base + ['extra_shift_pay_mode' => 'weekly'])->assertSessionHasErrors('extra_shift_pay_mode');
    }
    public function test_working_only_the_additional_shift_is_not_an_extra_day(): void
    {
        $this->payExtraShiftAs('extra_day');
        $user = $this->employee();
        $date = $this->month . '-05';
        // The rollup makes the only shift worked the normal one: is_additional 1, is_extra 0.
        $id = DB::table('attendances')->insertGetId([
            'tenant_id' => $this->tenantId, 'user_id' => $user->id, 'date' => $date, 'clock_in' => $date . ' 15:00:00', 'worked_hours' => 8,
            'attendance_status' => 'present', 'effective_status' => 'present', 'attendance_type' => 'manual', 'extra_shift_minutes' => 0, 'shift_count' => 1,
            'status' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('attendance_shift_segments')->insert(['tenant_id' => $this->tenantId, 'attendance_id' => $id, 'user_id' => $user->id, 'date' => $date,
            'is_additional' => 1, 'is_extra' => 0, 'worked_minutes' => 480, 'expected_minutes' => 480, 'created_at' => now(), 'updated_at' => now()]);
        $this->day($user->id, 6, 480);   // a day with both shifts → 1 extra day

        $calc = app(ExtraWorkDayCalculator::class);
        $this->assertSame(1.0, $calc->days($this->tenantId, $user->id, $this->month)['days']);
        $end = Carbon::createFromFormat('Y-m', $this->month)->endOfMonth()->toDateString();
        $this->assertSame(1.0, $calc->daysByUser($this->tenantId, $this->month . '-01', $end)[$user->id]);
    }

    public function test_salary_hours_leave_out_the_extra_shift(): void
    {
        // A past month the new employee has no other data in (the summary skips future days).
        $user = $this->employee();
        $month = now()->subMonthsNoOverflow(2)->format('Y-m');
        foreach ([['01', 16.0, 480], ['02', 8.0, 0]] as [$dd, $hours, $extra]) {   // 8 h + 8 h extra, then 8 h
            $date = "$month-$dd";
            DB::table('attendances')->insert([
                'tenant_id' => $this->tenantId, 'user_id' => $user->id, 'date' => $date, 'clock_in' => "$date 06:00:00",
                'clock_out' => Carbon::parse("$date 06:00:00")->addHours((int) $hours)->format('Y-m-d H:i:s'),
                'worked_hours' => $hours, 'total_hours' => sprintf('%02d:00:00', $hours), 'attendance_status' => 'present', 'effective_status' => 'present',
                'attendance_type' => 'manual', 'extra_shift_minutes' => $extra, 'shift_count' => $extra ? 2 : 1, 'status' => 1,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        app(\App\Services\AttendanceSummaryService::class)->updateMonthlySummary($user->id, $month, $this->tenantId);

        $days = app(\App\Services\Attendance\PayrollDaysService::class)->forMonth($user->id, $month, $this->tenantId);
        $this->assertEqualsWithDelta(16.0, $days['actual_worked_hours'], 0.01);   // 24 h worked − 8 h extra
    }
}
