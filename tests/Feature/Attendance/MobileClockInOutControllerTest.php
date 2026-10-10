<?php

namespace Tests\Feature\Attendance;

use App\Http\Controllers\Api\Attendance\ClockController;
use App\Models\Attendance;
use App\Models\AttendancePunch;
use App\Models\AttendanceTrackingPoint;
use App\Models\AttendanceTrackingSession;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Tests\Concerns\RestoresAttendanceSummary;
use Tests\TestCase;

/**
 * Exercises the real mobile clockIn()/clockOut() controller methods end to
 * end (geofence skip, punch capture, response shape) — not just the service
 * layer. Runs against the shared dev DB (no RefreshDatabase); Carbon test-now
 * pins the clock to a fixed date far from real usage so this can never
 * collide with genuine attendance data, and everything created is cleaned up
 * in tearDown() regardless of pass/fail.
 */
class MobileClockInOutControllerTest extends TestCase
{
    use RestoresAttendanceSummary;

    private int $tenantId;
    private int $userId;
    private string $date = '2020-02-10';

    protected function setUp(): void
    {
        parent::setUp();

        // A 'field' job-detail user skips the geofence branch entirely,
        // keeping this test focused on the punch pipeline wiring.
        $jobDetail = \DB::table('user_job_details')->where('type', 'field')->first();
        $this->userId = (int) $jobDetail->user_id;
        $this->tenantId = (int) $jobDetail->tenant_id;

        app()->instance('current_tenant', Tenant::find($this->tenantId));
        Auth::login(User::find($this->userId));
        $this->snapshotSummary($this->tenantId, $this->userId, '2020-02');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        $this->restoreSummary();

        $sessionIds = AttendanceTrackingSession::withoutGlobalScopes()
            ->where('tenant_id', $this->tenantId)->where('user_id', $this->userId)->where('date', $this->date)
            ->pluck('id');
        AttendanceTrackingPoint::withoutGlobalScopes()->whereIn('session_id', $sessionIds)->delete();
        AttendanceTrackingSession::withoutGlobalScopes()->whereIn('id', $sessionIds)->delete();

        AttendancePunch::withoutGlobalScopes()
            ->where('tenant_id', $this->tenantId)->where('user_id', $this->userId)->where('date', $this->date)
            ->delete();
        Attendance::withoutGlobalScopes()
            ->where('tenant_id', $this->tenantId)->where('user_id', $this->userId)->where('date', $this->date)
            ->delete();
        // Carbon::setTestNow() pins created_at on every log this test writes
        // to $this->date's fake "now", so a real-wall-clock time filter
        // wouldn't match them — scope by that fake date instead (safe: no
        // genuine attendance_logs row for this fixed past-and-unused date
        // could ever exist).
        \DB::table('attendance_logs')
            ->where('user_id', $this->userId)
            ->whereIn('source', ['clock_in', 'clock_out'])
            ->whereBetween('created_at', [$this->date . ' 00:00:00', $this->date . ' 23:59:59'])
            ->delete();

        parent::tearDown();
    }

    public function test_full_clock_in_then_clock_out_cycle_via_the_real_controller(): void
    {
        $controller = app(ClockController::class);

        Carbon::setTestNow(Carbon::parse($this->date . ' 09:00:00'));
        $inResponse = $controller->clockIn(Request::create('/x', 'POST', [
            'lat' => 28.6, 'long' => 77.2, 'address' => 'Test field site address',
        ]));
        $inData = json_decode($inResponse->getContent(), true);
        $this->assertTrue($inData['status'] ?? false, json_encode($inData));

        $row = Attendance::where('user_id', $this->userId)->where('date', $this->date)->first();
        $this->assertNotNull($row);
        $this->assertNotNull($row->clock_in);
        $this->assertNull($row->clock_out);

        Carbon::setTestNow(Carbon::parse($this->date . ' 17:00:00'));
        $outResponse = $controller->clockOut(Request::create('/x', 'POST', [
            'lat' => 28.6, 'long' => 77.2, 'address' => 'Test field site address',
        ]));
        $outData = json_decode($outResponse->getContent(), true);
        $this->assertTrue($outData['status'] ?? false, json_encode($outData));
        $this->assertEqualsWithDelta(8.0, (float) $outData['data']['worked_hours'], 0.01);

        $row->refresh();
        $this->assertNotNull($row->clock_out);
        $this->assertEqualsWithDelta(8.0, (float) $row->worked_hours, 0.01);
        $this->assertSame(1, (int) $row->session_count);

        // A second clock-in on the same (default single-punch) day is
        // rejected with the original, unchanged message.
        $secondIn = $controller->clockIn(Request::create('/x', 'POST', [
            'lat' => 28.6, 'long' => 77.2, 'address' => 'Test field site address',
        ]));
        $secondInData = json_decode($secondIn->getContent(), true);
        $this->assertFalse($secondInData['status']);
        $this->assertStringContainsString('already', strtolower($secondInData['message']));
    }
}
