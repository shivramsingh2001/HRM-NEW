<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Payroll\PayrollCalculationEngine;
use App\Services\Payroll\ShiftAllowanceCalculator;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Shift allowance (Shifts → allowance per day / per hour) paid by both payroll
 * engines: worked days only, half day = 50%, minimum hours, per-hour capped at
 * the shift's hours, multi-shift days paid per shift worked. Dev DB, always
 * rolled back.
 */
class ShiftAllowanceTest extends TestCase
{
    private User $admin;
    private int $tenantId;
    private string $month;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::withoutGlobalScopes()->where('role', 'admin')->where('status', 1)->whereNotNull('tenant_id')->orderBy('id')->firstOrFail();
        $this->tenantId = (int) $this->admin->tenant_id;
        app()->instance('current_tenant', \App\Models\Tenant::find($this->tenantId));
        // A month nobody has attendance in yet.
        $this->month = now()->addMonthsNoOverflow(3)->format('Y-m');
        DB::beginTransaction();
    }

    protected function tearDown(): void
    {
        while (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
        parent::tearDown();
    }

    private function shift(string $name, string $type, float $amount, ?float $minHours = null, float $hours = 8): int
    {
        return DB::table('shifts')->insertGetId([
            'tenant_id' => $this->tenantId, 'name' => $name . ' ' . uniqid(), 'start_time' => '22:00:00', 'end_time' => '06:00:00',
            'is_overnight' => 1, 'total_hours' => $hours, 'break_time' => 0, 'grace_minutes' => 0, 'status' => 1,
            'allowance_type' => $type, 'allowance_amount' => $amount, 'allowance_min_hours' => $minHours, 'created_by' => $this->admin->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function day(int $userId, int $d, string $status, float $hours, ?int $shiftId, ?string $effective = null): int
    {
        return DB::table('attendances')->insertGetId([
            'tenant_id' => $this->tenantId, 'user_id' => $userId, 'date' => $this->month . '-' . str_pad((string) $d, 2, '0', STR_PAD_LEFT),
            'clock_in' => $this->month . '-01 22:00:00', 'worked_hours' => $hours, 'shift_id' => $shiftId,
            'attendance_status' => $status, 'effective_status' => $effective ?? $status, 'attendance_type' => 'manual',
            'status' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function employee(): User
    {
        return User::withoutGlobalScopes()->forceCreate([
            'name' => 'PHPUnit SA ' . uniqid(), 'email' => 'sa.' . uniqid() . '@phpunit.test',
            'employee_id' => 'SA' . random_int(100000, 999999), 'password' => bcrypt('secret'),
            'role' => 'employee', 'status' => 1, 'tenant_id' => $this->tenantId,
        ]);
    }

    public function test_calculator_pays_worked_days_half_days_min_hours_and_per_hour_cap(): void
    {
        $user = $this->employee();
        $night = $this->shift('Night', 'per_day', 100, 4);
        $late = $this->shift('Late', 'per_hour', 20, null, 8);
        $plain = $this->shift('Plain', 'none', 0);

        $this->day($user->id, 1, 'present', 8, $night);                 // 100
        $this->day($user->id, 2, 'present', 5, $night, 'half_day');     // 50 (half day)
        $this->day($user->id, 3, 'present', 3, $night);                 // 0  (under 4 h)
        $this->day($user->id, 4, 'on_leave', 0, $night);                // 0  (leave)
        $this->day($user->id, 5, 'late', 10, $late);                    // 20 × 8 h (capped) = 160
        $this->day($user->id, 6, 'present', 9, $plain);                 // 0  (no allowance)

        // Multi-shift day: plain primary + a night extra shift, paid per shift worked.
        $multi = $this->day($user->id, 7, 'present', 14, $plain);
        foreach ([[$plain, 480], [$night, 360]] as [$shiftId, $minutes]) {
            DB::table('attendance_shift_segments')->insert(['tenant_id' => $this->tenantId, 'attendance_id' => $multi, 'user_id' => $user->id,
                'date' => $this->month . '-07', 'shift_id' => $shiftId, 'is_additional' => $shiftId === $night, 'worked_minutes' => $minutes,
                'created_at' => now(), 'updated_at' => now()]);
        }                                                               // 100 for the night segment

        $r = app(ShiftAllowanceCalculator::class)->calculate($this->tenantId, $user->id, $this->month);

        $this->assertSame(410.0, $r['amount']);
        $this->assertSame(3.5, $r['days']);
        $byShift = collect($r['breakdown'])->keyBy('shift_id');
        $this->assertSame(250.0, $byShift[$night]['amount']);
        $this->assertSame(2.5, $byShift[$night]['days']);
        $this->assertSame(160.0, $byShift[$late]['amount']);
        $this->assertSame(8.0, $byShift[$late]['hours']);
        $this->assertMatchesRegularExpression('/^Shift Allowance \(Night \S+ × 2\.5 days, Late \S+ × 8 h\)$/', $r['label']);

        // No allowance shifts / no attendance → nothing.
        $this->assertSame(0.0, app(ShiftAllowanceCalculator::class)->calculate($this->tenantId, $this->employee()->id, $this->month)['amount']);
    }

    public function test_dynamic_engine_adds_a_taxable_shift_allowance_line(): void
    {
        $employee = User::withoutGlobalScopes()->where('tenant_id', $this->tenantId)->where('status', 1)
            ->whereHas('currentDynamicPayrollStructure')->orderBy('id')->first();
        if (! $employee) {
            $this->markTestSkipped('Needs an employee with a dynamic salary structure.');
        }
        $night = $this->shift('Night', 'per_day', 150);
        foreach ([1, 2, 3] as $d) {
            $this->day($employee->id, $d, 'present', 8, $night);
        }

        $result = app(PayrollCalculationEngine::class)->calculate($employee, $this->tenantId, $this->month, false);

        $line = collect($result['line_items'])->firstWhere('code', 'shift_allowance');
        $this->assertNotNull($line);
        $this->assertSame(450.0, (float) $line['amount']);
        $this->assertTrue($line['is_taxable']);
        $this->assertSame('earning', $line['component_type']);
        $this->assertStringContainsString('× 3 days', $line['name']);
        $this->assertSame(450.0, $result['shift_allowance_amount']);
        $this->assertSame(3.0, $result['shift_allowance_days']);
    }

    public function test_legacy_engine_stores_the_allowance_on_the_payslip(): void
    {
        $month = Carbon::createFromFormat('Y-m', $this->month);
        $employee = User::withoutGlobalScopes()->where('tenant_id', $this->tenantId)->where('status', 1)->orderBy('id')->get()
            ->first(fn ($u) => \App\Models\UserPayroll::withoutGlobalScopes()->where('user_id', $u->id)->where('tenant_id', $this->tenantId)
                ->where('effective_from', '<=', $month->copy()->endOfMonth())->whereHas('payrollMaster')->exists());
        if (! $employee) {
            $this->markTestSkipped('Needs an employee with a legacy salary structure.');
        }
        $night = $this->shift('Night', 'per_day', 120);
        $this->day($employee->id, 1, 'present', 8, $night);
        $this->day($employee->id, 2, 'present', 8, $night, 'half_day');
        DB::table('monthly_payrolls')->where('user_id', $employee->id)->where('payroll_month', $this->month)->delete();

        $this->actingAs($this->admin)->withSession(['tenant_id' => $this->tenantId]);
        $controller = app(\App\Http\Controllers\Payroll\MonthlyPayrollController::class);
        $slip = (new \ReflectionMethod($controller, 'processEmployeeMonthlyPayroll'))->invoke($controller, $employee, $this->month,
            $month->copy()->startOfMonth()->toDateString(), $month->copy()->endOfMonth()->toDateString(), true, false);

        $this->assertEquals(180, (float) $slip->shift_allowance_amount);
        $this->assertEquals(1.5, (float) $slip->shift_allowance_days);
        $component = DB::table('payroll_components')->where('monthly_payroll_id', $slip->id)->where('component_name', 'like', 'Shift Allowance%')->first();
        $this->assertNotNull($component);
        $this->assertStringContainsString('× 1.5 days', $component->component_name);
        $this->assertEquals(180, (float) $component->amount);
        // It is part of gross.
        $this->assertEquals(round((float) DB::table('payroll_components')->where('monthly_payroll_id', $slip->id)->where('component_type', 'earning')->sum('amount'), 2),
            round((float) $slip->gross_earnings, 2));
    }

    public function test_shift_form_saves_the_allowance(): void
    {
        $this->actingAs($this->admin)->withSession(['tenant_id' => $this->tenantId])
            ->postJson(route('shift.store'), ['name' => 'Night SA ' . uniqid(), 'start_time' => '22:00', 'end_time' => '06:00', 'is_overnight' => 1,
                'allowance_type' => 'per_day', 'allowance_amount' => 75.5, 'allowance_min_hours' => 6])
            ->assertCreated();
        $shift = DB::table('shifts')->where('tenant_id', $this->tenantId)->orderByDesc('id')->first();
        $this->assertSame('per_day', $shift->allowance_type);
        $this->assertEquals(75.5, (float) $shift->allowance_amount);
        $this->assertEquals(6, (float) $shift->allowance_min_hours);

        // "No allowance" clears the amount.
        $this->actingAs($this->admin)->postJson(route('shift.update', $shift->id), ['name' => $shift->name, 'start_time' => '22:00', 'end_time' => '06:00',
            'is_overnight' => 1, 'allowance_type' => 'none', 'allowance_amount' => 75.5])->assertOk();
        $shift = DB::table('shifts')->where('id', $shift->id)->first();
        $this->assertSame('none', $shift->allowance_type);
        $this->assertEquals(0, (float) $shift->allowance_amount);

        $this->actingAs($this->admin)->postJson(route('shift.store'), ['name' => 'Bad ' . uniqid(), 'start_time' => '09:00', 'end_time' => '17:00',
            'allowance_type' => 'per_week', 'allowance_amount' => 10])->assertStatus(422)->assertJsonValidationErrors('allowance_type');
    }
}
