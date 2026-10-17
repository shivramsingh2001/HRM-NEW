<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AttendancePunch;
use App\Models\Shift;
use App\Models\User;
use App\Models\UserShift;
use App\Services\Attendance\AttendancePolicySnapshot;
use App\Services\Attendance\AttendancePunchService;
use App\Services\Attendance\AuditContext;
use App\Services\Attendance\PolicyResolver;
use App\Services\Attendance\PunchInput;
use App\Services\Attendance\ShiftChangeRegrader;
use App\Services\Shift\ShiftAssignmentService;
use App\Services\Shift\ShiftChangeRecorder;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * A shift assigned AFTER the days were worked (back-dated) re-grades those days.
 *
 * VPS case (user 155, 2026-10-17): punched 10:17 on 6 Oct with no shift → row
 * saved with no shift, late 0, Present. A 10:00 shift was then assigned from
 * 1 Oct; the late policy re-ran but read the stored late 0, so the day stayed
 * Present instead of a late-allowance half day.
 */
class ShiftChangeRegradeTest extends TestCase
{
    private User $admin;
    private User $employee;
    private int $tenantId;
    private string $tag;
    private array $originalFlags;

    protected function setUp(): void
    {
        parent::setUp();
        config(['attendance.async_recompute' => false]);
        $this->tag = 'PHPUnit SCR ' . uniqid();
        $this->admin = User::withoutGlobalScopes()->where('role', 'admin')->where('status', 1)->whereNotNull('tenant_id')->orderBy('id')->firstOrFail();
        $this->tenantId = (int) $this->admin->tenant_id;
        $this->originalFlags = (array) DB::table('tenants')->where('id', $this->tenantId)->first(['custom_shifts_enabled', 'allow_multiple_punches']);
        DB::table('tenants')->where('id', $this->tenantId)->update(['custom_shifts_enabled' => 1, 'allow_multiple_punches' => 0]);
        $this->employee = User::withoutGlobalScopes()->forceCreate([
            'name' => $this->tag . ' Employee', 'email' => 'scr.' . uniqid() . '@phpunit.test',
            'employee_id' => 'SR' . random_int(100000, 999999), 'password' => bcrypt('secret'),
            'role' => 'employee', 'status' => 1, 'tenant_id' => $this->tenantId,
        ]);

        // Late → half day from the 1st late day; fixed 15 min grace (tenant 7's VPS policy).
        $policy = AttendancePolicySnapshot::default()->withOverrides([
            'late_attendance_action' => 'half_day', 'monthly_late_allowance' => 0, 'grace_mode' => 'fixed', 'fixed_grace_minutes' => 15,
        ]);
        $this->mock(PolicyResolver::class, function ($m) use ($policy) {
            $m->shouldReceive('forUserDate')->andReturn($policy);
            $m->shouldReceive('forUserMonth')->andReturn($policy);
            $m->shouldReceive('forTenantDate')->andReturn($policy);
            $m->shouldReceive('forTenantMonth')->andReturn($policy);
            $m->shouldReceive('forget');
        });
    }

    protected function tearDown(): void
    {
        $uid = $this->employee->id;
        AttendancePunch::withoutGlobalScopes()->where('user_id', $uid)->delete();
        foreach (['attendance_tracking_sessions', 'attendance_logs', 'attendance_summaries', 'attendance_shift_segments', 'shift_change_logs', 'user_weekoffs', 'shift_assignments'] as $table) {
            DB::table($table)->where('user_id', $uid)->delete();
        }
        Attendance::withoutGlobalScopes()->where('user_id', $uid)->delete();
        UserShift::withoutGlobalScopes()->where('user_id', $uid)->delete();
        Shift::withoutGlobalScopes()->where('name', 'like', $this->tag . '%')->delete();
        User::withoutGlobalScopes()->where('id', $uid)->forceDelete();
        DB::table('tenants')->where('id', $this->tenantId)->update($this->originalFlags);
        Auth::logout();
        parent::tearDown();
    }

    private function punch(string $direction, string $at): void
    {
        app(AttendancePunchService::class)->capture(new PunchInput(
            userId: $this->employee->id, tenantId: $this->tenantId, direction: $direction, punchedAt: Carbon::parse($at),
            source: 'mobile_app', audit: new AuditContext(source: $direction === 'in' ? 'clock_in' : 'clock_out', reason: 'test', override: true),
        ));
    }

    private function row(string $date): Attendance
    {
        return Attendance::withoutGlobalScopes()->where('user_id', $this->employee->id)->where('date', $date)->firstOrFail();
    }

    /** Assign a 10:00–19:00 shift for [$from, $to] the way the Shift Assignment form does (logged via the recorder). */
    private function assignShift(string $from, string $to): Shift
    {
        $shift = Shift::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenantId, 'name' => $this->tag . ' Day', 'start_time' => '10:00', 'end_time' => '19:00',
            'is_overnight' => false, 'total_hours' => 9, 'grace_minutes' => 15, 'break_time' => 0, 'status' => 1, 'created_by' => $this->admin->id,
        ]);
        Auth::login($this->admin);
        app(ShiftChangeRecorder::class)->track($this->tenantId, [$this->employee->id], $from, $to, ['source' => 'roster_assign'], fn () => app(ShiftAssignmentService::class)
            ->assignFlexible($this->tenantId, $this->employee->id, $shift->id, Carbon::parse($from), Carbon::parse($to), $this->admin->id, true, false, null, null, null));

        return $shift;
    }

    public function test_a_back_dated_shift_re_grades_the_days_already_worked(): void
    {
        $late = Carbon::today()->subDays(3)->toDateString();
        $onTime = Carbon::today()->subDays(2)->toDateString();
        $this->punch('in', "$late 10:17:24");
        $this->punch('out', "$late 19:05:00");
        $this->punch('in', "$onTime 10:10:00");
        $this->punch('out', "$onTime 19:05:00");

        // Worked with no shift: nothing to be late against.
        $this->assertNull($this->row($late)->scheduled_shift_start);
        $this->assertSame(0, (int) $this->row($late)->late_minutes);

        $shift = $this->assignShift($late, $onTime);
        $this->assertNull($this->row($late)->scheduled_shift_start, 'nothing runs before the response is sent');
        app()->terminate();   // what the framework does after sending the response

        $this->assertSame(['regraded' => 0, 'skipped' => 0, 'locked' => 0, 'failed' => 0], app(ShiftChangeRegrader::class)->run(), 'queue already drained');
        $row = $this->row($late);
        $this->assertSame($shift->id, (int) $row->shift_id);
        $this->assertSame('10:00:00', substr((string) $row->scheduled_shift_start, 0, 8));
        $this->assertSame(17, (int) $row->late_minutes);
        $this->assertSame('half_day', $row->effective_status, 'late #1 over an allowance of 0');
        $this->assertSame('0.50', (string) $row->day_fraction);

        $this->assertSame(0, (int) $this->row($onTime)->late_minutes, '10 min late is inside the 15 min grace');
        $this->assertContains($this->row($onTime)->effective_status, ['present', 'overtime']);
    }

    public function test_hand_marked_days_and_days_without_punches_are_left_alone(): void
    {
        $marked = Carbon::today()->subDays(3)->toDateString();
        $empty = Carbon::today()->subDays(2)->toDateString();
        DB::table('attendances')->insert([
            'tenant_id' => $this->tenantId, 'user_id' => $this->employee->id, 'date' => $marked,
            'clock_in' => "$marked 10:40:00", 'clock_out' => "$marked 19:00:00", 'late_minutes' => 0, 'worked_hours' => 8.33,
            'attendance_status' => 'present', 'attendance_type' => 'manual', 'marked_by' => $this->admin->id,
            'effective_status' => 'present', 'day_fraction' => 1, 'status' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->assignShift($marked, $empty);
        $result = app(ShiftChangeRegrader::class)->run();

        $this->assertSame(['regraded' => 0, 'skipped' => 2, 'locked' => 0, 'failed' => 0], $result);
        $row = $this->row($marked);
        $this->assertSame(0, (int) $row->late_minutes, "an admin's mark is never re-graded");
        $this->assertSame('present', $row->effective_status);
        $this->assertNull($row->scheduled_shift_start);
        $this->assertFalse(Attendance::withoutGlobalScopes()->where('user_id', $this->employee->id)->where('date', $empty)->exists(),
            'no attendance row is created for a day with no punches');
    }

    public function test_future_days_are_not_queued_and_nothing_runs_without_a_change(): void
    {
        $this->assignShift(Carbon::today()->addDay()->toDateString(), Carbon::today()->addDays(5)->toDateString());

        $this->assertSame(['regraded' => 0, 'skipped' => 0, 'locked' => 0, 'failed' => 0], app(ShiftChangeRegrader::class)->run());
    }
}
