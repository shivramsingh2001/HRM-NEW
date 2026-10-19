<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Attendance\FloatingWeekOffAllocator;
use App\Services\Attendance\PolicyResolver;
use App\Services\Attendance\SandwichRule;
use App\Services\EmployeePolicyService;
use App\Support\AlwaysPaidOffDays;
use App\Support\WeekOffPredicate;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Floating week-offs (plan C1): "4 week-offs a month on any day". The nightly
 * allocator turns missed days into week-offs in date order up to the count;
 * the 5th+ missed day stays absent; a leave approved later frees the slot;
 * the per-week cap, mid-month pro-rating and Employee 360 override apply.
 */
class FloatingWeekOffTest extends TestCase
{
    private User $admin;

    private User $emp;

    private int $tenantId;

    private string $month;

    /** @var array<string,bool> */
    private array $holidays = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::withoutGlobalScopes()->where('role', 'admin')->where('status', 1)->whereNotNull('tenant_id')->orderBy('id')->firstOrFail();
        $this->tenantId = (int) $this->admin->tenant_id;
        $this->month = Carbon::today()->subMonthsNoOverflow(2)->format('Y-m');
        $this->emp = User::withoutGlobalScopes()->forceCreate([
            'name' => 'PHPUnit FWO '.uniqid(), 'email' => 'fwo.'.uniqid().'@phpunit.test', 'employee_id' => 'FW'.random_int(100000, 999999),
            'password' => bcrypt('secret'), 'role' => 'employee', 'status' => 1, 'tenant_id' => $this->tenantId,
        ]);
        // Real holidays on the dev DB are never floating days — leave them out of the expectations.
        foreach (DB::table('holidays')->where('tenant_id', $this->tenantId)->where('status', 1)->get(['start_date', 'end_date']) as $h) {
            for ($d = Carbon::parse($h->start_date); $d->lte(Carbon::parse($h->end_date ?: $h->start_date)); $d->addDay()) {
                $this->holidays[$d->toDateString()] = true;
            }
        }
        $this->floating(['floating_weekoffs_enabled' => 1, 'floating_weekoffs_per_month' => 4]);
    }

    protected function tearDown(): void
    {
        $uid = $this->emp->id;
        foreach (['floating_weekoff_days', 'user_weekoffs', 'attendances', 'leaves', 'attendance_summaries', 'user_job_details', 'employee_policy_overrides'] as $t) {
            DB::table($t)->where('user_id', $uid)->delete();
        }
        User::withoutGlobalScopes()->where('id', $uid)->forceDelete();
        app(EmployeePolicyService::class)->forget();
        app(PolicyResolver::class)->forget();
        Auth::logout();
        parent::tearDown();
    }

    /** Employee 360 → Policies override for this employee only. */
    private function floating(array $values): void
    {
        app(EmployeePolicyService::class)->save($this->tenantId, $this->emp->id, 'attendance', $values, $this->admin->id);
        app(EmployeePolicyService::class)->forget();
        app(PolicyResolver::class)->forget();
    }

    /** Clock in every working day of the month except $missed (days of month). */
    private function workAllBut(array $missed): array
    {
        $start = Carbon::parse($this->month.'-01');
        $missedDates = [];
        for ($d = $start->copy(); $d->month === $start->month; $d->addDay()) {
            $ds = $d->toDateString();
            if (isset($this->holidays[$ds])) {
                continue;
            }
            if (in_array($d->day, $missed, true)) {
                $missedDates[] = $ds;

                continue;
            }
            DB::table('attendances')->insert(['tenant_id' => $this->tenantId, 'user_id' => $this->emp->id, 'date' => $ds,
                'clock_in' => "$ds 10:00:00", 'clock_out' => "$ds 19:00:00", 'worked_hours' => 9, 'attendance_status' => 'present',
                'attendance_type' => 'app', 'status' => 1, 'created_at' => now(), 'updated_at' => now()]);
        }

        return $missedDates;
    }

    private function floatingDays(): array
    {
        return DB::table('floating_weekoff_days')->where('user_id', $this->emp->id)->orderBy('date')->pluck('date')
            ->map(fn ($d) => substr((string) $d, 0, 10))->all();
    }

    private function isWeekOff(string $date): bool
    {
        return WeekOffPredicate::isWeekOff(DB::table('user_weekoffs')->where('user_id', $this->emp->id)->where('status', 1)->get(), Carbon::parse($date));
    }

    public function test_the_first_missed_days_up_to_the_count_become_week_offs_and_the_rest_stay_absent(): void
    {
        $missed = $this->workAllBut([3, 9, 15, 21, 27]);

        $r = app(FloatingWeekOffAllocator::class)->allocateMonth($this->tenantId, $this->emp->id, $this->month);

        $this->assertSame(array_slice($missed, 0, 4), $r['added']);
        $this->assertSame(array_slice($missed, 0, 4), $this->floatingDays());
        foreach (array_slice($missed, 0, 4) as $d) {
            $this->assertTrue($this->isWeekOff($d), "$d is a week-off");
        }
        $this->assertFalse($this->isWeekOff($missed[4]), 'the 5th missed day stays absent');

        // Re-running changes nothing.
        $again = app(FloatingWeekOffAllocator::class)->allocateMonth($this->tenantId, $this->emp->id, $this->month);
        $this->assertSame([[], []], [$again['added'], $again['removed']]);
    }

    public function test_a_leave_approved_later_frees_its_slot_for_the_next_missed_day(): void
    {
        $missed = $this->workAllBut([3, 9, 15, 21, 27]);
        app(FloatingWeekOffAllocator::class)->allocateMonth($this->tenantId, $this->emp->id, $this->month);

        DB::table('leaves')->insert(['tenant_id' => $this->tenantId, 'leave_id' => 'PHPU-'.random_int(1000, 9999), 'user_id' => $this->emp->id,
            'leave_type' => 1, 'start_date' => $missed[1], 'start_session' => 'fullday', 'end_date' => $missed[1], 'end_session' => 'fullday',
            'status' => 'approved', 'created_at' => now()]);
        $r = app(FloatingWeekOffAllocator::class)->allocateMonth($this->tenantId, $this->emp->id, $this->month);

        $this->assertSame([$missed[1]], $r['removed']);
        $this->assertSame([$missed[4]], $r['added']);
        $this->assertSame([$missed[0], $missed[2], $missed[3], $missed[4]], $this->floatingDays());
        $this->assertFalse($this->isWeekOff($missed[1]), 'the leave day is a leave, not a week-off');
    }

    public function test_per_week_cap_pro_rating_and_switching_off(): void
    {
        // Three missed days in one week, cap 1 per week → only the first of them.
        $start = Carbon::parse($this->month.'-01')->next(Carbon::MONDAY);
        $days = [$start->day, $start->copy()->addDay()->day, $start->copy()->addDays(2)->day, $start->copy()->addDays(9)->day];
        $missed = $this->workAllBut($days);
        $this->floating(['floating_weekoffs_enabled' => 1, 'floating_weekoffs_per_month' => 4, 'floating_weekoffs_max_per_week' => 1]);
        $wanted = app(FloatingWeekOffAllocator::class)->wantedDays($this->tenantId, $this->emp->id, $this->month);
        $this->assertSame([Carbon::parse($missed[0])->format('o-W'), Carbon::parse(end($missed))->format('o-W')],
            array_map(fn ($d) => Carbon::parse($d)->format('o-W'), $wanted), 'one per week');

        // Joined on the 16th of a 30/31-day month → about half the count.
        DB::table('user_job_details')->insert(['tenant_id' => $this->tenantId, 'user_id' => $this->emp->id,
            'joining_date' => $this->month.'-16', 'created_at' => now(), 'updated_at' => now()]);
        $daysInMonth = Carbon::parse($this->month.'-01')->daysInMonth;
        $this->assertSame((int) round(4 * ($daysInMonth - 15) / $daysInMonth), app(FloatingWeekOffAllocator::class)->quota(4, $this->month, $this->tenantId, $this->emp->id));

        // Switched off for this employee: next run removes the floating days.
        DB::table('user_job_details')->where('user_id', $this->emp->id)->delete();
        $this->floating(['floating_weekoffs_enabled' => 1, 'floating_weekoffs_per_month' => 4]);
        app(FloatingWeekOffAllocator::class)->allocateMonth($this->tenantId, $this->emp->id, $this->month);
        $this->assertNotEmpty($this->floatingDays());
        $this->floating(['floating_weekoffs_enabled' => 0]);
        app(FloatingWeekOffAllocator::class)->allocateMonth($this->tenantId, $this->emp->id, $this->month);
        $this->assertSame([], $this->floatingDays());
        $this->assertSame(0, DB::table('user_weekoffs')->where('user_id', $this->emp->id)->count());
    }

    public function test_hand_marked_and_worked_days_are_never_taken_and_floating_days_are_always_paid(): void
    {
        $missed = $this->workAllBut([3, 9]);
        DB::table('attendances')->insert(['tenant_id' => $this->tenantId, 'user_id' => $this->emp->id, 'date' => $missed[0],
            'attendance_status' => 'absent', 'attendance_type' => 'manual', 'marked_by' => $this->admin->id, 'status' => 1,
            'created_at' => now(), 'updated_at' => now()]);

        app(FloatingWeekOffAllocator::class)->allocateMonth($this->tenantId, $this->emp->id, $this->month);
        $this->assertSame([$missed[1]], $this->floatingDays(), "an admin's absent mark stands");

        $this->assertSame([$missed[1]], AlwaysPaidOffDays::dates($this->tenantId, $this->emp->id, $this->month.'-01', $this->month.'-28'));
        $prev = Carbon::parse($missed[1])->subDay()->toDateString();
        $next = Carbon::parse($missed[1])->addDay()->toDateString();
        $this->assertSame([$missed[1] => true], SandwichRule::offDayPay([$prev => 'absent', $missed[1] => 'weekoff', $next => 'absent'], fn () => true, [$missed[1]]));
    }

    public function test_the_nightly_command_and_the_company_policies_card(): void
    {
        $this->workAllBut([3, 9]);
        $this->artisan('weekoffs:allocate-floating', ['--month' => $this->month, '--tenant' => $this->tenantId, '--user' => $this->emp->id])
            ->assertSuccessful();
        $this->assertCount(2, $this->floatingDays());

        $this->actingAs($this->admin)->get(route('workforce-settings.index'))->assertOk()->assertSee('Floating week-offs');
    }
}
