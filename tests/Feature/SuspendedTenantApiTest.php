<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * A company suspended from the Super Admin Panel is closed for mobile-app users
 * who were already logged in (valid JWT), not only for new logins.
 * Shared dev DB, rolled back via DatabaseTransactions.
 */
class SuspendedTenantApiTest extends TestCase
{
    use DatabaseTransactions;

    private User $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $employee = User::withoutGlobalScopes()
            ->join('tenants', 'tenants.id', '=', 'users.tenant_id')
            ->where('tenants.status', 'active')->where('users.role', 'employee')->where('users.status', '1')
            ->select('users.*')->first();
        if (! $employee) {
            $this->markTestSkipped('no active employee in the dev DB');
        }
        $this->employee = $employee;
    }

    private function headers(): array
    {
        $headers = ['Authorization' => 'Bearer '.auth('api')->login($this->employee), 'Accept' => 'application/json'];
        if ($this->employee->last_login_token !== null) {
            $headers['Device-Token'] = $this->employee->last_login_token;
        }

        return $headers;
    }

    private function setStatus(string $status): void
    {
        DB::table('tenants')->where('id', $this->employee->tenant_id)->update(['status' => $status]);
    }

    public function test_logged_in_employee_is_blocked_once_the_company_is_suspended(): void
    {
        $headers = $this->headers();
        $before = DB::table('attendances')->where('user_id', $this->employee->id)->count();

        $this->setStatus('suspended');

        $this->withHeaders($headers)->getJson('/api/user/profile')
            ->assertStatus(401)->assertJsonPath('message', 'Company not found or inactive.');
        $this->withHeaders($headers)
            ->postJson('/api/user/attendance/clock-in', ['lat' => 28.6, 'long' => 77.2, 'address' => 'Test'])
            ->assertStatus(401);

        $this->assertSame($before, DB::table('attendances')->where('user_id', $this->employee->id)->count());
    }

    public function test_active_and_trial_companies_still_work(): void
    {
        foreach (['active', 'trial'] as $status) {
            $this->setStatus($status);
            $this->withHeaders($this->headers())->getJson('/api/user/profile')->assertOk();
        }
    }
}
