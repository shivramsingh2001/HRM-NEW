<?php

namespace Tests\Feature\Attendance;

use App\Models\Tenant;
use App\Models\User;
use App\Services\FeatureService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Attendance type "biometric_only": the employee can use the app but cannot
 * clock in/out from it — punches come only from the biometric terminal.
 * Exercised through the real JWT 'api' guard.
 *
 * Shared dev DB, rolled back via DatabaseTransactions; feature cache busted
 * in tearDown so the rolled-back override can't linger.
 */
class BiometricOnlyPunchBlockTest extends TestCase
{
    use DatabaseTransactions;

    private int $tenantId;
    private User $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantId = (int) DB::table('users')
            ->join('user_job_details', 'users.id', '=', 'user_job_details.user_id')
            ->where('users.status', '1')->where('users.role', 'employee')->whereNotNull('users.tenant_id')
            ->select('users.tenant_id')->groupBy('users.tenant_id')
            ->orderByRaw('count(*) desc')->value('users.tenant_id');

        $employee = $this->tenantId
            ? User::withoutGlobalScopes()
                ->join('user_job_details', 'users.id', '=', 'user_job_details.user_id')
                ->where('users.tenant_id', $this->tenantId)->where('users.role', 'employee')->where('users.status', '1')
                ->select('users.*')->first()
            : null;

        if (! $employee || ! DB::table('super_admins')->exists()) {
            $this->markTestSkipped('fixture employee / super_admins row missing in the dev DB');
        }

        $this->employee = $employee;
        app()->instance('current_tenant', Tenant::find($this->tenantId));
        $this->setFeature('attendance', true);
    }

    protected function tearDown(): void
    {
        // Nothing reads features between this bust and the rollback below.
        if (! empty($this->tenantId)) {
            app(FeatureService::class)->bust($this->tenantId);
        }
        parent::tearDown(); // rolls back overrides + attendance_type
    }

    private function setFeature(string $key, bool $on): void
    {
        DB::table('tenant_feature_overrides')->where('tenant_id', $this->tenantId)->where('feature_key', $key)->delete();
        DB::table('tenant_feature_overrides')->insert([
            'tenant_id' => $this->tenantId, 'feature_key' => $key, 'is_enabled' => $on ? 1 : 0,
            'reason' => 'phpunit', 'overridden_by' => DB::table('super_admins')->value('id'),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        app(FeatureService::class)->bust($this->tenantId);
    }

    private function setType(string $type): void
    {
        DB::table('user_job_details')->where('user_id', $this->employee->id)->update(['attendance_type' => $type]);
    }

    /** Mirrors what CheckSingleDeviceLogin expects for this user. */
    private function headers(): array
    {
        $headers = ['Authorization' => 'Bearer ' . auth('api')->login($this->employee), 'Accept' => 'application/json'];
        if ($this->employee->last_login_token !== null) {
            $headers['Device-Token'] = $this->employee->last_login_token;
        }

        return $headers;
    }

    private function attendanceCount(): int
    {
        return DB::table('attendances')->where('user_id', $this->employee->id)->count();
    }

    public function test_biometric_only_employee_cannot_clock_in_or_out_from_the_app(): void
    {
        $this->setFeature('attendance_biometric', true);
        $this->setType('biometric_only');
        $before = $this->attendanceCount();

        foreach (['clock-in', 'clock-out'] as $action) {
            $this->withHeaders($this->headers())
                ->postJson("/api/user/attendance/{$action}", ['lat' => 28.6, 'long' => 77.2, 'address' => 'Test address'])
                ->assertOk()
                ->assertJsonPath('status', false)
                ->assertJsonPath('message', fn ($m) => str_contains($m, 'biometric machine'));
        }

        $this->assertSame($before, $this->attendanceCount());

        $this->withHeaders($this->headers())->getJson('/api/user/attendance/today')
            ->assertOk()
            ->assertJsonPath('data.attendance_type', 'biometric_only')
            ->assertJsonPath('data.can_mark_attendance', false);
    }

    public function test_not_blocked_once_the_biometric_add_on_is_removed(): void
    {
        $this->setFeature('attendance_biometric', false);
        $this->setType('biometric_only');

        $this->withHeaders($this->headers())->getJson('/api/user/attendance/today')
            ->assertOk()
            ->assertJsonPath('data.can_mark_attendance', true)
            ->assertJsonPath('data.attendance_block_reason', null);

        // Validation runs (not the biometric block) → normal app flow.
        $this->withHeaders($this->headers())->postJson('/api/user/attendance/clock-in', [])
            ->assertJsonPath('message', fn ($m) => ! str_contains((string) $m, 'biometric machine'));
    }

    public function test_manual_employee_is_not_blocked(): void
    {
        $this->setFeature('attendance_biometric', true);
        $this->setType('manual_attendance');

        $this->withHeaders($this->headers())->getJson('/api/user/attendance/today')
            ->assertOk()
            ->assertJsonPath('data.can_mark_attendance', true);
    }
}
