<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\AttendanceSummaryService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class UpdateAttendanceSummaries extends Command
{
    protected $signature = 'attendance:update-summaries 
                            {--month= : Month in Y-m format}
                            {--user= : Specific user ID to update}
                            {--force : Force update even if already calculated}
                            {--dry-run : Run without saving changes}';

    protected $description = 'Update attendance summaries for all users with worked hours thresholds (2h absent, 2-5h half day, 5-9h present)';

    protected $summaryService;

    public function __construct(AttendanceSummaryService $summaryService)
    {
        parent::__construct();
        $this->summaryService = $summaryService;
    }

    public function handle()
    {
        $month = $this->option('month') ?? Carbon::now()->format('Y-m');
        $userId = $this->option('user');
        $force = $this->option('force');
        $dryRun = $this->option('dry-run');

        $this->info("==========================================");
        $this->info("Attendance Summary Update");
        $this->info("==========================================");
        $this->info("Month: {$month}");
        $this->info("Thresholds:");
        $this->info("  • Absent: < 2 hours");
        $this->info("  • Half Day: 2-5 hours");
        $this->info("  • Present: 5-9 hours");
        $this->info("  • Overtime: > 9 hours");
        $this->info("==========================================\n");

        if ($dryRun) {
            $this->warn("DRY RUN MODE - No changes will be saved\n");
        }

        // Build user query
        $userQuery = User::where('status', 1);

        if ($userId) {
            $userQuery->where('id', $userId);
            $this->info("Updating summary for user ID: {$userId}");
        } else {
            $this->info("Updating summaries for all active users");
        }

        $users = $userQuery->get();

        if ($users->isEmpty()) {
            $this->error("No users found!");
            return 1;
        }

        $this->info("Found " . count($users) . " users to process\n");

        $bar = $this->output->createProgressBar(count($users));
        $bar->start();

        $successCount = 0;
        $failedCount = 0;
        $failedUsers = [];

        foreach ($users as $user) {
            try {
                if ($force) {
                    // Delete existing summary to force recalculation
                    if (!$dryRun) {
                        \App\Models\AttendanceSummary::where('user_id', $user->id)
                            ->where('year_month', $month)
                            ->delete();
                    }
                }

                if ($dryRun) {
                    // Just simulate the update
                    $this->simulateSummary($user->id, $month);
                    $successCount++;
                } else {
                    $result = $this->summaryService->updateMonthlySummary($user->id, $month);
                    if ($result) {
                        $successCount++;
                    } else {
                        throw new \Exception("Service returned false");
                    }
                }
            } catch (\Exception $e) {
                $failedCount++;
                $failedUsers[] = [
                    'id' => $user->id,
                    'name' => $user->name,
                    'error' => $e->getMessage()
                ];

                // Log::error("Failed to update summary for user {$user->id}", [
                //     'error' => $e->getMessage(),
                //     'month' => $month
                // ]);
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        // Display results
        $this->info("==========================================");
        $this->info("Update Complete!");
        $this->info("==========================================");
        $this->info("✓ Success: {$successCount} users");
        $this->error("✗ Failed: {$failedCount} users");

        if (!empty($failedUsers)) {
            $this->newLine();
            $this->warn("Failed Users Details:");
            $this->table(
                ['ID', 'Name', 'Error'],
                $failedUsers
            );
        }

        if ($dryRun) {
            $this->newLine();
            $this->warn("DRY RUN completed. No changes were saved.");
            $this->warn("Remove --dry-run flag to actually save changes.");
        }

        return $failedCount > 0 ? 1 : 0;
    }

    /**
     * Simulate summary update without saving
     */
    private function simulateSummary($userId, $month)
    {
        $startDate = Carbon::parse($month . '-01')->startOfMonth();
        $endDate = Carbon::parse($month . '-01')->endOfMonth();

        $attendances = \App\Models\Attendance::where('user_id', $userId)
            ->where('date', '>=', $startDate)
            ->where('date', '<=', $endDate)
            ->get()
            ->groupBy('date');

        $this->line("\n");
        $this->line("User ID: {$userId} - Month: {$month}");

        $presentCount = 0;
        $halfDayCount = 0;
        $absentCount = 0;

        for ($date = $startDate->copy(); $date <= $endDate; $date->addDay()) {
            $dateString = $date->format('Y-m-d');

            if (isset($attendances[$dateString])) {
                $hoursData = $this->calculateHoursFromEntries($attendances[$dateString]);
                $workedHours = $hoursData['total_hours'];

                if ($workedHours >= 5) {
                    $presentCount++;
                } elseif ($workedHours >= 2) {
                    $halfDayCount++;
                } else {
                    $absentCount++;
                }
            } else {
                $absentCount++;
            }
        }

        $this->line("  Present: {$presentCount} days");
        $this->line("  Half Day: {$halfDayCount} days");
        $this->line("  Absent: {$absentCount} days");
    }

    /**
     * Calculate hours from attendance entries
     */
    private function calculateHoursFromEntries($entries)
    {
        $totalSeconds = 0;

        foreach ($entries as $entry) {
            if ($entry->clock_in && $entry->clock_out) {
                $clockIn = Carbon::parse($entry->clock_in);
                $clockOut = Carbon::parse($entry->clock_out);

                if ($clockOut->gt($clockIn)) {
                    $totalSeconds += $clockOut->diffInSeconds($clockIn);
                }
            }
        }

        return [
            'total_hours' => round($totalSeconds / 3600, 2)
        ];
    }
}
