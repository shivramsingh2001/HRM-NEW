<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureCustomShiftsEnabled;
use App\Http\Middleware\EnsureFeatureEnabled;
use App\Models\Shift;
use App\Models\User;
use App\Models\UserShift;
use App\Models\WeekoffSwap;
use App\Services\Attendance\SandwichRule;
use App\Services\Shift\ShiftAssignmentService;
use App\Services\Shift\WeekOffSwapService;
use App\Support\WeekOffPredicate;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Week-off swap (plan A1): employees with Saturday off work a Saturday and get
 * the Monday after off instead. The Monday becomes a one-day week-off (no
 * shift that day), shows as a week-off everywhere, is always paid under the
 * sandwich rule, survives the shift-assignment form's "override" and comes
 * back as a working day when the swap is cancelled.
 */
class WeekOffSwapTest extends TestCase
{
    private User $admin;

    private User $a;

    private User $b;

    private int $tenantId;

    private string $tag;

    private int $originalCustom;

    private Shift $shift;

    private string $saturday;

    private string $monday;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([EnsureCustomShiftsEnabled::class, EnsureFeatureEnabled::class]);
        $this->tag = 'PHPUnit WOS '.uniqid();
        $this->admin = User::withoutGlobalScopes()->where('role', 'admin')->where('status', 1)->whereNotNull('tenant_id')->orderBy('id')->firstOrFail();
        $this->tenantId = (int) $this->admin->tenant_id;
        $this->originalCustom = (int) DB::table('tenants')->where('id', $this->tenantId)->value('custom_shifts_enabled');
        DB::table('tenants')->where('id', $this->tenantId)->update(['custom_shifts_enabled' => 1]);

        $this->saturday = Carbon::today()->addWeeks(2)->next(Carbon::SATURDAY)->toDateString();
        $this->monday = Carbon::parse($this->saturday)->addDays(2)->toDateString();
        $this->shift = Shift::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenantId, 'name' => $this->tag.' Day', 'start_time' => '10:00', 'end_time' => '19:00',
            'is_overnight' => false, 'total_hours' => 9, 'grace_minutes' => 15, 'break_time' => 0, 'status' => 1, 'created_by' => $this->admin->id,
        ]);
        $this->a = $this->employee('Alpha');
        $this->b = $this->employee('Bravo');
    }

    protected function tearDown(): void
    {
        $ids = [$this->a->id, $this->b->id];
        $swapIds = DB::table('weekoff_swap_employees')->whereIn('user_id', $ids)->pluck('weekoff_swap_id');
        DB::table('weekoff_swap_employees')->whereIn('weekoff_swap_id', $swapIds)->delete();
        DB::table('audit_logs')->where('entity_type', 'weekoff_swaps')->whereIn('entity_id', $swapIds)->delete();
        DB::table('weekoff_swaps')->whereIn('id', $swapIds)->delete();
        foreach (['user_weekoffs', 'shift_change_logs', 'shift_assignments', 'leaves', 'attendance_summaries'] as $table) {
            DB::table($table)->whereIn('user_id', $ids)->delete();
        }
        UserShift::withoutGlobalScopes()->whereIn('user_id', $ids)->delete();
        Shift::withoutGlobalScopes()->where('name', 'like', $this->tag.'%')->delete();
        User::withoutGlobalScopes()->whereIn('id', $ids)->forceDelete();
        DB::table('tenants')->where('id', $this->tenantId)->update(['custom_shifts_enabled' => $this->originalCustom]);
        Auth::logout();
        parent::tearDown();
    }

    /** An employee with Saturday + Sunday weekly off and a permanent 10–19 shift on the other days. */
    private function employee(string $name): User
    {
        $u = User::withoutGlobalScopes()->forceCreate([
            'name' => "{$this->tag} {$name}", 'email' => 'wos.'.uniqid().'@phpunit.test', 'employee_id' => 'WS'.random_int(100000, 999999),
            'password' => bcrypt('secret'), 'role' => 'employee', 'status' => 1, 'tenant_id' => $this->tenantId,
        ]);
        foreach (['Saturday', 'Sunday'] as $day) {
            DB::table('user_weekoffs')->insert(['tenant_id' => $this->tenantId, 'user_id' => $u->id, 'off_type' => 'day_based', 'day_name' => $day,
                'start_date' => null, 'end_date' => null, 'status' => 1, 'created_by' => $this->admin->id, 'created_at' => now(), 'updated_at' => now()]);
        }
        app(ShiftAssignmentService::class)->assignPermanent($this->tenantId, $u->id, $this->shift->id, Carbon::today(), $this->admin->id, null, null, null);

        return $u;
    }

    private function hasShift(User $u, string $date): bool
    {
        return UserShift::withoutGlobalScopes()->where('user_id', $u->id)->where('date', $date)->exists();
    }

    private function isWeekOff(User $u, string $date): bool
    {
        return WeekOffPredicate::isWeekOff(DB::table('user_weekoffs')->where('user_id', $u->id)->where('status', 1)->get(), Carbon::parse($date));
    }

    private function save(array $employees, ?string $day = null)
    {
        return $this->actingAs($this->admin)->postJson(route('shift.weekoff-swaps.store'), [
            'worked_date' => $this->saturday, 'replacement_date' => $day ?? $this->monday, 'reason' => 'Stock audit', 'employees' => $employees,
        ]);
    }

    public function test_a_swap_gives_the_monday_off_and_cancelling_brings_the_shift_back(): void
    {
        $this->assertTrue($this->hasShift($this->a, $this->monday));
        $this->assertFalse($this->isWeekOff($this->a, $this->monday));

        // Both had Saturday off, so both are offered.
        $list = $this->actingAs($this->admin)->getJson(route('shift.weekoff-swaps.candidates', ['worked_date' => $this->saturday, 'replacement_date' => $this->monday]))
            ->assertOk()->json('data');
        $mine = collect($list)->whereIn('user_id', [$this->a->id, $this->b->id]);
        $this->assertCount(2, $mine);
        $this->assertTrue($mine->every(fn ($e) => $e['eligible'] && ! $e['worked']));

        $this->save([['user_id' => $this->a->id], ['user_id' => $this->b->id]])->assertOk()->assertJson(['status' => true]);

        foreach ([$this->a, $this->b] as $u) {
            $this->assertTrue($this->isWeekOff($u, $this->monday), 'Monday is now a week-off');
            $this->assertFalse($this->hasShift($u, $this->monday), 'a day off has no shift');
            $this->assertTrue($this->isWeekOff($u, $this->saturday), 'the worked Saturday is left as it was');
        }
        $this->assertSame(2, DB::table('shift_change_logs')->whereIn('user_id', [$this->a->id, $this->b->id])->where('source', 'weekoff_swap')->count());
        $this->assertSame([$this->monday], WeekOffSwapService::replacementDates($this->tenantId, $this->a->id, $this->saturday, $this->monday));

        // Cancel Alpha only: their shift is back, Bravo keeps the day off.
        $swap = WeekoffSwap::withoutGlobalScopes()->where('worked_date', $this->saturday)->latest('id')->firstOrFail();
        $this->postJson(route('shift.weekoff-swaps.cancel', $swap->id), ['user_id' => $this->a->id])->assertOk();
        $this->assertFalse($this->isWeekOff($this->a, $this->monday));
        $this->assertTrue($this->hasShift($this->a, $this->monday));
        $this->assertTrue($this->isWeekOff($this->b, $this->monday));
        $this->assertSame('active', $swap->fresh()->status);

        // Cancel the rest: the swap is cancelled.
        $this->postJson(route('shift.weekoff-swaps.cancel', $swap->id))->assertOk();
        $this->assertTrue($this->hasShift($this->b, $this->monday));
        $this->assertSame('cancelled', $swap->fresh()->status);
        $this->assertSame([], WeekOffSwapService::replacementDates($this->tenantId, $this->b->id, $this->saturday, $this->monday));
    }

    public function test_employees_who_cannot_take_the_day_are_skipped_with_a_reason(): void
    {
        $sunday = Carbon::parse($this->saturday)->addDay()->toDateString();
        DB::table('leaves')->insert(['tenant_id' => $this->tenantId, 'leave_id' => 'PHPU-'.random_int(1000, 9999), 'user_id' => $this->b->id,
            'leave_type' => 1, 'start_date' => $this->monday, 'start_session' => 'fullday', 'end_date' => $this->monday, 'end_session' => 'fullday',
            'status' => 'approved', 'created_at' => now()]);

        // Sunday is already their week-off.
        $this->save([['user_id' => $this->a->id]], $sunday)->assertStatus(422)->assertJsonPath('skipped.0.reason', 'Already a week-off on '.Carbon::parse($sunday)->format('d M'));

        // Bravo is on leave on Monday; Alpha goes through.
        $r = $this->save([['user_id' => $this->a->id], ['user_id' => $this->b->id]])->assertOk();
        $this->assertSame([$this->b->id], array_column($r->json('skipped'), 'user_id'));
        $this->assertStringContainsString('On approved leave', $r->json('skipped.0.reason'));
        $this->assertFalse($this->isWeekOff($this->b, $this->monday));

        // The same worked day cannot be swapped twice.
        $this->save([['user_id' => $this->a->id]], Carbon::parse($this->monday)->addDay()->toDateString())
            ->assertStatus(422)->assertJsonPath('skipped.0.reason', 'Already swapped for this worked day');

        // A worked day that is not their week-off (a Friday).
        $this->actingAs($this->admin)->postJson(route('shift.weekoff-swaps.store'), [
            'worked_date' => Carbon::parse($this->saturday)->subDay()->toDateString(), 'replacement_date' => $this->monday, 'employees' => [['user_id' => $this->b->id]],
        ])->assertStatus(422)->assertJsonPath('skipped.0.reason', 'The worked day is not their week-off');
    }

    public function test_a_per_employee_day_off_and_the_list_page(): void
    {
        $tuesday = Carbon::parse($this->monday)->addDay()->toDateString();
        $this->save([['user_id' => $this->a->id], ['user_id' => $this->b->id, 'replacement_date' => $tuesday]])->assertOk();

        $this->assertTrue($this->isWeekOff($this->a, $this->monday));
        $this->assertTrue($this->isWeekOff($this->b, $tuesday));
        $this->assertFalse($this->isWeekOff($this->b, $this->monday));

        $this->get(route('shift.weekoff-swaps.index'))->assertOk()->assertSee('Week-off Swaps')->assertSee($this->a->name)->assertSee('Stock audit');
    }

    public function test_the_assignment_form_override_keeps_swap_days(): void
    {
        $this->save([['user_id' => $this->a->id]])->assertOk();

        // Shift → Assign with "override existing" + new weekly offs wipes the employee's week-offs…
        $controller = app(\App\Http\Controllers\Shift\ShiftAssignmentController::class);
        $request = \Illuminate\Http\Request::create('/', 'POST', ['override_existing' => 1, 'week_off_type' => 'day_based', 'week_off_days' => ['Sunday'],
            'start_date' => Carbon::today()->toDateString()]);
        $userId = $this->a->id;
        (function () use ($userId, $request) {
            return $this->assignWeekOffs($userId, $request);
        })->call($controller);

        // …but not the swap's day off.
        $this->assertFalse($this->isWeekOff($this->a, $this->saturday), 'old Saturday weekly off replaced');
        $this->assertTrue($this->isWeekOff($this->a, $this->monday), 'swap day kept');
    }

    public function test_a_swap_day_is_always_paid_under_the_sandwich_rule(): void
    {
        // Absent on both sides of Monday — a plain week-off there would be unpaid with the rule on.
        $days = ['2026-10-16' => 'absent', '2026-10-17' => 'absent', '2026-10-18' => 'weekoff', '2026-10-19' => 'weekoff', '2026-10-20' => 'absent'];
        $on = fn () => true;

        $this->assertSame(['2026-10-18' => false, '2026-10-19' => false], SandwichRule::offDayPay($days, $on));
        $this->assertSame(['2026-10-18' => false, '2026-10-19' => true], SandwichRule::offDayPay($days, $on, ['2026-10-19']));
        $this->assertSame(['2026-10-18' => true, '2026-10-19' => true], SandwichRule::offDayPay($days, fn () => false, ['2026-10-19']));
    }
}
