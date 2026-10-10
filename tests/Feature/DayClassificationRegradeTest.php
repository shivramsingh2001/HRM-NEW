<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Attendance\LatePolicyService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Turning Day Classification off (Company Policies) re-grades the saved days
 * at once — also mid-month and back-dated — and the admin dashboard counts the
 * same graded status as the Team page instead of "clocked in = present".
 * Runs on the dev DB; restores the company's policy versions afterwards.
 */
class DayClassificationRegradeTest extends TestCase
{
    private User $admin;
    private User $employee;
    private int $tenantId;
    private array $policies;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::withoutGlobalScopes()->where('role', 'admin')->where('status', 1)->whereNotNull('tenant_id')->orderBy('id')->firstOrFail();
        $this->tenantId = (int) $this->admin->tenant_id;
        $this->policies = DB::table('attendance_policies')->where('tenant_id', $this->tenantId)->get()->map(fn ($r) => (array) $r)->all();

        $this->employee = User::withoutGlobalScopes()->forceCreate([
            'name' => 'PHPUnit DCR ' . uniqid(),
            'email' => 'dcr.' . uniqid() . '@phpunit.test',
            'employee_id' => 'DC' . random_int(100000, 999999),
            'password' => bcrypt('secret'),
            'role' => 'employee',
            'status' => 1,
            'tenant_id' => $this->tenantId,
        ]);

        // Classification ON from a week ago (policy version), as before the switch.
        $this->setPolicy(Carbon::today()->subDays(7)->toDateString(), true);
    }

    protected function tearDown(): void
    {
        DB::table('attendances')->where('user_id', $this->employee->id)->delete();
        DB::table('attendance_summaries')->where('user_id', $this->employee->id)->delete();
        DB::table('attendance_policies')->where('tenant_id', $this->tenantId)->delete();
        foreach ($this->policies as $p) {
            DB::table('attendance_policies')->insert($p);
        }
        User::withoutGlobalScopes()->where('id', $this->employee->id)->forceDelete();
        app(\App\Services\Attendance\PolicyResolver::class)->forget();
        parent::tearDown();
    }

    private function setPolicy(string $from, bool $enabled): void
    {
        $current = app(\App\Services\Attendance\PolicyResolver::class)->forTenantDate($this->tenantId, $from);
        DB::table('attendance_policies')->where('tenant_id', $this->tenantId)->where('effective_from', '>=', $from)->delete();
        DB::table('attendance_policies')->insert(array_merge($current->toPersistableArray(), [
            'tenant_id' => $this->tenantId, 'effective_from' => $from, 'day_classification_enabled' => $enabled,
            'fallback_present_hours' => 6, 'fallback_half_hours' => 4, 'created_at' => now(), 'updated_at' => now(),
        ]));
        app(\App\Services\Attendance\PolicyResolver::class)->forget();
    }

    /** A 12-minute day, graded with the policy as it is now. */
    private function shortDay(string $date): int
    {
        $id = DB::table('attendances')->insertGetId([
            'tenant_id' => $this->tenantId, 'user_id' => $this->employee->id, 'date' => $date,
            'clock_in' => "$date 10:00:00", 'clock_out' => "$date 10:12:00", 'worked_hours' => 0.2,
            'attendance_status' => 'present', 'attendance_type' => 'app', 'created_at' => now(), 'updated_at' => now(),
        ]);
        app(LatePolicyService::class)->recalculateMonth($this->employee->id, $this->tenantId, substr($date, 0, 7));

        return $id;
    }

    private function graded(int $id): string
    {
        return (string) DB::table('attendances')->where('id', $id)->value('effective_status');
    }

    public function test_switching_off_regrades_saved_days_from_the_apply_from_date(): void
    {
        $twoDaysAgo = Carbon::today()->subDays(2)->toDateString();
        $today = Carbon::today()->toDateString();
        $old = $this->shortDay($twoDaysAgo);
        $now = $this->shortDay($today);
        $this->assertSame('absent', $this->graded($old)); // 12 min < 4 h half-day fallback
        $this->assertSame('absent', $this->graded($now));

        // Off from today: today's day is re-graded at once; two days ago stays (rule was on then).
        $this->actingAs($this->admin)->put(route('day-classification-settings.update'), ['day_classification_enabled' => 0])
            ->assertRedirect()->assertSessionHas('success');
        $this->assertSame('present', $this->graded($now));
        $this->assertSame('absent', $this->graded($old));

        // Back-dated: off from two days ago → that day is re-graded too.
        $this->actingAs($this->admin)->put(route('day-classification-settings.update'), ['day_classification_enabled' => 0, 'apply_from' => $twoDaysAgo])
            ->assertRedirect()->assertSessionHas('success');
        $this->assertSame('present', $this->graded($old));
        $this->assertSame(0, DB::table('attendance_policies')->where('tenant_id', $this->tenantId)->where('effective_from', '>=', $twoDaysAgo)->where('day_classification_enabled', 1)->count());
    }

    public function test_dashboard_counts_the_graded_status_not_just_clocked_in(): void
    {
        $id = $this->shortDay(Carbon::today()->toDateString()); // graded absent while classification is on
        $this->assertSame('absent', $this->graded($id));

        $html = $this->actingAs($this->admin)->get('/dashboard')->assertOk()->getContent();
        $this->assertStringContainsString('Half day', $html);

        $breakdown = (new \ReflectionMethod(\App\Http\Controllers\Dashboard\DashboardController::class, 'dashboardDayBucket'));
        $bucket = $breakdown->invoke(app(\App\Http\Controllers\Dashboard\DashboardController::class), DB::table('attendances')->where('id', $id)->first());
        $this->assertSame('absent', $bucket);
    }
}
