<?php

namespace Tests\Feature\Attendance;

use App\Exceptions\NoOpenPunchSessionException;
use App\Exceptions\OpenPunchSessionException;
use App\Models\Attendance;
use App\Models\AttendancePunch;
use App\Models\AttendanceTrackingPoint;
use App\Models\AttendanceTrackingSession;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Attendance\AttendancePunchService;
use App\Services\Attendance\AuditContext;
use App\Services\Attendance\PunchInput;
use Carbon\Carbon;
use Tests\Concerns\RestoresAttendanceSummary;
use Tests\TestCase;

/**
 * Exercises AttendancePunchService directly (no HTTP/JWT layer, mirroring
 * AttendanceLocationTest's pattern). Runs against the shared dev DB (no
 * RefreshDatabase) — every row this test creates, and the tenant flag it
 * flips, is cleaned up / restored in tearDown() regardless of pass/fail.
 */
class MultiPunchClockInOutTest extends TestCase
{
    use RestoresAttendanceSummary;

    private int $tenantId;
    private int $userId;
    private ?bool $originalFlag;
    private string $date;
    private $testStartedAt;

    protected function setUp(): void
    {
        parent::setUp();

        // attendance_logs.created_at is a second-precision `timestamp`
        // column; a 2s buffer avoids a same-second row falling just before
        // the microsecond-precise `now()` captured here.
        $this->testStartedAt = now()->subSeconds(2);
        $this->tenantId = (int) \DB::table('users')
            ->whereNotNull('tenant_id')
            ->select('tenant_id')
            ->groupBy('tenant_id')
            ->havingRaw('count(*) >= 1')
            ->orderByRaw('count(*) desc')
            ->value('tenant_id');

        $this->userId = (int) User::where('tenant_id', $this->tenantId)->value('id');
        $this->originalFlag = Tenant::find($this->tenantId)->allow_multiple_punches;

        // A date far in the past with no real attendance history, so this
        // test can never collide with genuine data.
        $this->date = '2020-01-06';

        app()->instance('current_tenant', Tenant::find($this->tenantId));
        $this->snapshotSummary($this->tenantId, $this->userId, '2020-01');
    }

    protected function tearDown(): void
    {
        $this->restoreSummary();

        // AttendancePunchService::capture() opens an attendance_tracking_sessions
        // row on every direction='in' punch (see TrackingSessionService) — a
        // test that deliberately leaves a punch open (to assert an exception
        // on the next capture) leaves one of these behind too.
        $sessionIds = AttendanceTrackingSession::withoutGlobalScopes()
            ->where('tenant_id', $this->tenantId)
            ->where('user_id', $this->userId)
            ->where('date', $this->date)
            ->pluck('id');
        AttendanceTrackingPoint::withoutGlobalScopes()->whereIn('session_id', $sessionIds)->delete();
        AttendanceTrackingSession::withoutGlobalScopes()->whereIn('id', $sessionIds)->delete();

        AttendancePunch::withoutGlobalScopes()
            ->where('tenant_id', $this->tenantId)
            ->where('user_id', $this->userId)
            ->where('date', $this->date)
            ->delete();

        Attendance::withoutGlobalScopes()
            ->where('tenant_id', $this->tenantId)
            ->where('user_id', $this->userId)
            ->where('date', $this->date)
            ->delete();

        Tenant::whereKey($this->tenantId)->update(['allow_multiple_punches' => $this->originalFlag]);

        \DB::table('attendance_logs')
            ->where('user_id', $this->userId)
            ->whereIn('source', ['clock_in', 'clock_out', 'manual'])
            ->where('created_at', '>=', $this->testStartedAt)
            ->delete();

        parent::tearDown();
    }

    private function punch(string $direction, string $time): PunchInput
    {
        return new PunchInput(
            userId: $this->userId,
            tenantId: $this->tenantId,
            direction: $direction,
            punchedAt: Carbon::parse($this->date . ' ' . $time),
            source: 'mobile_app',
            method: 'gps',
            lat: 28.6,
            long: 77.2,
            address: 'Test address',
            audit: new AuditContext(actorId: $this->userId, source: $direction === 'in' ? 'clock_in' : 'clock_out'),
        );
    }

    public function test_single_punch_tenant_rejects_a_second_clock_in_same_day(): void
    {
        Tenant::whereKey($this->tenantId)->update(['allow_multiple_punches' => false]);
        $service = app(AttendancePunchService::class);

        $service->capture($this->punch('in', '09:00:00'));
        $service->capture($this->punch('out', '17:00:00'));

        $this->expectException(OpenPunchSessionException::class);
        $service->capture($this->punch('in', '18:00:00'));
    }

    public function test_single_punch_tenant_clock_out_without_open_session_is_rejected(): void
    {
        Tenant::whereKey($this->tenantId)->update(['allow_multiple_punches' => false]);
        $service = app(AttendancePunchService::class);

        $this->expectException(NoOpenPunchSessionException::class);
        $service->capture($this->punch('out', '17:00:00'));
    }

    public function test_multi_punch_tenant_allows_a_second_session_after_clock_out(): void
    {
        Tenant::whereKey($this->tenantId)->update(['allow_multiple_punches' => true]);
        $service = app(AttendancePunchService::class);

        $service->capture($this->punch('in', '09:00:00'));
        $service->capture($this->punch('out', '13:00:00'));

        // clock_out should now be set (day closed, no open session).
        $row = Attendance::withoutGlobalScopes()
            ->where('tenant_id', $this->tenantId)->where('user_id', $this->userId)->where('date', $this->date)
            ->first();
        $this->assertNotNull($row);
        $this->assertNotNull($row->clock_out);
        $this->assertEqualsWithDelta(4.0, (float) $row->worked_hours, 0.01);

        // Clock back in for a second session — must succeed under =1.
        $service->capture($this->punch('in', '14:00:00'));

        $row->refresh();
        // clock_out reverts to null while the new session is open, even
        // though a session already closed earlier today.
        $this->assertNull($row->clock_out);
        $this->assertSame('2020-01-06 09:00:00', $row->clock_in);

        $service->capture($this->punch('out', '18:00:00'));
        $row->refresh();

        $this->assertNotNull($row->clock_out);
        $this->assertEqualsWithDelta(8.0, (float) $row->worked_hours, 0.01);
        $this->assertSame(2, (int) $row->session_count);

        $punchCount = AttendancePunch::withoutGlobalScopes()
            ->where('tenant_id', $this->tenantId)->where('user_id', $this->userId)->where('date', $this->date)
            ->count();
        $this->assertSame(4, $punchCount);
    }

    public function test_multi_punch_tenant_still_blocks_a_second_open_session(): void
    {
        Tenant::whereKey($this->tenantId)->update(['allow_multiple_punches' => true]);
        $service = app(AttendancePunchService::class);

        $service->capture($this->punch('in', '09:00:00'));

        $this->expectException(OpenPunchSessionException::class);
        $service->capture($this->punch('in', '10:00:00'));
    }

    public function test_client_ref_retry_is_idempotent(): void
    {
        Tenant::whereKey($this->tenantId)->update(['allow_multiple_punches' => false]);
        $service = app(AttendancePunchService::class);

        $ref = 'test-ref-' . uniqid();
        $input = new PunchInput(
            userId: $this->userId,
            tenantId: $this->tenantId,
            direction: 'in',
            punchedAt: Carbon::parse($this->date . ' 09:00:00'),
            source: 'mobile_app',
            clientRef: $ref,
        );

        $first = $service->capture($input);
        $second = $service->capture($input);

        $this->assertSame($first->id, $second->id);

        $count = AttendancePunch::withoutGlobalScopes()
            ->where('tenant_id', $this->tenantId)->where('user_id', $this->userId)
            ->where('client_ref', $ref)->count();
        $this->assertSame(1, $count);
    }

    /**
     * Under the pre-punch architecture, ANY existing `attendances` row for
     * (tenant,user,date) unconditionally blocked a new clock-in (the unique
     * key caught it) — regardless of allow_multiple_punches. A manual mark
     * or an applied regularization must keep that protection: a day an
     * admin already decided is never silently overwritten by a later raw
     * punch from any source.
     */
    public function test_a_manually_marked_day_blocks_a_fresh_clock_in_and_is_not_overwritten(): void
    {
        Tenant::whereKey($this->tenantId)->update(['allow_multiple_punches' => true]);
        $actor = User::where('tenant_id', $this->tenantId)->where('role', 'admin')->first();

        $result = app(\App\Services\Attendance\AttendanceEntryService::class)->markStatus([
            'user_id' => $this->userId,
            'tenant_id' => $this->tenantId,
            'date' => $this->date,
            'status' => \App\Enums\AttendanceStatus::Absent,
            'remarks' => 'test manual mark',
        ], $actor);
        $this->assertSame('absent', $result['attendance']->attendance_status);

        $this->expectException(OpenPunchSessionException::class);
        try {
            app(AttendancePunchService::class)->capture($this->punch('in', '09:00:00'));
        } finally {
            $row = Attendance::withoutGlobalScopes()
                ->where('tenant_id', $this->tenantId)->where('user_id', $this->userId)->where('date', $this->date)
                ->first();
            $this->assertSame('absent', $row->attendance_status);
            $this->assertSame('manual', $row->attendance_type);
        }
    }

    /**
     * Mark Attendance → "Clock in only": the admin sets the clock-in for an
     * employee who forgot, the day stays open, and the employee's own
     * clock-out closes it as usual.
     */
    public function test_a_clock_in_only_mark_leaves_the_day_open_for_the_employees_clock_out(): void
    {
        Tenant::whereKey($this->tenantId)->update(['allow_multiple_punches' => false]);
        $actor = User::where('tenant_id', $this->tenantId)->where('role', 'admin')->first();
        $entry = app(\App\Services\Attendance\AttendanceEntryService::class);

        // The day was first marked absent, then corrected to "checked in at 09:30".
        $entry->markStatus([
            'user_id' => $this->userId, 'tenant_id' => $this->tenantId, 'date' => $this->date,
            'status' => \App\Enums\AttendanceStatus::Absent,
        ], $actor);

        $result = $entry->markStatus([
            'user_id' => $this->userId, 'tenant_id' => $this->tenantId, 'date' => $this->date,
            'status' => \App\Enums\AttendanceStatus::Present,
            'clock_in' => '09:30', 'clock_out' => null, 'clock_in_only' => true,
            'remarks' => 'forgot to clock in',
        ], $actor);

        $row = $result['attendance'];
        $this->assertSame($this->date . ' 09:30:00', (string) $row->clock_in);
        $this->assertNull($row->clock_out);
        $this->assertContains($row->attendance_status, ['present', 'late']);
        $this->assertSame('manual', $row->attendance_type);

        $punches = AttendancePunch::withoutGlobalScopes()
            ->where('tenant_id', $this->tenantId)->where('user_id', $this->userId)
            ->where('date', $this->date)->where('status', 'active')->get();
        $this->assertCount(1, $punches);
        $this->assertSame('in', $punches[0]->direction);
        $this->assertSame('manual', $punches[0]->source);

        // The employee clocks out from the app.
        app(AttendancePunchService::class)->capture($this->punch('out', '18:00:00'));

        $row = Attendance::withoutGlobalScopes()
            ->where('tenant_id', $this->tenantId)->where('user_id', $this->userId)->where('date', $this->date)
            ->first();
        $this->assertSame($this->date . ' 09:30:00', (string) $row->clock_in);
        $this->assertSame($this->date . ' 18:00:00', (string) $row->clock_out);
        $this->assertEqualsWithDelta(8.5, (float) $row->worked_hours, 0.01);
    }

    /** Without the opt-in flag (public API), a present mark with no clock-out behaves as before. */
    public function test_present_without_clock_out_and_without_the_flag_creates_no_punch(): void
    {
        $actor = User::where('tenant_id', $this->tenantId)->where('role', 'admin')->first();

        $result = app(\App\Services\Attendance\AttendanceEntryService::class)->markStatus([
            'user_id' => $this->userId, 'tenant_id' => $this->tenantId, 'date' => $this->date,
            'status' => \App\Enums\AttendanceStatus::Present, 'clock_in' => '09:30',
        ], $actor);

        $this->assertNull($result['attendance']->clock_in);
        $this->assertSame(0, AttendancePunch::withoutGlobalScopes()
            ->where('tenant_id', $this->tenantId)->where('user_id', $this->userId)
            ->where('date', $this->date)->where('status', 'active')->count());
    }
}
