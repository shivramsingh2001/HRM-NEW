<?php

namespace Tests\Feature\Attendance;

use App\Models\Attendance;
use App\Models\AttendancePunch;
use App\Models\AttendanceTrackingPoint;
use App\Models\AttendanceTrackingSession;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Attendance\AttendancePunchService;
use App\Services\Attendance\AuditContext;
use App\Services\Attendance\PunchInput;
use App\Services\FieldTracking\TrackingPointIngestService;
use Carbon\Carbon;
use Tests\Concerns\RestoresAttendanceSummary;
use Tests\TestCase;

/**
 * The two-table GPS tracking redesign (attendance_tracking_sessions +
 * attendance_tracking_points) — session-per-punch-pair separation, retry-safe
 * point dedup, and the post-clock-out grace window. Exercises
 * AttendancePunchService/TrackingPointIngestService directly (no HTTP/JWT
 * layer), mirroring MultiPunchClockInOutTest's pattern. Runs against the
 * shared dev DB (no RefreshDatabase) — everything this test creates is
 * cleaned up in tearDown() regardless of pass/fail.
 *
 * TrackingPointIngestService resolves a point's session by comparing real
 * now() against the session's ended_at (the post-clock-out grace window), so
 * tests freeze now() with Carbon::setTestNow() around each ingestBatch() call
 * rather than relying on wall-clock proximity to backdated punch times.
 */
class AttendanceTrackingSessionsTest extends TestCase
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
        $this->date = '2020-02-10';

        app()->instance('current_tenant', Tenant::find($this->tenantId));
        Tenant::whereKey($this->tenantId)->update(['allow_multiple_punches' => true]);
        $this->snapshotSummary($this->tenantId, $this->userId, '2020-02');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow(null);
        $this->restoreSummary();

        $sessionIds = AttendanceTrackingSession::withoutGlobalScopes()
            ->where('tenant_id', $this->tenantId)->where('user_id', $this->userId)
            ->where('date', $this->date)->pluck('id');

        AttendanceTrackingPoint::withoutGlobalScopes()->whereIn('session_id', $sessionIds)->delete();
        AttendanceTrackingSession::withoutGlobalScopes()->whereIn('id', $sessionIds)->delete();

        AttendancePunch::withoutGlobalScopes()
            ->where('tenant_id', $this->tenantId)->where('user_id', $this->userId)
            ->where('date', $this->date)->delete();

        Attendance::withoutGlobalScopes()
            ->where('tenant_id', $this->tenantId)->where('user_id', $this->userId)
            ->where('date', $this->date)->delete();

        Tenant::whereKey($this->tenantId)->update(['allow_multiple_punches' => $this->originalFlag]);

        \DB::table('attendance_logs')
            ->where('user_id', $this->userId)
            ->whereIn('source', ['clock_in', 'clock_out', 'manual'])
            ->where('created_at', '>=', $this->testStartedAt)
            ->delete();

        parent::tearDown();
    }

    private function punch(string $direction, string $time): AttendancePunch
    {
        return app(AttendancePunchService::class)->capture(new PunchInput(
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
        ));
    }

    private function sessionsForDate(): \Illuminate\Support\Collection
    {
        return AttendanceTrackingSession::withoutGlobalScopes()
            ->where('tenant_id', $this->tenantId)->where('user_id', $this->userId)
            ->where('date', $this->date)->orderBy('session_seq')->get();
    }

    public function test_session_opens_on_clock_in_and_closes_on_clock_out(): void
    {
        $in = $this->punch('in', '09:00:00');
        $sessions = $this->sessionsForDate();
        $this->assertCount(1, $sessions);
        $this->assertSame('open', $sessions[0]->status);
        $this->assertSame($in->id, $sessions[0]->punch_in_id);
        $this->assertSame(1, (int) $sessions[0]->session_seq);
        $this->assertNull($sessions[0]->punch_out_id);

        $out = $this->punch('out', '13:00:00');
        $sessions = $this->sessionsForDate();
        $this->assertCount(1, $sessions);
        $this->assertSame('closed', $sessions[0]->status);
        $this->assertSame('clock_out', $sessions[0]->close_reason);
        $this->assertSame($out->id, $sessions[0]->punch_out_id);
        $this->assertSame('2020-02-10 13:00:00', $sessions[0]->ended_at->format('Y-m-d H:i:s'));

        // Backfilled by AttendanceRollupService::recompute() after the day's
        // attendances row is written.
        $this->assertNotNull($sessions[0]->attendance_id);
    }

    /**
     * The core redesign goal: a multi-punch day's two clock-in/out cycles
     * produce two separate session rows, each with its own breadcrumb trail
     * — today's attendance_id-keyed attendance_tracks table could not tell
     * these apart.
     */
    public function test_multi_session_per_day_separates_breadcrumbs(): void
    {
        $this->punch('in', '09:00:00');
        $this->punch('out', '13:00:00');
        $this->punch('in', '14:00:00');
        $this->punch('out', '18:00:00');

        $sessions = $this->sessionsForDate();
        $this->assertCount(2, $sessions);
        $this->assertSame(1, (int) $sessions[0]->session_seq);
        $this->assertSame(2, (int) $sessions[1]->session_seq);
        $this->assertNotSame($sessions[0]->punch_in_id, $sessions[1]->punch_in_id);

        $ingest = app(TrackingPointIngestService::class);

        // A point during session 1, ingested shortly after session 1 closed
        // (within the grace window of "now").
        Carbon::setTestNow(Carbon::parse($this->date . ' 13:05:00'));
        $r1 = $ingest->ingestBatch($this->tenantId, $this->userId, [[
            'point_id' => 'pt-s1-a', 'lat' => 28.61, 'long' => 77.21,
            'track_time' => $this->date . ' 12:30:00',
        ]]);
        $this->assertSame(1, $r1['saved']);
        $this->assertSame($sessions[0]->id, $r1['session_id']);

        // A point claiming to be after session 1's clock-out — rejected even
        // though still inside the grace window.
        $r2 = $ingest->ingestBatch($this->tenantId, $this->userId, [[
            'point_id' => 'pt-s1-late', 'lat' => 28.61, 'long' => 77.21,
            'track_time' => $this->date . ' 13:10:00',
        ]]);
        $this->assertSame(0, $r2['saved']);
        $this->assertSame(1, $r2['rejected']);

        // A point during session 2, ingested while session 2 is still open.
        Carbon::setTestNow(Carbon::parse($this->date . ' 14:30:00'));
        $r3 = $ingest->ingestBatch($this->tenantId, $this->userId, [[
            'point_id' => 'pt-s2-a', 'lat' => 28.62, 'long' => 77.22,
            'track_time' => $this->date . ' 14:15:00',
        ]]);
        $this->assertSame(1, $r3['saved']);
        $this->assertSame($sessions[1]->id, $r3['session_id']);

        $points1 = AttendanceTrackingPoint::withoutGlobalScopes()->where('session_id', $sessions[0]->id)->get();
        $points2 = AttendanceTrackingPoint::withoutGlobalScopes()->where('session_id', $sessions[1]->id)->get();

        $this->assertCount(1, $points1);
        $this->assertSame('pt-s1-a', $points1[0]->point_id);
        $this->assertCount(1, $points2);
        $this->assertSame('pt-s2-a', $points2[0]->point_id);

        $s1 = $sessions[0]->fresh();
        $this->assertSame(1, (int) $s1->point_count);
    }

    public function test_duplicate_point_retry_is_idempotent(): void
    {
        $this->punch('in', '09:00:00');
        $ingest = app(TrackingPointIngestService::class);
        Carbon::setTestNow(Carbon::parse($this->date . ' 09:05:00'));

        $payload = [[
            'point_id' => 'pt-retry-1', 'lat' => 28.6, 'long' => 77.2,
            'track_time' => $this->date . ' 09:02:00',
        ]];

        $first = $ingest->ingestBatch($this->tenantId, $this->userId, $payload);
        $this->assertSame(1, $first['saved']);
        $this->assertSame(0, $first['duplicates']);

        $second = $ingest->ingestBatch($this->tenantId, $this->userId, $payload);
        $this->assertSame(0, $second['saved']);
        $this->assertSame(1, $second['duplicates']);

        $sessionId = $this->sessionsForDate()->first()->id;
        $count = AttendanceTrackingPoint::withoutGlobalScopes()->where('session_id', $sessionId)->count();
        $this->assertSame(1, $count);
    }

    public function test_missing_point_id_is_derived_server_side_and_retry_is_idempotent(): void
    {
        $this->punch('in', '09:00:00');
        $ingest = app(TrackingPointIngestService::class);
        Carbon::setTestNow(Carbon::parse($this->date . ' 09:05:00'));

        $first = $ingest->ingestBatch($this->tenantId, $this->userId, [
            ['lat' => 28.6, 'long' => 77.2, 'track_time' => $this->date . ' 09:01:00'],
            ['point_id' => '', 'lat' => 28.61, 'long' => 77.21, 'track_time' => $this->date . ' 09:02:00'],
        ]);
        $this->assertSame(2, $first['saved']);

        // Same readings re-sent, one with its time as epoch ms and coords formatted differently.
        $second = $ingest->ingestBatch($this->tenantId, $this->userId, [
            ['lat' => '28.6000000', 'long' => 77.2, 'track_time' => Carbon::parse($this->date . ' 09:01:00')->getTimestampMs()],
            ['lat' => 28.61, 'long' => 77.21, 'track_time' => $this->date . ' 09:02:00'],
        ]);
        $this->assertSame(0, $second['saved']);
        $this->assertSame(2, $second['duplicates']);

        $sessionId = $this->sessionsForDate()->first()->id;
        $ids = AttendanceTrackingPoint::withoutGlobalScopes()->where('session_id', $sessionId)->pluck('point_id');
        $this->assertCount(2, $ids);
        $ids->each(fn ($id) => $this->assertMatchesRegularExpression('/^[0-9a-f]{40}$/', $id));
    }

    public function test_partial_duplicate_batch_only_inserts_new_points(): void
    {
        $this->punch('in', '09:00:00');
        $ingest = app(TrackingPointIngestService::class);
        Carbon::setTestNow(Carbon::parse($this->date . ' 09:10:00'));

        $ingest->ingestBatch($this->tenantId, $this->userId, [[
            'point_id' => 'pt-a', 'lat' => 28.6, 'long' => 77.2, 'track_time' => $this->date . ' 09:01:00',
        ]]);

        $result = $ingest->ingestBatch($this->tenantId, $this->userId, [
            ['point_id' => 'pt-a', 'lat' => 28.6, 'long' => 77.2, 'track_time' => $this->date . ' 09:01:00'],
            ['point_id' => 'pt-b', 'lat' => 28.61, 'long' => 77.21, 'track_time' => $this->date . ' 09:02:00'],
        ]);

        $this->assertSame(1, $result['saved']);
        $this->assertSame(1, $result['duplicates']);

        $sessionId = $this->sessionsForDate()->first()->id;
        $count = AttendanceTrackingPoint::withoutGlobalScopes()->where('session_id', $sessionId)->count();
        $this->assertSame(2, $count);
    }

    public function test_batch_with_no_open_or_recent_session_is_rejected(): void
    {
        Carbon::setTestNow(Carbon::parse($this->date . ' 09:00:00'));
        $ingest = app(TrackingPointIngestService::class);

        $result = $ingest->ingestBatch($this->tenantId, $this->userId, [[
            'point_id' => 'pt-none', 'lat' => 28.6, 'long' => 77.2, 'track_time' => $this->date . ' 08:55:00',
        ]]);

        $this->assertTrue($result['no_session']);
        $this->assertSame(0, $result['saved']);
        $this->assertSame(1, $result['rejected']);
    }

    public function test_batch_past_the_grace_window_after_clock_out_is_rejected(): void
    {
        $this->punch('in', '09:00:00');
        $this->punch('out', '13:00:00');

        $graceMinutes = (int) config('location.late_point_grace_minutes', 15);
        Carbon::setTestNow(Carbon::parse($this->date . ' 13:00:00')->addMinutes($graceMinutes + 5));

        $ingest = app(TrackingPointIngestService::class);
        $result = $ingest->ingestBatch($this->tenantId, $this->userId, [[
            'point_id' => 'pt-too-late', 'lat' => 28.6, 'long' => 77.2, 'track_time' => $this->date . ' 12:30:00',
        ]]);

        $this->assertTrue($result['no_session']);
        $this->assertSame(0, $result['saved']);
    }
}
