<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Clock In/Out Log report (PunchReportController): punch rows from
 * attendance_punches plus days that only have an attendances row.
 * Shared dev DB — throwaway employee, cleaned in tearDown.
 */
class PunchReportTest extends TestCase
{
    private User $admin;
    private User $employee;
    private string $date;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::withoutGlobalScopes()->where('role', 'admin')->whereNotNull('tenant_id')->orderBy('id')->firstOrFail();
        $tenantId = (int) $this->admin->tenant_id;
        $this->date = now()->startOfMonth()->toDateString();

        $this->employee = User::withoutGlobalScopes()->forceCreate([
            'name' => 'Punch Report Temp ' . uniqid(),
            'email' => 'punchreport.' . uniqid() . '@phpunit.test',
            'employee_id' => 'PR' . random_int(100000, 999999),
            'password' => bcrypt('x'),
            'role' => 'employee',
            'status' => 1,
            'tenant_id' => $tenantId,
        ]);

        // Two sessions from the punch log...
        foreach ([['in', '09:00:00', 1], ['out', '13:00:00', 1], ['in', '14:00:00', 2], ['out', '18:30:00', 2]] as [$dir, $t, $seq]) {
            DB::table('attendance_punches')->insert([
                'tenant_id' => $tenantId, 'user_id' => $this->employee->id, 'date' => $this->date,
                'direction' => $dir, 'punched_at' => "{$this->date} {$t}", 'source' => 'mobile_app', 'method' => 'gps',
                'lat' => 28.6139, 'long' => 77.2090, 'address' => 'phpunit street', 'distance_meters' => 12.5,
                'location_verification' => 'verified', 'status' => 'active', 'session_seq' => $seq, 'is_regularized' => 0,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        // ...and a day that exists only as an attendances row (single-punch path).
        DB::table('attendances')->insert([
            'tenant_id' => $tenantId, 'user_id' => $this->employee->id,
            'date' => now()->startOfMonth()->addDay()->toDateString(),
            'clock_in' => now()->startOfMonth()->addDay()->setTime(10, 0)->format('Y-m-d H:i:s'),
            'clock_out' => now()->startOfMonth()->addDay()->setTime(17, 0)->format('Y-m-d H:i:s'),
            'attendance_type' => 'fingerprint', 'clock_in_address' => 'phpunit gate', 'status' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        DB::table('attendance_punches')->where('user_id', $this->employee->id)->delete();
        DB::table('attendances')->where('user_id', $this->employee->id)->delete();
        User::withoutGlobalScopes()->where('id', $this->employee->id)->forceDelete();
        parent::tearDown();
    }

    public function test_lists_every_punch_and_single_punch_days_for_the_employee(): void
    {
        $res = $this->actingAs($this->admin)->get(route('report.attendance.punches.index', ['user_id' => $this->employee->id]));

        $res->assertOk();
        $rows = $res->viewData('rows');
        $this->assertSame(6, $rows->total());                       // 4 punches + day row in/out
        $this->assertSame(3, (int) $res->viewData('stats')->ins);
        $this->assertEqualsCanonicalizing(['mobile_app', 'biometric'], collect($rows->items())->pluck('source')->unique()->values()->all());
        $res->assertSee('phpunit street')->assertSee('Inside radius');
    }

    public function test_direction_filter_and_default_current_month(): void
    {
        $res = $this->actingAs($this->admin)->get(route('report.attendance.punches.index', ['user_id' => $this->employee->id, 'direction' => 'out']));

        $res->assertOk();
        $this->assertSame(3, $res->viewData('rows')->total());
        $this->assertSame(now()->startOfMonth()->toDateString(), $res->viewData('filters')['start']);
        $this->assertSame(now()->endOfMonth()->toDateString(), $res->viewData('filters')['end']);
    }

    public function test_csv_export(): void
    {
        $res = $this->actingAs($this->admin)->get(route('report.attendance.punches.export', ['user_id' => $this->employee->id]));

        $res->assertOk();
        $this->assertStringContainsString('text/csv', $res->headers->get('Content-Type'));
        $csv = $res->getContent();
        $this->assertStringContainsString('phpunit street', $csv);
        $this->assertSame(7, count(array_filter(explode("\n", trim($csv)))));  // header + 6 rows
    }
}
