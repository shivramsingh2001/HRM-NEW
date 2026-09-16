<?php

namespace App\Jobs;

use App\Services\Attendance\LatePolicyService;
use App\Services\AttendanceSummaryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Tier 1 / W2 — the deferred half of an attendance write.
 *
 * Recomputes effective_status / day_fraction (LatePolicyService) and the
 * monthly rollup (AttendanceSummaryService) for one (tenant, user, month).
 * Idempotent and unique: many writes to the same month in quick succession
 * collapse to a single run.
 */
class RecalculateAttendanceMonth implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public array $backoff = [10, 30, 60, 120];

    /** Drop the uniqueness lock after 5 min even if the job never ran. */
    public int $uniqueFor = 300;

    public function __construct(
        public int $userId,
        public int $tenantId,
        public string $yearMonth,
    ) {
        $this->onQueue('attendance');
    }

    public function uniqueId(): string
    {
        return "{$this->tenantId}:{$this->userId}:{$this->yearMonth}";
    }

    public function handle(LatePolicyService $latePolicy, AttendanceSummaryService $summary): void
    {
        $latePolicy->recalculateMonth($this->userId, $this->tenantId, $this->yearMonth);
        $summary->updateMonthlySummary($this->userId, $this->yearMonth, $this->tenantId);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('RecalculateAttendanceMonth failed', [
            'tenant_id' => $this->tenantId,
            'user_id' => $this->userId,
            'year_month' => $this->yearMonth,
            'error' => $e->getMessage(),
        ]);
    }
}
