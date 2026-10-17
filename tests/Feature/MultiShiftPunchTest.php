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

    public function test_a_late_extra_shift_does_not_make_the_day_late(): void
    {
        // 2026-10-14 rule: the day's normal shift alone decides late / status
        // (it used to be: any shift starting late made the whole day late).
        $d = $this->day;
        $this->onDay($this->shift('Morning', '06:00', '14:00'), $d);
        $evening = $this->onDay($this->shift('Evening', '15:00', '23:00'), $d, true);

        $this->punch('in', "$d 06:00");
        $this->punch('out', "$d 14:00");
        $this->punch('in', "$d 15:30");

        $a = $this->attendance($d);
        $this->assertSame(0, (int) $a->late_minutes);
        $this->assertSame('present', $a->attendance_status);
        $this->assertNull($a->clock_out); // 2nd shift still open
        // The extra shift's own lateness is still recorded on its segment.
        $this->assertSame(30, (int) AttendanceShiftSegment::withoutGlobalScopes()->where('user_shift_id', $evening->id)->value('late_minutes'));
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
        // The only shift worked is the day's normal shift — not extra (2026-10-14 rule).
        $this->assertSame(0, (int) $a->extra_shift_minutes);
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
        $this->assertSame([false, false], array_column($today['shifts'], 'is_extra'));   // the Evening shift isn't worked yet
        $this->assertEquals(1.0, $today['day_value']);
        // Clocked out of Morning: status 0 (Clock In offered) only while Evening is open for clock-in (21:00–23:30).
        $windowOpen = now()->between(Carbon::parse("$d 21:00"), Carbon::parse("$d 23:30"));
        $this->assertSame($windowOpen ? 0 : 1, (int) $today['status']);

        $history = $controller->punchHistory(Request::create('/', 'GET', ['date' => $d]))->getData(true)['data'];
        $this->assertSame($this->tag . ' Morning', $history['sessions'][0]['shift']['name']);
    }
    public function test_admin_marked_shift_leaves_the_days_other_shift_open_to_clock_in(): void
    {
        // User 1152 on 12 Oct: night shift primary, day shift additional; the admin
        // re-marked the day shift by hand, then the night clock-in was refused.
        $d = $this->day;
        $night = $this->onDay($this->shift("Night", "21:20", "07:30"), $d);
        $general = $this->onDay($this->shift("General", "09:00", "18:30"), $d, true);
        $this->punch("in", "$d 13:16");
        $this->punch("out", "$d 17:00");

        app(\App\Services\Attendance\AttendanceEntryService::class)->markStatus([
            "user_id" => $this->employee->id, "tenant_id" => $this->tenantId, "date" => $d,
            "status" => \App\Enums\AttendanceStatus::Present, "clock_in" => "09:33", "clock_out" => "17:00",
        ], $this->admin);

        $kept = AttendancePunch::withoutGlobalScopes()->where("user_id", $this->employee->id)->where("status", "active")->get();
        $this->assertSame([$general->id, $general->id], $kept->pluck("user_shift_id")->map(fn ($v) => (int) $v)->all());
        $this->assertSame(["upcoming", "complete"], [$night->fresh()->status, $general->fresh()->status]);

        $in = $this->punch("in", "$d 21:20");
        $this->assertSame($night->id, (int) $in->user_shift_id);
        $this->punch("out", Carbon::parse($d)->addDay()->toDateString() . " 07:30");

        $a = $this->attendance($d);
        $this->assertSame(2, (int) $a->shift_count);
        $this->assertSame("$d 09:33:00", Carbon::parse($a->clock_in)->format("Y-m-d H:i:s"));
        $this->assertSame(447, (int) $a->extra_shift_minutes); // 09:33 → 17:00 on the additional shift
    }
    public function test_team_page_lists_each_shifts_clock_in_and_out(): void
    {
        $d = $this->day;
        $this->onDay($this->shift("General", "09:30", "17:00"), $d);
        $this->onDay($this->shift("Night", "17:15", "02:15"), $d, true);
        $this->punch("in", "$d 09:30");
        $this->punch("out", "$d 17:00");
        $this->punch("in", "$d 17:19");

        $this->actingAs($this->admin)->withSession(["tenant_id" => $this->tenantId]);
        app()->instance("current_tenant", \App\Models\Tenant::find($this->tenantId));
        $controller = app(\App\Http\Controllers\Team\TeamController::class);
        [$admin, $name] = [$this->admin, $this->employee->name];
        $members = (function () use ($admin, $d, $name) {
            return $this->getTeamMembers($admin, $d, null, $name);
        })->call($controller);
        $member = $members->firstWhere("id", $this->employee->id);
        $this->assertNotNull($member);
        $this->assertSame([$this->tag . " General", $this->tag . " Night"], $member->shift_sessions->pluck("name")->all());
        $this->assertSame(["09:30", "17:19"], $member->shift_sessions->map(fn ($s) => $s->in->format("H:i"))->all());
        $this->assertSame("17:00", $member->shift_sessions[0]->out->format("H:i"));
        $this->assertNull($member->shift_sessions[1]->out);
        $this->assertSame($this->tag . " Night", $member->open_shift);
        $this->assertSame(["present", "checked_in"], $member->shift_sessions->pluck("state")->all());
        $html = $this->get(route("team.index", ["date" => $d, "search" => $name]))->assertOk()->getContent();
        $this->assertStringContainsString('>Extra</span>', $html);   // the Night (extra) shift's tag
        // Status / punch columns carry no shift-name label (the Shift column has it); punches show the date.
        $this->assertStringNotContainsString('General:</span>', $html);
        $this->assertMatchesRegularExpression("/<span class=\"badge badge-present\">.*Present/s", $html);
        $this->assertStringContainsString(\Carbon\Carbon::parse("$d 09:30")->format("d M Y, h:i A"), $html);
        $this->assertSame([[$this->tag . " General", "09:30 AM–05:00 PM", false], [$this->tag . " Night", "05:15 PM–02:15 AM", true]],
            $member->shifts->map(fn ($s) => [$s->name, $s->time, $s->extra])->all());

        $html = view("client.team.partials.direct-reports", ["directReports" => collect([$member])])->render();
        $this->assertStringContainsString("09:30 AM –", $html);
        $this->assertStringContainsString("05:19 PM –", $html);
        $this->assertStringContainsString("ongoing", $html);
    }
    public function test_mark_attendance_marks_only_the_chosen_shift(): void
    {
        $d = $this->day;
        $general = $this->onDay($this->shift("General", "09:30", "17:00"), $d);
        $night = $this->onDay($this->shift("Night", "17:15", "02:15"), $d, true);
        $this->punch("in", "$d 09:31");
        $this->punch("out", "$d 17:02");
        $resolver = app(\App\Services\Attendance\TenantShiftResolver::class);

        // The picker: both shifts, the General one with its recorded times.
        $choices = $resolver->markableShifts($this->employee->id, $this->tenantId, $d);
        $this->assertSame([$general->id, $night->id], array_column($choices, "user_shift_id"));
        $this->assertSame(["09:31", "17:02"], [$choices[0]["clock_in"], $choices[0]["clock_out"]]);
        $this->assertSame("", $choices[1]["clock_in"]);

        // Admin marks the Night shift only, through the real endpoint.
        $this->actingAs($this->admin)->withSession(["tenant_id" => $this->tenantId]);
        app()->instance("current_tenant", \App\Models\Tenant::find($this->tenantId));
        $this->postJson(route("team.mark-attendance"), [
            "user_id" => $this->employee->id, "date" => $d, "status" => "present",
            "clock_in" => "17:20", "clock_out" => "02:00", "user_shift_id" => $night->id,
        ])->assertOk()->assertJson(["success" => true]);

        $active = AttendancePunch::withoutGlobalScopes()->where("user_id", $this->employee->id)->where("status", "active")->orderBy("punched_at")->get();
        $this->assertSame(["mobile_app", "mobile_app", "manual", "manual"], $active->pluck("source")->all());
        $this->assertSame([$general->id, $general->id, $night->id, $night->id], $active->pluck("user_shift_id")->map(fn ($v) => (int) $v)->all());
        $this->assertSame(Carbon::parse($d)->addDay()->toDateString() . " 02:00:00", Carbon::parse($active[3]->punched_at)->format("Y-m-d H:i:s"));
        $a = $this->attendance($d);
        $this->assertSame(2, (int) $a->shift_count);
        $this->assertSame(520, (int) $a->extra_shift_minutes); // 17:20 → 02:00
        $this->assertSame(["complete", "complete"], [$general->fresh()->status, $night->fresh()->status]);

        // Re-marking the General shift replaces only its punches.
        $this->postJson(route("team.mark-attendance"), [
            "user_id" => $this->employee->id, "date" => $d, "status" => "present",
            "clock_in" => "09:30", "clock_out" => "17:00", "user_shift_id" => $general->id,
        ])->assertOk();
        $active = AttendancePunch::withoutGlobalScopes()->where("user_id", $this->employee->id)->where("status", "active")->orderBy("punched_at")->get();
        $this->assertSame(["09:30", "17:00", "17:20", "02:00"], $active->map(fn ($p) => Carbon::parse($p->punched_at)->format("H:i"))->all());
        $this->assertSame(520, (int) $this->attendance($d)->extra_shift_minutes);

        // One shift that day: no picker.
        $this->assertSame([], $resolver->markableShifts($this->admin->id, $this->tenantId, $d));
    }
    public function test_today_api_status_is_0_while_the_next_shift_can_be_clocked_in(): void
    {
        // Evening starts 30 min from now (its clock-in window is open); skip near midnight.
        $now = now();
        if ($now->hour < 1 || $now->copy()->addMinutes(150)->toDateString() !== $now->toDateString()) {
            $this->markTestSkipped('Needs both shifts inside today.');
        }
        $d = $now->toDateString();
        $this->onDay($this->shift('Morning', '00:00', '00:30'), $d);
        $this->onDay($this->shift('Evening', $now->copy()->addMinutes(30)->format('H:i'), $now->copy()->addMinutes(150)->format('H:i')), $d, true);
        $this->punch('in', "$d 00:05");
        $this->punch('out', "$d 00:25");
        Auth::login($this->employee);
        $controller = app(AttendanceController::class);

        $this->assertSame(0, (int) $controller->getAttendance()->getData(true)['data']['status']);
        $this->punch('in', $now->format('Y-m-d H:i:s'));   // accepted: the Evening shift
        $this->assertSame(1, (int) $controller->getAttendance()->getData(true)['data']['status']);
        $this->punch('out', $now->copy()->addMinutes(5)->format('Y-m-d H:i:s'));
        // Evening already worked (not over yet): stays 1.
        $this->assertSame(1, (int) $controller->getAttendance()->getData(true)['data']['status']);
    }
    public function test_only_the_shifts_beyond_the_days_normal_one_are_extra(): void
    {
        [$main, $second] = [$this->shift('Main', '06:00', '14:00'), $this->shift('Second', '15:00', '23:00')];
        $segments = fn ($d) => AttendanceShiftSegment::withoutGlobalScopes()->where('attendance_id', $this->attendance($d)->id)
            ->orderBy('id')->get()->map(fn ($s) => [(int) $s->user_shift_id, (bool) $s->is_extra])->all();

        // Day 1 — only the additional shift worked: it is the day's normal shift, nothing extra.
        $d1 = Carbon::parse($this->day)->subDay()->toDateString();
        $this->onDay($main, $d1);
        $second1 = $this->onDay($second, $d1, true);
        $this->punch('in', "$d1 14:55");
        $this->punch('out', "$d1 23:00");
        $this->assertSame([[$second1->id, false]], $segments($d1));
        $this->assertSame(0, (int) $this->attendance($d1)->extra_shift_minutes);

        // Day 2 — both worked: the main shift is the normal one, the additional one is extra.
        $d2 = $this->day;
        $main2 = $this->onDay($main, $d2);
        $second2 = $this->onDay($second, $d2, true);
        $this->punch('in', "$d2 05:55");
        $this->punch('out', "$d2 14:00");
        $this->punch('in', "$d2 14:55");
        $this->punch('out', "$d2 23:00");
        $this->assertSame([[$main2->id, false], [$second2->id, true]], $segments($d2));
        $this->assertSame(485, (int) $this->attendance($d2)->extra_shift_minutes); // 14:55 → 23:00
    }
    public function test_the_days_status_comes_from_the_normal_shift_alone(): void
    {
        [$main, $second] = [$this->shift('Main', '06:00', '14:00'), $this->shift('Second', '15:00', '23:00')];
        $base = Carbon::parse($this->day);

        // Late only for the extra shift: the day is not late.
        $d1 = $base->copy()->subDays(10)->toDateString();
        $this->onDay($main, $d1);
        $this->onDay($second, $d1, true);
        $this->punch('in', "$d1 05:58");
        $this->punch('out', "$d1 14:00");
        $this->punch('in', "$d1 15:40");   // 40 min late for the extra shift
        $this->punch('out', "$d1 23:00");
        $a = $this->attendance($d1);
        $this->assertSame(0, (int) $a->late_minutes);
        $this->assertSame('present', $a->attendance_status);

        // Only the additional shift worked: the day is measured against THAT shift.
        $d2 = $base->copy()->subDays(5)->toDateString();
        $this->onDay($main, $d2);
        $this->onDay($second, $d2, true);
        $this->punch('in', "$d2 15:20");   // 20 min late for the shift worked
        $this->punch('out', "$d2 23:00");
        $a = $this->attendance($d2);
        $this->assertSame($second->id, (int) $a->shift_id);
        $this->assertSame('15:00', substr((string) $a->scheduled_shift_start, 0, 5));
        $this->assertSame(20, (int) $a->late_minutes);
        $this->assertSame(0, (int) $a->extra_shift_minutes);

        // Short normal shift (4.5 h of 8) + full extra shift, Day Classification on (present ≥ 90 %,
        // half day ≥ 50 %): the normal shift alone decides → half day (not the 12.5 h total);
        // the extra shift is still fully extra.
        $d3 = $this->day;
        $resolver = app(\App\Services\Attendance\PolicyResolver::class);
        $resolver->forget();
        $policy = $resolver->forTenantDate($this->tenantId, $d3)->toPersistableArray();
        DB::table('attendance_policies')->where('tenant_id', $this->tenantId)->where('effective_from', $d3)->delete();
        DB::table('attendance_policies')->insert(array_merge($policy, ['day_classification_enabled' => true, 'present_ratio' => 0.9, 'half_day_ratio' => 0.5,
            'tenant_id' => $this->tenantId, 'effective_from' => $d3, 'created_at' => now(), 'updated_at' => now()]));
        $resolver->forget();
        try {
            $this->onDay($main, $d3);
            $this->onDay($second, $d3, true);
            $this->punch('in', "$d3 06:00");
            $this->punch('out', "$d3 10:30");
            $this->punch('in', "$d3 15:00");
            $this->punch('out', "$d3 23:00");
            $a = $this->attendance($d3);
            $this->assertSame(480, (int) $a->extra_shift_minutes);
            $this->assertSame('half_day', $a->effective_status);
            $this->assertEquals(0.5, (float) $a->day_fraction);
        } finally {
            DB::table('attendance_policies')->where('tenant_id', $this->tenantId)->where('effective_from', $d3)->delete();
            $resolver->forget();
        }
    }
    public function test_calendars_and_api_show_the_days_worth_per_company_policy(): void
    {
        $this->withoutMiddleware(\App\Http\Middleware\EnsureFeatureEnabled::class);
        [$main, $second] = [$this->shift('Main', '06:00', '14:00'), $this->shift('Second', '15:00', '23:00')];
        $d1 = Carbon::parse($this->day)->subDays(6)->toDateString();   // both shifts in full
        $d2 = Carbon::parse($this->day)->subDays(3)->toDateString();   // 2nd shift under half its hours
        foreach ([[$d1, '23:00'], [$d2, '18:00']] as [$d, $out]) {
            $this->onDay($main, $d);
            $this->onDay($second, $d, true);
            $this->punch('in', "$d 06:00");
            $this->punch('out', "$d 14:00");
            $this->punch('in', "$d 15:00");
            $this->punch('out', "$d $out");
        }
        $ids = [$this->attendance($d1)->id, $this->attendance($d2)->id];
        $mode = DB::table('overtime_settings')->where('tenant_id', $this->tenantId)->value('extra_shift_pay_mode');
        $policy = app(\App\Services\Attendance\OvertimePolicyService::class);
        $setMode = function (string $m) use ($policy) {
            DB::table('overtime_settings')->updateOrInsert(['tenant_id' => $this->tenantId], ['extra_shift_pay_mode' => $m, 'updated_at' => now()]);
            $policy->forget();
        };

        try {
            // Extra shifts paid as extra working days: "Present ×2" / "×1.5" everywhere.
            $setMode('extra_day');
            $days = app(\App\Services\Attendance\MultiShiftDay::class)->forAttendances($this->tenantId, $ids);
            $this->assertSame([1.0, 0.5], [$days[$ids[0]]['extra_days'], $days[$ids[1]]['extra_days']]);
            $this->assertSame([false, true], array_column($days[$ids[0]]['shifts'], 'is_extra'));

            $this->actingAs($this->admin)->withSession(['tenant_id' => $this->tenantId]);
            $member = app(\App\Services\Team\TeamMemberAttendance::class)->getAttendanceDataForUser($d1, $d2, $this->employee->id)->keyBy('date');
            $this->assertSame('Present ×2', $member[$d1]->day_label);
            $this->assertSame('Present ×1.5', $member[$d2]->day_label);

            Auth::login($this->employee);
            $history = collect(app(AttendanceController::class)->history(Request::create('/', 'GET', ['start_date' => $d1, 'end_date' => $d2]))->getData(true)['data'])->keyBy('date');
            $this->assertEquals([2.0, 1.5], [$history[$d1]['day_value'], $history[$d2]['day_value']]);

            $this->actingAs($this->employee)->withSession(['tenant_id' => $this->tenantId]);
            $calendar = collect($this->get(route('attendance.index', ['start_date' => $d1, 'end_date' => $d2]))->assertOk()->viewData('calendarData'))->keyBy('start');
            $this->assertSame(['Present ×2', 'Present ×1.5'], [$calendar[$d1]['title'], $calendar[$d2]['title']]);

            // Extra shifts paid as OT hours: the calendars / API show plain Present.
            $setMode('overtime');
            $this->assertSame(0.0, app(\App\Services\Attendance\MultiShiftDay::class)->forAttendances($this->tenantId, $ids)[$ids[0]]['extra_days']);
            $calendar = collect($this->get(route('attendance.index', ['start_date' => $d1, 'end_date' => $d2]))->assertOk()->viewData('calendarData'))->keyBy('start');
            $this->assertSame('Present', $calendar[$d1]['title']);
            Auth::login($this->employee);
            $history = collect(app(AttendanceController::class)->history(Request::create('/', 'GET', ['start_date' => $d1, 'end_date' => $d1]))->getData(true)['data']);
            $this->assertEquals(1.0, $history->first()['day_value']);
        } finally {
            DB::table('overtime_settings')->where('tenant_id', $this->tenantId)->update(['extra_shift_pay_mode' => $mode ?? 'overtime']);
            $policy->forget();
        }
    }
    public function test_recompute_command_rebuilds_old_days_with_the_new_rules(): void
    {
        $d = $this->day;
        $this->onDay($this->shift('Main', '06:00', '14:00'), $d);
        $this->onDay($this->shift('Second', '15:00', '23:00'), $d, true);
        $this->punch('in', "$d 15:00");
        $this->punch('out', "$d 23:00");
        $a = $this->attendance($d);
        // As the old rules saved it: the lone 2nd shift counted as extra (paid twice).
        DB::table('attendances')->where('id', $a->id)->update(['extra_shift_minutes' => 480]);
        DB::table('attendance_shift_segments')->where('attendance_id', $a->id)->update(['is_extra' => 1]);

        $this->artisan('attendance:recompute-shifts', ['--from' => $d, '--to' => $d, '--tenant' => $this->tenantId, '--dry-run' => true])
            ->expectsOutputToContain('Changed 1')->assertSuccessful();
        $this->assertSame(480, (int) $this->attendance($d)->extra_shift_minutes);   // dry run saved nothing

        $this->artisan('attendance:recompute-shifts', ['--from' => $d, '--to' => $d, '--tenant' => $this->tenantId])->assertSuccessful();
        $this->assertSame(0, (int) $this->attendance($d)->extra_shift_minutes);
        $this->assertSame(0, (int) AttendanceShiftSegment::withoutGlobalScopes()->where('attendance_id', $a->id)->value('is_extra'));
    }
}
