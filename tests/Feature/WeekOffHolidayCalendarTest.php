<?php

namespace Tests\Feature;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * /attendance calendar, /team/member-detail calendar and the day report:
 *  - an inactive holiday (Holidays page → status off) is NOT a holiday
 *  - a weekly off with no dates (Employee 360 / Fixed shift) is a week off every week
 *  - a Flexible weekly off that already ended is NOT a week off any more
 */
class WeekOffHolidayCalendarTest extends TestCase
{
    private User $admin;
    private User $employee;
    private int $tenantId;
    private string $tag;
    private Carbon $monday;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tag = 'PHPUnit WOH ' . uniqid();
        $this->admin = User::withoutGlobalScopes()->where('role', 'admin')->where('status', 1)->whereNotNull('tenant_id')->orderBy('id')->firstOrFail();
        $this->tenantId = (int) $this->admin->tenant_id;
        $this->employee = User::withoutGlobalScopes()->forceCreate([
            'name' => $this->tag, 'email' => 'woh.' . uniqid() . '@phpunit.test', 'employee_id' => 'WH' . random_int(100000, 999999),
            'password' => bcrypt('secret'), 'role' => 'employee', 'status' => 1, 'tenant_id' => $this->tenantId,
        ]);
        $this->monday = Carbon::today()->subWeeks(3)->startOfWeek();
        $uid = $this->employee->id;
        $day = fn (int $n) => $this->monday->copy()->addDays($n)->toDateString();

        // Saturday: weekly off with no dates (applies every week).
        // Sunday:   Flexible weekly off that ended before this week (must not apply).
        DB::table('user_weekoffs')->insert([
            ['tenant_id' => $this->tenantId, 'user_id' => $uid, 'off_type' => 'day_based', 'day_name' => 'Saturday',
                'start_date' => null, 'end_date' => null, 'created_by' => $uid, 'status' => 1, 'created_at' => now()],
            ['tenant_id' => $this->tenantId, 'user_id' => $uid, 'off_type' => 'day_based', 'day_name' => 'Sunday',
                'start_date' => $this->monday->copy()->subMonth()->toDateString(), 'end_date' => $day(-1), 'created_by' => $uid, 'status' => 1, 'created_at' => now()],
        ]);
        // Wednesday: inactive holiday. Thursday: active holiday.
        DB::table('holidays')->insert([
            ['tenant_id' => $this->tenantId, 'name' => $this->tag . ' Off', 'start_date' => $day(2), 'end_date' => $day(2), 'status' => 0, 'created_at' => now()],
            ['tenant_id' => $this->tenantId, 'name' => $this->tag . ' On', 'start_date' => $day(3), 'end_date' => $day(3), 'status' => 1, 'created_at' => now()],
        ]);
    }

    protected function tearDown(): void
    {
        DB::table('user_weekoffs')->where('user_id', $this->employee->id)->delete();
        DB::table('holidays')->where('name', 'like', $this->tag . '%')->delete();
        User::withoutGlobalScopes()->where('id', $this->employee->id)->forceDelete();
        Auth::logout();
        parent::tearDown();
    }

    /** Real (active) tenant holidays already on the dev DB win over everything — skip those days. */
    private function realHoliday(string $date): bool
    {
        return DB::table('holidays')->where('tenant_id', $this->tenantId)->where('status', 1)
            ->where('name', 'not like', $this->tag . '%')->where('start_date', '<=', $date)->where('end_date', '>=', $date)->exists();
    }

    private function expect(callable $statusOf, array $expected, string $where): void
    {
        foreach ($expected as $offset => $status) {
            $date = $this->monday->copy()->addDays($offset)->toDateString();
            if (! $this->realHoliday($date)) {
                $this->assertSame($status, $statusOf($date), "$where $date");
            }
        }
    }

    public function test_attendance_calendar(): void
    {
        $this->actingAs($this->employee)->withSession(['tenant_id' => $this->tenantId]);
        $calendar = collect($this->get(route('attendance.index', [
            'start_date' => $this->monday->toDateString(), 'end_date' => $this->monday->copy()->addDays(6)->toDateString(),
        ]))->assertOk()->viewData('calendarData'))->keyBy('start');

        $this->expect(fn ($d) => $calendar[$d]['title'], [
            2 => 'Absent', 3 => $this->tag . ' On', 5 => 'Week Off (Saturday)', 6 => 'Absent',
        ], '/attendance');
    }

    public function test_team_member_detail_calendar(): void
    {
        $this->actingAs($this->admin)->withSession(['tenant_id' => $this->tenantId]);
        $days = app(\App\Services\Team\TeamMemberAttendance::class)->getAttendanceDataForUser(
            $this->monday->toDateString(), $this->monday->copy()->addDays(6)->toDateString(), $this->employee->id
        )->keyBy('date');

        $this->expect(fn ($d) => $days[$d]->day_status, [
            2 => 'Absent', 3 => 'Holiday', 5 => 'Week Off', 6 => 'Absent',
        ], 'member-detail');
    }

    public function test_day_attendance_report(): void
    {
        $this->actingAs($this->admin)->withSession(['tenant_id' => $this->tenantId]);
        $statusOf = function (string $date) {
            $rows = collect($this->get(route('report.attendance.day.index', ['date' => $date, 'user_id' => $this->employee->id]))
                ->assertOk()->viewData('reportData')->items());

            return $rows->firstWhere('user_id', $this->employee->id)['status'];
        };

        $this->expect($statusOf, [2 => 'absent', 3 => 'holiday', 5 => 'week_off', 6 => 'absent'], 'day report');
    }
}
