<?php

namespace Tests\Feature;

use App\Console\Commands\CheckMissedCheckIns;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Missed check-in: who is alerted, and who is not expected to clock in.
 * Shared dev DB — throwaway users and 2031-dated rows, all removed in tearDown.
 */
class MissedCheckInRulesTest extends TestCase
{
    private int $tenantId;
    private array $users = [];
    private CheckMissedCheckIns $cmd;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenantId = (int) DB::table('users')->whereNotNull('tenant_id')->value('tenant_id');
        $this->cmd = app(CheckMissedCheckIns::class);
    }

    protected function tearDown(): void
    {
        DB::table('leaves')->whereIn('user_id', $this->users)->delete();
        DB::table('user_weekoffs')->whereIn('user_id', $this->users)->delete();
        DB::table('user_reporting_heads')->whereIn('user_id', $this->users)->delete();
        DB::table('holidays')->where('tenant_id', $this->tenantId)->where('name', 'PHPUnit Holiday')->delete();
        User::withoutGlobalScopes()->whereIn('id', $this->users)->forceDelete();
        parent::tearDown();
    }

    private function user(string $role = 'employee'): User
    {
        $u = User::withoutGlobalScopes()->forceCreate([
            'name' => 'MC ' . $role, 'email' => 'mc.' . uniqid() . '@phpunit.test', 'password' => bcrypt('x'),
            'role' => $role, 'status' => 1, 'tenant_id' => $this->tenantId,
        ]);
        $this->users[] = $u->id;

        return $u;
    }

    private function invokePrivate(string $method, ...$args)
    {
        $m = new \ReflectionMethod($this->cmd, $method);
        $m->setAccessible(true);

        return $m->invoke($this->cmd, ...$args);
    }

    private function row(User $u): object
    {
        return (object) ['user_id' => $u->id, 'tenant_id' => $this->tenantId];
    }

    private function leave(User $u, string $start, string $end, string $session = 'fullday'): void
    {
        DB::table('leaves')->insert([
            'tenant_id' => $this->tenantId, 'leave_id' => 'LV-MC', 'user_id' => $u->id, 'leave_type' => 1,
            'start_date' => $start, 'start_session' => $session, 'end_date' => $end, 'status' => 'approved',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_recipients_are_employee_own_manager_hr_and_admin_not_every_manager(): void
    {
        $emp = $this->user();
        $ownManager = $this->user('manager');
        $otherManager = $this->user('manager');
        $hr = $this->user('hr');
        $admin = $this->user('admin');
        DB::table('user_reporting_heads')->insert(['tenant_id' => $this->tenantId, 'user_id' => $emp->id, 'reporting_head_id' => $ownManager->id]);

        $ids = $this->invokePrivate('getNotificationRecipientIds', $emp->id, $this->tenantId)->all();

        foreach ([$emp, $ownManager, $hr, $admin] as $expected) {
            $this->assertContains($expected->id, $ids);
        }
        $this->assertNotContains($otherManager->id, $ids);
    }

    public function test_leave_holiday_and_week_off_are_not_alerted(): void
    {
        $emp = $this->user();
        $this->assertNull($this->invokePrivate('notExpectedReason', $this->row($emp), Carbon::parse('2031-03-04')));

        $this->leave($emp, '2031-03-03', '2031-03-05');
        $this->assertSame('leave', $this->invokePrivate('notExpectedReason', $this->row($emp), Carbon::parse('2031-03-04')));

        // Second-half leave on this day: they still owe a morning clock-in.
        $this->leave($emp, '2031-03-10', '2031-03-10', 'session2');
        $this->assertNull($this->invokePrivate('notExpectedReason', $this->row($emp), Carbon::parse('2031-03-10')));

        DB::table('holidays')->insert(['tenant_id' => $this->tenantId, 'name' => 'PHPUnit Holiday', 'start_date' => '2031-03-12',
            'end_date' => '2031-03-13', 'status' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $this->assertSame('holiday', $this->invokePrivate('notExpectedReason', $this->row($emp), Carbon::parse('2031-03-13')));

        DB::table('user_weekoffs')->insert(['tenant_id' => $this->tenantId, 'user_id' => $emp->id, 'off_type' => 'day_based',
            'day_name' => 'Sunday', 'status' => 1, 'created_by' => $emp->id, 'created_at' => now(), 'updated_at' => now()]);
        $this->assertSame('week_off', $this->invokePrivate('notExpectedReason', $this->row($emp), Carbon::parse('2031-03-16')));
    }
}
