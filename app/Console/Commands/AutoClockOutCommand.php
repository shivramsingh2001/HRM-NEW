<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Attendance;
use App\Models\AttendanceLog;
use App\Models\AttendanceTrack;
use App\Models\UserShift;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AutoClockOutCommand extends Command
{
    protected $signature = 'attendance:auto-clockout 
                            {--hours=15 : Max hours before auto clockout}
                            {--dry-run : Test mode}
                            {--user-id= : Specific user ID}';

    protected $description = 'Auto clock-out users exceeding allowed working hours';

    public function handle()
    {
        $this->info('🚀 Starting auto clock-out process...');
        $this->newLine();

        $maxHours = (int) $this->option('hours');
        $isDryRun = $this->option('dry-run');
        $specificUserId = $this->option('user-id');
        $now = Carbon::now();

        if ($isDryRun) {
            $this->warn('⚠️ DRY RUN MODE - No database changes');
        }

        $this->line("Max Hours: {$maxHours}");
        $this->line("Current Time: " . $now->format('Y-m-d H:i:s'));
        $this->line("User Filter: " . ($specificUserId ?? 'All'));
        $this->newLine();

        /**
         * Step 1: Fetch active attendances
         */
        $query = Attendance::whereNotNull('clock_in')
            ->where(function ($q) {
                $q->whereNull('clock_out')
                    ->orWhere('clock_out', '0000-00-00 00:00:00')
                    ->orWhere('clock_out', '');
            });

        if ($specificUserId) {
            $query->where('user_id', $specificUserId);
        }

        $records = $query->get();

        /**
         * Step 2: Filter by hours (FIXED for night shifts)
         */
        $activeAttendances = $records->filter(function ($attendance) use ($maxHours, $now) {
            try {
                $clockIn = Carbon::parse($attendance->clock_in);
                
                // For night shifts that cross midnight, adjust the calculation
                $calculationNow = clone $now;
                if ($calculationNow->lt($clockIn)) {
                    $calculationNow->addDay();
                }
                
                $hoursWorked = $clockIn->diffInHours($calculationNow);
                return $hoursWorked >= $maxHours;
            } catch (\Exception $e) {
                return false;
            }
        })->values();

        $total = $activeAttendances->count();

        if ($total === 0) {
            $this->info('✅ No users exceed working hours.');
            return Command::SUCCESS;
        }

        $this->info("📊 Found {$total} records");
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $success = 0;
        $errors = 0;

        /**
         * Step 3: Process records
         */
        foreach ($activeAttendances as $attendance) {
            try {
                $clockIn = Carbon::parse($attendance->clock_in);
                $clockOut = $now;
                
                // FIXED: Calculate worked hours correctly for night shifts
                // Create a copy for calculation
                $calculationClockOut = clone $clockOut;
                
                // If clock_out time is less than clock_in time, it means it crossed midnight
                // Add a day to clock_out for correct calculation
                if ($calculationClockOut->lt($clockIn)) {
                    $calculationClockOut->addDay();
                }
                
                // Calculate difference in seconds
                $workedSeconds = $clockIn->diffInSeconds($calculationClockOut);
                $workedHours = round($workedSeconds / 3600, 2);
                
                // FIXED: Format total hours correctly for any duration (including >24 hours)
                $hours = floor($workedSeconds / 3600);
                $minutes = floor(($workedSeconds % 3600) / 60);
                $seconds = $workedSeconds % 60;
                $totalHoursFormatted = sprintf("%02d:%02d:%02d", $hours, $minutes, $seconds);
                
                // Log for debugging
                $this->line("\n📝 Processing: Attendance ID: {$attendance->id}");
                $this->line("   Clock In: {$clockIn->format('Y-m-d H:i:s')}");
                $this->line("   Clock Out: {$clockOut->format('Y-m-d H:i:s')}");
                $this->line("   Worked Seconds: {$workedSeconds}");
                $this->line("   Total Hours: {$totalHoursFormatted}");
                $this->line("   Worked Hours: {$workedHours}");

                if (!$isDryRun) {
                    DB::beginTransaction();

                    // Update attendance with correct calculations
                    $attendance->update([
                        'clock_out' => $clockOut,
                        'total_hours' => $totalHoursFormatted, // FIXED: Now shows correct hours
                        'worked_hours' => $workedHours,
                        'attendance_status' => 'present',
                        'remarks' => "Auto clocked out after {$maxHours} hrs on " . $now->format('Y-m-d H:i:s'),
                    ]);

                    // Create log with correct data
                    $this->createLog($attendance, $maxHours, $now, $totalHoursFormatted, $workedHours);

                    // Update shift
                    UserShift::where('user_id', $attendance->user_id)
                        ->where('date', $attendance->date)
                        ->update(['status' => 'complete']);

                    DB::commit();

                    Log::info('Auto clock-out success', [
                        'attendance_id' => $attendance->id,
                        'user_id' => $attendance->user_id,
                        'clock_in' => $clockIn->format('Y-m-d H:i:s'),
                        'clock_out' => $clockOut->format('Y-m-d H:i:s'),
                        'total_hours' => $totalHoursFormatted,
                        'worked_hours' => $workedHours,
                        'worked_seconds' => $workedSeconds
                    ]);
                }

                $success++;

            } catch (\Exception $e) {
                $errors++;

                if (!$isDryRun) {
                    DB::rollBack();
                }

                Log::error('Auto clock-out failed', [
                    'attendance_id' => $attendance->id ?? null,
                    'user_id' => $attendance->user_id ?? null,
                    'error' => $e->getMessage()
                ]);
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        /**
         * Summary
         */
        $this->info("✅ Completed");
        $this->line("Processed: {$total}");
        $this->line("Success: {$success}");
        $this->line("Errors: {$errors}");

        return Command::SUCCESS;
    }

    /**
     * Create log with complete details
     */
    private function createLog($attendance, $maxHours, $now, $totalHoursFormatted, $workedHours)
    {
        try {
            $clockIn = Carbon::parse($attendance->clock_in);
            
            AttendanceLog::create([
                'user_id' => $attendance->user_id,
                'attendance_id' => $attendance->id,
                'event_type' => 'auto_check_out',
                'event_time' => $now,
                'latitude' => $attendance->clock_in_lat,
                'longitude' => $attendance->clock_in_long,
                'address' => 'Auto clock-out',
                'verification_method' => 'system',
                'user_agent' => 'AutoClockOutCommand',
                'raw_data' => json_encode([
                    'reason' => "{$maxHours} hours exceeded",
                    'clock_in' => $clockIn->format('Y-m-d H:i:s'),
                    'clock_out' => $now->toDateTimeString(),
                    'total_hours' => $totalHoursFormatted,
                    'worked_hours' => $workedHours,
                    'worked_seconds' => $clockIn->diffInSeconds($now),
                    'max_hours_allowed' => $maxHours,
                    'night_shift' => $now->lt($clockIn)
                ])
            ]);
        } catch (\Exception $e) {
            Log::error('Log creation failed', [
                'attendance_id' => $attendance->id,
                'error' => $e->getMessage()
            ]);
        }
    }
}