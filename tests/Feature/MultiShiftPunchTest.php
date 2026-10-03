<?php

namespace Tests\Feature;

use App\Exceptions\OpenPunchSessionException;
use App\Http\Controllers\Api\Attendance\AttendanceController;
use App\Models\Attendance;
use App\Models\AttendancePunch;
use App\Models\AttendanceShiftSegment;
use App\Models\Shift;
use App\Models\User;
use App\Models\UserShift;
use App\Services\Attendance\AttendancePunchService;
use App\Services\Attendance\AuditContext;
use App\Services\Attendance\PunchInput;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Multi-shift Phase 3: punches matched to shifts, a 2nd clock-in allowed for
 * a 2nd shift, per-shift segments, auto clock-out through the punch pipeline,
 * mobile API shift info. Shared dev DB — a throwaway employee/shifts, removed
 * in tearDown; tenant flags restored.
 */
class MultiShiftPunchTest extends TestCase
{
    private string $tag;
    private User $admin;
    private User $employee;
    private int $tenantId;
    private array $originalFlags;
    private string $day;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tag = 'PHPUnit MP ' . uniqid();

        $this->admin = User::withoutGlobalScopes()->where('role', 'admin')->where('status', 1)->whereNotNull('tenant_id')->orderBy('id')->firstOrFail();
        $this->tenantId = (int) $this->admin->tenant_id;
        $this->originalFlags = (array) DB::table('tenants')->where('id', $this->tenantId)->first(['custom_shifts_enabled', 'allow_multiple_punches']);
        DB::table('tenants')->where('id', $this->tenantId)->update(['custom_shifts_enabled' => 1, 'allow_multiple_punches' => 0]);

        $this->employee = User::withoutGlobalScopes()->forceCreate([
            'name' => $this->tag . ' Employee',
            'email' => 'mp.' . uniqid() . '@phpunit.test',
            'employee_id' => 'MP' . random_int(100000, 999999),
            'password' => bcrypt('secret'),
            'role' => 'employee',
            'status' => 1,
            'tenant_id' => $this->tenantId,
        ]);

        $this->day = Carbon::today()->subDays(2)->toDateString();
    }

    protected function tearDown(): void
    {
        $uid = $this->employee->id;
        AttendancePunch::withoutGlobalScopes()->where('user_id', $uid)->delete();
        DB::table('attendance_tracking_sessions')->where('user_id', $uid)->delete();
        DB::table('attendance_logs')->where('user_id', $uid)->delete();
        Attendance::withoutGlobalScopes()->where('user_id', $uid)->delete();
        DB::table('attendance_summaries')->where('user_id', $uid)->delete();
        UserShift::withoutGlobalScopes()->where('user_id', $uid)->delete();
        Shift::withoutGlobalScopes()->where('name', 'like', $this->tag . '%')->delete();
        User::withoutGlobalScopes()->where('id', $uid)->forceDelete();
        DB::table('tenants')->where('id', $this->tenantId)->update($this->originalFlags);
        Auth::logout();
        parent::tearDown();
    }

    private function shift(string $name, string $start, string $end, int $grace = 0): Shift
    {
        return Shift::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenantId, 'name' => $this->tag . ' ' . $name,
            'start_time' => $start, 'end_time' => $end, 'is_overnight' => $end <= $start,
            'total_hours' => 8, 'grace_minutes' => $grace, 'break_time' => 0,
            'status' => 1, 'created_by' => $this->admin->id,
        ]);
    }

    private function onDay(Shift $shift, string $date, bool $additional = false): UserShift
    {
        return UserShift::create([
            'tenant_id' => $this->tenantId, 'user_id' => $this->employee->id, 'shift_id' => $shift->id,
            'is_additional' => $additional, 'date' => $date, 'status' => 'upcoming', 'created_by' => $this->admin->id,
        ]);
    }

    private function punch(string $direction, string $at): AttendancePunch
    {
        return app(AttendancePunchService::class)->capture(new PunchInput(
            userId: $this->employee->id,
            tenantId: $this->tenantId,
            direction: $direction,
            punchedAt: Carbon::parse($at),
            source: 'mobile_app',
            audit: new AuditContext(source: $direction === 'in' ? 'clock_in' : 'clock_out', reason: 'test', override: true),
        ));
    }

    private function attendance(string $date): Attendance
    {
        return Attendance::withoutGlobalScopes()->where('user_id', $this->employee->id)->where('date', $date)->firstOrFail();
    }

    public function test_two_shifts_in_one_day_are_two_matched_sessions(): void
    {
        $d = $this->day;
        $morning = $this->onDay($this->shift('Morning', '06:00', '14:00'), $d);
        $evening = $this->onDay($this->shift('Evening', '15:00', '23:00'), $d, true);

        $in1 = $this->punch('in', "$d 05:50");
        $out1 = $this->punch('out', "$d 14:05");
        $in2 = $this->punch('in', "$d 14:55"); // allowed although multiple punches are off
        $out2 = $this->punch('out', "$d 23:10");

        $this->assertSame([$morning->id, $morning->id, $evening->id, $evening->id],
            array_map(fn ($p) => (int) $p->user_shift_id, [$in1, $out1, $in2, $out2]));

        $a = $this->attendance($d);
        $this->assertSame(2, (int) $a->session_count);
        $this->assertSame(2, (int) $a->shift_count);
        $this->assertSame(495, (int) $a->extra_shift_minutes); // 14:55 → 23:10
        $this->assertSame(480, (int) $a->expected_minutes);
        $this->assertSame("$d 05:50:00", Carbon::parse($a->clock_in)->format('Y-m-d H:i:s'));
        $this->assertSame("$d 23:10:00", Carbon::parse($a->clock_out)->format('Y-m-d H:i:s'));
        $this->assertSame(0, (int) $a->late_minutes);

        $segments = AttendanceShiftSegment::withoutGlobalScopes()->where('attendance_id', $a->id)->orderBy('id')->get();
        $this->assertSame([false, true], $segments->pluck('is_additional')->all());
        $this->assertSame([495, 495], $segments->pluck('worked_minutes')->map(fn ($v) => (int) $v)->all());
        $this->assertSame(['complete', 'complete'], [$morning->fresh()->status, $evening->fresh()->status]);

        // A third clock-in has no unstarted shift left → still blocked.
        $this->expectException(OpenPunchSessionException::class);
        $this->punch('in', "$d 23:30");
    }

    public function test_the_day_is_late_when_the_second_shift_starts_late(): void
    {
        $d = $this->day;
        $this->onDay($this->shift('Morning', '06:00', '14:00'), $d);
        $this->onDay($this->shift('Evening', '15:00', '23:00'), $d, true);

        $this->punch('in', "$d 06:00");
        $this->punch('out', "$d 14:00");
        $this->punch('in', "$d 15:30");

        $a = $this->attendance($d);
        $this->assertSame(30, (int) $a->late_minutes);
        $this->assertSame('late', $a->attendance_status);
        $this->assertNull($a->clock_out); // 2nd shift still open
    }

    public function test_working_only_the_additional_shift_is_measured_against_that_shift(): void
    {
        $d = $this->day;
        $this->onDay($this->shift('Morning', '06:00', '14:00'), $d);
        $this->onDay($this->shift('Evening', '15:00', '23:00', 10), $d, true);

        $this->punch('in', "$d 15:05"); // within the evening grace
        $this->punch('out', "$d 23:00");

        $a = $this->attendance($d);
        $this->assertSame(0, (int) $a->late_minutes);
        $this->assertSame(1, (int) $a->shift_count);
        $this->assertSame(475, (int) $a->extra_shift_minutes);
    }

    public function test_overnight_additional_shift_belongs_to_the_day_it_starts(): void
    {
        $d = $this->day;
        $next = Carbon::parse($d)->addDay()->toDateString();
        $this->onDay($this->shift('Day', '08:00', '16:00'), $d);
        $night = $this->onDay($this->shift('Night', '22:00', '06:00'), $d, true);
        $this->onDay(Shift::withoutGlobalScopes()->where('name', $this->tag . ' Day')->first(), $next);

        $in = $this->punch('in', "$d 21:50");
        $out = $this->punch('out', "$next 06:05");
        $this->assertSame($d, Carbon::parse($in->date)->toDateString());
        $this->assertSame($d, Carbon::parse($out->date)->toDateString());
        $this->assertSame($night->id, (int) $out->user_shift_id);

        // Next morning's own shift goes to the next day.
        $morning = $this->punch('in', "$next 07:55");
        $this->assertSame($next, Carbon::parse($morning->date)->toDateString());
    }

    public function test_one_shift_employee_keeps_the_single_session_rule(): void
    {
        $d = $this->day;
        $row = $this->onDay($this->shift('Only', '09:00', '18:00'), $d);

        $this->punch('in', "$d 09:00");
        $this->punch('out', "$d 18:00");

        $a = $this->attendance($d);
        $this->assertSame(1, (int) $a->shift_count);
        $this->assertSame(0, (int) $a->extra_shift_minutes);
        $this->assertSame($row->id, (int) AttendancePunch::withoutGlobalScopes()->where('user_id', $this->employee->id)->value('user_shift_id'));

        $this->expectException(OpenPunchSessionException::class);
        $this->punch('in', "$d 19:00");
    }

    public function test_auto_clock_out_closes_the_open_session_with_a_punch(): void
    {
        $start = Carbon::now()->subHours(20)->startOfMinute();
        $date = $start->toDateString();
        // Primary shift 12h away from the punch, so the punch clearly matches the 2nd shift.
        $this->onDay($this->shift('Early', $start->copy()->addHours(12)->format('H:i'), $start->copy()->addHours(13)->format('H:i')), $date);
        $this->onDay($this->shift('Late', $start->format('H:i'), $start->copy()->addHours(8)->format('H:i')), $date, true);

        $this->punch('in', $start->format('Y-m-d H:i'));
        $this->artisan('attendance:auto-clockout', ['--user-id' => $this->employee->id])->assertExitCode(0);

        $out = AttendancePunch::withoutGlobalScopes()->where('user_id', $this->employee->id)->where('direction', 'out')->firstOrFail();
        $this->assertSame('system', $out->source);
        $this->assertSame($start->copy()->addHours(8)->format('Y-m-d H:i'), $out->punched_at->format('Y-m-d H:i')); // the 2nd shift's end
        $a = Attendance::withoutGlobalScopes()->where('user_id', $this->employee->id)->firstOrFail();
        $this->assertNotNull($a->clock_out);
        $this->assertStringContainsString('Auto clock-out', (string) $a->remarks);

        // Next clock-in is no longer blocked by a dangling open punch.
        $this->artisan('attendance:auto-clockout', ['--user-id' => $this->employee->id])->expectsOutputToContain('Nothing to close');
    }

    public function test_mobile_api_lists_the_days_shifts_and_the_punch_shift(): void
    {
        $d = Carbon::today()->toDateString();
        $this->onDay($this->shift('Morning', '00:00', '00:30'), $d);
        $this->onDay($this->shift('Evening', '23:00', '23:30'), $d, true);
        $this->punch('in', "$d 00:05");
        $this->punch('out', "$d 00:25");

        Auth::login($this->employee);
        $controller = app(AttendanceController::class);

        $today = $controller->getAttendance()->getData(true)['data'];
        $this->assertCount(2, $today['shifts']);
        $this->assertSame(['completed', 'upcoming'], array_column($today['shifts'], 'status'));
        $this->assertSame([false, true], array_column($today['shifts'], 'is_additional'));

        $history = $controller->punchHistory(Request::create('/', 'GET', ['date' => $d]))->getData(true)['data'];
        $this->assertSame($this->tag . ' Morning', $history['sessions'][0]['shift']['name']);
    }
}
