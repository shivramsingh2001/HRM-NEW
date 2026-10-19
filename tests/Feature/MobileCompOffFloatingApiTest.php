<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureFeatureEnabled;
use App\Models\Shift;
use App\Models\User;
use App\Models\UserShift;
use App\Services\Attendance\PolicyResolver;
use App\Services\EmployeePolicyService;
use App\Services\Shift\ShiftAssignmentService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Mobile API (JWT) for comp-off and floating week-offs: the employee screens
 * (/api/comp-off, /api/floating-weekoffs) and the approver screens
 * (/api/manager/…), with the legacy envelope { success, message, data } and
 * 422 { success: false, message, errors } for a refusal / bad input.
 */
class MobileCompOffFloatingApiTest extends TestCase
{
    private User $admin;

    private User $emp;

    private int $tenantId;

    private string $tag;

    private bool $hadCompOffType;

    private int $originalCustom;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([EnsureFeatureEnabled::class]);
        $this->tag = 'PHPUnit MCF '.uniqid();
        $this->admin = User::withoutGlobalScopes()->where('role', 'admin')->where('status', 1)->whereNotNull('tenant_id')->orderBy('id')->firstOrFail();
        $this->tenantId = (int) $this->admin->tenant_id;
        $this->hadCompOffType = DB::table('leave_types')->where('tenant_id', $this->tenantId)->where('code', 'comp_off')->exists();
        $this->originalCustom = (int) DB::table('tenants')->where('id', $this->tenantId)->value('custom_shifts_enabled');
        DB::table('tenants')->where('id', $this->tenantId)->update(['custom_shifts_enabled' => 1]);
        $this->emp = User::withoutGlobalScopes()->forceCreate([
            'name' => $this->tag.' Emp', 'email' => 'mcf.'.uniqid().'@phpunit.test', 'employee_id' => 'MC'.random_int(100000, 999999),
            'password' => bcrypt('secret'), 'role' => 'employee', 'status' => 1, 'tenant_id' => $this->tenantId,
        ]);
        DB::table('user_weekoffs')->insert(['tenant_id' => $this->tenantId, 'user_id' => $this->emp->id, 'off_type' => 'day_based', 'day_name' => 'Saturday',
            'start_date' => null, 'end_date' => null, 'status' => 1, 'created_by' => $this->admin->id, 'created_at' => now(), 'updated_at' => now()]);
        app(EmployeePolicyService::class)->save($this->tenantId, $this->emp->id, 'attendance',
            ['comp_off_enabled' => 1, 'floating_weekoffs_enabled' => 1, 'floating_weekoffs_per_month' => 4], $this->admin->id);
        app(EmployeePolicyService::class)->forget();
        app(PolicyResolver::class)->forget();
    }

    protected function tearDown(): void
    {
        $uid = $this->emp->id;
        foreach (['comp_off_credits', 'floating_weekoff_days', 'user_weekoffs', 'attendances', 'leave_balances', 'leave_transactions',
            'employee_policy_overrides', 'shift_change_logs', 'shift_assignments', 'attendance_summaries'] as $t) {
            DB::table($t)->where('user_id', $uid)->delete();
        }
        DB::table('audit_logs')->whereIn('entity_type', ['comp_off_credits', 'floating_weekoff_days'])->where('created_at', '>=', now()->subHour())
            ->whereIn('actor_id', [$uid, $this->admin->id])->delete();
        UserShift::withoutGlobalScopes()->where('user_id', $uid)->delete();
        Shift::withoutGlobalScopes()->where('name', 'like', $this->tag.'%')->delete();
        User::withoutGlobalScopes()->where('id', $uid)->forceDelete();
        if (! $this->hadCompOffType) {
            DB::table('leave_types')->where('tenant_id', $this->tenantId)->where('code', 'comp_off')->delete();
        }
        DB::table('tenants')->where('id', $this->tenantId)->update(['custom_shifts_enabled' => $this->originalCustom]);
        app(EmployeePolicyService::class)->forget();
        app(PolicyResolver::class)->forget();
        parent::tearDown();
    }

    /** JWT + Device-Token headers, as the app sends them. */
    private function as(User $user): array
    {
        $device = Str::random(40);
        DB::table('users')->where('id', $user->id)->update(['last_login_token' => $device]);
        $user->last_login_token = $device;

        return ['Authorization' => 'Bearer '.auth('api')->login($user), 'Device-Token' => $device, 'Accept' => 'application/json'];
    }

    private function freeSaturday(): string
    {
        $d = Carbon::today()->subWeeks(2)->previous(Carbon::SATURDAY);
        while (DB::table('holidays')->where('tenant_id', $this->tenantId)->where('status', 1)->where('start_date', '<=', $d->toDateString())
            ->whereRaw("COALESCE(NULLIF(end_date, ''), start_date) >= ?", [$d->toDateString()])->exists()) {
            $d->subWeek();
        }

        return $d->toDateString();
    }

    public function test_comp_off_screens_request_and_decision(): void
    {
        $sat = $this->freeSaturday();
        DB::table('attendances')->insert(['tenant_id' => $this->tenantId, 'user_id' => $this->emp->id, 'date' => $sat, 'clock_in' => "$sat 10:00:00",
            'clock_out' => "$sat 19:00:00", 'worked_hours' => 9, 'attendance_status' => 'present', 'attendance_type' => 'app', 'status' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $emp = $this->as($this->emp);

        $data = $this->getJson('/api/comp-off', $emp)->assertOk()->assertJson(['success' => true])->json('data');
        $this->assertTrue($data['enabled']);
        $this->assertSame([['date' => $sat, 'day_type' => 'week_off', 'hours' => 9, 'days' => 1]], $data['claimable']);
        $this->assertEquals(0, $data['balance']);

        $this->postJson('/api/comp-off', [], $emp)->assertStatus(422)->assertJson(['success' => false])->assertJsonStructure(['message', 'errors']);
        $id = $this->postJson('/api/comp-off', ['worked_date' => $sat, 'reason' => 'Audit'], $emp)->assertOk()->json('data.id');
        $this->postJson('/api/comp-off', ['worked_date' => $sat], $emp)->assertStatus(422)->assertJsonPath('message', 'Already claimed as comp-off');

        // The employee is no approver; the admin sees it and approves it.
        $this->getJson('/api/manager/comp-off', $emp)->assertStatus(403);
        $admin = $this->as($this->admin);
        $list = $this->getJson('/api/manager/comp-off', $admin)->assertOk()->json('data.requests');
        $this->assertContains($id, array_column($list, 'id'));
        $row = $this->postJson("/api/manager/comp-off/{$id}/decision", ['action' => 'approved'], $admin)->assertOk()->json('data');
        $this->assertSame(['approved', $this->emp->id], [$row['status'], $row['employee']['id']]);

        $after = $this->getJson('/api/comp-off', $this->as($this->emp))->json('data');
        $this->assertEquals(1, $after['balance']);
        $this->assertSame('approved', $after['requests'][0]['status']);
        $this->assertSame(Carbon::parse($sat)->addDays(60)->toDateString(), $after['requests'][0]['expires_on']);
    }

    public function test_floating_week_off_screens_plan_check_decide_and_cancel(): void
    {
        $shift = Shift::withoutGlobalScopes()->create(['tenant_id' => $this->tenantId, 'name' => $this->tag.' Day', 'start_time' => '10:00', 'end_time' => '19:00',
            'is_overnight' => false, 'total_hours' => 9, 'grace_minutes' => 15, 'break_time' => 0, 'status' => 1, 'created_by' => $this->admin->id]);
        app(ShiftAssignmentService::class)->assignPermanent($this->tenantId, $this->emp->id, $shift->id, Carbon::today(), $this->admin->id, null, null, null);
        $day = Carbon::today()->addMonthNoOverflow()->startOfMonth()->next(Carbon::TUESDAY);
        while (DB::table('holidays')->where('tenant_id', $this->tenantId)->where('status', 1)->where('start_date', '<=', $day->toDateString())
            ->whereRaw("COALESCE(NULLIF(end_date, ''), start_date) >= ?", [$day->toDateString()])->exists()) {
            $day->addWeek();
        }
        $day = $day->toDateString();
        $emp = $this->as($this->emp);

        $data = $this->getJson('/api/floating-weekoffs', $emp)->assertOk()->json('data');
        $this->assertTrue($data['enabled']);
        $this->assertSame(['month', 'label', 'enabled', 'quota', 'used', 'pending', 'per_week', 'left'], array_keys($data['months'][0]));

        $this->getJson("/api/floating-weekoffs/check?date={$day}", $emp)->assertOk()->assertJsonPath('data.allowed', true);
        $this->getJson('/api/floating-weekoffs/check?date=nope', $emp)->assertStatus(422);
        $id = $this->postJson('/api/floating-weekoffs', ['date' => $day, 'reason' => 'Family'], $emp)->assertOk()->json('data.id');
        $this->getJson("/api/floating-weekoffs/check?date={$day}", $emp)->assertJsonPath('data.allowed', false)
            ->assertJsonPath('data.reason', 'Already a floating week-off (or asked for)');

        $admin = $this->as($this->admin);
        $this->assertContains($id, array_column($this->getJson('/api/manager/floating-weekoffs', $admin)->assertOk()->json('data.requests'), 'id'));
        $this->postJson("/api/manager/floating-weekoffs/{$id}/decision", ['action' => 'maybe'], $admin)->assertStatus(422);
        $this->postJson("/api/manager/floating-weekoffs/{$id}/decision", ['action' => 'approved'], $admin)->assertOk()->assertJsonPath('data.status', 'approved');
        $this->assertFalse(UserShift::withoutGlobalScopes()->where('user_id', $this->emp->id)->where('date', $day)->exists());

        $emp = $this->as($this->emp);
        $mine = $this->getJson('/api/floating-weekoffs', $emp)->json('data.days');
        $this->assertSame(['approved', true], [$mine[0]['status'], $mine[0]['can_cancel']]);
        $this->postJson("/api/floating-weekoffs/{$id}/cancel", [], $emp)->assertOk();
        $this->assertTrue(UserShift::withoutGlobalScopes()->where('user_id', $this->emp->id)->where('date', $day)->exists());
    }
}
