<?php

namespace Tests\Feature;

use App\Http\Controllers\User\EmployeeProfileActionController;
use App\Models\User;
use App\Services\Attendance\LatePolicyService;
use App\Services\Attendance\PolicyResolver;
use App\Services\EmployeePolicyService;
use App\Services\Payroll\PayrollAttendanceContextBuilder;
use App\Services\Performance\PerformancePolicyResolver;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Employee 360 page (Phase 3): company policy values customised for one
 * employee (`employee_policy_overrides`). No override → the company value;
 * an override → that employee's value in attendance grading, overtime,
 * performance scoring and leave. Runs on the dev DB — every test is wrapped
 * in a transaction that is rolled back.
 */
class EmployeePolicyOverrideTest extends TestCase
{
    private User $admin;
    private User $employee;
    private User $colleague;
    private int $tenantId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::withoutGlobalScopes()->where('role', 'admin')->where('status', 1)->whereNotNull('tenant_id')->orderBy('id')->firstOrFail();
        $this->tenantId = (int) $this->admin->tenant_id;

        $ids = DB::table('attendances')->join('users', 'users.id', '=', 'attendances.user_id')
            ->where('attendances.tenant_id', $this->tenantId)->where('users.role', '!=', 'admin')
            ->groupBy('attendances.user_id')->orderByRaw('COUNT(*) DESC')->limit(2)->pluck('attendances.user_id');
        if ($ids->count() < 2) {
            $this->markTestSkipped('Needs two employees with attendance in the dev DB.');
        }
        $this->employee = User::withoutGlobalScopes()->findOrFail($ids[0]);
        $this->colleague = User::withoutGlobalScopes()->findOrFail($ids[1]);

        DB::beginTransaction();
        DB::table('employee_policy_overrides')->where('tenant_id', $this->tenantId)
            ->whereIn('user_id', [$this->employee->id, $this->colleague->id])->delete();
        $this->policy()->forget();
    }

    protected function tearDown(): void
    {
        while (DB::transactionLevel() > 0) {
            DB::rollBack();
        }

        parent::tearDown();
    }

    private function policy(): EmployeePolicyService
    {
        return app(EmployeePolicyService::class);
    }

    private function saveUrl(string $section, ?int $userId = null): string
    {
        return route('employee.profile.policy', ['id' => encrypt($userId ?? $this->employee->id), 'section' => $section]);
    }

    private function formUrl(string $form): string
    {
        return route('employee.profile.form', ['id' => encrypt($this->employee->id), 'form' => $form]);
    }

    private function tabOn(string $tab): bool
    {
        return in_array($tab, app(EmployeeProfileActionController::class)->enabledTabs(), true);
    }

    public function test_policies_tab_and_forms_render(): void
    {
        $this->actingAs($this->admin);
        $this->policy()->save($this->tenantId, $this->employee->id, 'attendance', ['monthly_late_allowance' => 1], $this->admin->id);

        $tab = $this->get(route('employee.profile.tab', ['id' => encrypt($this->employee->id), 'tab' => 'policies']))->assertOk();
        if ($this->tabOn('attendance')) {
            $tab->assertSee('1 custom')->assertSee('/form/policy-attendance', false);
            $this->get($this->formUrl('policy-attendance'))->assertOk()->assertSee('custom[monthly_late_allowance]', false);
        }
        if ($this->tabOn('performance')) {
            $this->get($this->formUrl('policy-performance'))->assertOk()->assertSee('custom[weight_attendance]', false);
        }
        if ($this->tabOn('leave')) {
            $this->get($this->formUrl('policy-leave'))->assertOk()->assertSee('data-p360-form', false);
        }
    }

    public function test_saving_attendance_overrides(): void
    {
        if (!$this->tabOn('attendance')) {
            $this->markTestSkipped('Attendance is not in this company\'s plan.');
        }
        $this->actingAs($this->admin);
        $url = $this->saveUrl('attendance');
        $today = now()->toDateString();
        $company = app(PolicyResolver::class)->forTenantDate($this->tenantId, $today);

        // Ticked without a value → refused.
        $this->postJson($url, ['custom' => ['monthly_late_allowance' => 1], 'value' => []])->assertStatus(422);
        // Half-day threshold above the present threshold → refused.
        $this->postJson($url, ['custom' => ['half_day_ratio' => 1, 'present_ratio' => 1], 'value' => ['half_day_ratio' => 0.9, 'present_ratio' => 0.5]])
            ->assertStatus(422)->assertJson(['success' => false]);

        // Only ticked settings are stored; an unticked value is ignored.
        $this->postJson($url, [
            'custom' => ['monthly_late_allowance' => 1, 'fixed_grace_minutes' => 1, 'late_attendance_action' => 1],
            'value' => ['monthly_late_allowance' => 2, 'fixed_grace_minutes' => 25, 'late_attendance_action' => 'half_day', 'sandwich_leave' => 1],
        ])->assertOk()->assertJson(['success' => true]);

        $this->assertEqualsCanonicalizing(
            ['monthly_late_allowance', 'fixed_grace_minutes', 'late_attendance_action'],
            DB::table('employee_policy_overrides')->where('user_id', $this->employee->id)->where('section', 'attendance')->pluck('key')->all()
        );

        $mine = app(PolicyResolver::class)->forUserDate($this->tenantId, $this->employee->id, $today);
        $this->assertSame(2, $mine->monthlyLateAllowance);
        $this->assertSame(25, $mine->fixedGraceMinutes);
        $this->assertSame('half_day', $mine->lateAttendanceAction);
        $this->assertSame($company->sandwichLeave, $mine->sandwichLeave);      // not ticked → company
        $this->assertSame($company->presentRatio, $mine->presentRatio);

        // Nobody else is affected, and the company policy itself is untouched.
        $this->assertEquals($company, app(PolicyResolver::class)->forUserDate($this->tenantId, $this->colleague->id, $today));
        $this->assertEquals($company, app(PolicyResolver::class)->forTenantDate($this->tenantId, $today));

        $this->assertTrue(DB::table('audit_logs')->where('tenant_id', $this->tenantId)->where('action', 'employee.policy_override_saved')
            ->where('entity_id', $this->employee->id)->exists());

        // Nothing ticked (the "reset" link) → back to the company policy.
        $this->postJson($url, [])->assertOk();
        $this->assertSame(0, DB::table('employee_policy_overrides')->where('user_id', $this->employee->id)->count());
        $this->assertEquals($company, app(PolicyResolver::class)->forUserDate($this->tenantId, $this->employee->id, $today));
    }

    public function test_late_allowance_override_changes_this_employees_month(): void
    {
        $late = app(LatePolicyService::class);

        // A month in which this employee was late at least once.
        $month = null;
        for ($i = 0; $i < 12 && !$month; $i++) {
            $candidate = now()->subMonths($i)->format('Y-m');
            if ($late->excessCounts($this->employee->id, $this->tenantId, $candidate)['lateSeen'] > 0) {
                $month = $candidate;
            }
        }
        if (!$month) {
            $this->markTestSkipped('Needs a month with a late day.');
        }
        $colleagueBefore = $late->excessCounts($this->colleague->id, $this->tenantId, $month);

        $this->policy()->save($this->tenantId, $this->employee->id, 'attendance', ['monthly_late_allowance' => 0], $this->admin->id);
        $strict = $late->excessCounts($this->employee->id, $this->tenantId, $month);
        $this->assertSame($strict['lateSeen'], $strict['lateExcess']);   // no allowance: every late day is an excess day

        $this->policy()->save($this->tenantId, $this->employee->id, 'attendance', ['monthly_late_allowance' => 31], $this->admin->id);
        $this->assertSame(0, $late->excessCounts($this->employee->id, $this->tenantId, $month)['lateExcess']);

        $this->assertSame($colleagueBefore, $late->excessCounts($this->colleague->id, $this->tenantId, $month));
    }

    public function test_overtime_overrides(): void
    {
        $policy = $this->policy();
        $company = (object) ['rate_multiplier' => 1.5, 'max_hours_per_day' => 4, 'max_hours_per_month' => null, 'require_approval' => 1, 'auto_approve_limit' => null];

        // No custom value → the very same company object.
        $this->assertSame($company, $policy->overtime($this->tenantId, $this->employee->id, $company));
        $this->assertTrue($policy->overtimeEligible($this->tenantId, $this->employee->id));
        $this->assertNull($policy->overtimeRefusal($this->tenantId, $this->employee->id, now()->toDateString(), 2));

        $policy->save($this->tenantId, $this->employee->id, 'overtime', ['rate_multiplier' => 2.5, 'max_hours_per_day' => 1, 'max_hours_per_month' => 3], $this->admin->id);
        $mine = $policy->overtime($this->tenantId, $this->employee->id, $company);
        $this->assertEquals(2.5, $mine->rate_multiplier);
        $this->assertEquals(1, $mine->max_hours_per_day);
        $this->assertEquals(1, $mine->require_approval);               // not customised → company
        $this->assertEquals(1.5, $company->rate_multiplier);           // the company object is not modified
        $this->assertSame($company, $policy->overtime($this->tenantId, $this->colleague->id, $company));

        // Their own monthly cap.
        $this->assertNull($policy->overtimeRefusal($this->tenantId, $this->employee->id, '2031-03-04', 3));
        $this->assertNotNull($policy->overtimeRefusal($this->tenantId, $this->employee->id, '2031-03-04', 3.5));

        // Both payroll engines' attendance context: custom rate, and nothing at all when not eligible.
        $month = now()->subMonth()->format('Y-m');
        $context = app(PayrollAttendanceContextBuilder::class)->build($this->employee->id, $this->tenantId, $month);
        $this->assertEquals(2.5, $context['overtime_rate_multiplier']);

        $policy->save($this->tenantId, $this->employee->id, 'overtime', ['eligible' => false], $this->admin->id);
        $this->assertFalse($policy->overtimeEligible($this->tenantId, $this->employee->id));
        $this->assertSame('This employee is not eligible for overtime.', $policy->overtimeRefusal($this->tenantId, $this->employee->id, now()->toDateString(), 1));
        $context = app(PayrollAttendanceContextBuilder::class)->build($this->employee->id, $this->tenantId, $month);
        $this->assertEquals(0, $context['approved_overtime_hours']);
        $this->assertEquals(0, $context['extra_shift_overtime_hours']);

        // The overtime request endpoint refuses them too.
        if (app(\App\Services\FeatureService::class)->enabled($this->tenantId, 'overtime')) {
            $this->actingAs($this->employee)->postJson(route('overtime.store'), [
                'date' => now()->toDateString(), 'overtime_hours' => 1, 'reason' => 'p360 policy test',
            ])->assertStatus(422)->assertJson(['success' => false, 'message' => 'This employee is not eligible for overtime.']);
        }
    }

    public function test_performance_overrides(): void
    {
        if (!$this->tabOn('performance')) {
            $this->markTestSkipped('Performance is not in this company\'s plan.');
        }
        $this->actingAs($this->admin);
        $today = now()->toDateString();
        $company = app(PerformancePolicyResolver::class)->forTenantDate($this->tenantId, $today);

        // Changing one weight alone breaks the 100 total → refused.
        $this->postJson($this->saveUrl('performance'), [
            'custom' => ['weight_attendance' => 1], 'value' => ['weight_attendance' => $company->weightAttendance + 10],
        ])->assertStatus(422);

        $this->postJson($this->saveUrl('performance'), [
            'custom' => ['late_penalty_per_incident' => 1, 'late_grace_minutes' => 1],
            'value' => ['late_penalty_per_incident' => 7.5, 'late_grace_minutes' => 45],
        ])->assertOk();

        $mine = app(PerformancePolicyResolver::class)->forUserDate($this->tenantId, $this->employee->id, $today);
        $this->assertSame(7.5, $mine->latePenaltyPerIncident);
        $this->assertSame(45, $mine->lateGraceMinutes);
        $this->assertSame($company->weightAttendance, $mine->weightAttendance);
        $this->assertEquals($company, app(PerformancePolicyResolver::class)->forUserDate($this->tenantId, $this->colleague->id, $today));
    }

    public function test_leave_overrides(): void
    {
        if (!$this->tabOn('leave')) {
            $this->markTestSkipped('Leave is not in this company\'s plan.');
        }
        $types = DB::table('leave_types')->where('tenant_id', $this->tenantId)->where('status', 1)->orderBy('id')->limit(2)->get();
        if ($types->count() < 2) {
            $this->markTestSkipped('Needs two leave types.');
        }
        [$blocked, $custom] = [$types[0], $types[1]];
        $policy = $this->policy();
        $start = Carbon::parse('2031-03-03');

        $this->actingAs($this->admin)->postJson($this->saveUrl('leave'), [
            'custom' => [$blocked->id => ['allowed' => 1], $custom->id => ['credit_value' => 1, 'max_consecutive_days' => 1]],
            'value' => [$blocked->id => ['allowed' => 0], $custom->id => ['credit_value' => 2.5, 'max_consecutive_days' => 2, 'min_notice_days' => 9]],
        ])->assertOk()->assertJson(['success' => true]);

        $this->assertFalse($policy->leaveAllowed($this->tenantId, $this->employee->id, $blocked->id));
        $this->assertTrue($policy->leaveAllowed($this->tenantId, $this->colleague->id, $blocked->id));

        $rule = $policy->leaveRule($this->tenantId, $this->employee->id, $custom);
        $this->assertEquals(2.5, $rule->credit_value);
        $this->assertEquals(2, $rule->max_consecutive_days);
        $this->assertEquals($custom->min_notice_days, $rule->min_notice_days);      // not ticked → the type's own
        $this->assertSame($custom, $policy->leaveRule($this->tenantId, $this->colleague->id, $custom));

        $this->assertStringContainsString('not available', (string) $policy->leaveRefusal($this->tenantId, $this->employee->id, $blocked, $start, $start));
        $this->assertStringContainsString('more than 2 consecutive', (string) $policy->leaveRefusal(
            $this->tenantId, $this->employee->id, $custom, $start, $start->copy()->addDays(2), customOnly: true, notice: false
        ));
        $this->assertNull($policy->leaveRefusal($this->tenantId, $this->employee->id, $custom, $start, $start->copy()->addDay(), customOnly: true, notice: false));

        // The employee's own web form refuses the switched-off type …
        $this->actingAs($this->employee)->post(route('leave.apply-store'), [
            'leave_type' => $blocked->id, 'start_date' => $start->toDateString(), 'start_session' => 'fullday',
            'end_date' => $start->toDateString(), 'end_session' => 'fullday', 'reason' => 'p360 policy test',
        ])->assertSessionHas('error', "{$blocked->name} is not available for this employee.");
        // … and so does "apply on behalf".
        $this->actingAs($this->admin)->postJson(route('employee.profile.apply-leave', encrypt($this->employee->id)), [
            'leave_type' => $blocked->id, 'start_date' => $start->toDateString(), 'start_session' => 'fullday',
            'end_date' => $start->toDateString(), 'end_session' => 'fullday', 'reason' => 'p360 policy test',
        ])->assertStatus(422);
        $this->assertFalse(DB::table('leaves')->where('reason', 'p360 policy test')->exists());

        // Unticking everything returns the employee to the leave type's rules.
        $this->actingAs($this->admin)->postJson($this->saveUrl('leave'), [])->assertOk();
        $this->assertTrue($policy->leaveAllowed($this->tenantId, $this->employee->id, $blocked->id));
        $this->assertSame($custom, $policy->leaveRule($this->tenantId, $this->employee->id, $custom));
    }

    public function test_policies_are_for_admin_and_hr_and_this_company_only(): void
    {
        $this->actingAs($this->colleague)->postJson($this->saveUrl('attendance'), [])->assertForbidden();

        $this->actingAs($this->admin);
        $this->postJson($this->saveUrl('nonsense'), [])->assertNotFound();

        $other = User::withoutGlobalScopes()->where('tenant_id', '!=', $this->tenantId)->whereNotNull('tenant_id')
            ->where('role', '!=', 'admin')->value('id');
        if ($other && $this->tabOn('attendance')) {
            $this->postJson($this->saveUrl('attendance', (int) $other), [
                'custom' => ['monthly_late_allowance' => 1], 'value' => ['monthly_late_allowance' => 1],
            ])->assertNotFound();
            $this->assertSame(0, DB::table('employee_policy_overrides')->where('user_id', $other)->count());
        }
    }
}
