<?php

namespace App\Services\Attendance;

use Illuminate\Support\Facades\DB;

/**
 * Tier 2 / T2-E — read/change attendance_period_locks.
 */
class PeriodLockService
{
    /** @var array<string,bool> */
    private array $memo = [];

    public function isLocked(int $tenantId, string $yearMonth): bool
    {
        $key = $tenantId . '|' . $yearMonth;
        if (array_key_exists($key, $this->memo)) {
            return $this->memo[$key];
        }

        $status = DB::table('attendance_period_locks')
            ->where('tenant_id', $tenantId)
            ->where('year_month', $yearMonth)
            ->value('status');

        return $this->memo[$key] = ($status === 'locked');
    }

    public function lock(int $tenantId, string $yearMonth, ?int $actorId, ?string $reason = null): void
    {
        DB::table('attendance_period_locks')->updateOrInsert(
            ['tenant_id' => $tenantId, 'year_month' => $yearMonth],
            [
                'status' => 'locked',
                'locked_by' => $actorId,
                'locked_at' => now(),
                'reason' => $reason,
                'updated_at' => now(),
                'created_at' => DB::raw('COALESCE(created_at, NOW())'),
            ]
        );
        unset($this->memo[$tenantId . '|' . $yearMonth]);
    }

    public function reopen(int $tenantId, string $yearMonth, ?int $actorId, ?string $reason = null): void
    {
        DB::table('attendance_period_locks')
            ->where('tenant_id', $tenantId)
            ->where('year_month', $yearMonth)
            ->update([
                'status' => 'reopened',
                'reopened_by' => $actorId,
                'reopened_at' => now(),
                'reason' => $reason,
                'updated_at' => now(),
            ]);
        unset($this->memo[$tenantId . '|' . $yearMonth]);
    }
}
