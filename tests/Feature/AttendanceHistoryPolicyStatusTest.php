<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\Attendance\AttendanceController;
use App\Models\User;
use App\Services\Attendance\AttendancePolicySnapshot;
use App\Services\Attendance\LatePolicyService;
use App\Services\Attendance\PolicyResolver;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * GET /api/user/attendance/history — a clocked-out day reads the status the
 * attendance policy graded it (effective_status), not a separate hours rule.
 *
 * Before: a late-allowance half day (in 10:47, out 19:04 on a 10–19 shift)
 * came back "Present" / day_value 1 because the SQL only compared worked hours
 * to the shift length (≥ 60% = Present). The response keys must not change —
 * the mobile app in production reads them.
 */
class AttendanceHistoryPolicyStatusTest extends TestCase
{
    /** The history row keys the production app already reads — exactly these, no more, no less. */
    private const KEYS = ['user_id', 'name', 'email', 'date', 'day_name', 'is_future_date', 'task_count', 'attendance_id',
        'clock_in', 'clock_out', 'total_hours', 'worked_hours', 'scheduled_shift_start', 'scheduled_shift_end', 'leave_type',
        'leave_reason', 'leave_session', 'holiday_name', 'message', 'week_off', 'day_status', 'day_value'];

    private User $employee;
    private int $tenantId;
    private string $month;

    protected function setUp(): void
    {
        parent::setUp();
        $admin = User::withoutGlobalScopes()->where('role', 'admin')->where('status', 1)->whereNotNull('tenant_id')->orderBy('id')->firstOrFail();
        $this->tenantId = (int) $admin->tenant_id;
        $this->month = Carbon::today()->subMonths(3)->format('Y-m');
        $this->employee = User::withoutGlobalScopes()->forceCreate([
            'name' => 'PHPUnit HistPolicy ' . uniqid(), 'email' => 'histpol.' . uniqid() . '@phpunit.test',
            'employee_id' => 'HP' . random_int(100000, 999999), 'password' => bcrypt('secret'),
            'role' => 'employee', 'status' => 1, 'tenant_id' => $this->tenantId,
        ]);
    }

    protected function tearDown(): void
    {
        DB::table('attendances')->where('user_id', $this->employee->id)->delete();
        User::withoutGlobalScopes()->where('id', $this->employee->id)->forceDelete();
        Auth::logout();
        parent::tearDown();
    }

    /** One clocked-out day on a 10:00–19:00 shift. */
    private function day(int $dom, string $in, string $out, array $extra = []): string
    {
        $date = "{$this->month}-" . str_pad((string) $dom, 2, '0', STR_PAD_LEFT);
        $ci = Carbon::parse("$date $in");
        $co = Carbon::parse("$date $out");
        $secs = $co->getTimestamp() - $ci->getTimestamp();
        DB::table('attendances')->insert($extra + [
            'tenant_id' => $this->tenantId, 'user_id' => $this->employee->id, 'date' => $date,
            'clock_in' => $ci, 'clock_out' => $co, 'scheduled_shift_start' => '10:00:00', 'scheduled_shift_end' => '19:00:00',
            'late_minutes' => max(0, (int) Carbon::parse("$date 10:00")->diffInMinutes($ci, false)),
            'early_departure_minutes' => max(0, (int) $co->diffInMinutes(Carbon::parse("$date 19:00"), false)),
            'worked_hours' => round($secs / 3600, 2), 'total_hours' => gmdate('H:i:s', $secs),
            'attendance_status' => 'present', 'attendance_type' => 'app', 'is_regularized' => 0, 'status' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return $date;
    }

    /** Grade the month with $policy (the real LatePolicyService), then read the history API. */
    private function gradeAndRead(array $policy): \Illuminate\Support\Collection
    {
        $snapshot = AttendancePolicySnapshot::default()->withOverrides($policy + ['grace_mode' => 'fixed', 'fixed_grace_minutes' => 10]);
        $this->mock(PolicyResolver::class, function ($m) use ($snapshot) {
            $m->shouldReceive('forUserDate')->andReturn($snapshot);
            $m->shouldReceive('forUserMonth')->andReturn($snapshot);
        });
        app(LatePolicyService::class)->recalculateMonth($this->employee->id, $this->tenantId, $this->month);

        Auth::login($this->employee);
        $rows = collect(app(AttendanceController::class)->history(Request::create('/', 'GET', [
            'start_date' => "{$this->month}-01", 'end_date' => "{$this->month}-12",
        ]))->getData(true)['data']);

        foreach ($rows as $row) {
            $this->assertSame(self::KEYS, array_keys($row), 'history keys must stay exactly as in production');
        }

        return $rows->keyBy('date');
    }

    private function assertDay($rows, string $date, string $status, float $value, string $why): void
    {
        $this->assertSame($status, $rows[$date]['day_status'], "$why: day_status");
        $this->assertEquals($value, $rows[$date]['day_value'], "$why: day_value");
    }

    public function test_late_allowance_half_day_reads_half_day_not_present(): void
    {
        $onTime = $this->day(2, '10:00', '19:00');
        $late1 = $this->day(3, '10:47', '19:04');   // the VPS case (user 155, 16 Oct): 92% worked, 47 min late
        $late2 = $this->day(4, '10:30', '19:00');

        $rows = $this->gradeAndRead(['late_attendance_action' => 'half_day', 'monthly_late_allowance' => 0]);

        $this->assertDay($rows, $onTime, 'Present', 1.0, 'on time');
        $this->assertDay($rows, $late1, 'Half Day', 0.5, 'late #1 over an allowance of 0');
        $this->assertDay($rows, $late2, 'Half Day', 0.5, 'late #2 over an allowance of 0');
    }

    public function test_late_allowance_absent_after_the_allowed_count(): void
    {
        $late1 = $this->day(2, '10:47', '19:04');
        $late2 = $this->day(3, '10:40', '19:00');

        $rows = $this->gradeAndRead(['late_attendance_action' => 'absent', 'monthly_late_allowance' => 1]);

        $this->assertDay($rows, $late1, 'Present', 1.0, 'late #1 within allowance');
        $this->assertDay($rows, $late2, 'Absent', 0.0, 'late #2 over allowance → absent');
    }

    public function test_keep_original_attendance_leaves_late_days_present(): void
    {
        $late = $this->day(2, '10:47', '19:04');

        $rows = $this->gradeAndRead(['late_attendance_action' => 'none', 'monthly_late_allowance' => 0]);

        $this->assertDay($rows, $late, 'Present', 1.0, 'late action none');
    }

    public function test_early_leaving_allowance_half_day(): void
    {
        $early = $this->day(2, '10:00', '18:20');   // 40 min early, 92.6% worked

        $rows = $this->gradeAndRead(['early_attendance_action' => 'half_day', 'monthly_early_allowance' => 0]);

        $this->assertDay($rows, $early, 'Half Day', 0.5, 'early-leaving over allowance');
    }

    public function test_present_ratio_and_day_classification_off(): void
    {
        $short = $this->day(2, '10:00', '17:00');   // 7 of 9 h = 78%: under the 90% present ratio (old SQL: ≥ 60% = Present)
        $veryShort = $this->day(3, '10:00', '13:00'); // 3 of 9 h = 33% (old SQL: Half Day)

        $rows = $this->gradeAndRead([]);
        $this->assertDay($rows, $short, 'Half Day', 0.5, 'present ratio 90%');
        $this->assertDay($rows, $veryShort, 'Absent', 0.0, 'under the 50% half-day ratio');

        $rows = $this->gradeAndRead(['day_classification_enabled' => false]);
        $this->assertDay($rows, $short, 'Present', 1.0, 'day classification off');
        $this->assertDay($rows, $veryShort, 'Present', 1.0, 'day classification off');
    }

    public function test_regularized_and_hand_marked_days_are_not_downgraded(): void
    {
        $regularized = $this->day(2, '10:47', '19:04', ['is_regularized' => 1]);
        $manualPresent = $this->day(3, '10:47', '19:04', ['attendance_type' => 'manual', 'marked_by' => $this->employee->id]);
        $manualHalf = $this->day(4, '10:00', '19:00', ['attendance_type' => 'manual', 'marked_by' => $this->employee->id, 'attendance_status' => 'half_day']);

        $rows = $this->gradeAndRead(['late_attendance_action' => 'half_day', 'monthly_late_allowance' => 0]);

        $this->assertDay($rows, $regularized, 'Present', 1.0, 'regularized late day');
        $this->assertDay($rows, $manualPresent, 'Present', 1.0, 'hand-marked present');
        $this->assertDay($rows, $manualHalf, 'Half Day', 0.5, 'hand-marked half day');
    }

    public function test_a_day_not_graded_yet_keeps_the_hours_rule(): void
    {
        $legacy = $this->day(2, '10:00', '17:00', ['effective_status' => null]);

        Auth::login($this->employee);
        $row = collect(app(AttendanceController::class)->history(Request::create('/', 'GET', [
            'start_date' => $legacy, 'end_date' => $legacy,
        ]))->getData(true)['data'])->first();

        $this->assertSame(self::KEYS, array_keys($row));
        $this->assertSame('Present', $row['day_status'], 'no effective_status → the old ≥ 60% hours rule');
    }
}
