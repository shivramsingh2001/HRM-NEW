<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\LeaveCarryForwardService;
use App\Services\LeaveYearService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Leave carry forward: leave-type fields, the Company Policies switch and
 * LeaveCarryForwardService (year-end lapse + expiry). Runs on the dev DB inside
 * a transaction that is always rolled back.
 */
class LeaveCarryForwardTest extends TestCase
{
    private int $tenantId;
    private User $admin;
    private User $e1;
    private User $e2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::withoutGlobalScopes()->where('role', 'admin')->where('status', 1)->whereNotNull('tenant_id')->orderBy('id')->firstOrFail();
        $this->tenantId = (int) $this->admin->tenant_id;
        $employees = User::withoutGlobalScopes()->where('tenant_id', $this->tenantId)->where('role', '!=', 'admin')
            ->where('status', 1)->orderBy('id')->limit(2)->get();
        if ($employees->count() < 2) {
            $this->markTestSkipped('Needs two active employees.');
        }
        [$this->e1, $this->e2] = [$employees[0], $employees[1]];

        DB::beginTransaction();
        DB::table('tenants')->where('id', $this->tenantId)->update([
            'leave_carry_forward_enabled' => 1,
            'leave_carry_forward_enabled_at' => '2027-01-01 00:00:00',
            'leave_year_start_month' => 4,
            'leave_year_start_day' => 1,
        ]);
        app(LeaveYearService::class)->forget();
    }

    protected function tearDown(): void
    {
        while (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
        app(LeaveYearService::class)->forget();

        parent::tearDown();
    }

    private function type(?float $limit, ?int $expiry, array $extra = []): int
    {
        return DB::table('leave_types')->insertGetId($extra + [
            'tenant_id' => $this->tenantId,
            'name' => 'CF test ' . uniqid(),
            'credit_type' => 'yearly',
            'credit_value' => 12,
            'status' => 1,
            'is_unpaid' => 0,
            'max_carry_forward' => $limit,
            'carry_forward_expiry_months' => $expiry,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function balance(User $u, int $typeId, float $balance): void
    {
        DB::table('leave_balances')->insert([
            'tenant_id' => $this->tenantId, 'user_id' => $u->id, 'leave_type_id' => $typeId,
            'balance' => $balance, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function tx(User $u, int $typeId, string $type, float $days, string $at, ?int $leaveId = null): void
    {
        DB::table('leave_transactions')->insert([
            'tenant_id' => $this->tenantId, 'leave_id' => $leaveId, 'user_id' => $u->id, 'leave_type' => $typeId,
            'transaction_type' => $type, 'total_leaves' => $days, 'leaves_count' => $days,
            'before_leaves' => 0, 'after_leaves' => 0, 'transaction_date' => $at, 'leave_detail' => 'paid',
            'remarks' => 'cf test', 'status' => 1, 'created_at' => $at, 'updated_at' => $at,
        ]);
    }

    private function bal(User $u, int $typeId): float
    {
        return (float) DB::table('leave_balances')->where('user_id', $u->id)->where('leave_type_id', $typeId)->value('balance');
    }

    private function run_(string $date, bool $dry = false): array
    {
        return app(LeaveCarryForwardService::class)->run($this->tenantId, Carbon::parse($date), $dry);
    }

    public function test_days_above_the_limit_lapse_and_run_is_idempotent(): void
    {
        $t = $this->type(5, null);
        $this->balance($this->e1, $t, 12);

        $s = $this->run_('2027-04-01');

        $this->assertSame('2027-04-01', $s['year_start']);
        $this->assertEquals(5, $this->bal($this->e1, $t));
        $row = DB::table('leave_carry_forwards')->where('user_id', $this->e1->id)->where('leave_type_id', $t)->first();
        $this->assertEquals(12, (float) $row->closing_balance);
        $this->assertEquals(5, (float) $row->carried);
        $this->assertEquals(7, (float) $row->lapsed);
        $this->assertNull($row->expires_on);
        $lapse = DB::table('leave_transactions')->where('user_id', $this->e1->id)->where('leave_type', $t)
            ->where('remarks', 'like', 'Carry forward:%')->first();
        $this->assertSame('sub', $lapse->transaction_type);
        $this->assertEquals(7, (float) $lapse->leaves_count);
        $this->assertStringContainsString('2027-28', $lapse->remarks);

        // Second run in the same leave year changes nothing.
        $this->run_('2027-04-02');
        $this->assertEquals(5, $this->bal($this->e1, $t));
        $this->assertSame(1, DB::table('leave_carry_forwards')->where('user_id', $this->e1->id)->where('leave_type_id', $t)->count());
    }

    public function test_limit_zero_lapses_everything_and_no_limit_type_is_untouched(): void
    {
        $zero = $this->type(0, null);
        $open = $this->type(null, null);
        $this->balance($this->e1, $zero, 4);
        $this->balance($this->e1, $open, 9);

        $this->run_('2027-04-01');

        $this->assertEquals(0, $this->bal($this->e1, $zero));
        $this->assertEquals(9, $this->bal($this->e1, $open));
        $this->assertSame(0, DB::table('leave_carry_forwards')->where('leave_type_id', $open)->count());
    }

    public function test_credit_added_on_the_new_year_morning_is_not_lapsed(): void
    {
        $t = $this->type(5, null);
        // Closing 12, then this morning's yearly credit of 3 → balance 15.
        $this->balance($this->e1, $t, 15);
        $this->tx($this->e1, $t, 'add', 3, '2027-04-01 00:30:00');

        $this->run_('2027-04-01');

        $this->assertEquals(8, $this->bal($this->e1, $t)); // 5 carried + 3 new
    }

    public function test_unpaid_and_never_credited_types_are_skipped(): void
    {
        $unpaid = $this->type(0, null, ['is_unpaid' => 1]);
        $never = $this->type(0, null, ['credit_type' => 'no', 'credit_value' => 0]);
        $this->balance($this->e1, $unpaid, 3);
        $this->balance($this->e1, $never, 3);

        $this->run_('2027-04-01');

        $this->assertEquals(3, $this->bal($this->e1, $unpaid));
        $this->assertEquals(3, $this->bal($this->e1, $never));
    }

    public function test_switched_off_or_switched_on_mid_year_does_nothing(): void
    {
        $t = $this->type(5, null);
        $this->balance($this->e1, $t, 12);

        DB::table('tenants')->where('id', $this->tenantId)->update(['leave_carry_forward_enabled' => 0]);
        $this->assertNotNull($this->run_('2027-04-01')['skipped']);
        $this->assertEquals(12, $this->bal($this->e1, $t));

        // On again, but only since May: the leave year that began 1 April is not touched.
        DB::table('tenants')->where('id', $this->tenantId)->update(['leave_carry_forward_enabled' => 1, 'leave_carry_forward_enabled_at' => '2027-05-01 10:00:00']);
        $this->run_('2027-06-01');
        $this->assertEquals(12, $this->bal($this->e1, $t));
        $this->assertSame(0, DB::table('leave_carry_forwards')->where('leave_type_id', $t)->count());

        // The next leave year is.
        $this->run_('2028-04-01');
        $this->assertEquals(5, $this->bal($this->e1, $t));
    }

    public function test_dry_run_changes_nothing(): void
    {
        $t = $this->type(5, null);
        $this->balance($this->e1, $t, 12);

        $s = $this->run_('2027-04-01', true);

        $this->assertSame(1, $s['lapsed_rows']);
        $this->assertEquals(12, $this->bal($this->e1, $t));
        $this->assertSame(0, DB::table('leave_carry_forwards')->where('leave_type_id', $t)->count());
    }

    public function test_unused_carried_days_expire_after_the_set_months(): void
    {
        $t = $this->type(5, 3);
        $leaveId = (int) (DB::table('leaves')->where('tenant_id', $this->tenantId)->value('id') ?? 0) ?: null;
        $this->balance($this->e1, $t, 12);
        $this->balance($this->e2, $t, 12);

        $this->run_('2027-04-01');
        $this->assertSame('2027-07-01', (string) DB::table('leave_carry_forwards')->where('user_id', $this->e1->id)->where('leave_type_id', $t)->value('expires_on'));

        // e1 uses 2 of the 5 carried days, e2 uses 6 (more than carried).
        $this->tx($this->e1, $t, 'sub', 2, '2027-05-10 10:00:00', $leaveId);
        DB::table('leave_balances')->where('user_id', $this->e1->id)->where('leave_type_id', $t)->update(['balance' => 3]);
        $this->tx($this->e2, $t, 'sub', 6, '2027-05-10 10:00:00', $leaveId);
        DB::table('leave_balances')->where('user_id', $this->e2->id)->where('leave_type_id', $t)->update(['balance' => 0]);

        $this->run_('2027-06-30'); // not yet
        $this->assertEquals(3, $this->bal($this->e1, $t));

        $s = $this->run_('2027-07-01');
        $this->assertSame(1, $s['expired_rows']);
        $this->assertEquals(0, $this->bal($this->e1, $t));   // 3 unused carried days expired
        $this->assertEquals(0, $this->bal($this->e2, $t));   // nothing left to expire
        $row = DB::table('leave_carry_forwards')->where('user_id', $this->e1->id)->where('leave_type_id', $t)->first();
        $this->assertEquals(3, (float) $row->expired);
        $this->assertNotNull($row->expired_at);
    }

    public function test_leave_type_form_saves_and_validates_carry_forward(): void
    {
        $this->actingAs($this->admin)->withSession(['tenant_id' => $this->tenantId]);

        $bad = $this->postJson(route('leave-type.create'), [
            'name' => 'CF form bad', 'credit_type' => 'yearly', 'credit_value' => 12, 'max_carry_forward' => -1,
        ]);
        $bad->assertStatus(422)->assertJsonValidationErrors('max_carry_forward');

        $this->postJson(route('leave-type.create'), [
            'name' => 'CF form ok', 'credit_type' => 'yearly', 'credit_value' => 12,
            'max_carry_forward' => 5, 'carry_forward_expiry_months' => 3,
        ])->assertOk();
        $row = DB::table('leave_types')->where('tenant_id', $this->tenantId)->where('name', 'CF form ok')->first();
        $this->assertEquals(5, (float) $row->max_carry_forward);
        $this->assertSame(3, (int) $row->carry_forward_expiry_months);

        // Unpaid → both cleared.
        $this->postJson(route('leave-type.create'), [
            'name' => 'CF form unpaid', 'credit_type' => 'yearly', 'credit_value' => 12, 'is_unpaid' => 1,
            'max_carry_forward' => 5, 'carry_forward_expiry_months' => 3,
        ])->assertOk();
        $row = DB::table('leave_types')->where('tenant_id', $this->tenantId)->where('name', 'CF form unpaid')->first();
        $this->assertNull($row->max_carry_forward);
        $this->assertNull($row->carry_forward_expiry_months);

        $this->get(route('leave-type.index'))->assertOk()->assertSee('Carry forward');
    }

    public function test_carry_forward_is_required_capped_at_credit_value_and_expiry_at_12_months(): void
    {
        $this->actingAs($this->admin)->withSession(['tenant_id' => $this->tenantId]);
        $base = ['credit_type' => 'monthly', 'credit_value' => 1.5];

        $this->postJson(route('leave-type.create'), $base + ['name' => 'CF cap blank'])
            ->assertStatus(422)->assertJsonValidationErrors('max_carry_forward');
        $this->postJson(route('leave-type.create'), $base + ['name' => 'CF cap over', 'max_carry_forward' => 2])
            ->assertStatus(422)->assertJsonValidationErrors('max_carry_forward');
        $this->postJson(route('leave-type.create'), ['name' => 'CF exp 13', 'credit_type' => 'yearly', 'credit_value' => 12,
            'max_carry_forward' => 12, 'carry_forward_expiry_months' => 13])
            ->assertStatus(422)->assertJsonValidationErrors('carry_forward_expiry_months');

        $this->postJson(route('leave-type.create'), ['name' => 'CF exp 12', 'credit_type' => 'yearly', 'credit_value' => 12,
            'max_carry_forward' => 12, 'carry_forward_expiry_months' => 12])->assertOk();
        $this->assertSame(12, (int) DB::table('leave_types')->where('tenant_id', $this->tenantId)->where('name', 'CF exp 12')->value('carry_forward_expiry_months'));

        // Monthly: carry forward = credit value is fine; expiry is not kept.
        $this->postJson(route('leave-type.create'), $base + ['name' => 'CF cap ok', 'max_carry_forward' => 1.5, 'carry_forward_expiry_months' => 3])->assertOk();
        $row = DB::table('leave_types')->where('tenant_id', $this->tenantId)->where('name', 'CF cap ok')->first();
        $this->assertEquals(1.5, (float) $row->max_carry_forward);
        $this->assertNull($row->carry_forward_expiry_months);
    }

    public function test_monthly_type_carries_up_to_the_limit_into_next_month(): void
    {
        $t = $this->type(1.5, null, ['credit_type' => 'monthly', 'credit_value' => 1.5]);
        $this->balance($this->e1, $t, 4);

        $this->run_('2027-12-01');
        $this->assertEquals(1.5, $this->bal($this->e1, $t));
        $row = DB::table('leave_carry_forwards')->where('user_id', $this->e1->id)->where('leave_type_id', $t)->first();
        $this->assertSame('2027-12-01', (string) $row->leave_year_start);
        $this->assertEquals(2.5, (float) $row->lapsed);
        $this->assertNull($row->expires_on);
        $remark = DB::table('leave_transactions')->where('user_id', $this->e1->id)->where('leave_type', $t)
            ->where('remarks', 'like', 'Carry forward:%')->value('remarks');
        $this->assertStringContainsString('Nov 2027', $remark);

        // Same month again → nothing; the December credit is never lapsed.
        $this->tx($this->e1, $t, 'add', 1.5, '2027-12-01 00:30:00');
        DB::table('leave_balances')->where('user_id', $this->e1->id)->where('leave_type_id', $t)->update(['balance' => 3]);
        $this->run_('2027-12-15');
        $this->assertEquals(3, $this->bal($this->e1, $t));

        // Next month: closing 3 → 1.5 carries.
        $this->run_('2028-01-01');
        $this->assertEquals(1.5, $this->bal($this->e1, $t));
        $this->assertSame(2, DB::table('leave_carry_forwards')->where('user_id', $this->e1->id)->where('leave_type_id', $t)->count());
    }

    public function test_weekly_type_carries_up_to_the_limit_into_next_week(): void
    {
        $t = $this->type(1, null, ['credit_type' => 'weekly', 'credit_value' => 1]);
        $this->balance($this->e1, $t, 3);

        $this->run_('2027-12-06'); // a Monday
        $this->assertEquals(1, $this->bal($this->e1, $t));
        $this->assertSame('2027-12-06', (string) DB::table('leave_carry_forwards')->where('user_id', $this->e1->id)->where('leave_type_id', $t)->value('leave_year_start'));
    }

    public function test_company_policy_switch_records_when_it_was_turned_on(): void
    {
        DB::table('tenants')->where('id', $this->tenantId)->update(['leave_carry_forward_enabled' => 0, 'leave_carry_forward_enabled_at' => null]);
        $this->actingAs($this->admin)->withSession(['tenant_id' => $this->tenantId]);

        $this->put(route('leave-carry-forward-settings.update'), [
            'leave_carry_forward_enabled' => 1, 'leave_year_start_month' => 1, 'leave_year_start_day' => 1,
        ])->assertRedirect();

        $t = DB::table('tenants')->where('id', $this->tenantId)->first();
        $this->assertSame(1, (int) $t->leave_carry_forward_enabled);
        $this->assertNotNull($t->leave_carry_forward_enabled_at);
        $this->assertSame(1, (int) $t->leave_year_start_month);
        $this->assertSame('2027-01-01', app(LeaveYearService::class)->startFor($this->tenantId, Carbon::parse('2027-06-15'))->toDateString());

        $this->put(route('leave-carry-forward-settings.update'), [
            'leave_carry_forward_enabled' => 1, 'leave_year_start_month' => 2, 'leave_year_start_day' => 30,
        ])->assertSessionHasErrors('leave_year_start_day');

        $this->get(route('workforce-settings.index'))->assertOk()->assertSee('Leave carry forward');
    }
}
