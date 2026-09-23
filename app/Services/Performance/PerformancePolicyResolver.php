<?php

namespace App\Services\Performance;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Resolves the performance-scoring policy in force for a tenant on a given
 * date. Mirrors App\Services\Attendance\PolicyResolver exactly.
 *
 * Precedence:
 *   1. newest performance_policies row for the tenant with effective_from <= date
 *   2. the global default row (tenant_id IS NULL)
 *   3. PerformancePolicySnapshot::default() if the table is empty
 *
 * Results are memoised per (tenant, date) for the request; bust with
 * forget() after a settings save. $tenantId is always passed in explicitly —
 * never resolved via app('current_tenant') — so this is safe to call from
 * scheduled console commands where that binding is absent.
 */
class PerformancePolicyResolver
{
    /** @var array<string,PerformancePolicySnapshot> */
    private array $memo = [];

    public function forTenantDate(int $tenantId, string $date): PerformancePolicySnapshot
    {
        $day = Carbon::parse($date)->format('Y-m-d');
        $key = $tenantId . '|' . $day;

        if (isset($this->memo[$key])) {
            return $this->memo[$key];
        }

        return $this->memo[$key] = $this->resolve($tenantId, $day);
    }

    /**
     * Policy in force on the 1st of the given month — the anchor a monthly
     * rollup must use so a later-dated policy row never retroactively
     * re-grades a closed month.
     */
    public function forTenantMonth(int $tenantId, string $yearMonth): PerformancePolicySnapshot
    {
        return $this->forTenantDate($tenantId, Carbon::parse($yearMonth . '-01')->format('Y-m-d'));
    }

    public function forget(): void
    {
        $this->memo = [];
    }

    private function resolve(int $tenantId, string $day): PerformancePolicySnapshot
    {
        if (! $this->tableReady()) {
            return PerformancePolicySnapshot::default();
        }

        $row = DB::table('performance_policies')
            ->whereNull('deleted_at')
            ->where('tenant_id', $tenantId)
            ->whereDate('effective_from', '<=', $day)
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->first();

        if ($row) {
            return PerformancePolicySnapshot::fromRow($row);
        }

        $global = DB::table('performance_policies')
            ->whereNull('deleted_at')
            ->whereNull('tenant_id')
            ->whereDate('effective_from', '<=', $day)
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->first();

        return $global ? PerformancePolicySnapshot::fromRow($global) : PerformancePolicySnapshot::default();
    }

    private function tableReady(): bool
    {
        static $ready = null;
        if ($ready === null) {
            try {
                $ready = DB::getSchemaBuilder()->hasTable('performance_policies');
            } catch (\Throwable $e) {
                $ready = false;
            }
        }

        return $ready;
    }
}
