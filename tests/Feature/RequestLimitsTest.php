<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\EmployeePolicyService;
use App\Services\RequestLimitService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Employee 360 Phase 4: WFH / regularization limits (company + per employee)
 * and the per-employee monthly expense limit. No limit = accepted as before.
 * Runs on the dev DB — every test is wrapped in a transaction that is rolled back.
 */
class RequestLimitsTest extends TestCase
{
    private User $admin;
    private User $employee;
    private int $tenantId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::withoutGlobalScopes()->where('role', 'admin')->where('status', 1)->whereNotNull('tenant_id')->orderBy('id')->firstOrFail();
        $this->tenantId = (int) $this->admin->tenant_id;
        $this->employee = User::withoutGlobalScopes()->where('tenant_id', $this->tenantId)->where('role', 'employee')
            ->where('status', 1)->orderBy('id')->firstOrFail();

        DB::beginTransaction();
        DB::table('employee_policy_overrides')->where('tenant_id', $this->tenantId)->where('user_id', $this->employee->id)->delete();
        DB::table('tenants')->where('id', $this->tenantId)->update(array_fill_keys(RequestLimitService::REQUEST_KEYS, null));
        app(EmployeePolicyService::class)->forget();
    }

    protected function tearDown(): void
    {
        while (DB::transactionLevel() > 0) {
            DB::rollBack();
        }

        parent::tearDown();
    }

    /** A mobile-app login for the employee (JWT + the single-login device token). */
    private function apiHeaders(): array
    {
        $deviceToken = \Illuminate\Support\Str::random(40);
        DB::table('users')->where('id', $this->employee->id)->update(['last_login_token' => $deviceToken]);
        $this->employee->last_login_token = $deviceToken;

        return ['Authorization' => 'Bearer ' . auth('api')->login($this->employee), 'Device-Token' => $deviceToken, 'Accept' => 'application/json'];
    }

    private function limits(): RequestLimitService
    {
        return app(RequestLimitService::class);
    }

    private function override(string $section, array $values): void
    {
        app(EmployeePolicyService::class)->save($this->tenantId, $this->employee->id, $section, $values, $this->admin->id);
    }

    private function wfhTypeId(): ?int
    {
        return DB::table('request_types')->where('tenant_id', $this->tenantId)->get(['id', 'type_name'])
            ->first(fn ($t) => in_array(strtolower(preg_replace('/[^a-z]/i', '', $t->type_name)), ['wfh', 'workfromhome'], true))?->id;
    }

    public function test_company_setting_is_saved_by_admin_and_hr_only(): void
    {
        $payload = ['wfh_max_days_per_month' => 4, 'wfh_min_notice_days' => 2, 'regularization_max_per_month' => 3, 'regularization_max_days_back' => 0];

        $this->actingAs($this->employee)->put(route('request-limits-settings.update'), $payload)->assertForbidden();

        $this->actingAs($this->admin)->put(route('request-limits-settings.update'), $payload)->assertRedirect();
        $this->assertSame(
            ['wfh_max_days_per_month' => 4, 'wfh_min_notice_days' => 2, 'regularization_max_per_month' => 3, 'regularization_max_days_back' => 0],
            $this->limits()->company($this->tenantId)
        );
        $this->assertNull(DB::table('tenants')->where('id', $this->tenantId)->value('regularization_max_days_back')); // 0 stored as "no limit"

        $this->actingAs($this->admin)->put(route('request-limits-settings.update'), ['wfh_max_days_per_month' => 40])->assertSessionHasErrors('wfh_max_days_per_month');
        $this->actingAs($this->admin)->get(route('workforce-settings.index'))->assertOk()->assertSee('Request limits')->assertSee('requestLimitsModal', false);
    }

    public function test_regularization_limits(): void
    {
        $svc = $this->limits();
        $old = now()->subDays(10)->toDateString();
        $month = '2031-03-10';

        $this->assertNull($svc->regularizationRefusal($this->tenantId, $this->employee->id, $old)); // no limit

        DB::table('tenants')->where('id', $this->tenantId)->update(['regularization_max_days_back' => 7, 'regularization_max_per_month' => 2]);
        $this->assertStringContainsString('last 7 day', (string) $svc->regularizationRefusal($this->tenantId, $this->employee->id, $old));
        $this->assertNull($svc->regularizationRefusal($this->tenantId, $this->employee->id, now()->subDays(3)->toDateString()));

        // Two pending/approved requests in a month fill it; a rejected one does not count.
        $row = fn (string $date, string $status) => DB::table('attendance_regularizations')->insertGetId([
            'tenant_id' => $this->tenantId, 'user_id' => $this->employee->id, 'date' => $date, 'request_type' => 'full_day',
            'reason' => 'p360 limits test', 'status' => $status, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('tenants')->where('id', $this->tenantId)->update(['regularization_max_days_back' => null]);
        $row('2031-03-01', 'approved');
        $row('2031-03-02', 'rejected');
        $this->assertNull($svc->regularizationRefusal($this->tenantId, $this->employee->id, $month));
        $second = $row('2031-03-03', 'pending');
        $this->assertStringContainsString('At most 2', (string) $svc->regularizationRefusal($this->tenantId, $this->employee->id, $month));
        $this->assertNull($svc->regularizationRefusal($this->tenantId, $this->employee->id, $month, $second)); // editing one of them
        $this->assertNull($svc->regularizationRefusal($this->tenantId, $this->employee->id, '2031-04-10'));    // another month

        // The employee's own value wins over the company's.
        $this->override('requests', ['regularization_max_per_month' => 5]);
        $this->assertNull($svc->regularizationRefusal($this->tenantId, $this->employee->id, $month));
        $this->override('requests', ['regularization_max_per_month' => 0]);   // 0 = no limit for them
        $this->assertNull($svc->regularizationRefusal($this->tenantId, $this->employee->id, $month));
    }

    public function test_regularization_endpoints_return_the_limit_message(): void
    {
        DB::table('tenants')->where('id', $this->tenantId)->update(['regularization_max_days_back' => 2]);
        $old = now()->subDays(5)->toDateString();
        $payload = ['request_type' => 'full_day', 'date' => $old, 'reason' => 'Forgot to punch, p360 limits test'];

        if (app(\App\Services\FeatureService::class)->enabled($this->tenantId, 'regularization')) {
            $this->actingAs($this->employee)->postJson(route('attendance-regularization.store'), $payload)
                ->assertStatus(422)->assertJson(['success' => false, 'message' => 'Regularization can only be requested for the last 2 day(s).']);
            $this->postJson('/api/user/attendance/regularization', $payload, $this->apiHeaders())
                ->assertOk()->assertJson(['success' => false, 'message' => 'Regularization can only be requested for the last 2 day(s).']);
        }
        $this->assertFalse(DB::table('attendance_regularizations')->where('reason', 'like', '%p360 limits test%')->exists());
    }

    public function test_wfh_limits(): void
    {
        $wfh = $this->wfhTypeId();
        if (!$wfh) {
            $this->markTestSkipped('Needs a WFH request type.');
        }
        $travel = DB::table('request_types')->where('tenant_id', $this->tenantId)->where('id', '!=', $wfh)->value('id');
        $svc = $this->limits();

        $this->assertNull($svc->wfhRefusal($this->tenantId, $this->employee->id, $wfh, now()->toDateString(), now()->toDateString()));

        // Notice.
        DB::table('tenants')->where('id', $this->tenantId)->update(['wfh_min_notice_days' => 3]);
        $this->assertStringContainsString('3 day(s) in advance', (string) $svc->wfhRefusal($this->tenantId, $this->employee->id, $wfh, now()->addDay()->toDateString(), now()->addDay()->toDateString()));
        $this->assertNull($svc->wfhRefusal($this->tenantId, $this->employee->id, $wfh, now()->addDays(3)->toDateString(), now()->addDays(3)->toDateString()));
        if ($travel) {
            $this->assertNull($svc->wfhRefusal($this->tenantId, $this->employee->id, (int) $travel, now()->toDateString(), now()->toDateString()));
        }

        // Days per month, counted per month across a month boundary.
        DB::table('tenants')->where('id', $this->tenantId)->update(['wfh_min_notice_days' => null, 'wfh_max_days_per_month' => 3]);
        DB::table('requests')->insert([
            'tenant_id' => $this->tenantId, 'request_type_id' => $wfh, 'user_id' => $this->employee->id,
            'start_date' => '2031-03-30', 'end_date' => '2031-03-31', 'reason' => 'p360 limits test', 'status' => 'APPROVED',
            'applied_date' => now()->toDateString(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->assertNull($svc->wfhRefusal($this->tenantId, $this->employee->id, $wfh, '2031-03-29', '2031-03-29'));            // 3 in March
        $this->assertStringContainsString('4 day(s) in March 2031', (string) $svc->wfhRefusal($this->tenantId, $this->employee->id, $wfh, '2031-03-28', '2031-03-29'));
        $this->assertNull($svc->wfhRefusal($this->tenantId, $this->employee->id, $wfh, '2031-04-01', '2031-04-03'));             // 3 in April
        $this->assertStringContainsString('April 2031', (string) $svc->wfhRefusal($this->tenantId, $this->employee->id, $wfh, '2031-04-01', '2031-04-04'));

        // The employee's own limit wins.
        $this->override('requests', ['wfh_max_days_per_month' => 10]);
        $this->assertNull($svc->wfhRefusal($this->tenantId, $this->employee->id, $wfh, '2031-03-20', '2031-03-27'));
    }

    public function test_wfh_endpoints_return_the_limit_message(): void
    {
        $wfh = $this->wfhTypeId();
        if (!$wfh || !app(\App\Services\FeatureService::class)->enabled($this->tenantId, 'wfh_travel')) {
            $this->markTestSkipped('Needs WFH requests in the plan.');
        }
        $this->override('requests', ['wfh_min_notice_days' => 5]);
        $payload = ['request_type_id' => $wfh, 'start_date' => now()->toDateString(), 'end_date' => now()->toDateString(), 'reason' => 'p360 limits test'];
        $message = 'Work from home must be requested at least 5 day(s) in advance.';

        $this->actingAs($this->employee)->postJson(route('requests.store'), $payload)->assertStatus(422)->assertJson(['success' => false, 'message' => $message]);
        $this->postJson('/api/request/store', $payload, $this->apiHeaders())->assertOk()->assertJson(['success' => false, 'message' => $message]);
        $this->assertFalse(DB::table('requests')->where('reason', 'p360 limits test')->exists());
    }

    public function test_expense_monthly_limit(): void
    {
        $svc = $this->limits();
        $date = '2031-03-10';

        $this->assertNull($svc->expenseRefusal($this->tenantId, $this->employee->id, $date, 999999, 'reimbursement')); // no limit

        $this->override('expense', ['monthly_limit' => 1000]);
        $category = DB::table('expense_types')->where('tenant_id', $this->tenantId)->where('status', 1)->orderBy('id')->value('id');
        if (!$category) {
            $this->markTestSkipped('Needs an expense category.');
        }
        $row = fn (float $amount, string $type, string $status) => DB::table('expenses')->insertGetId([
            'expense_type' => $category,
            'tenant_id' => $this->tenantId, 'user_id' => $this->employee->id, 'expense_number' => 'P360-' . uniqid(),
            'requirement_type' => $type, 'amount' => $amount, 'paid_amount' => 0, 'date' => $date, 'status' => $status,
            'description' => 'p360 limits test', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $mine = $row(600, 'reimbursement', 'pending');
        $row(5000, 'advance', 'approved');        // advances don't count
        $row(5000, 'reimbursement', 'cancelled'); // cancelled doesn't count

        $this->assertNull($svc->expenseRefusal($this->tenantId, $this->employee->id, $date, 400, 'settlement'));
        $this->assertStringContainsString('at most ₹400.00 more', (string) $svc->expenseRefusal($this->tenantId, $this->employee->id, $date, 401, 'reimbursement'));
        $this->assertNull($svc->expenseRefusal($this->tenantId, $this->employee->id, $date, 5000, 'advance'));              // advances not limited
        $this->assertNull($svc->expenseRefusal($this->tenantId, $this->employee->id, $date, 1000, 'reimbursement', $mine)); // editing that claim
        $this->assertNull($svc->expenseRefusal($this->tenantId, $this->employee->id, '2031-04-10', 1000, 'reimbursement')); // another month

        // The shared expense service (web + mobile submit) refuses it.
        $type = DB::table('expense_types')->where('tenant_id', $this->tenantId)->where('status', 1)->whereNull('receipt_required_above')->value('id');
        if ($type) {
            try {
                app(\App\Services\Expense\ExpenseService::class)->submit($this->employee, [
                    'expense_type' => $type, 'amount' => 2000, 'date' => $date, 'requirement_type' => 'reimbursement', 'description' => 'p360 limits test',
                ]);
                $this->fail('Expected the monthly limit to refuse the claim.');
            } catch (\App\Exceptions\ExpenseException $e) {
                $this->assertSame(422, $e->httpStatus());
                $this->assertStringContainsString('limited to ₹1,000.00 a month', $e->getMessage());
            }
        }
        $this->assertSame(3, DB::table('expenses')->where('description', 'p360 limits test')->count());
    }

    public function test_policies_tab_shows_and_saves_the_limit_sections(): void
    {
        $this->actingAs($this->admin);
        $id = encrypt($this->employee->id);
        DB::table('tenants')->where('id', $this->tenantId)->update(['regularization_max_per_month' => 4]);

        $tab = $this->get(route('employee.profile.tab', ['id' => $id, 'tab' => 'policies']))->assertOk();
        $features = app(\App\Services\FeatureService::class);
        if ($features->enabled($this->tenantId, 'wfh_travel') || $features->enabled($this->tenantId, 'regularization')) {
            $tab->assertSee('WFH &amp; regularization limits', false);
            $this->get(route('employee.profile.form', ['id' => $id, 'form' => 'policy-requests']))->assertOk()
                ->assertSee('custom[regularization_max_per_month]', false);
            $this->postJson(route('employee.profile.policy', ['id' => $id, 'section' => 'requests']), [
                'custom' => ['regularization_max_per_month' => 1], 'value' => ['regularization_max_per_month' => 9],
            ])->assertOk();
            $this->assertSame(9, $this->limits()->forEmployee($this->tenantId, $this->employee->id)['regularization_max_per_month']);
        }
        if ($features->enabled($this->tenantId, 'expense_management')) {
            $tab->assertSee('Expense limit');
            $this->postJson(route('employee.profile.policy', ['id' => $id, 'section' => 'expense']), [
                'custom' => ['monthly_limit' => 1], 'value' => ['monthly_limit' => 2500],
            ])->assertOk();
            $this->assertEquals(2500, app(EmployeePolicyService::class)->section($this->tenantId, $this->employee->id, 'expense')['monthly_limit']);
        }
    }
}
