<?php

namespace Tests\Concerns;

/**
 * Any write through AttendanceEntryService::record() (which every punch
 * capture goes through) also recomputes attendance_summaries for the
 * affected month. Tests that run against the shared dev DB (no
 * RefreshDatabase) must snapshot the pre-existing summary row before writing
 * test data, and restore it afterwards — otherwise a test-only row (or a
 * test-triggered recompute of a real row) leaks into real data permanently.
 */
trait RestoresAttendanceSummary
{
    private ?array $summarySnapshot = null;

    private function snapshotSummary(int $tenantId, int $userId, string $yearMonth): void
    {
        $row = \DB::table('attendance_summaries')
            ->where('tenant_id', $tenantId)->where('user_id', $userId)->where('year_month', $yearMonth)
            ->first();

        $this->summarySnapshot = [
            'tenant_id' => $tenantId, 'user_id' => $userId, 'year_month' => $yearMonth,
            'row' => $row ? (array) $row : null,
        ];
    }

    private function restoreSummary(): void
    {
        if ($this->summarySnapshot === null) {
            return;
        }

        ['tenant_id' => $tenantId, 'user_id' => $userId, 'year_month' => $yearMonth, 'row' => $original] = $this->summarySnapshot;

        \DB::table('attendance_summaries')
            ->where('tenant_id', $tenantId)->where('user_id', $userId)->where('year_month', $yearMonth)
            ->delete();

        if ($original !== null) {
            \DB::table('attendance_summaries')->insert($original);
        }
    }
}
