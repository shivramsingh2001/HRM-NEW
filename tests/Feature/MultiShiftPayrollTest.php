<?php

namespace Tests\Feature;

use App\Enums\AttendanceStatus;
use App\Http\Middleware\EnsureFeatureEnabled;
use App\Models\Attendance;
use App\Models\AttendancePunch;
use App\Models\AttendanceRegularization;
use App\Models\AttendanceShiftSegment;
use App\Models\Shift;
use App\Models\User;
use App\Models\UserShift;
use App\Services\Attendance\AttendanceEntryService;
use App\Services\Attendance\AttendancePunchService;
use App\Services\Attendance\AttendanceRollupService;
use App\Services\Attendance\AuditContext;
use App\Services\Attendance\LatePolicyService;
use App\Services\Attendance\PolicyResolver;
use App\Services\Attendance\PunchInput;
use App\Services\Payroll\PayrollAttendanceContextBuilder;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Multi-shift Phase 4: day status on the primary shift, 2nd-shift hours as
 * automatic overtime in payroll + reports, per-shift / overnight
 * regularization written as punches, manual marking voiding punches.
 * Shared dev DB — throwaway employee/shifts removed in tearDown.
 */
class MultiShiftPayrollTest extends TestCase
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
        $this->tag = 'PHPUnit MPay ' . uniqid();
        $this->withoutMiddleware(EnsureFeatureEnabled::class);

        $this->admin = User::withoutGlobalScopes()->where('role', 'admin')->where('status', 1)->whereNotNull('tenant_id')->orderBy('id')->firstOrFail();
        $this->tenantId = (int) $this->admin->tenant_id;
        $this->originalFlags = (array) DB::table('tenants')->where('id', $this->tenantId)->first(['custom_shifts_enabled', 'allow_multiple_punches']);
        DB::table('tenants')->where('id', $this->tenantId)->update(['custom_shifts_enabled' => 1, 'allow_multiple_punches' => 0]);

        $this->employee = User::withoutGlobalScopes()->forceCreate([
            'name' => $this->tag . ' Employee',
            'email' => 'mpay.' . uniqid() . '@phpunit.test',
            'employee_id' => 'MPY' . random_int(100000, 999999),
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
        AttendanceRegularization::withoutGlobalScopes()->where('user_id', $uid)->delete();
        UserShift::withoutGlobalScopes()->where('user_id', $uid)->delete();
        Shift::withoutGlobalScopes()->where('name', 'like', $this->tag . '%')->delete();
        User::withoutGlobalScopes()->where('id', $uid)->forceDelete();
        DB::table('tenants')->where('id', $this->tenantId)->update($this->originalFlags);
        parent::tearDown();
    }

    private function shift(string $name, string $start, string $end): Shift
    {
        return Shift::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenantId, 'name' => $this->tag . ' ' . $name,
            'start_time' => $start, 'end_time' => $end, 'is_overnight' => $end <= $start,
            'total_hours' => 8, 'grace_minutes' => 0, 'break_time' => 0,
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
            userId: $this->employee->id, tenantId: $this->tenantId, direction: $direction,
            punchedAt: Carbon::parse($at), source: 'mobile_app',
            audit: new AuditContext(source: $direction === 'in' ? 'clock_in' : 'clock_out', reason: 'test', override: true),
        ));
    }

    private function twoShiftDay(string $firstOut = '14:00'): array
    {
        $d = $this->day;
        $morning = $this->onDay($this->shift('Morning', '06:00', '14:00'), $d);
        $evening = $this->onDay($this->shift('Evening', '15:00', '23:00'), $d, true);
        $this->punch('in', "$d 06:00");
        $this->punch('out', "$d $firstOut");
        $this->punch('in', "$d 15:00");
        $this->punch('out', "$d 23:00");

        return [$morning, $evening];
    }

    private function attendance(): Attendance
    {
        return Attendance::withoutGlobalScopes()->where('user_id', $this->employee->id)->where('date', $this->day)->firstOrFail();
    }

    public function test_day_status_is_decided_by_the_primary_shift_only(): void
    {
        $this->twoShiftDay('09:00'); // 3h of an 8h primary shift + a full 8h extra shift
        $a = $this->attendance();
        $this->assertSame(480, (int) $a->extra_shift_minutes);
        $this->assertEquals(11.0, (float) $a->worked_hours);

        // Base status (before the tenant's late/early allowance rules), default policy.
        $policy = \App\Services\Attendance\AttendancePolicySnapshot::default();
        $base = new \ReflectionMethod(LatePolicyService::class, 'baseStatus');
        $row = (object) DB::table('attendances')->where('id', $a->id)->first();

        // Judged on 3h of the 8h primary shift — not the 06:00–23:00 day.
        $this->assertSame($policy->classify(3.0, 8 * 3600), $base->invoke(app(LatePolicyService::class), $row, $policy)[0]);
        $this->assertNotSame('present', $policy->classify(3.0, 8 * 3600));

        // Same row without the extra shift would have been a full day.
        $row->extra_shift_minutes = 0;
        $this->assertSame('present', $base->invoke(app(LatePolicyService::class), $row, $policy)[0]);

        // The day resolver (summaries / payroll days) agrees.
        $resolved = app(\App\Services\Attendance\AttendanceDayResolver::class)
            ->resolve([(object) DB::table('attendances')->where('id', $a->id)->first(['clock_in', 'clock_out', 'worked_hours', 'extra_shift_minutes', 'shift_count', 'scheduled_shift_start', 'scheduled_shift_end', 'attendance_type'])], [], [], false, false, $this->day, $policy);
        $this->assertSame($policy->classify(3.0, 8 * 3600), $resolved['token']);
        $this->assertEquals(11.0, (float) $resolved['worked_hours']); // the sessions, not the 06:00–23:00 span
    }

    public function test_extra_shift_hours_are_paid_as_overtime_in_payroll(): void
    {
        $this->twoShiftDay();
        $ym = Carbon::parse($this->day)->format('Y-m');

        $context = app(PayrollAttendanceContextBuilder::class)->build($this->employee->id, $this->tenantId, $ym);
        $this->assertSame(8.0, (float) $context['extra_shift_overtime_hours']);
        $this->assertGreaterThanOrEqual(8.0, (float) $context['approved_overtime_hours']);

        $controller = app(\App\Http\Controllers\Payroll\MonthlyPayrollController::class);
        $legacy = (new \ReflectionMethod($controller, 'getApprovedOvertimeHours'))->invoke($controller, $this->employee->id, $ym, $this->tenantId);
        $this->assertSame(8.0, (float) $legacy['extra_shift_hours']);
        $this->assertContains('Additional shift (automatic overtime)', array_column($legacy['details'], 'reason'));
    }

    public function test_regularizing_one_shift_rewrites_only_its_punches_and_survives_a_recompute(): void
    {
        [, $evening] = $this->twoShiftDay();
        $reg = AttendanceRegularization::create([
            'tenant_id' => $this->tenantId, 'user_id' => $this->employee->id, 'date' => $this->day,
            'user_shift_id' => $evening->id, 'request_type' => 'out_time', 'out_time' => '23:30',
            'reason' => 'Stayed late', 'status' => 'approved',
        ]);

        app(AttendanceEntryService::class)->applyRegularization($reg, $this->admin);

        $active = AttendancePunch::withoutGlobalScopes()->where('user_id', $this->employee->id)->where('status', 'active')->orderBy('punched_at')->get();
        $this->assertCount(4, $active);
        $this->assertSame(['06:00', '14:00', '15:00', '23:30'], $active->map(fn ($p) => $p->punched_at->format('H:i'))->all());
        $this->assertSame(2, AttendancePunch::withoutGlobalScopes()->where('user_id', $this->employee->id)->where('status', 'void')->count());
        $this->assertTrue((bool) $active[3]->is_regularized);

        $a = $this->attendance();
        $this->assertSame(510, (int) $a->extra_shift_minutes); // 15:00 → 23:30
        $this->assertTrue((bool) $a->is_regularized);

        // A later rollup derives the same day from the punches — no overwrite.
        app(AttendanceRollupService::class)->recompute($this->employee->id, $this->tenantId, $this->day, new AuditContext(source: 'clock_out'));
        $this->assertSame(Carbon::parse($this->day . ' 23:30')->format('Y-m-d H:i:s'), Carbon::parse($this->attendance()->clock_out)->format('Y-m-d H:i:s'));
    }

    public function test_overnight_regularization_puts_the_out_time_on_the_next_day(): void
    {
        $this->onDay($this->shift('Night', '22:00', '06:00'), $this->day);
        $reg = AttendanceRegularization::create([
            'tenant_id' => $this->tenantId, 'user_id' => $this->employee->id, 'date' => $this->day,
            'request_type' => 'both', 'in_time' => '22:00', 'out_time' => '06:00',
            'reason' => 'Forgot to punch', 'status' => 'approved',
        ]);

        app(AttendanceEntryService::class)->applyRegularization($reg, $this->admin);

        $a = $this->attendance();
        $next = Carbon::parse($this->day)->addDay()->toDateString();
        $this->assertSame("$next 06:00:00", Carbon::parse($a->clock_out)->format('Y-m-d H:i:s'));
        $this->assertEquals(8.0, (float) $a->worked_hours);
    }

    public function test_regularization_form_accepts_overnight_times_only_for_a_night_shift(): void
    {
        $this->onDay($this->shift('Day', '09:00', '18:00'), $this->day);
        $data = ['request_type' => 'both', 'date' => $this->day, 'in_time' => '22:00', 'out_time' => '06:00', 'reason' => 'Forgot to punch out'];

        $this->actingAs($this->employee)->postJson(route('attendance-regularization.store'), $data)
            ->assertStatus(422)->assertJsonPath('errors.out_time.0', 'Out time must be after in time');

        UserShift::withoutGlobalScopes()->where('user_id', $this->employee->id)->delete();
        $this->onDay($this->shift('Night', '22:00', '06:00'), $this->day);
        $this->actingAs($this->employee)->postJson(route('attendance-regularization.store'), $data)->assertOk();
    }

    public function test_manual_marking_voids_the_days_punches(): void
    {
        $this->twoShiftDay();

        app(AttendanceEntryService::class)->markStatus([
            'user_id' => $this->employee->id, 'tenant_id' => $this->tenantId,
            'date' => $this->day, 'status' => AttendanceStatus::Absent,
        ], $this->admin);

        $this->assertSame(0, AttendancePunch::withoutGlobalScopes()->where('user_id', $this->employee->id)->where('status', 'active')->count());
        $a = $this->attendance();
        $this->assertSame('absent', $a->attendance_status);
        $this->assertSame(0, (int) $a->extra_shift_minutes);
        $this->assertSame(0, AttendanceShiftSegment::withoutGlobalScopes()->where('attendance_id', $a->id)->count());
    }

    public function test_reports_show_the_shifts_and_the_extra_shift_overtime(): void
    {
        $this->twoShiftDay();
        session(['tenant_id' => $this->tenantId]);

        $this->actingAs($this->admin)
            ->get(route('report.attendance.day.index', ['date' => $this->day, 'search' => $this->employee->employee_id]))
            ->assertOk()
            ->assertSee('+ ' . $this->tag . ' Evening')
            ->assertSee('Includes 480 min from additional shift(s)', false);

        $this->actingAs($this->admin)
            ->get(route('report.overtime.monthly.index', ['month' => Carbon::parse($this->day)->format('Y-m'), 'search' => $this->employee->employee_id]))
            ->assertOk()
            ->assertSee('Extra Shift')
            ->assertSee('8.0h');
    }
}
