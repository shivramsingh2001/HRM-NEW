<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureFeatureEnabled;
use App\Models\FloatingWeekoffDay;
use App\Models\Shift;
use App\Models\User;
use App\Models\UserShift;
use App\Services\Attendance\FloatingWeekOffAllocator;
use App\Services\Attendance\PolicyResolver;
use App\Services\EmployeePolicyService;
use App\Services\Shift\ShiftAssignmentService;
use App\Support\WeekOffPredicate;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Floating week-offs planned ahead (plan C2): the employee asks for a day, an
 * approver approves → the day is a week-off without a shift; it uses the
 * month's count (and week cap) before the nightly fill of missed days; a
 * planned day worked anyway is given back; cancelling brings the shift back.
 */
class FloatingWeekOffPlanTest extends TestCase
{
    private User $admin;
    private User $emp;
    private int $tenantId;
    private string $tag;
    private int $originalCustom;
    /** @var string[] four non-holiday weekdays of next month, two in the same week */
    private array $days = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([EnsureFeatureEnabled::class]);
        $this->tag = 'PHPUnit FWP ' . uniqid();
        $this->admin = User::withoutGlobalScopes()->where('role', 'admin')->where('status', 1)->whereNotNull('tenant_id')->orderBy('id')->firstOrFail();
        $this->tenantId = (int) $this->admin->tenant_id;
        $this->originalCustom = (int) DB::table('tenants')->where('id', $this->tenantId)->value('custom_shifts_enabled');
        DB::table('tenants')->where('id', $this->tenantId)->update(['custom_shifts_enabled' => 1]);
        $this->emp = User::withoutGlobalScopes()->forceCreate([
            'name' => $this->tag . ' Emp', 'email' => 'fwp.' . uniqid() . '@phpunit.test', 'employee_id' => 'FP' . random_int(100000, 999999),
            'password' => bcrypt('secret'), 'role' => 'employee', 'status' => 1, 'tenant_id' => $this->tenantId,
        ]);
        $shift = Shift::withoutGlobalScopes()->create(['tenant_id' => $this->tenantId, 'name' => $this->tag . ' Day', 'start_time' => '10:00', 'end_time' => '19:00',
            'is_overnight' => false, 'total_hours' => 9, 'grace_minutes' => 15, 'break_time' => 0, 'status' => 1, 'created_by' => $this->admin->id]);
        app(ShiftAssignmentService::class)->assignPermanent($this->tenantId, $this->emp->id, $shift->id, Carbon::today(), $this->admin->id, null, null, null);
        $this->policy(['floating_weekoffs_enabled' => 1, 'floating_weekoffs_per_month' => 3]);

        for ($d = Carbon::today()->addMonthNoOverflow()->startOfMonth()->next(Carbon::MONDAY); count($this->days) < 4; $d->addDay()) {
            $holiday = DB::table('holidays')->where('tenant_id', $this->tenantId)->where('status', 1)->where('start_date', '<=', $d->toDateString())
                ->whereRaw("COALESCE(NULLIF(end_date, ''), start_date) >= ?", [$d->toDateString()])->exists();
            if (! $d->isWeekend() && ! $holiday) {
                $this->days[] = $d->toDateString();
            }
        }
    }

    protected function tearDown(): void
    {
        $uid = $this->emp->id;
        $ids = DB::table('floating_weekoff_days')->where('user_id', $uid)->pluck('id');
        DB::table('audit_logs')->where('entity_type', 'floating_weekoff_days')->whereIn('entity_id', $ids)->delete();
        foreach (['floating_weekoff_days', 'user_weekoffs', 'attendances', 'attendance_summaries', 'employee_policy_overrides', 'shift_change_logs', 'shift_assignments'] as $t) {
            DB::table($t)->where('user_id', $uid)->delete();
        }
        UserShift::withoutGlobalScopes()->where('user_id', $uid)->delete();
        Shift::withoutGlobalScopes()->where('name', 'like', $this->tag . '%')->delete();
        User::withoutGlobalScopes()->where('id', $uid)->forceDelete();
        DB::table('tenants')->where('id', $this->tenantId)->update(['custom_shifts_enabled' => $this->originalCustom]);
        app(EmployeePolicyService::class)->forget();
        app(PolicyResolver::class)->forget();
        Auth::logout();
        parent::tearDown();
    }

    private function policy(array $values): void
    {
        app(EmployeePolicyService::class)->save($this->tenantId, $this->emp->id, 'attendance', $values, $this->admin->id);
        app(EmployeePolicyService::class)->forget();
        app(PolicyResolver::class)->forget();
    }

    private function ask(string $date)
    {
        return $this->actingAs($this->emp)->postJson(route('leave.floating-weekoffs.store'), ['date' => $date, 'reason' => 'Family']);
    }

    private function isWeekOff(string $date): bool
    {
        return WeekOffPredicate::isWeekOff(DB::table('user_weekoffs')->where('user_id', $this->emp->id)->where('status', 1)->get(), Carbon::parse($date));
    }

    private function hasShift(string $date): bool
    {
        return UserShift::withoutGlobalScopes()->where('user_id', $this->emp->id)->where('date', $date)->exists();
    }

    public function test_plan_approve_and_cancel_a_day_off(): void
    {
        $day = $this->days[0];
        $this->assertTrue($this->hasShift($day));
        $this->actingAs($this->emp)->getJson(route('leave.floating-weekoffs.check', ['date' => $day]))->assertJson(['status' => true]);

        $this->ask($day)->assertOk();
        $row = FloatingWeekoffDay::withoutGlobalScopes()->where('user_id', $this->emp->id)->firstOrFail();
        $this->assertSame(['planned', 'pending'], [$row->source, $row->status]);
        $this->assertFalse($this->isWeekOff($day), 'nothing changes until approved');

        // The employee cannot approve it; the admin can.
        $this->actingAs($this->emp)->postJson(route('leave.floating-weekoffs.decide', $row->id), ['action' => 'approved'])->assertStatus(403);
        $this->actingAs($this->admin)->postJson(route('leave.floating-weekoffs.decide', $row->id), ['action' => 'approved'])->assertOk();
        $this->assertSame('approved', $row->fresh()->status);
        $this->assertTrue($this->isWeekOff($day));
        $this->assertFalse($this->hasShift($day), 'a day off has no shift');
        $this->assertSame(1, DB::table('shift_change_logs')->where('user_id', $this->emp->id)->where('source', 'floating_weekoff')->count());

        // The same day again is refused; cancelling brings the shift back.
        $this->ask($day)->assertStatus(422)->assertJsonPath('message', 'Already a floating week-off (or asked for)');
        $this->actingAs($this->emp)->postJson(route('leave.floating-weekoffs.cancel', $row->id))->assertOk();
        $this->assertFalse($this->isWeekOff($day));
        $this->assertTrue($this->hasShift($day));

        $this->actingAs($this->emp)->get(route('leave.floating-weekoffs.index'))->assertOk()->assertSee('left in');
        $this->actingAs($this->admin)->get(route('leave.floating-weekoffs.index'))->assertOk()->assertSee('Team requests');
    }

    public function test_the_month_count_and_the_week_cap_limit_planning(): void
    {
        foreach (array_slice($this->days, 0, 3) as $d) {
            $this->ask($d)->assertOk();
        }
        $this->ask($this->days[3])->assertStatus(422)->assertJsonPath('message',
            'All 3 floating week-off(s) of ' . Carbon::parse($this->days[3])->format('M Y') . ' are used or asked for');

        // Cap 1 a week: the first two asks above fell in one week (Mon, Tue).
        DB::table('floating_weekoff_days')->where('user_id', $this->emp->id)->delete();
        $this->policy(['floating_weekoffs_enabled' => 1, 'floating_weekoffs_per_month' => 3, 'floating_weekoffs_max_per_week' => 1]);
        $this->ask($this->days[0])->assertOk();
        $this->ask($this->days[1])->assertStatus(422)->assertJsonPath('message', 'At most 1 floating week-off(s) in one week');

        $this->policy(['floating_weekoffs_enabled' => 0]);
        $this->ask($this->days[2])->assertStatus(422)->assertJsonPath('message', 'You are not on floating week-offs');
    }

    public function test_planned_days_use_the_count_first_and_a_worked_planned_day_is_given_back(): void
    {
        // A past month: two approved planned days, five missed days, count 3 → only one missed day is filled.
        $ym = Carbon::today()->subMonthsNoOverflow(2)->format('Y-m');
        $start = Carbon::parse($ym . '-01');
        $days = [];
        for ($d = $start->copy(); $d->month === $start->month; $d->addDay()) {
            $holiday = DB::table('holidays')->where('tenant_id', $this->tenantId)->where('status', 1)->where('start_date', '<=', $d->toDateString())
                ->whereRaw("COALESCE(NULLIF(end_date, ''), start_date) >= ?", [$d->toDateString()])->exists();
            if (! $holiday) {
                $days[] = $d->toDateString();
            }
        }
        [$p1, $p2, $m1, $m2] = [$days[1], $days[2], $days[5], $days[8]];
        foreach ($days as $d) {
            if (! in_array($d, [$p1, $p2, $m1, $m2], true)) {
                DB::table('attendances')->insert(['tenant_id' => $this->tenantId, 'user_id' => $this->emp->id, 'date' => $d, 'clock_in' => "$d 10:00:00",
                    'clock_out' => "$d 19:00:00", 'worked_hours' => 9, 'attendance_status' => 'present', 'attendance_type' => 'app', 'status' => 1, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
        foreach ([$p1, $p2] as $d) {
            $off = DB::table('user_weekoffs')->insertGetId(['tenant_id' => $this->tenantId, 'user_id' => $this->emp->id, 'off_type' => 'date_based',
                'start_date' => $d, 'end_date' => $d, 'status' => 1, 'created_by' => $this->admin->id, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('floating_weekoff_days')->insert(['tenant_id' => $this->tenantId, 'user_id' => $this->emp->id, 'date' => $d, 'user_weekoff_id' => $off,
                'source' => 'planned', 'status' => 'approved', 'created_at' => now(), 'updated_at' => now()]);
        }

        $r = app(FloatingWeekOffAllocator::class)->allocateMonth($this->tenantId, $this->emp->id, $ym);
        $this->assertSame([$m1], $r['added'], '3 − 2 planned = 1 missed day');

        // He came in on a planned day after all → that slot goes to the next missed day.
        DB::table('attendances')->insert(['tenant_id' => $this->tenantId, 'user_id' => $this->emp->id, 'date' => $p2, 'clock_in' => "$p2 10:00:00",
            'clock_out' => "$p2 19:00:00", 'worked_hours' => 9, 'attendance_status' => 'present', 'attendance_type' => 'app', 'status' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $r = app(FloatingWeekOffAllocator::class)->allocateMonth($this->tenantId, $this->emp->id, $ym);
        $this->assertSame([$p2], $r['removed']);
        $this->assertSame([$m2], $r['added']);
        $this->assertSame('cancelled', DB::table('floating_weekoff_days')->where('user_id', $this->emp->id)->where('date', $p2)->value('status'));
    }
}
