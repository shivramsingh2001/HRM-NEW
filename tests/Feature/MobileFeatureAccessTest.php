<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\FeatureService;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Mobile app plan access: GET /api/user/features + login payload tell the app
 * which modules the company bought, and the API itself refuses the rest.
 * Shared dev DB — throwaway users on existing tenants; asserts follow each
 * tenant's live feature state instead of hard-coding it.
 */
class MobileFeatureAccessTest extends TestCase
{
    private array $users = [];

    protected function tearDown(): void
    {
        User::withoutGlobalScopes()->whereIn('id', $this->users)->forceDelete();
        parent::tearDown();
    }

    private function user(int $tenantId, string $role = 'employee'): User
    {
        $u = User::withoutGlobalScopes()->forceCreate([
            'name' => 'Mobile ' . $role, 'email' => 'mob.' . uniqid() . '@phpunit.test', 'password' => bcrypt('x'),
            'employee_id' => 'MOB' . random_int(10000, 99999), 'role' => $role, 'status' => 1,
            'tenant_id' => $tenantId, 'last_login_token' => Str::random(40),
        ]);
        $this->users[] = $u->id;

        return $u;
    }

    private function headers(User $u): array
    {
        return ['Authorization' => 'Bearer ' . auth('api')->login($u), 'Device-Token' => $u->last_login_token];
    }

    public function test_features_endpoint_returns_plan_modules_and_permissions(): void
    {
        $u = $this->user(7);
        $expected = app(FeatureService::class)->allForTenant(7);

        $data = $this->withHeaders($this->headers($u))->getJson('/api/user/features')->assertOk()->json('data');

        $this->assertSame($expected, $data['features']);
        $this->assertSame(array_keys(array_filter($expected)), $data['enabled_features']);
        $this->assertSame('employee', $data['role']);
        // employee role: own leave view/create, nothing company-wide on payroll
        $this->assertSame('own', $data['permissions']['leave']['view']);
        $this->assertNull($data['permissions']['leave']['approve']);
        $this->assertArrayHasKey('export', $data['permissions']['payroll']);
        // employees apply for their own overtime and travel/WFH requests
        $this->assertSame('own', $data['permissions']['overtime']['create']);
        $this->assertSame('own', $data['permissions']['requests']['create']);
        $this->assertNull($data['permissions']['overtime']['approve']);
    }

    public function test_admin_permissions_are_company_wide(): void
    {
        $admin = $this->user(7, 'admin');

        $perm = $this->withHeaders($this->headers($admin))->getJson('/api/user/features')->json('data.permissions');

        $this->assertSame('company', $perm['expenses']['approve']);
        $this->assertSame('company', $perm['payroll']['manage']);
    }

    public function test_module_outside_the_plan_is_refused_with_json_403(): void
    {
        $features = app(FeatureService::class);
        // Find a tenant without expense and one with it (live data).
        $off = collect([7, 8, 10, 11, 12, 15])->first(fn ($t) => ! $features->enabled($t, 'expense_management'));
        $on = collect([7, 8, 10, 11, 12, 15])->first(fn ($t) => $features->enabled($t, 'expense_management'));
        $this->assertNotNull($off);
        $this->assertNotNull($on);

        // No Accept: application/json header on purpose — the app may not send it.
        $res = $this->withHeaders($this->headers($this->user($off)))->get('/api/view-expense');
        $res->assertStatus(403)->assertJson(['success' => false, 'code' => 'feature_disabled', 'feature' => 'expense_management']);

        $this->withHeaders($this->headers($this->user($on)))->getJson('/api/view-expense')->assertOk();
    }

    public function test_login_response_carries_the_access_block(): void
    {
        $u = $this->user(7);

        $res = $this->withHeaders(['X-Tenant' => '7'])
            ->postJson('/api/login', ['employee_id' => $u->employee_id, 'password' => 'x'])
            ->assertOk()->assertJson(['success' => true]);

        $this->assertSame(app(FeatureService::class)->allForTenant(7), $res->json('data.access.features'));
        $this->assertArrayHasKey('leave', $res->json('data.access.permissions'));
    }

    public function test_location_tracking_reports_company_and_employee_status_separately(): void
    {
        $access = app(\App\Services\AppAccessService::class);
        $companyOn = fn (int $t) => app(FeatureService::class)->enabled($t, 'geo_tracking')
            && (bool) \Illuminate\Support\Facades\DB::table('tenants')->where('id', $t)->value('field_tracking_enabled');

        // An employee who holds a tracking seat (existing row, read only).
        $trackedId = \Illuminate\Support\Facades\DB::table('user_job_details as jd')->join('users as u', 'u.id', '=', 'jd.user_id')
            ->where('jd.location_tracking_enabled', 1)->where('u.status', 1)->value('u.id');
        if ($trackedId) {
            $tracked = User::withoutGlobalScopes()->find($trackedId);
            $lt = $access->forUser($tracked)['location_tracking'];
            $this->assertSame($companyOn((int) $tracked->tenant_id), $lt['company_enabled']);
            $this->assertSame($lt['company_enabled'], $lt['employee_enabled']);
            $this->assertGreaterThan(0, $lt['ping_seconds']);
        }

        // Same company, no seat: company may be on, the employee is not.
        $untracked = $this->user(7);
        $lt = $this->withHeaders($this->headers($untracked))->getJson('/api/user/features')->json('data.location_tracking');
        $this->assertSame($companyOn(7), $lt['company_enabled']);
        $this->assertFalse($lt['employee_enabled']);

        // Company without the switch: both off.
        $this->assertFalse($access->forUser($this->user(8))['location_tracking']['company_enabled']);
    }

    public function test_core_routes_stay_open_without_any_module(): void
    {
        $u = $this->user(8);
        $h = $this->headers($u);

        $this->withHeaders($h)->getJson('/api/user/profile')->assertOk();
        $this->withHeaders($h)->getJson('/api/notifications/unread-count')->assertOk();
    }
}
