<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Single login domain: every company signs in on the same host and the tenant
 * comes from the logged-in user (TenantMiddleware::handleWebRequest +
 * Auth\AuthController::login). Shared dev DB — throwaway users, cleaned in tearDown.
 */
class SingleDomainLoginTest extends TestCase
{
    private const HOST = 'https://hrm.phpunit-example.com';

    /** @var array<int,User> */
    private array $users = [];

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.url' => self::HOST,
            'tenancy.app_host' => 'hrm.phpunit-example.com',
            'tenancy.base_domain' => 'phpunit-example.com',
            'tenancy.redirect_company_subdomains' => true,
        ]);

        // Two companies whose login gates pass (active, no expired subscription).
        $tenantIds = Tenant::where('status', 'active')->orderBy('id')->pluck('id')->filter(function ($id) {
            $sub = DB::table('tenant_subscriptions')->where('tenant_id', $id)->whereIn('status', ['active', 'trial'])
                ->orderByDesc('start_date')->orderByDesc('id')->first();
            $end = $sub?->end_date ?? ($sub?->status === 'trial' ? $sub?->trial_ends_at : null);

            return !$end || now()->lt($end);
        })->take(2)->values();

        if ($tenantIds->count() < 2) {
            $this->markTestSkipped('Needs two active tenants in the dev DB.');
        }

        foreach ($tenantIds as $tenantId) {
            $this->users[] = User::withoutGlobalScopes()->forceCreate([
                'name' => 'Single Domain Temp ' . uniqid(),
                'email' => 'singledomain.' . uniqid() . '@phpunit.test',
                'employee_id' => 'SDL' . random_int(100000, 999999),
                'password' => bcrypt('secret-pass'),
                'role' => 'employee',
                'status' => 1,
                'tenant_id' => $tenantId,
            ]);
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->users as $user) {
            DB::table('password_reset_tokens')->where('email', $user->email)->delete();
            User::withoutGlobalScopes()->where('id', $user->id)->forceDelete();
        }
        parent::tearDown();
    }

    public function test_users_of_two_companies_log_in_on_the_same_host_and_get_their_own_tenant(): void
    {
        foreach ($this->users as $user) {
            $this->post(self::HOST . '/login', ['employee_id' => $user->employee_id, 'password' => 'secret-pass'])
                ->assertRedirect();
            $this->assertAuthenticatedAs($user);

            // Next request on the same host binds the user's own company.
            $this->get(self::HOST . '/forgot-password');
            $this->assertSame((int) $user->tenant_id, (int) app('current_tenant')->id);
            $this->assertSame((int) $user->tenant_id, (int) session('tenant_id'));

            auth()->logout();
            app()->forgetInstance('current_tenant');
        }
    }

    public function test_login_by_email_and_wrong_password(): void
    {
        $user = $this->users[0];

        $this->post(self::HOST . '/login', ['employee_id' => $user->email, 'password' => 'secret-pass'])->assertRedirect();
        $this->assertAuthenticatedAs($user);
        auth()->logout();

        $this->post(self::HOST . '/login', ['employee_id' => $user->employee_id, 'password' => 'wrong'])
            ->assertSessionHasErrors('error');
        $this->assertGuest();

        $this->post(self::HOST . '/login', ['employee_id' => 'NOPE' . uniqid(), 'password' => 'x'])
            ->assertSessionHasErrors('error');
        $this->assertGuest();
    }

    public function test_inactive_company_cannot_log_in(): void
    {
        $user = $this->users[0];
        $tenant = Tenant::find($user->tenant_id);
        $original = $tenant->status;

        try {
            DB::table('tenants')->where('id', $tenant->id)->update(['status' => 'suspended']);

            $this->post(self::HOST . '/login', ['employee_id' => $user->employee_id, 'password' => 'secret-pass'])
                ->assertSessionHasErrors('error');
            $this->assertGuest();
        } finally {
            DB::table('tenants')->where('id', $tenant->id)->update(['status' => $original]);
        }
    }

    public function test_old_company_subdomain_redirects_to_the_single_host(): void
    {
        $this->get('https://democo.phpunit-example.com/forgot-password?x=1')
            ->assertStatus(301)
            ->assertRedirect(self::HOST . '/forgot-password?x=1');
    }

    public function test_careers_page_is_resolved_from_the_company_code_in_the_url(): void
    {
        $this->get(self::HOST . '/careers/no-such-company-' . uniqid())->assertNotFound();

        $code = Tenant::find($this->users[0]->tenant_id)->subdomain;
        $this->assertNotSame(404, $this->get(self::HOST . '/careers/' . $code)->getStatusCode());
    }

    public function test_mobile_api_login_without_company_header_finds_each_users_company(): void
    {
        foreach ($this->users as $user) {
            $res = $this->postJson(self::HOST . '/api/login', ['employee_id' => $user->employee_id, 'password' => 'secret-pass'])
                ->assertOk()->assertJson(['success' => true]);
            $this->assertNotEmpty($res->json('data.token'));
            $this->assertSame((int) $user->tenant_id, (int) app('current_tenant')->id);
            // Same app instance across requests in a test: the JWT singleton keeps
            // the previous token, so log it out before the next user's login.
            auth('api')->logout();
            app()->forgetInstance('current_tenant');
            $this->app['auth']->forgetGuards();
        }
    }

    public function test_mobile_api_login_still_accepts_the_company_header(): void
    {
        $user = $this->users[0];
        $code = Tenant::find($user->tenant_id)->subdomain;

        $this->withHeaders(['X-Tenant' => $code])
            ->postJson(self::HOST . '/api/login', ['employee_id' => $user->employee_id, 'password' => 'secret-pass'])
            ->assertOk()->assertJson(['success' => true]);
    }

    public function test_mobile_api_login_unknown_id_and_inactive_company(): void
    {
        $this->postJson(self::HOST . '/api/login', ['employee_id' => 'NOPE' . uniqid(), 'password' => 'x'])
            ->assertOk()->assertJson(['success' => false, 'message' => 'Employee not found.']);

        $user = $this->users[0];
        $original = Tenant::find($user->tenant_id)->status;
        try {
            DB::table('tenants')->where('id', $user->tenant_id)->update(['status' => 'suspended']);
            $this->postJson(self::HOST . '/api/login', ['employee_id' => $user->employee_id, 'password' => 'secret-pass'])
                ->assertStatus(400)->assertJson(['message' => 'Company not found or inactive.']);
        } finally {
            DB::table('tenants')->where('id', $user->tenant_id)->update(['status' => $original]);
        }
    }

    public function test_mobile_api_otp_without_company_header_resolves_by_mobile(): void
    {
        $user = $this->users[0];
        $mobile = (string) random_int(6000000000, 9999999999);
        DB::table('users')->where('id', $user->id)->update(['contact' => $mobile]);

        $res = $this->postJson(self::HOST . '/api/send-otp', ['mobile_no' => $mobile]);
        $this->assertNotSame(400, $res->getStatusCode(), $res->getContent());
        $this->assertStringNotContainsString('Missing tenant identifier', $res->getContent());
        $this->assertSame((int) $user->tenant_id, (int) app('current_tenant')->id);
        DB::table('otps')->where('mobile_no', $mobile)->delete();
    }

    public function test_forgot_password_api_works_without_a_company_header(): void
    {
        Mail::fake();

        $this->postJson(self::HOST . '/api/forgot-password', ['email' => $this->users[0]->email])
            ->assertOk()->assertJson(['success' => true]);

        // Unknown email: same generic reply, not a "missing tenant" error.
        $this->postJson(self::HOST . '/api/forgot-password', ['email' => 'nobody.' . uniqid() . '@phpunit.test'])
            ->assertOk()->assertJson(['success' => true]);
    }
}
