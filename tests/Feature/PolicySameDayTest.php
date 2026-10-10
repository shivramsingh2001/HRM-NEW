<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Attendance\LatePolicyService;
use App\Services\Attendance\PolicyResolver;
use App\Services\Attendance\SandwichRule;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Company Policies changes apply from their own day, not from the next month:
 * Late Arrival allowance / action / deduction are judged per day, and the
 * sandwich-leave rule is one shared implementation. Runs on the dev DB and
 * restores the company's attendance policy versions afterwards.
 */
class PolicySameDayTest extends TestCase
{
    private User $admin;
    private User $employee;
    private int $tenantId;
    private array $policies;
    private string $month;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::withoutGlobalScopes()->where('role', 'admin')->where('status', 1)->whereNotNull('tenant_id')->orderBy('id')->firstOrFail();
        $this->tenantId = (int) $this->admin->tenant_id;
        $this->policies = DB::table('attendance_policies')->where('tenant_id', $this->tenantId)->get()->map(fn ($r) => (array) $r)->all();
        $this->month = Carbon::today()->subMonthNoOverflow()->format('Y-m'); // a whole past month
        $this->employee = User::withoutGlobalScopes()->forceCreate([
            'name' => 'PHPUnit PSD ' . uniqid(), 'email' => 'psd.' . uniqid() . '@phpunit.test',
            'employee_id' => 'PS' . random_int(100000, 999999), 'password' => bcrypt('secret'),
            'role' => 'employee', 'status' => 1, 'tenant_id' => $this->tenantId,
        ]);
    }

    protected function tearDown(): void
    {
        DB::table('attendances')->where('user_id', $this->employee->id)->delete();
        DB::table('attendance_policies')->where('tenant_id', $this->tenantId)->delete();
        foreach ($this->policies as $p) {
            DB::table('attendance_policies')->insert($p);
        }
        User::withoutGlobalScopes()->where('id', $this->employee->id)->forceDelete();
        app(PolicyResolver::class)->forget();
        parent::tearDown();
    }

    private function version(string $from, array $fields): void
    {
        $resolver = app(PolicyResolver::class);
        $resolver->forget();
        $base = $resolver->forTenantDate($this->tenantId, $from)->toPersistableArray();
        DB::table('attendance_policies')->where('tenant_id', $this->tenantId)->where('effective_from', $from)->delete();
        DB::table('attendance_policies')->insert(array_merge($base, $fields, [
            'tenant_id' => $this->tenantId, 'effective_from' => $from, 'created_at' => now(), 'updated_at' => now(),
        ]));
        $resolver->forget();
    }

    private function lateDay(string $date): int
    {
        return DB::table('attendances')->insertGetId([
            'tenant_id' => $this->tenantId, 'user_id' => $this->employee->id, 'date' => $date,
            'clock_in' => "$date 09:30:00", 'clock_out' => "$date 18:30:00", 'worked_hours' => 9,
            'late_minutes' => 30, 'attendance_status' => 'late', 'attendance_type' => 'app',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_late_rules_changed_mid_month_apply_from_their_day(): void
    {
        $base = ['day_classification_enabled' => false, 'grace_mode' => 'fixed', 'fixed_grace_minutes' => 10, 'late_deduction_enabled' => true, 'late_deduction_mode' => 'fixed_amount'];
        // 1st: allowance 5, keep the day as it is. 15th: allowance 0 → half day, ₹100 per excess day.
        $this->version("{$this->month}-01", $base + ['monthly_late_allowance' => 5, 'late_attendance_action' => 'none', 'late_deduction_amount' => 50]);
        $this->version("{$this->month}-15", $base + ['monthly_late_allowance' => 0, 'late_attendance_action' => 'half_day', 'late_deduction_amount' => 100]);

        $early = $this->lateDay("{$this->month}-05");
        $later = $this->lateDay("{$this->month}-20");

        app(LatePolicyService::class)->recalculateMonth($this->employee->id, $this->tenantId, $this->month);

        // Before the change: within the old allowance. After it: the new rule (it used to wait for next month).
        $this->assertSame('late', DB::table('attendances')->where('id', $early)->value('effective_status'));
        $this->assertSame('half_day', DB::table('attendances')->where('id', $later)->value('effective_status'));

        $excess = app(LatePolicyService::class)->excessCounts($this->employee->id, $this->tenantId, $this->month);
        $this->assertSame(1, $excess['lateExcess']);
        $this->assertSame(100.0, (float) reset($excess['lateExcessDays'])->lateDeductionAmount);
    }

    public function test_a_worked_holiday_is_paid_once_and_does_not_hide_absences(): void
    {
        $start = Carbon::parse($this->month . '-01');
        $days = $start->daysInMonth;
        $holiday = $start->copy()->addDays(9)->toDateString();          // the 10th
        $absent = [$start->copy()->addDays(19)->toDateString(), $start->copy()->addDays(20)->toDateString()];
        $this->version($start->toDateString(), ['day_classification_enabled' => false, 'sandwich_leave' => false]);
        $holidayId = DB::table('holidays')->insertGetId(['tenant_id' => $this->tenantId, 'start_date' => $holiday, 'end_date' => $holiday, 'name' => 'PHPUnit holiday', 'status' => 1, 'created_at' => now(), 'updated_at' => now()]);

        try {
            for ($d = $start->copy(); $d->month === $start->month; $d->addDay()) {
                $ds = $d->toDateString();
                if (! in_array($ds, $absent, true)) {   // works every day, including the holiday
                    DB::table('attendances')->insert([
                        'tenant_id' => $this->tenantId, 'user_id' => $this->employee->id, 'date' => $ds,
                        'clock_in' => "$ds 09:00:00", 'clock_out' => "$ds 18:00:00", 'worked_hours' => 9,
                        'attendance_status' => 'present', 'attendance_type' => 'app', 'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
            }
            app(\App\Services\AttendanceSummaryService::class)->updateMonthlySummary($this->employee->id, $this->month, $this->tenantId);
            $result = app(\App\Services\Attendance\PayrollDaysService::class)->forMonth($this->employee->id, $this->month, $this->tenantId);

            // Two absences → two unpaid days. Before the fix the worked holiday counted 3× and hid them (full month).
            $this->assertSame((float) ($days - 2), (float) $result['payable_days']);
            // Every worked holiday / week-off (the company's default week-offs too) is paid once.
            $summary = DB::table('attendance_summaries')->where('user_id', $this->employee->id)->where('year_month', $this->month)->first();
            $this->assertGreaterThanOrEqual(1, (int) $summary->holiday_work_days);
            $this->assertSame((float) ($summary->holiday_work_days + $summary->weekoff_work_days), (float) $result['worked_off_day_credit']);
        } finally {
            DB::table('holidays')->where('id', $holidayId)->delete();
            DB::table('attendance_summaries')->where('user_id', $this->employee->id)->delete();
        }
    }

    public function test_sandwich_rule(): void
    {
        $days = [
            '2026-10-02' => 'absent', '2026-10-03' => 'weekoff', '2026-10-04' => 'weekoff', '2026-10-05' => 'absent', // sandwiched
            '2026-10-09' => 'present', '2026-10-10' => 'holiday', '2026-10-11' => 'absent',                           // worked before
            '2026-10-30' => 'absent', '2026-10-31' => 'weekoff',                                                      // next day unknown
        ];
        $pay = SandwichRule::offDayPay($days, fn () => true);
        $this->assertSame(['2026-10-03' => false, '2026-10-04' => false, '2026-10-10' => true, '2026-10-31' => true], $pay);

        $off = SandwichRule::offDayPay($days, fn () => false);
        $this->assertNotContains(false, $off);

        // Paid leave next to a week-off counts as worked.
        $this->assertSame(['2026-10-03' => true], SandwichRule::offDayPay(['2026-10-02' => 'paid_leave', '2026-10-03' => 'weekoff', '2026-10-04' => 'absent'], fn () => true));
    }
}
