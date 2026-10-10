<?php

namespace Tests\Feature;

use App\Models\AttendanceRegularization;
use App\Models\User;
use App\Services\Attendance\AttendanceDayResolver;
use App\Services\Attendance\AttendanceEntryService;
use App\Services\Attendance\AttendancePunchService;
use App\Services\Attendance\PunchInput;
use App\Services\Attendance\RegularizationShiftCheck;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * A day clocked in at 06:30 and out at 09:00 the next morning is 26.5 h —
 * through punches, manual marking ("clock-out is on the next day"),
 * regularization ("out time is on the next day"), the day grading (no 24 h
 * clamp) and the per-company auto clock-out limit.
 */
class NextDayClockOutTest extends TestCase
{
    private User $admin;
    private User $employee;
    private int $tenantId;
    private string $date;
    private array $tenantBefore;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::withoutGlobalScopes()->where('role', 'admin')->where('status', 1)->whereNotNull('tenant_id')->orderBy('id')->firstOrFail();
        $this->tenantId = (int) $this->admin->tenant_id;
        $this->tenantBefore = (array) DB::table('tenants')->where('id', $this->tenantId)->first(['allow_multiple_punches', 'auto_clockout_hours']);
        $this->date = Carbon::today()->subDays(10)->toDateString();
        $this->employee = User::withoutGlobalScopes()->forceCreate([
            'name' => 'PHPUnit NDC ' . uniqid(), 'email' => 'ndc.' . uniqid() . '@phpunit.test',
            'employee_id' => 'ND' . random_int(100000, 999999), 'password' => bcrypt('secret'),
            'role' => 'employee', 'status' => 1, 'tenant_id' => $this->tenantId,
        ]);
        app()->instance('current_tenant', \App\Models\Tenant::find($this->tenantId));
    }

    protected function tearDown(): void
    {
        DB::table('attendance_punches')->where('user_id', $this->employee->id)->delete();
        DB::table('attendance_logs')->where('user_id', $this->employee->id)->delete();
        DB::table('attendances')->where('user_id', $this->employee->id)->delete();
        DB::table('attendance_regularizations')->where('user_id', $this->employee->id)->delete();
        DB::table('attendance_summaries')->where('user_id', $this->employee->id)->delete();
        DB::table('tenants')->where('id', $this->tenantId)->update($this->tenantBefore);
        User::withoutGlobalScopes()->where('id', $this->employee->id)->forceDelete();
        parent::tearDown();
    }

    private function row()
    {
        return DB::table('attendances')->where('user_id', $this->employee->id)->where('date', $this->date)->first();
    }

    private function nextDay(string $time): string
    {
        return Carbon::parse($this->date)->addDay()->toDateString() . ' ' . $time;
    }

    public function test_punches_across_midnight_count_26_and_a_half_hours(): void
    {
        $punches = app(AttendancePunchService::class);
        $punches->capture(new PunchInput($this->employee->id, $this->tenantId, 'in', Carbon::parse($this->date . ' 06:30:00'), 'web'));
        $punches->capture(new PunchInput($this->employee->id, $this->tenantId, 'out', Carbon::parse($this->nextDay('09:00:00')), 'web'));

        $row = $this->row();
        $this->assertSame($this->nextDay('09:00:00'), $row->clock_out);
        $this->assertSame(26.5, (float) $row->worked_hours);
        $this->assertSame('26:30:00', $row->total_hours);

        // The day resolver used by summaries / payroll no longer clamps it to 24 h.
        $aggregate = new \ReflectionMethod(AttendanceDayResolver::class, 'aggregate');
        $day = $aggregate->invoke(app(AttendanceDayResolver::class), collect([$row]));
        $this->assertSame(26.5, (float) $day['worked_hours']);
    }

    public function test_manual_mark_with_clock_out_on_the_next_day(): void
    {
        $this->actingAs($this->admin)->postJson(route('team.mark-attendance'), [
            'user_id' => $this->employee->id, 'date' => $this->date, 'status' => 'present',
            'clock_in' => '06:30', 'clock_out' => '09:00', 'clock_out_next_day' => 1, 'remarks' => 'Long duty',
        ])->assertOk();

        $row = $this->row();
        $this->assertSame($this->nextDay('09:00:00'), $row->clock_out);
        $this->assertSame(26.5, (float) $row->worked_hours);

        // Without the tick it is the same day: 2.5 h.
        $this->actingAs($this->admin)->postJson(route('team.mark-attendance'), [
            'user_id' => $this->employee->id, 'date' => $this->date, 'status' => 'present',
            'clock_in' => '06:30', 'clock_out' => '09:00', 'remarks' => 'Short',
        ])->assertOk();
        $this->assertSame(2.5, (float) $this->row()->worked_hours);
    }

    public function test_regularization_with_out_time_on_the_next_day(): void
    {
        $check = app(RegularizationShiftCheck::class);
        $this->assertNotNull($check->check($this->employee->id, $this->tenantId, $this->date, null, '22:00', '06:00')['error']);
        $this->assertNull($check->check($this->employee->id, $this->tenantId, $this->date, null, '22:00', '06:00', true)['error']);

        $reg = AttendanceRegularization::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenantId, 'user_id' => $this->employee->id, 'date' => $this->date,
            'request_type' => 'both', 'in_time' => '06:30', 'out_time' => '09:00', 'out_next_day' => 1,
            'reason' => 'Long duty, forgot to punch', 'status' => 'approved',
        ]);
        app(AttendanceEntryService::class)->applyRegularization($reg, $this->admin);

        $row = $this->row();
        $this->assertSame($this->nextDay('09:00:00'), $row->clock_out);
        $this->assertSame(26.5, (float) $row->worked_hours);
    }

    public function test_auto_clock_out_uses_the_company_limit(): void
    {
        $in = Carbon::now()->subHours(20)->startOfMinute();
        $this->date = $in->toDateString();
        app(AttendancePunchService::class)->capture(new PunchInput($this->employee->id, $this->tenantId, 'in', $in, 'web'));

        DB::table('tenants')->where('id', $this->tenantId)->update(['auto_clockout_hours' => 30]);
        $this->artisan('attendance:auto-clockout', ['--hours' => 15, '--user-id' => $this->employee->id])->assertSuccessful();
        $this->assertNull($this->row()->clock_out, 'a 30 h company limit leaves a 20 h day open');

        DB::table('tenants')->where('id', $this->tenantId)->update(['auto_clockout_hours' => 15]);
        $this->artisan('attendance:auto-clockout', ['--hours' => 15, '--user-id' => $this->employee->id])->assertSuccessful();
        $this->assertNotNull($this->row()->clock_out);
    }

    public function test_reports_and_exports_show_the_long_day(): void
    {
        $this->withoutMiddleware([\App\Http\Middleware\EnsureFeatureEnabled::class]);
        DB::table('user_job_details')->insert(['tenant_id' => $this->tenantId, 'user_id' => $this->employee->id, 'joining_date' => '2026-01-01', 'created_at' => now(), 'updated_at' => now()]);
        $punches = app(AttendancePunchService::class);
        $punches->capture(new PunchInput($this->employee->id, $this->tenantId, 'in', Carbon::parse($this->date . ' 06:30:00'), 'web'));
        $punches->capture(new PunchInput($this->employee->id, $this->tenantId, 'out', Carbon::parse($this->nextDay('09:00:00')), 'web'));
        $month = substr($this->date, 0, 7);

        try {
            // Employee-wise export (its controller method used to be missing → 500).
            $csv = $this->actingAs($this->admin)->get(route('report.attendance.detailed.export', ['employee_id' => $this->employee->id, 'month' => $month]))
                ->assertOk()->streamedContent();
            $this->assertStringContainsString('26:30:00', $csv);
            $this->assertStringContainsString('09:00 AM (+1 day)', $csv);
            $this->actingAs($this->admin)->get(route('report.attendance.monthly.summary.export', ['month' => $month, 'search' => $this->employee->name]))->assertOk();

            // Clock In/Out Log export carries the punch's own date.
            $res = $this->actingAs($this->admin)->get(route('report.attendance.punches.export', ['start_date' => $this->date, 'end_date' => $this->date, 'search' => $this->employee->name]))->assertOk();
            $log = $res->baseResponse instanceof \Symfony\Component\HttpFoundation\StreamedResponse ? $res->streamedContent() : $res->getContent();
            $this->assertStringContainsString($this->date . ',' . Carbon::parse($this->date)->addDay()->toDateString() . ',09:00:00,OUT', $log);
        } finally {
            DB::table('user_job_details')->where('user_id', $this->employee->id)->delete();
        }
    }

    public function test_runaway_clock_out_is_repaired_and_blocked(): void
    {
        // A bulk auto clock-out stamped a later date: 296 h for one day, real length kept in total_hours.
        $id = DB::table('attendances')->insertGetId([
            'tenant_id' => $this->tenantId, 'user_id' => $this->employee->id, 'date' => $this->date,
            'clock_in' => $this->date . ' 10:08:36', 'clock_out' => Carbon::parse($this->date)->addDays(12)->toDateString() . ' 18:39:40',
            'worked_hours' => 296.52, 'total_hours' => '08:31:04', 'attendance_status' => 'present', 'attendance_type' => 'app',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->artisan('attendance:repair-runaway-clockouts')->assertSuccessful();
        $row = DB::table('attendances')->where('id', $id)->first();
        $this->assertSame($this->date . ' 18:39:40', $row->clock_out);
        $this->assertSame(8.52, (float) $row->worked_hours);

        // A clock-out more than 48 h after the open clock-in is refused.
        $punches = app(AttendancePunchService::class);
        $in = Carbon::now()->subHours(50)->startOfMinute();
        $this->date = $in->toDateString();
        $punches->capture(new PunchInput($this->employee->id, $this->tenantId, 'in', $in, 'web'));
        $this->expectException(\App\Exceptions\NoOpenPunchSessionException::class);
        $punches->capture(new PunchInput($this->employee->id, $this->tenantId, 'out', Carbon::now()->startOfMinute(), 'web'));
    }

    public function test_clock_out_time_marks_the_next_day(): void
    {
        $this->assertSame('09:00 AM (+1 day)', clock_out_time($this->nextDay('09:00:00'), $this->date));
        $this->assertSame('06:00 PM', clock_out_time($this->date . ' 18:00:00', $this->date));
        $this->assertSame('—', clock_out_time(null, $this->date));
    }
}
