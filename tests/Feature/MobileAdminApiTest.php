<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Tenant;
use App\Models\User;
use App\Services\FeatureService;
use App\Services\Reports\ReportTableReader;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Mobile admin API (/api/admin/*, 2026-11-11): Admin / HR login, dashboard,
 * team, reports. Runs against the shared dev DB (no RefreshDatabase); restores
 * every user's last_login_token and deletes the attendance it marks.
 */
class MobileAdminApiTest extends TestCase
{
    private ?User $admin = null;

    private ?Tenant $tenant = null;

    /** @var array<int, ?string> user id => original last_login_token */
    private array $tokens = [];

    /** @var array<int, int> attendance ids created by the test */
    private array $marked = [];

    protected function setUp(): void
    {
        parent::setUp();
        // An active admin whose company has Reports, Attendance and Leave.
        foreach (User::query()->whereNotNull('tenant_id')->where('role', 'admin')->where('status', 1)->orderBy('id')->get() as $u) {
            $tenant = Tenant::query()->find($u->tenant_id);
            if (! $tenant || $tenant->status !== 'active') {
                continue;
            }
            app()->instance('current_tenant', $tenant);
            app()->forgetScopedInstances();
            $f = app(FeatureService::class);
            if ($f->enabledForCurrentTenant('daily_reports') && $f->enabledForCurrentTenant('leave_management')
                && $f->enabledForCurrentTenant('attendance')) {
                [$this->admin, $this->tenant] = [$u, $tenant];
                break;
            }
        }
        app()->forgetInstance('current_tenant');
        if (! $this->admin) {
            $this->markTestSkipped('No active admin with Reports + Attendance + Leave on this DB.');
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->tokens as $id => $token) {
            DB::table('users')->where('id', $id)->update(['last_login_token' => $token]);
        }
        if ($this->marked !== []) {
            DB::table('attendance_logs')->whereIn('attendance_id', $this->marked)->delete();
            DB::table('attendance_shift_segments')->whereIn('attendance_id', $this->marked)->delete();
            DB::table('attendances')->whereIn('id', $this->marked)->delete();
        }
        parent::tearDown();
    }

    /** @return array<string, string> JWT + device token headers, as the app sends them */
    private function headersFor(User $user): array
    {
        $this->tokens[$user->id] ??= DB::table('users')->where('id', $user->id)->value('last_login_token');
        $device = 'phpunit-'.uniqid();
        DB::table('users')->where('id', $user->id)->update(['last_login_token' => $device]);
        $user->last_login_token = $device;
        app('auth')->forgetGuards();

        return ['Authorization' => 'Bearer '.auth('api')->login($user), 'Device-Token' => $device];
    }

    private function colleague(string $role): ?User
    {
        return User::query()->where('tenant_id', $this->admin->tenant_id)->where('role', $role)->where('status', 1)->first();
    }

    // ------------------------------------------------------------------ access

    public function test_admin_and_hr_pass_the_login_role_check(): void
    {
        // Wrong password on purpose: reaching "Invalid password." means the role check let them through.
        // Lockout counter and audit log are mocked, so nothing is written.
        $this->mock(\App\Services\LoginAttemptService::class)->shouldIgnoreMissing()->shouldReceive('isLockedOut')->andReturn(false);
        $this->mock(\App\Services\AuthAuditService::class)->shouldIgnoreMissing();
        app()->instance('current_tenant', $this->tenant);

        $checked = 0;
        foreach ([$this->admin, $this->colleague('hr')] as $user) {
            if (! $user || ! $user->employee_id) {
                continue;
            }
            $result = app(\App\Services\AuthService::class)->login($user->employee_id, 'definitely-wrong-password-'.uniqid());
            $this->assertSame('Invalid password.', $result['message'] ?? null, $user->role);
            $checked++;
        }
        app()->forgetInstance('current_tenant');
        if ($checked === 0) {
            $this->markTestSkipped('No admin / HR with an employee ID in this company.');
        }
    }

    public function test_employee_and_manager_get_403(): void
    {
        foreach (['employee', 'manager'] as $role) {
            $user = $this->colleague($role);
            if (! $user) {
                continue;
            }
            $this->getJson('/api/admin/dashboard', $this->headersFor($user))->assertStatus(403);
            $this->getJson('/api/admin/team', $this->headersFor($user))->assertStatus(403);
            $this->getJson('/api/admin/reports', $this->headersFor($user))->assertStatus(403);
        }
        $this->assertTrue(true);
    }

    public function test_no_token_is_401(): void
    {
        $this->getJson('/api/admin/dashboard')->assertStatus(401);
    }

    // --------------------------------------------------------------- dashboard

    public function test_dashboard_sections(): void
    {
        $res = $this->getJson('/api/admin/dashboard?range=this_month', $this->headersFor($this->admin))->assertOk();

        $res->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['range' => ['preset', 'label', 'from', 'to'], 'show', 'employees' => ['total', 'active', 'inactive', 'joinings'],
                'attendance', 'attendance_chart', 'pending_approvals', 'celebrations', 'company_trend', 'department_distribution']]);
        $this->assertSame('this_month', $res->json('data.range.preset'));
        $this->assertSame($res->json('data.show.payroll') ? 'array' : 'NULL', gettype($res->json('data.payroll')));
    }

    // -------------------------------------------------------------------- team

    public function test_team_list_counts_and_filter(): void
    {
        $h = $this->headersFor($this->admin);
        $all = $this->getJson('/api/admin/team?per_page=5', $h)->assertOk();
        $counts = $all->json('data.counts');

        $this->assertSame($counts['total'], $all->json('data.pagination.total'));
        $this->assertLessThanOrEqual(5, count($all->json('data.members')));
        $this->assertSame($counts['present'], $this->getJson('/api/admin/team?status=present', $h)->json('data.pagination.total'));
        $this->getJson('/api/admin/team?status=nonsense', $h)->assertStatus(422);
        $this->getJson('/api/admin/team/filters', $h)->assertOk()->assertJsonStructure(['data' => ['branches', 'departments', 'designations', 'leave_types', 'statuses']]);
    }

    public function test_team_member_detail_and_other_company_is_404(): void
    {
        $h = $this->headersFor($this->admin);
        $member = $this->colleague('employee') ?? $this->colleague('manager');
        if ($member) {
            $this->getJson('/api/admin/team/'.$member->id, $h)->assertOk()
                ->assertJsonStructure(['data' => ['header', 'personal', 'job', 'address', 'bank', 'documents', 'month_summary', 'direct_reports']]);
            $this->getJson('/api/admin/team/'.$member->id.'/attendance-stats', $h)->assertOk()->assertJsonPath('status', true);
            $this->getJson('/api/admin/team/'.$member->id.'/attendance-table', $h)->assertOk()->assertJsonPath('status', true);
        }

        $stranger = User::query()->where('tenant_id', '!=', $this->admin->tenant_id)->where('role', 'employee')->first();
        if ($stranger) {
            $this->getJson('/api/admin/team/'.$stranger->id, $h)->assertStatus(404);
        }
        $this->assertTrue(true);
    }

    public function test_mark_attendance_single_and_bulk(): void
    {
        $member = $this->colleague('employee');
        if (! $member) {
            $this->markTestSkipped('No employee in this company.');
        }
        // A past day the employee has no attendance on.
        $day = Carbon::today()->subDays(40);
        while (Attendance::withoutGlobalScopes()->where('tenant_id', $member->tenant_id)->where('user_id', $member->id)->whereDate('date', $day)->exists()) {
            $day->subDay();
        }
        $h = $this->headersFor($this->admin);

        $this->postJson('/api/admin/team/mark-attendance', ['user_id' => $member->id, 'date' => $day->toDateString(), 'status' => 'absent'], $h)
            ->assertOk()->assertJsonPath('success', true);
        $this->rememberMarked($member->id, $day->toDateString());

        $this->postJson('/api/admin/team/mark-attendance', ['user_id' => $member->id, 'date' => 'not-a-date', 'status' => 'absent'], $h)
            ->assertStatus(422);

        $day2 = $day->copy()->subDay();
        $res = $this->postJson('/api/admin/team/mark-attendance-bulk', ['user_ids' => [$member->id], 'date' => $day2->toDateString(), 'status' => 'absent'], $h);
        $this->rememberMarked($member->id, $day2->toDateString());
        $this->assertContains($res->status(), [200, 422], $res->getContent());
    }

    private function rememberMarked(int $userId, string $date): void
    {
        $id = Attendance::withoutGlobalScopes()->where('tenant_id', $this->admin->tenant_id)->where('user_id', $userId)->whereDate('date', $date)->value('id');
        if ($id) {
            $this->marked[] = (int) $id;
        }
    }

    // ----------------------------------------------------------------- reports

    public function test_reports_catalog_lists_only_allowed_reports(): void
    {
        $res = $this->getJson('/api/admin/reports', $this->headersFor($this->admin))->assertOk();
        $keys = collect($res->json('data.groups'))->flatMap(fn ($g) => collect($g['reports'])->pluck('key'))->all();

        $this->assertContains('leave.balance', $keys);
        $this->assertContains('attendance.day', $keys);
        foreach ($keys as $key) {
            $this->assertNull(\App\Support\ReportCatalog::denialReason($key, $this->admin), $key);
        }
    }

    public function test_report_table_download_and_errors(): void
    {
        $h = $this->headersFor($this->admin);

        $leave = $this->getJson('/api/admin/reports/leave.balance', $h)->assertOk();
        $this->assertNotEmpty($leave->json('data.columns'));
        $this->assertSame('leave.balance', $leave->json('data.report.key'));

        $day = $this->getJson('/api/admin/reports/attendance.day?date='.Carbon::yesterday()->toDateString().'&per_page=3', $h)->assertOk();
        $this->assertNotEmpty($day->json('data.columns'));
        $this->assertLessThanOrEqual(3, count($day->json('data.rows')));
        $this->assertSame(Carbon::yesterday()->format('d M Y'), $day->json('data.period_label'));

        $this->getJson('/api/admin/reports/attendance.punches?from=2026-01-01&to=2026-06-30', $h)->assertStatus(422); // over 93 days
        $this->getJson('/api/admin/reports/attendance.day?month=2026-10', $h)->assertStatus(422);                     // day report, month given
        $this->getJson('/api/admin/reports/no.such-report', $h)->assertStatus(404);

        $file = $this->get('/api/admin/reports/leave.balance/download', $h)->assertOk();
        $this->assertStringContainsString('attachment;', (string) $file->headers->get('Content-Disposition'));
        $this->assertNotSame('', $file->getContent());

        // The outer API request is untouched by the replay: a second call still works as the API user.
        $this->getJson('/api/admin/team/filters', $h)->assertOk();
    }

    public function test_table_reader_csv_with_title_lines_and_formula_guard(): void
    {
        $csv = "\xEF\xBB\xBFDaywise Attendance\nDate: 10 Nov 2026\n\nName,Status,Note\nAsha,Present,'=SUM(1)\nRavi,Absent\n";
        $t = app(ReportTableReader::class)->read($csv, 'text/csv');

        $this->assertSame(['Daywise Attendance', 'Date: 10 Nov 2026'], $t['title_lines']);
        $this->assertSame(['Name', 'Status', 'Note'], $t['columns']);
        $this->assertSame([['Asha', 'Present', '=SUM(1)'], ['Ravi', 'Absent', '']], $t['rows']);
    }
}
