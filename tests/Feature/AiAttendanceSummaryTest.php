<?php

namespace Tests\Feature;

use App\Http\Controllers\AI\AttendanceController;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * /api/ai/attendance + /api/ai/attendance-summary: future days are never
 * reported as Absent, and the summary counts match the full endpoint.
 */
class AiAttendanceSummaryTest extends TestCase
{
    private User $employee;

    protected function setUp(): void
    {
        parent::setUp();
        $admin = User::withoutGlobalScopes()->where('role', 'admin')->where('status', 1)->whereNotNull('tenant_id')->orderBy('id')->firstOrFail();

        // Own scope keeps the rows to this one test user.
        $this->employee = User::withoutGlobalScopes()->forceCreate([
            'name' => 'PHPUnit AI Summary ' . uniqid(),
            'email' => 'aisum.' . uniqid() . '@phpunit.test',
            'employee_id' => 'AS' . random_int(100000, 999999),
            'password' => bcrypt('secret'),
            'role' => 'employee',
            'status' => 1,
            'tenant_id' => (int) $admin->tenant_id,
        ]);
        Auth::login($this->employee);
    }

    protected function tearDown(): void
    {
        Auth::logout();
        User::withoutGlobalScopes()->where('id', $this->employee->id)->forceDelete();
        parent::tearDown();
    }

    private function fetch(string $method, string $start, string $end): array
    {
        return app(AttendanceController::class)->{$method}(Request::create('/', 'GET', [
            'start_date' => $start,
            'end_date' => $end,
            'user_id' => $this->employee->id,
        ]))->getData(true);
    }

    public function test_range_stops_at_today(): void
    {
        $today = Carbon::today();
        $body = $this->fetch('view_ai_all', $today->copy()->subDays(2)->toDateString(), $today->copy()->addDays(10)->toDateString());

        $this->assertTrue($body['success']);
        $this->assertCount(3, $body['data']);
        $this->assertSame($today->toDateString(), $body['summary']['date_range']['end']);
        foreach ($body['data'] as $row) {
            $this->assertLessThanOrEqual($today->toDateString(), $row['date']);
        }
    }

    public function test_range_entirely_in_the_future_is_empty(): void
    {
        $start = Carbon::today()->addDays(3)->toDateString();
        $end = Carbon::today()->addDays(5)->toDateString();

        $body = $this->fetch('view_ai_all', $start, $end);
        $this->assertSame([], $body['data']);
        $this->assertSame(0, $body['summary']['total_records']);

        $summary = $this->fetch('view_ai_summary', $start, $end);
        $this->assertSame(0, $summary['summary']['by_status']['absent']);
    }

    public function test_summary_matches_the_full_endpoint_without_rows(): void
    {
        $start = Carbon::today()->subDays(6)->toDateString();
        $end = Carbon::today()->toDateString();

        $full = $this->fetch('view_ai_all', $start, $end);
        $summary = $this->fetch('view_ai_summary', $start, $end);

        $this->assertTrue($summary['success']);
        $this->assertArrayNotHasKey('data', $summary);
        $this->assertSame($full['summary'], $summary['summary']);
    }
}
