<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * POST /api/apply-leave (mobile). Regression: the endpoint passed Carbon\Carbon dates to
 * LeaveService::computeLeaveDays(), which only accepted Illuminate\Support\Carbon, so every
 * mobile leave request failed with a 500 TypeError. Runs on the dev DB in a rolled-back transaction.
 */
class MobileApplyLeaveTest extends TestCase
{
    protected function tearDown(): void
    {
        while (DB::transactionLevel() > 0) {
            DB::rollBack();
        }

        parent::tearDown();
    }

    public function test_mobile_leave_request_is_saved(): void
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

        $res = $this->postJson('/api/apply-leave', [
            'leave_type' => $type->id,
            'start_date' => '2031-03-03', 'start_session' => 'fullday',   // Monday
            'end_date' => '2031-03-04', 'end_session' => 'fullday',       // Tuesday
            'reason' => 'p360 mobile leave test',
        ], $headers);

        $this->assertNotSame(500, $res->getStatusCode(), (string) $res->getContent());
        $res->assertJson(['success' => true]);

        $leave = DB::table('leaves')->where('user_id', $employee->id)->where('reason', 'p360 mobile leave test')->first();
        $this->assertNotNull($leave);
        $this->assertEquals(2, (float) $leave->total_days);
        $this->assertSame('pending', $leave->status);
    }
}
