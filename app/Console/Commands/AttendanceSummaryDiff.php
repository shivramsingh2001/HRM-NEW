<?php

namespace App\Console\Commands;

use App\Models\AttendanceSummary;
use App\Models\User;
use App\Services\AttendanceSummaryService;
use Illuminate\Console\Command;

/**
 * Tier 1 / W3 — parity check for the summary read-through.
 *
 * Recomputes each sampled (user, month) live and compares it to the persisted
 * attendance_summaries row through AttendanceSummaryService::getMonthly(). Flip
 * ATTENDANCE_SUMMARY_READTHROUGH=true only when this reports no material drift.
 */
class AttendanceSummaryDiff extends Command
{
    protected $signature = 'attendance:summary-diff
                            {--month= : Y-m to compare (default: current month)}
                            {--tenant= : restrict to one tenant}
                            {--user= : restrict to one user}
                            {--limit=20 : how many users to sample}
                            {--tolerance=0.01 : ignore numeric deltas at or below this}';

    protected $description = 'Compare live-recomputed vs persisted monthly attendance summaries.';

    /** Fields compared and the tolerance treatment (true = numeric). */
    private const FIELDS = [
        'present_days' => true, 'absent_days' => true, 'half_days' => true,
        'late_days' => true, 'paid_leaves' => true, 'unpaid_leaves' => true,
        'total_leaves' => true, 'holidays' => true, 'week_offs' => true,
        'holiday_work_days' => true, 'weekoff_work_days' => true,
        'total_worked_hours' => true, 'total_overtime_hours' => true,
        'total_late_minutes' => true, 'continuous_present_days' => true,
        'continuous_absent_days' => true,
    ];

    public function handle(AttendanceSummaryService $service): int
    {
        $month = $this->option('month') ?: now()->format('Y-m');
        $tolerance = (float) $this->option('tolerance');

        $users = User::withoutGlobalScopes()
            ->when($this->option('tenant'), fn ($q) => $q->where('tenant_id', $this->option('tenant')))
            ->when($this->option('user'), fn ($q) => $q->where('id', $this->option('user')))
            ->where('status', 1)
            ->limit((int) $this->option('limit'))
            ->get(['id', 'tenant_id', 'name']);

        if ($users->isEmpty()) {
            $this->error('No matching users.');

            return self::FAILURE;
        }

        $this->info("Comparing {$users->count()} user(s) for {$month}  (tolerance {$tolerance})");

        $drifted = 0;
        $rows = [];

        foreach ($users as $user) {
            // Live recompute (writes the row), then snapshot it.
            $service->updateMonthlySummary($user->id, $month, $user->tenant_id);
            $live = AttendanceSummary::withoutGlobalScopes()
                ->where('tenant_id', $user->tenant_id)
                ->where('user_id', $user->id)
                ->where('year_month', $month)
                ->first();

            // Read-through path (should return the same row, no recompute).
            $read = $service->getMonthly($user->id, $month, $user->tenant_id, allowStale: true);

            $deltas = [];
            foreach (self::FIELDS as $field => $numeric) {
                $a = (float) ($live->{$field} ?? 0);
                $b = (float) ($read[$field] ?? 0);
                if (abs($a - $b) > $tolerance) {
                    $deltas[] = "{$field}: live={$a} read={$b}";
                }
            }

            if ($deltas) {
                $drifted++;
                $rows[] = [$user->id, $user->name, implode('; ', $deltas)];
            }
        }

        if ($rows) {
            $this->table(['User', 'Name', 'Drift'], $rows);
            $this->warn("{$drifted} of {$users->count()} drifted.");

            return self::FAILURE;
        }

        $this->info('No drift — safe to enable ATTENDANCE_SUMMARY_READTHROUGH.');

        return self::SUCCESS;
    }
}
