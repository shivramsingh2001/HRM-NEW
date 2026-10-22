<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\LeaveService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Leave day count by session (Start Session 2 = from the second half of the
 * first day, End Session 1 = up to the first half of the last day) and the
 * "same day + session already has a pending/approved leave" check. Regression:
 * LV-000678 / LV-000685 — the same employee applied twice for 30 Oct Session 1 →
 * Session 2, and each was counted as 0.5 day instead of 1. Runs on the dev DB in
 * a rolled-back transaction.
 */
class LeaveSessionAndOverlapTest extends TestCase
{
    protected function tearDown(): void
    {
        while (DB::transactionLevel() > 0) {
            DB::rollBack();
        }

        parent::tearDown();
    }

    public function test_session_day_count(): void
    {
        $svc = app(LeaveService::class);
        $days = fn (string $from, string $fs, string $to, string $ts) => $svc->computeLeaveDays(Carbon::parse($from), Carbon::parse($to), $fs, $ts);

        // 2031-03-03 is a Monday; no tenant → no holidays.
        $this->assertEquals(1, $days('2031-03-03', 'fullday', '2031-03-03', 'fullday'));
        $this->assertEquals(1, $days('2031-03-03', 'session1', '2031-03-03', 'session2'));
        $this->assertEquals(0.5, $days('2031-03-03', 'session1', '2031-03-03', 'session1'));
        $this->assertEquals(0.5, $days('2031-03-03', 'session2', '2031-03-03', 'session2'));
        $this->assertEquals(1.5, $days('2031-03-03', 'session1', '2031-03-04', 'session1'));
        $this->assertEquals(1.5, $days('2031-03-03', 'session2', '2031-03-04', 'session2'));
        $this->assertEquals(2, $days('2031-03-03', 'session2', '2031-03-05', 'session1'));
        $this->assertEquals(3, $days('2031-03-03', 'session1', '2031-03-05', 'session2'));
        $this->assertEquals(3, $days('2031-03-03', 'fullday', '2031-03-05', 'fullday'));
        // Friday second half → Monday first half: the weekend is skipped.
        $this->assertEquals(1, $days('2031-03-07', 'session2', '2031-03-10', 'session1'));
    }

    public function test_same_day_and_session_is_refused_until_rejected(): void
    {
        $employee = User::withoutGlobalScopes()->where('role', 'employee')->where('status', 1)->whereNotNull('tenant_id')->orderBy('id')->firstOrFail();
        $type = DB::table('leave_types')->where('tenant_id', $employee->tenant_id)->orderBy('id')->first();
        if (! $type) {
            $this->markTestSkipped('Needs a leave type.');
        }

        DB::beginTransaction();
        $svc = app(LeaveService::class);
        $day = Carbon::parse('2031-03-03');
        $leaveId = DB::table('leaves')->insertGetId([
            'tenant_id' => $employee->tenant_id, 'user_id' => $employee->id, 'leave_type' => $type->id,
            'start_date' => '2031-03-03', 'start_session' => 'session1', 'end_date' => '2031-03-03', 'end_session' => 'session1',
            'leave_count' => 0.5, 'total_days' => 0.5, 'reason' => 'overlap test', 'status' => 'pending',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->assertNotNull($svc->applyRefusal($employee->id, $day, $day, 'session1', 'session1'), 'same half again');
        $this->assertNotNull($svc->applyRefusal($employee->id, $day, $day, 'session1', 'session2'), 'full day over a taken first half');
        $this->assertNotNull($svc->applyRefusal($employee->id, $day, $day, 'fullday', 'fullday'));
        $this->assertNotNull($svc->applyRefusal($employee->id, Carbon::parse('2031-03-02'), $day, 'fullday', 'session1'), 'range ending on the taken half');
        $this->assertNull($svc->applyRefusal($employee->id, $day, $day, 'session2', 'session2'), 'other half is free');
        $this->assertNull($svc->applyRefusal($employee->id, Carbon::parse('2031-03-02'), $day, 'fullday', 'session1', $leaveId), 'editing the same leave');
        $this->assertNull($svc->applyRefusal($employee->id, $day, $day, 'session1', 'session1', null, ['approved']), 'only approved leaves block approval');
        $this->assertNotNull($svc->applyRefusal($employee->id, $day, $day, 'session2', 'session1'), 'one day cannot run second half → first half');

        DB::table('leaves')->where('id', $leaveId)->update(['status' => 'cancelled']);
        $this->assertNull($svc->applyRefusal($employee->id, $day, $day, 'session1', 'session1'), 'rejected leave no longer blocks');
    }

    public function test_mobile_second_request_for_same_day_is_refused(): void
    {
        $admin = User::withoutGlobalScopes()->where('role', 'admin')->where('status', 1)->whereNotNull('tenant_id')->orderBy('id')->firstOrFail();
        $tenantId = (int) $admin->tenant_id;
        $employee = User::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('role', 'employee')->where('status', 1)->orderBy('id')->first();
        $type = DB::table('leave_types')->where('tenant_id', $tenantId)->where('status', 1)->orderBy('id')->first();
        if (! $employee || ! $type || ! app(\App\Services\FeatureService::class)->enabled($tenantId, 'leave_management')) {
            $this->markTestSkipped('Needs an employee, a leave type and the leave module.');
        }

        DB::beginTransaction();
        DB::table('employee_policy_overrides')->where('user_id', $employee->id)->delete();
        DB::table('leave_balances')->updateOrInsert(
            ['tenant_id' => $tenantId, 'user_id' => $employee->id, 'leave_type_id' => $type->id],
            ['balance' => 10, 'updated_at' => now(), 'created_at' => now()]
        );
        $device = Str::random(40);
        DB::table('users')->where('id', $employee->id)->update(['last_login_token' => $device]);
        $employee->last_login_token = $device;
        $headers = ['Authorization' => 'Bearer ' . auth('api')->login($employee), 'Device-Token' => $device, 'Accept' => 'application/json'];
        $body = [
            'leave_type' => $type->id,
            'start_date' => '2031-03-03', 'start_session' => 'session1',   // Monday
            'end_date' => '2031-03-03', 'end_session' => 'session2',
            'reason' => 'overlap mobile test',
        ];

        $this->postJson('/api/apply-leave', $body, $headers)->assertJson(['success' => true]);
        $this->postJson('/api/apply-leave', $body, $headers)->assertJson(['success' => false]);

        $rows = DB::table('leaves')->where('user_id', $employee->id)->where('reason', 'overlap mobile test')->get();
        $this->assertCount(1, $rows);
        $this->assertEquals(1, (float) $rows[0]->total_days);
    }
}
