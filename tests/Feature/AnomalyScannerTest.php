<?php

namespace Tests\Feature;

use App\Models\AttendanceAnomaly;
use App\Services\Analytics\AnomalyScanner;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Tier 2 / T2-F — buddy-punch detection + idempotent re-scan.
 */
class AnomalyScannerTest extends TestCase
{
    public function test_buddy_punch_is_flagged_once(): void
    {
        $tenantId = (int) (DB::table('users')->value('tenant_id') ?: 1);
        $userIds = DB::table('users')->where('tenant_id', $tenantId)->limit(2)->pluck('id')->all();
        $this->assertCount(2, $userIds, 'need two users in a tenant');

        $date = '2019-02-02'; // far past, no real data
        $device = 'PHPUNIT-DEV-' . uniqid();

        $ids = [];
        foreach ($userIds as $i => $uid) {
            $ids[] = DB::table('attendances')->insertGetId([
                'tenant_id' => $tenantId, 'user_id' => $uid, 'date' => $date,
                'clock_in' => $date . ' 09:0' . $i . ':00',
                'device_id' => $device, 'status' => 1,
            ]);
        }

        $scanner = app(AnomalyScanner::class);

        $first = $scanner->scanDay($tenantId, $date);
        $this->assertGreaterThanOrEqual(1, $first, 'buddy punch should be flagged');

        $second = $scanner->scanDay($tenantId, $date);
        $this->assertSame(0, $second, 're-scan must not double-flag');

        $anomaly = AttendanceAnomaly::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)->where('date', $date)->where('type', 'buddy_punch')->first();
        $this->assertNotNull($anomaly);
        $this->assertSame('high', $anomaly->severity);
        $this->assertContains($userIds[0], $anomaly->detail['user_ids']);

        // cleanup
        AttendanceAnomaly::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('date', $date)->delete();
        DB::table('attendances')->whereIn('id', $ids)->delete();
    }
}
