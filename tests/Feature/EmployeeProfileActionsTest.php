<?php

namespace Tests\Feature;

use App\Http\Controllers\User\EmployeeProfileActionController;
use App\Models\User;
use App\Services\Attendance\TenantShiftResolver;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Employee 360 page (Phase 2, actions): the form partials
 * (`employee.profile.form`), the three actions that live on the profile
 * controller (reset password, apply leave, weekly offs) and the existing
 * endpoints the forms post to. Runs on the dev DB — every test that writes is
 * wrapped in a transaction that is rolled back.
 */
class EmployeeProfileActionsTest extends TestCase
{
    private User $admin;
    private User $employee;
    private int $tenantId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::withoutGlobalScopes()->where('role', 'admin')->where('status', 1)->whereNotNull('tenant_id')->orderBy('id')->firstOrFail();
        $this->tenantId = (int) $this->admin->tenant_id;

        $id = DB::table('attendances')->join('users', 'users.id', '=', 'attendances.user_id')
            ->where('attendances.tenant_id', $this->tenantId)->where('users.role', '!=', 'admin')
            ->groupBy('attendances.user_id')->orderByRaw('COUNT(*) DESC')->value('attendances.user_id');
        if (!$id) {
            $this->markTestSkipped('Needs an employee with attendance in the dev DB.');
        }
        $this->employee = User::withoutGlobalScopes()->findOrFail($id);

        DB::beginTransaction();
    }

    protected function tearDown(): void
    {
        while (DB::transactionLevel() > 0) {
            DB::rollBack();
        }

        parent::tearDown();
    }

    private function formUrl(string $form, array $query = [], ?int $userId = null): string
    {
        return route('employee.profile.form', ['id' => encrypt($userId ?? $this->employee->id), 'form' => $form] + $query);
    }

    private function enc(): string
    {
        return encrypt($this->employee->id);
    }

    private function tabOn(string $tab): bool
    {
        return in_array($tab, app(EmployeeProfileActionController::class)->enabledTabs(), true);
    }

    public function test_the_page_shows_the_actions_menu_and_edit_links(): void
    {
        $this->actingAs($this->admin)->get(route('employee.show', $this->enc()))->assertOk()
            ->assertSee('id="p360Modal"', false)
            ->assertSee('/form/password', false)
            ->assertSee('/form/status', false)
            ->assertSee('/form/job', false)
            ->assertSee('/form/document', false);
    }

    public function test_every_form_without_a_record_renders(): void
    {
        $this->actingAs($this->admin);
        $customShifts = app(TenantShiftResolver::class)->isCustomShifts($this->tenantId);

        $forms = ['login', 'personal', 'job', 'address', 'bank', 'document', 'password', 'status'];
        if ($this->tabOn('attendance')) {
            $forms[] = 'attendance-mark';
        }
        if ($this->tabOn('leave')) {
            array_push($forms, 'leave-credit', 'leave-debit', 'leave-apply');
        }
        if ($this->tabOn('assets')) {
            $forms[] = 'asset-assign';
        }
        if ($this->tabOn('shift') && $customShifts) {
            array_push($forms, 'shift-assign', 'weekoffs');
        }

        foreach ($forms as $form) {
            $res = $this->get($this->formUrl($form));
            $this->assertSame(200, $res->getStatusCode(), "form {$form}: " . substr(strip_tags((string) $res->getContent()), 0, 300));
            $res->assertSee('data-p360-form', false);
        }

        if ($this->tabOn('shift') && !$customShifts) {
            $this->get($this->formUrl('shift-assign'))->assertForbidden();
            $this->get($this->formUrl('weekoffs'))->assertForbidden();
        }
    }

    public function test_edit_salary_form_saves_a_salary_revision(): void
    {
        if (!app(\App\Services\FeatureService::class)->enabled($this->tenantId, 'payroll')) {
            $this->markTestSkipped('Payroll is not in this company\'s plan.');
        }
        $this->actingAs($this->admin);

        $this->get(route('employee.show', $this->enc()))->assertOk()->assertSee('/form/payroll', false);
        $this->get($this->formUrl('payroll'))->assertOk()
            ->assertSee('data-p360-form', false)->assertSee('name="annual_ctc"', false)->assertSee('value="6"', false);

        // What the form posts for a 6,00,000 CTC with the default split, from a new effective date.
        $date = now()->addMonth()->startOfMonth()->toDateString();
        $this->post(route('employee.update.step', $this->enc()), [
            'step' => 6, 'annual_ctc' => 600000, 'salary_effective_date' => $date, 'payroll_structure_id' => '',
            'basic_salary' => 25000, 'hra' => 10000, 'conveyence' => 1600, 'medical_allowance' => 1250,
            'children_allowance' => 0, 'post_allowance' => 0, 'leave_travel_allowance' => 0, 'monthly_incentive' => 0,
            'special_allowance' => 0, 'provident_fund' => 1800, 'employer_provident_fund' => 1800, 'esi' => 0,
            'employer_esi' => 0, 'professional_tax' => 200, 'gross_salary' => 37850, 'net_salary' => 35850,
        ], ['Accept' => 'application/json'])->assertOk()->assertJson(['success' => true]);

        $structure = DB::table('payroll_employee_structures')->where('tenant_id', $this->tenantId)
            ->where('user_id', $this->employee->id)->whereDate('effective_from', $date)->first();
        $this->assertNotNull($structure);
        $this->assertEquals(600000, (float) $structure->ctc);
        $this->assertEquals(37850, (float) DB::table('user_job_details')->where('user_id', $this->employee->id)->value('salary'));

        // A CTC below the minimum comes back as a field error for the modal.
        $this->post(route('employee.update.step', $this->enc()), ['step' => 6, 'annual_ctc' => 5000, 'salary_effective_date' => $date],
            ['Accept' => 'application/json'])->assertStatus(422)->assertJsonStructure(['errors' => ['annual_ctc']]);
    }

    public function test_forms_are_for_admin_and_hr_and_this_company_only(): void
    {
        $plain = User::withoutGlobalScopes()->where('tenant_id', $this->tenantId)->where('role', 'employee')->where('status', 1)
            ->where('id', '!=', $this->employee->id)->first();
        if ($plain) {
            $this->actingAs($plain)->get($this->formUrl('bank'))->assertForbidden();
            $this->actingAs($plain)->post(route('employee.profile.reset-password', $this->enc()), [
                'password' => 'secret12', 'password_confirmation' => 'secret12',
            ])->assertForbidden();
        }

        $this->actingAs($this->admin);
        $this->get($this->formUrl('nonsense'))->assertNotFound();

        $other = User::withoutGlobalScopes()->where('tenant_id', '!=', $this->tenantId)->whereNotNull('tenant_id')
            ->where('role', '!=', 'admin')->value('id');
        if ($other) {
            $this->get($this->formUrl('bank', [], (int) $other))->assertNotFound();
            $this->post(route('employee.profile.reset-password', encrypt($other)), [
                'password' => 'secret12', 'password_confirmation' => 'secret12',
            ])->assertNotFound();
        }
    }

    public function test_a_record_form_only_opens_for_this_employees_own_record(): void
    {
        $this->actingAs($this->admin);

        // Unknown ids.
        foreach ([
            'leave-decide' => ['leave' => 0], 'regularization' => ['reg' => 0], 'expense-decide' => ['expense' => 0],
            'asset-return' => ['assignment' => 0], 'shift-end' => ['assignment' => 0], 'document' => ['remove' => 999999999],
        ] as $form => $query) {
            if ($this->tabOn(EmployeeProfileActionController::FORMS[$form])) {
                $this->get($this->formUrl($form, $query))->assertNotFound();
            }
        }

        if (!$this->tabOn('leave')) {
            return;
        }

        // A pending leave of ANOTHER employee must not open on this employee's page.
        $leaveType = DB::table('leave_types')->where('tenant_id', $this->tenantId)->where('status', 1)->value('id');
        $otherEmployee = User::withoutGlobalScopes()->where('tenant_id', $this->tenantId)->where('role', '!=', 'admin')
            ->where('id', '!=', $this->employee->id)->value('id');
        if (!$leaveType || !$otherEmployee) {
            return;
        }
        $row = fn (int $userId) => DB::table('leaves')->insertGetId([
            'tenant_id' => $this->tenantId, 'user_id' => $userId, 'leave_type' => $leaveType,
            'start_date' => '2031-03-03', 'end_date' => '2031-03-03', 'start_session' => 'fullday', 'end_session' => 'fullday',
            'leave_count' => 1, 'reason' => 'p360 test', 'status' => 'pending', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->get($this->formUrl('leave-decide', ['leave' => $row((int) $otherEmployee), 'decision' => 'approved']))->assertNotFound();
        $this->get($this->formUrl('leave-decide', ['leave' => $row($this->employee->id), 'decision' => 'approved']))
            ->assertOk()->assertSee('p360 test');
    }

    public function test_reset_password(): void
    {
        $this->actingAs($this->admin);
        $url = route('employee.profile.reset-password', $this->enc());

        $this->postJson($url, ['password' => 'abc', 'password_confirmation' => 'abc'])->assertStatus(422);
        $this->postJson($url, ['password' => 'secret12', 'password_confirmation' => 'different'])->assertStatus(422);

        $this->postJson($url, ['password' => 'secret12', 'password_confirmation' => 'secret12'])
            ->assertOk()->assertJson(['success' => true]);

        $hash = DB::table('users')->where('id', $this->employee->id)->value('password');
        $this->assertTrue(Hash::check('secret12', $hash));
        $this->assertTrue(DB::table('audit_logs')->where('tenant_id', $this->tenantId)->where('action', 'employee.password_reset')
            ->where('entity_id', $this->employee->id)->exists());
    }

    public function test_apply_leave_on_behalf(): void
    {
        if (!$this->tabOn('leave')) {
            $this->markTestSkipped('Leave is not in this company\'s plan.');
        }
        $this->actingAs($this->admin);
        $url = route('employee.profile.apply-leave', $this->enc());

        $leaveType = DB::table('leave_types')->where('tenant_id', $this->tenantId)->where('status', 1)
            ->where(fn ($q) => $q->where('is_unpaid', 0)->orWhereNull('is_unpaid'))->orderBy('id')->first();
        if (!$leaveType) {
            $this->markTestSkipped('Needs a paid leave type.');
        }

        // A Monday–Tuesday far in the future (no existing leave, no holiday expected).
        $start = Carbon::parse('2031-03-03');
        $payload = [
            'leave_type' => $leaveType->id, 'start_date' => $start->toDateString(), 'start_session' => 'fullday',
            'end_date' => $start->copy()->addDay()->toDateString(), 'end_session' => 'fullday', 'reason' => 'p360 on-behalf test',
        ];
        $balanceRow = fn () => DB::table('leave_balances')->where('tenant_id', $this->tenantId)
            ->where('user_id', $this->employee->id)->where('leave_type_id', $leaveType->id);

        // Not enough balance → refused, nothing created.
        $balanceRow()->delete();
        $this->postJson($url, $payload)->assertStatus(422)->assertJson(['success' => false]);
        $this->assertFalse(DB::table('leaves')->where('user_id', $this->employee->id)->where('reason', 'p360 on-behalf test')->exists());

        DB::table('leave_balances')->insert([
            'tenant_id' => $this->tenantId, 'user_id' => $this->employee->id, 'leave_type_id' => $leaveType->id,
            'balance' => 5, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->postJson($url, $payload)->assertOk()->assertJson(['success' => true]);

        $leave = DB::table('leaves')->where('user_id', $this->employee->id)->where('reason', 'p360 on-behalf test')->first();
        $this->assertNotNull($leave);
        $this->assertSame('approved', $leave->status);
        $this->assertSame('on_behalf', $leave->source);
        $this->assertEquals($this->admin->id, $leave->applied_by);
        $this->assertEquals(2, (float) $leave->leave_count);
        $this->assertEquals(3, (float) $balanceRow()->value('balance'));

        // Same dates again → overlap.
        $this->postJson($url, $payload)->assertStatus(422);
        // End before start → validation.
        $this->postJson($url, ['end_date' => '2031-03-01'] + $payload)->assertStatus(422);
    }

    public function test_set_weekly_offs(): void
    {
        if (!$this->tabOn('shift')) {
            $this->markTestSkipped('Shifts are not in this company\'s plan.');
        }
        $this->actingAs($this->admin);
        $url = route('employee.profile.weekoffs', $this->enc());

        if (!app(TenantShiftResolver::class)->isCustomShifts($this->tenantId)) {
            $this->postJson($url, ['days' => ['Sunday']])->assertForbidden();

            return;
        }

        $days = fn () => DB::table('user_weekoffs')->where('tenant_id', $this->tenantId)->where('user_id', $this->employee->id)
            ->where('off_type', 'day_based')->where('status', 1)->pluck('day_name')->sort()->values()->all();
        $dateBased = DB::table('user_weekoffs')->where('tenant_id', $this->tenantId)->where('user_id', $this->employee->id)
            ->where('off_type', 'date_based')->count();

        $this->postJson($url, ['days' => ['Funday']])->assertStatus(422);

        $this->postJson($url, ['days' => ['Sunday', 'Wednesday']])->assertOk()->assertJson(['success' => true]);
        $this->assertSame(['Sunday', 'Wednesday'], $days());

        // No shift is left on a new week-off day from tomorrow on.
        $onOffDays = DB::table('user_shifts')->where('tenant_id', $this->tenantId)->where('user_id', $this->employee->id)
            ->whereNotNull('shift_assignment_id')->where('is_additional', 0)
            ->where('date', '>', now()->toDateString())->whereRaw("DAYNAME(date) IN ('Sunday', 'Wednesday')")->count();
        $this->assertSame(0, $onOffDays);

        $this->postJson($url, [])->assertOk();
        $this->assertSame([], $days());

        // One-off week-off dates are never touched.
        $this->assertSame($dateBased, DB::table('user_weekoffs')->where('tenant_id', $this->tenantId)
            ->where('user_id', $this->employee->id)->where('off_type', 'date_based')->count());
    }

    /** The forms post to the modules' existing endpoints — check the payloads they send are accepted. */
    public function test_edit_forms_post_to_the_existing_wizard_steps(): void
    {
        $this->actingAs($this->admin);
        $url = route('employee.update.step', $this->enc());

        // Bank form (step 5).
        $this->post($url, [
            'step' => 5, 'bank_name' => 'P360 Test Bank', 'branch_name' => 'Main Branch', 'account_number' => '1234567890',
            'ifsc' => 'TEST0001234', 'uan_no' => '', 'pf_no' => '', 'esic_no' => '',
        ], ['Accept' => 'application/json'])->assertOk()->assertJson(['success' => true]);
        $this->assertSame('P360 Test Bank', DB::table('user_bank_details')->where('user_id', $this->employee->id)->value('bank_name'));

        // Login form (step 1) — same values back, name changed.
        $contact = preg_match('/^\d{10}$/', (string) $this->employee->contact) ? $this->employee->contact : '9000000001';
        $this->post($url, [
            'step' => 1, 'name' => 'P360 Renamed', 'email' => $this->employee->email, 'contact' => $contact, 'role' => $this->employee->role,
        ], ['Accept' => 'application/json'])->assertOk()->assertJson(['success' => true]);
        $this->assertSame('P360 Renamed', DB::table('users')->where('id', $this->employee->id)->value('name'));

        // A bad value comes back as a 422 with field errors (what the modal shows).
        $this->post($url, ['step' => 1, 'name' => '', 'email' => 'not-an-email', 'contact' => '1', 'role' => 'employee'], ['Accept' => 'application/json'])
            ->assertStatus(422)->assertJsonStructure(['errors' => ['name', 'email', 'contact']]);
    }

    public function test_mark_attendance_form_posts_to_the_team_endpoint(): void
    {
        if (!$this->tabOn('attendance')) {
            $this->markTestSkipped('Attendance is not in this company\'s plan.');
        }
        $this->actingAs($this->admin);
        $date = now()->subDays(3)->toDateString();

        // Exactly what the form sends for "Absent": the clock / leave fields are disabled, so absent from the request.
        $this->post(route('team.mark-attendance'), [
            'user_id' => $this->employee->id, 'date' => $date, 'end_date' => '', 'status' => 'absent', 'remarks' => 'p360 test',
        ], ['Accept' => 'application/json'])->assertOk()->assertJson(['success' => true]);

        $row = DB::table('attendances')->where('tenant_id', $this->tenantId)->where('user_id', $this->employee->id)->where('date', $date)->first();
        $this->assertNotNull($row);
        $this->assertSame('absent', $row->attendance_status);

        // Present without times → field errors for the modal.
        $this->post(route('team.mark-attendance'), [
            'user_id' => $this->employee->id, 'date' => $date, 'status' => 'present',
        ], ['Accept' => 'application/json'])->assertStatus(422)->assertJsonStructure(['errors' => ['clock_in']]);

        // Clock-in only (no clock-out) is for the current day: a past date still needs both times...
        $this->post(route('team.mark-attendance'), [
            'user_id' => $this->employee->id, 'date' => $date, 'status' => 'present', 'clock_in' => '09:30',
        ], ['Accept' => 'application/json'])->assertStatus(422)->assertJsonStructure(['errors' => ['clock_out']]);

        // Clock-out only needs an open clock-in on the day (this day was marked absent above).
        $this->post(route('team.mark-attendance'), [
            'user_id' => $this->employee->id, 'date' => $date, 'status' => 'present', 'clock_out' => '18:00', 'clock_out_only' => 1,
        ], ['Accept' => 'application/json'])->assertStatus(422)->assertJsonStructure(['errors' => ['clock_out']]);

        // ...and so does a half day.
        $this->post(route('team.mark-attendance'), [
            'user_id' => $this->employee->id, 'date' => $date, 'status' => 'half_day', 'clock_in' => '09:30',
        ], ['Accept' => 'application/json'])->assertStatus(422)->assertJsonStructure(['errors' => ['clock_out']]);
    }

    public function test_assign_shift_form_posts_to_the_shift_endpoint(): void
    {
        if (!$this->tabOn('shift') || !app(TenantShiftResolver::class)->isCustomShifts($this->tenantId)) {
            $this->markTestSkipped('Needs a custom-shift company.');
        }
        $shift = DB::table('shifts')->where('tenant_id', $this->tenantId)->where('status', 1)->value('id');
        if (!$shift) {
            $this->markTestSkipped('Needs an active shift.');
        }
        $this->actingAs($this->admin);
        $date = '2031-03-04'; // a Tuesday far in the future

        DB::table('user_weekoffs')->where('tenant_id', $this->tenantId)->where('user_id', $this->employee->id)->delete();

        // "Only for selected dates", replacing whatever is there — sent as an AJAX form post.
        $this->post(route('shift.assign'), [
            'assign_type' => 'user', 'user_ids' => [$this->employee->id], 'shift_id' => $shift, 'type' => 'flexible',
            'start_date' => $date, 'end_date' => $date, 'override_existing' => 1,
        ], ['Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest'])->assertOk()->assertJson(['status' => true]);

        $this->assertTrue(DB::table('user_shifts')->where('tenant_id', $this->tenantId)->where('user_id', $this->employee->id)
            ->where('date', $date)->where('shift_id', $shift)->exists());
        // Only this employee got it.
        $this->assertSame(1, DB::table('shift_assignments')->where('tenant_id', $this->tenantId)->where('type', 'flexible')
            ->where('start_date', $date)->where('end_date', $date)->count());
    }

    public function test_leave_credit_and_debit_forms_post_to_the_existing_endpoints(): void
    {
        if (!$this->tabOn('leave')) {
            $this->markTestSkipped('Leave is not in this company\'s plan.');
        }
        $this->actingAs($this->admin);

        $leaveType = DB::table('leave_types')->where('tenant_id', $this->tenantId)->where('status', 1)->value('id');
        if (!$leaveType) {
            $this->markTestSkipped('Needs a leave type.');
        }
        $balance = fn () => (float) DB::table('leave_balances')->where('tenant_id', $this->tenantId)
            ->where('user_id', $this->employee->id)->where('leave_type_id', $leaveType)->value('balance');
        $before = $balance();

        $this->postJson(route('leave-credit.manual.store'), [
            'user_id' => $this->employee->id, 'leave_type_id' => $leaveType, 'credit_value' => 2, 'remarks' => 'p360 test',
        ])->assertOk()->assertJson(['success' => true]);
        $this->assertEquals($before + 2, $balance());

        $this->postJson(route('leave-credit.manual.debit'), [
            'user_id' => $this->employee->id, 'leave_type_id' => $leaveType, 'debit_value' => 1.5, 'remarks' => 'p360 test',
        ])->assertOk()->assertJson(['success' => true]);
        $this->assertEquals($before + 0.5, $balance());
    }
}
