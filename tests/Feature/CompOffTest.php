<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureFeatureEnabled;
use App\Models\ApprovalRequest;
use App\Models\CompOffCredit;
use App\Models\User;
use App\Services\Approvals\CompOffApprovalHandler;
use App\Services\Attendance\PolicyResolver;
use App\Services\EmployeePolicyService;
use App\Services\Leave\CompOffService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Comp-off (plan B): working a week-off / holiday earns a day off. Request →
 * approve credits the "Comp Off" leave balance; half days, overtime / swap
 * exclusions, the monthly cap, auto mode, expiry (oldest first) and the
 * approval-workflow handler.
 */
class CompOffTest extends TestCase
{
    private User $admin;
    private User $emp;
    private int $tenantId;
    private string $sat1;
    private string $sat2;
    private bool $hadCompOffType;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([EnsureFeatureEnabled::class]);
        $this->admin = User::withoutGlobalScopes()->where('role', 'admin')->where('status', 1)->whereNotNull('tenant_id')->orderBy('id')->firstOrFail();
        $this->tenantId = (int) $this->admin->tenant_id;
        $this->hadCompOffType = DB::table('leave_types')->where('tenant_id', $this->tenantId)->where('code', 'comp_off')->exists();
        $this->emp = User::withoutGlobalScopes()->forceCreate([
            'name' => 'PHPUnit COF ' . uniqid(), 'email' => 'cof.' . uniqid() . '@phpunit.test', 'employee_id' => 'CO' . random_int(100000, 999999),
            'password' => bcrypt('secret'), 'role' => 'employee', 'status' => 1, 'tenant_id' => $this->tenantId,
        ]);
        DB::table('user_weekoffs')->insert(['tenant_id' => $this->tenantId, 'user_id' => $this->emp->id, 'off_type' => 'day_based', 'day_name' => 'Saturday',
            'start_date' => null, 'end_date' => null, 'status' => 1, 'created_by' => $this->admin->id, 'created_at' => now(), 'updated_at' => now()]);
        $this->sat1 = $this->freeSaturday(Carbon::today()->subWeeks(3));
        $this->sat2 = $this->freeSaturday(Carbon::parse($this->sat1)->addDays(8));
        $this->policy(['comp_off_enabled' => 1, 'comp_off_mode' => 'request']);
    }

    protected function tearDown(): void
    {
        $uid = $this->emp->id;
        $swapIds = DB::table('weekoff_swap_employees')->where('user_id', $uid)->pluck('weekoff_swap_id');
        DB::table('weekoff_swap_employees')->whereIn('weekoff_swap_id', $swapIds)->delete();
        DB::table('weekoff_swaps')->whereIn('id', $swapIds)->delete();
        $creditIds = DB::table('comp_off_credits')->where('user_id', $uid)->pluck('id');
        DB::table('audit_logs')->where('entity_type', 'comp_off_credits')->whereIn('entity_id', $creditIds)->delete();
        foreach (['comp_off_credits', 'user_weekoffs', 'attendances', 'overtime_requests', 'leave_balances', 'leave_transactions', 'employee_policy_overrides', 'weekoff_swap_employees'] as $t) {
            DB::table($t)->where('user_id', $uid)->delete();
        }
        User::withoutGlobalScopes()->where('id', $uid)->forceDelete();
        if (! $this->hadCompOffType) {   // the "Comp Off" type this test created
            DB::table('leave_types')->where('tenant_id', $this->tenantId)->where('code', 'comp_off')->delete();
        }
        app(EmployeePolicyService::class)->forget();
        app(PolicyResolver::class)->forget();
        Auth::logout();
        parent::tearDown();
    }

    /** A Saturday on or before $from that is not a company holiday on the dev DB. */
    private function freeSaturday(Carbon $from): string
    {
        $d = $from->copy()->previous(Carbon::SATURDAY);
        while (DB::table('holidays')->where('tenant_id', $this->tenantId)->where('status', 1)->where('start_date', '<=', $d->toDateString())
            ->whereRaw("COALESCE(NULLIF(end_date, ''), start_date) >= ?", [$d->toDateString()])->exists()) {
            $d->subWeek();
        }

        return $d->toDateString();
    }

    private function policy(array $values): void
    {
        app(EmployeePolicyService::class)->save($this->tenantId, $this->emp->id, 'attendance', $values, $this->admin->id);
        app(EmployeePolicyService::class)->forget();
        app(PolicyResolver::class)->forget();
    }

    private function worked(string $date, float $hours): void
    {
        DB::table('attendances')->insert(['tenant_id' => $this->tenantId, 'user_id' => $this->emp->id, 'date' => $date,
            'clock_in' => "$date 10:00:00", 'clock_out' => Carbon::parse("$date 10:00")->addMinutes((int) ($hours * 60))->format('Y-m-d H:i:s'),
            'worked_hours' => $hours, 'attendance_status' => 'present', 'attendance_type' => 'app', 'status' => 1, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function svc(): CompOffService
    {
        return app(CompOffService::class);
    }

    public function test_request_and_approve_credits_the_comp_off_balance(): void
    {
        $this->worked($this->sat1, 9);
        $claimable = $this->svc()->claimableDays($this->tenantId, $this->emp->id, Carbon::parse($this->sat1)->subDay()->toDateString(), Carbon::yesterday()->toDateString());
        $this->assertSame([$this->sat1], array_column($claimable, 'date'));
        $this->assertSame(['week_off', 1.0], [$claimable[0]['day_type'], $claimable[0]['days']]);

        $this->actingAs($this->emp)->postJson(route('leave.comp-off.store'), ['worked_date' => $this->sat1, 'reason' => 'Audit'])->assertOk();
        $credit = CompOffCredit::withoutGlobalScopes()->where('user_id', $this->emp->id)->firstOrFail();
        $this->assertSame('pending', $credit->status);

        // The employee cannot approve it (no permission); the admin can.
        $this->actingAs($this->emp)->postJson(route('leave.comp-off.decide', $credit->id), ['action' => 'approved'])->assertStatus(403);
        $this->actingAs($this->admin)->postJson(route('leave.comp-off.decide', $credit->id), ['action' => 'approved'])->assertOk();

        $credit->refresh();
        $this->assertSame('approved', $credit->status);
        $this->assertSame(Carbon::parse($this->sat1)->addDays(60)->toDateString(), $credit->expires_on->toDateString());
        $this->assertEquals(1.0, $this->svc()->balance($this->tenantId, $this->emp->id));
        $this->assertSame(1, DB::table('leave_transactions')->where('user_id', $this->emp->id)->where('transaction_type', 'add')->count());
        $this->assertSame('comp_off', DB::table('leave_types')->where('id', $credit->leave_type_id)->value('code'));

        // Same day again: refused.
        $this->actingAs($this->emp)->postJson(route('leave.comp-off.store'), ['worked_date' => $this->sat1])->assertStatus(422)
            ->assertJsonPath('message', 'Already claimed as comp-off');

        $this->actingAs($this->emp)->get(route('leave.comp-off.index'))->assertOk()->assertSee('Comp Off balance');
        $this->actingAs($this->admin)->get(route('leave.comp-off.index'))->assertOk()->assertSee('Team requests');
    }

    public function test_hours_decide_full_half_or_nothing_and_a_working_day_cannot_be_claimed(): void
    {
        $this->worked($this->sat1, 5);
        $this->worked($this->sat2, 3);
        $friday = Carbon::parse($this->sat2)->subDay()->toDateString();
        $this->worked($friday, 9);

        $this->assertEquals(0.5, $this->svc()->dayInfo($this->tenantId, $this->emp->id, $this->sat1)['days']);
        $this->assertStringContainsString('less than the 4 h', $this->svc()->dayInfo($this->tenantId, $this->emp->id, $this->sat2)['reason']);
        $this->assertSame('Not a week-off or holiday', $this->svc()->dayInfo($this->tenantId, $this->emp->id, $friday)['reason']);

        $this->policy(['comp_off_enabled' => 0]);
        $this->assertSame('Comp-off is not on for this employee', $this->svc()->dayInfo($this->tenantId, $this->emp->id, $this->sat1)['reason']);
    }

    public function test_overtime_swap_and_monthly_cap_exclusions(): void
    {
        $this->worked($this->sat1, 9);
        $this->worked($this->sat2, 9);

        // Approved overtime that day → cannot also be comp-off.
        DB::table('overtime_requests')->insert(['tenant_id' => $this->tenantId, 'user_id' => $this->emp->id, 'date' => $this->sat1,
            'overtime_hours' => 9, 'reason' => 'Saturday work', 'status' => 'approved', 'created_at' => now(), 'updated_at' => now()]);
        $this->assertSame('Already claimed as overtime', $this->svc()->dayInfo($this->tenantId, $this->emp->id, $this->sat1)['reason']);

        // Pending overtime is rejected when the comp-off is approved.
        DB::table('overtime_requests')->where('user_id', $this->emp->id)->update(['status' => 'pending']);
        $credit = $this->svc()->request($this->emp, $this->sat1, null);
        $this->svc()->decide($credit, $this->admin, 'approved');
        $this->assertSame('rejected', DB::table('overtime_requests')->where('user_id', $this->emp->id)->value('status'));

        // Cap of 1 day a month: the 2nd Saturday (same month or not) is checked against its own month.
        $this->policy(['comp_off_enabled' => 1, 'comp_off_monthly_cap' => 1]);
        $sameMonth = Carbon::parse($this->sat1)->format('Y-m') === Carbon::parse($this->sat2)->format('Y-m');
        $reason = $this->svc()->dayInfo($this->tenantId, $this->emp->id, $this->sat2)['reason'];
        $sameMonth ? $this->assertStringContainsString('per month', $reason) : $this->assertNull($reason);

        // A swapped week-off already gave another day off.
        $swapId = DB::table('weekoff_swaps')->insertGetId(['tenant_id' => $this->tenantId, 'worked_date' => $this->sat2, 'replacement_date' => Carbon::parse($this->sat2)->addDays(2)->toDateString(), 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('weekoff_swap_employees')->insert(['tenant_id' => $this->tenantId, 'weekoff_swap_id' => $swapId, 'user_id' => $this->emp->id,
            'worked_date' => $this->sat2, 'replacement_date' => Carbon::parse($this->sat2)->addDays(2)->toDateString(), 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $this->policy(['comp_off_enabled' => 1, 'comp_off_monthly_cap' => 0]);
        $this->assertSame('A week-off swap already gave another day off for it', $this->svc()->dayInfo($this->tenantId, $this->emp->id, $this->sat2)['reason']);
    }

    public function test_auto_mode_credits_and_expiry_lapses_the_unused_part_oldest_first(): void
    {
        $this->policy(['comp_off_enabled' => 1, 'comp_off_mode' => 'auto', 'comp_off_expiry_days' => 10]);
        $this->worked($this->sat1, 9);
        $this->worked($this->sat2, 9);

        $done = $this->svc()->autoCredit($this->tenantId, $this->emp->id, $this->sat1, Carbon::yesterday()->toDateString());
        $this->assertSame([$this->sat1, $this->sat2], $done);
        $this->assertEquals(2.0, $this->svc()->balance($this->tenantId, $this->emp->id));

        // Half a day taken as leave: balance 1.5. When the older credit expires, only its unused half lapses.
        $typeId = CompOffCredit::withoutGlobalScopes()->where('user_id', $this->emp->id)->value('leave_type_id');
        DB::table('leave_balances')->where('user_id', $this->emp->id)->where('leave_type_id', $typeId)->update(['balance' => 1.5]);
        $n = $this->svc()->expire(Carbon::parse($this->sat1)->addDays(11)->toDateString());

        $this->assertSame(1, $n);
        $old = CompOffCredit::withoutGlobalScopes()->where('user_id', $this->emp->id)->where('worked_date', $this->sat1)->first();
        $this->assertSame(['expired', 0.5], [$old->status, $old->lapsed_days]);
        $this->assertEquals(1.0, $this->svc()->balance($this->tenantId, $this->emp->id));
    }

    public function test_the_approval_workflow_handler_and_the_settings_card(): void
    {
        $this->worked($this->sat1, 9);
        $credit = $this->svc()->request($this->emp, $this->sat1, null);
        $request = new ApprovalRequest(['tenant_id' => $this->tenantId, 'request_type' => 'comp_off', 'subject_id' => $credit->id]);

        app(CompOffApprovalHandler::class)->approved($request, $this->admin);
        $this->assertSame('approved', $credit->fresh()->status);
        $this->assertEquals(1.0, $this->svc()->balance($this->tenantId, $this->emp->id));

        $this->actingAs($this->admin)->get(route('workforce-settings.index'))->assertOk()->assertSee('Comp-off');
    }
}
