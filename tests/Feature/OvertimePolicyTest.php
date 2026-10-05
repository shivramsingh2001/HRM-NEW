<?php

namespace Tests\Feature;

use App\Models\OvertimeRequest;
use App\Models\User;
use App\Services\Attendance\AutoOvertimeService;
use App\Services\Payroll\OvertimePayService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Company Policies → Overtime: switch, request & approval vs automatic mode,
 * limits, rate type. Dev DB, everything inside a rolled-back transaction.
 */
class OvertimePolicyTest extends TestCase
{
    private int $tenantId;
    private User $admin;
    private User $emp;
    private int $shiftId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::withoutGlobalScopes()->where('role', 'admin')->where('status', 1)->whereNotNull('tenant_id')->orderBy('id')->firstOrFail();
        $this->tenantId = (int) $this->admin->tenant_id;
        $this->emp = User::withoutGlobalScopes()->where('tenant_id', $this->tenantId)->where('role', 'employee')
            ->where('status', 1)->orderBy('id')->first() ?? $this->markTestSkipped('Needs an active employee.');

        DB::beginTransaction();
        $this->shiftId = $this->shift('09:00:00', '18:00:00', 10);
        DB::table('tenants')->where('id', $this->tenantId)->update(['custom_shifts_enabled' => 0, 'default_shift_id' => $this->shiftId]);
        DB::table('employee_policy_overrides')->where('tenant_id', $this->tenantId)->where('user_id', $this->emp->id)->where('section', 'overtime')->delete();
        DB::table('overtime_requests')->where('user_id', $this->emp->id)->whereBetween('date', ['2019-03-01', '2019-03-31'])->delete();
        $this->settings(['enabled' => 1, 'mode' => 'request', 'min_hours' => null, 'max_hours_per_day' => null,
            'max_hours_per_month' => null, 'rate_type' => 'multiplier', 'rate_multiplier' => 1.5, 'fixed_rate_per_hour' => null,
            'require_approval' => 1, 'auto_approve_limit' => null, 'auto_start_basis' => 'grace', 'auto_start_after_minutes' => 0]);
    }

    protected function tearDown(): void
    {
        while (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
        parent::tearDown();
    }

    private function shift(string $start, string $end, int $grace, bool $overnight = false): int
    {
        return DB::table('shifts')->insertGetId([
            'tenant_id' => $this->tenantId, 'name' => 'OT test ' . uniqid(), 'start_time' => $start, 'end_time' => $end,
            'is_overnight' => $overnight ? 1 : 0, 'total_hours' => '9', 'grace_minutes' => $grace, 'break_time' => 0,
            'status' => 1, 'created_by' => $this->admin->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function settings(array $values): void
    {
        DB::table('overtime_settings')->updateOrInsert(['tenant_id' => $this->tenantId], $values + ['updated_at' => now()]);
    }

    private function attendance(string $date, ?string $clockOut, ?string $remarks = null): void
    {
        DB::table('attendances')->where('tenant_id', $this->tenantId)->where('user_id', $this->emp->id)->where('date', $date)->delete();
        DB::table('attendances')->insert([
            'tenant_id' => $this->tenantId, 'user_id' => $this->emp->id, 'date' => $date,
            'clock_in' => $date . ' 09:00:00', 'clock_out' => $clockOut, 'remarks' => $remarks,
            'status' => 1, 'attendance_status' => 'present', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function sync(string $date): ?OvertimeRequest
    {
        return app(AutoOvertimeService::class)->syncDay((int) $this->emp->id, $this->tenantId, $date);
    }

    // ------------------------------------------------------------- automatic mode

    public function test_fixed_start_counts_from_shift_end_plus_minutes(): void
    {
        $this->settings(['mode' => 'auto', 'auto_start_basis' => 'fixed', 'auto_start_after_minutes' => 30]);
        $this->attendance('2019-03-04', '2019-03-04 20:00:00');

        $row = $this->sync('2019-03-04');

        $this->assertNotNull($row);
        $this->assertSame('auto', $row->source);
        $this->assertSame('approved', $row->status);
        $this->assertEquals(1.5, (float) $row->approved_hours); // 18:30 → 20:00
        $this->assertSame(90, (int) $row->auto_minutes);

        // Re-sync changes nothing and never duplicates.
        $this->sync('2019-03-04');
        $this->assertSame(1, OvertimeRequest::withoutGlobalScopes()->where('user_id', $this->emp->id)->whereDate('date', '2019-03-04')->count());
    }

    public function test_grace_basis_and_early_missing_and_system_clock_out_give_nothing(): void
    {
        $this->settings(['mode' => 'auto', 'auto_start_basis' => 'grace']);

        $this->attendance('2019-03-05', '2019-03-05 18:05:00'); // inside the 10-min grace
        $this->assertNull($this->sync('2019-03-05'));

        $this->attendance('2019-03-06', '2019-03-06 17:00:00'); // left early
        $this->assertNull($this->sync('2019-03-06'));

        $this->attendance('2019-03-07', null); // no clock-out
        $this->assertNull($this->sync('2019-03-07'));

        $this->attendance('2019-03-08', '2019-03-09 00:00:00', 'Auto clock-out (cap 15h) on 2019-03-09 00:00');
        $this->assertNull($this->sync('2019-03-08'));

        $this->attendance('2019-03-11', '2019-03-11 19:10:00'); // grace: from 18:10 → 1 h
        $this->assertEquals(1.0, (float) $this->sync('2019-03-11')->approved_hours);

        // Clock-out corrected to before the start → the automatic entry is removed.
        DB::table('attendances')->where('user_id', $this->emp->id)->where('date', '2019-03-11')->update(['clock_out' => '2019-03-11 18:00:00']);
        $this->assertNull($this->sync('2019-03-11'));
        $this->assertFalse(OvertimeRequest::withoutGlobalScopes()->where('user_id', $this->emp->id)->whereDate('date', '2019-03-11')->exists());
    }

    public function test_overnight_shift_uses_the_next_day_end(): void
    {
        $night = $this->shift('22:00:00', '06:00:00', 0, true);
        DB::table('tenants')->where('id', $this->tenantId)->update(['default_shift_id' => $night]);
        $this->settings(['mode' => 'auto', 'auto_start_basis' => 'fixed', 'auto_start_after_minutes' => 0]);
        $this->attendance('2019-03-12', '2019-03-13 07:30:00');

        $this->assertEquals(1.5, (float) $this->sync('2019-03-12')->approved_hours);
    }

    public function test_minimum_daily_and_monthly_limits(): void
    {
        $this->settings(['mode' => 'auto', 'auto_start_basis' => 'fixed', 'auto_start_after_minutes' => 0,
            'min_hours' => 1, 'max_hours_per_day' => 2, 'max_hours_per_month' => 3]);

        $this->attendance('2019-03-13', '2019-03-13 18:30:00'); // 0.5 h < minimum
        $this->assertNull($this->sync('2019-03-13'));

        $this->attendance('2019-03-14', '2019-03-14 22:00:00'); // 4 h → capped at 2
        $this->assertEquals(2.0, (float) $this->sync('2019-03-14')->approved_hours);

        $this->attendance('2019-03-15', '2019-03-15 21:00:00'); // 3 h, 1 h left in the month
        $this->assertEquals(1.0, (float) $this->sync('2019-03-15')->approved_hours);

        $this->attendance('2019-03-18', '2019-03-18 21:00:00'); // month full
        $this->assertNull($this->sync('2019-03-18'));
    }

    public function test_hr_adjusted_entry_and_requests_are_never_overwritten_and_employee_cannot_request(): void
    {
        $this->settings(['mode' => 'auto', 'auto_start_basis' => 'fixed', 'auto_start_after_minutes' => 0]);
        $this->attendance('2019-03-19', '2019-03-19 20:00:00');
        $row = $this->sync('2019-03-19');
        $row->update(['approved_hours' => 0.5, 'manually_adjusted_at' => now()]);

        DB::table('attendances')->where('user_id', $this->emp->id)->where('date', '2019-03-19')->update(['clock_out' => '2019-03-19 23:00:00']);
        $this->assertEquals(0.5, (float) $this->sync('2019-03-19')->approved_hours);

        $this->actingAs($this->emp)->postJson(route('overtime.store'), [
            'date' => now()->addDay()->toDateString(), 'overtime_hours' => 2, 'reason' => 'Release work',
        ])->assertStatus(422)->assertJsonFragment(['message' => 'Overtime is calculated automatically from your attendance — no request is needed.']);
    }

    public function test_request_mode_does_not_calculate_automatically(): void
    {
        $this->attendance('2019-03-20', '2019-03-20 21:00:00');
        $this->assertNull($this->sync('2019-03-20'));
    }

    // ------------------------------------------------------------- request mode

    public function test_request_limits_switch_off_and_rejected_date_can_be_raised_again(): void
    {
        $this->settings(['min_hours' => 1, 'max_hours_per_day' => 3, 'max_hours_per_month' => 4]);
        $date = now()->addDays(2)->toDateString();
        $this->actingAs($this->emp);

        $this->postJson(route('overtime.store'), ['date' => $date, 'overtime_hours' => 0.5, 'reason' => 'Too short'])
            ->assertStatus(422)->assertJsonFragment(['message' => 'Overtime must be at least 1 hour per day.']);
        $this->postJson(route('overtime.store'), ['date' => $date, 'overtime_hours' => 4, 'reason' => 'Too long'])
            ->assertStatus(422)->assertJsonFragment(['message' => 'Overtime hours cannot exceed 3 hours per day.']);

        $this->postJson(route('overtime.store'), ['date' => $date, 'overtime_hours' => 3, 'reason' => 'Stock count'])->assertOk();
        $this->postJson(route('overtime.store'), ['date' => $date, 'overtime_hours' => 2, 'reason' => 'Again'])
            ->assertStatus(422)->assertJsonFragment(['message' => 'You have already submitted an overtime request for this date']);

        // Monthly: 3 booked + 2 > 4 (only when the next date is in the same month).
        $other = now()->addDays(2)->day <= 27 ? now()->addDays(3)->toDateString() : null;
        if ($other) {
            $this->postJson(route('overtime.store'), ['date' => $other, 'overtime_hours' => 2, 'reason' => 'Month cap'])
                ->assertStatus(422);
        }

        // Rejected → the same date can be raised again (row reopened as pending).
        $row = OvertimeRequest::withoutGlobalScopes()->where('user_id', $this->emp->id)->whereDate('date', $date)->first();
        $row->update(['status' => 'rejected', 'rejection_reason' => 'No']);
        $this->postJson(route('overtime.store'), ['date' => $date, 'overtime_hours' => 1, 'reason' => 'Retry'])->assertOk();
        $row->refresh();
        $this->assertSame('pending', $row->status);
        $this->assertEquals(1, (float) $row->overtime_hours);
        $this->assertNull($row->rejection_reason);

        // Switched off → nothing new.
        $this->settings(['enabled' => 0]);
        $this->postJson(route('overtime.store'), ['date' => now()->addDays(5)->toDateString(), 'overtime_hours' => 1, 'reason' => 'Off'])
            ->assertStatus(422)->assertJsonFragment(['message' => 'Overtime is turned off for your company.']);
    }

    // ------------------------------------------------------------- pay + settings

    public function test_rate_is_multiplier_or_fixed_per_hour(): void
    {
        $pay = app(OvertimePayService::class);
        $this->assertEquals(150.0, $pay->ratePerHour($this->tenantId, (int) $this->emp->id, 100));

        $this->settings(['rate_type' => 'fixed', 'fixed_rate_per_hour' => 250]);
        $this->assertEquals(250.0, app(OvertimePayService::class)->ratePerHour($this->tenantId, (int) $this->emp->id, 100));
        $this->assertSame('₹250.00/hour', app(OvertimePayService::class)->describe($this->tenantId, (int) $this->emp->id)['label']);
    }

    public function test_company_policies_cards_save_and_old_page_redirects(): void
    {
        $this->actingAs($this->admin)->withSession(['tenant_id' => $this->tenantId]);

        $this->put(route('overtime-policy-settings.update'), [
            'section' => 'mode', 'enabled' => 1, 'mode' => 'auto', 'auto_start_basis' => 'fixed', 'auto_start_after_minutes' => 30,
        ])->assertSessionHasNoErrors()->assertRedirect();
        $row = DB::table('overtime_settings')->where('tenant_id', $this->tenantId)->first();
        $this->assertSame('auto', $row->mode);
        $this->assertSame(30, (int) $row->auto_start_after_minutes);

        $this->put(route('overtime-policy-settings.update'), [
            'section' => 'limits', 'min_hours' => 1, 'max_hours_per_day' => 4, 'max_hours_per_month' => 40,
            'rate_type' => 'fixed', 'fixed_rate_per_hour' => 200,
        ])->assertSessionHasNoErrors();
        $row = DB::table('overtime_settings')->where('tenant_id', $this->tenantId)->first();
        $this->assertSame('fixed', $row->rate_type);
        $this->assertEquals(200, (float) $row->fixed_rate_per_hour);
        $this->assertEquals(1, (float) $row->min_hours);

        $this->put(route('overtime-policy-settings.update'), [
            'section' => 'limits', 'min_hours' => 5, 'max_hours_per_day' => 4, 'rate_type' => 'multiplier', 'rate_multiplier' => 1.5,
        ])->assertSessionHasErrors('max_hours_per_day');

        $this->get(route('overtime.settings'))->assertRedirect(route('workforce-settings.index') . '#overtime');
        $this->get(route('workforce-settings.index'))->assertOk()->assertSee('Overtime limits');

        $this->actingAs($this->emp)->put(route('overtime-policy-settings.update'), ['section' => 'mode', 'mode' => 'request'])
            ->assertForbidden();
    }
}
