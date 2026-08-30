<?php
// app/Jobs/ProcessFingerprintPunch.php

namespace App\Jobs;

use App\Models\Attendance;
use App\Models\FingerprintPunchLog;
use App\Models\FingerprintDevice;
use App\Models\User;
use App\Models\Shift;
use App\Models\UserShift;
use App\Models\Branch;
use App\Models\UserJobDetail;
use Carbon\Carbon;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessFingerprintPunch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 60;
    public $tries = 3;

    public function __construct(
        protected FingerprintPunchLog $log,
        protected FingerprintDevice $device
    ) {}

    public function handle(): void
    {
        // =============================================
        // STEP 1: LOG START
        // =============================================
        Log::channel('daily')->info('===== PROCESS FINGERPRINT PUNCH START =====');
        Log::channel('daily')->info('Log ID: ' . $this->log->id);
        Log::channel('daily')->info('Device ID: ' . $this->device->id);
        Log::channel('daily')->info('Log Data:', [
            'serial_number' => $this->log->serial_number,
            'device_user_id' => $this->log->device_user_id,
            'user_id' => $this->log->user_id,
            'punch_type' => $this->log->punch_type,
            'log_time' => $this->log->log_time?->format('Y-m-d H:i:s'),
            'processed' => $this->log->processed,
            'process_error' => $this->log->process_error,
        ]);

        try {
            // =============================================
            // STEP 2: CHECK IF ALREADY PROCESSED
            // =============================================
            Log::channel('daily')->info('Step 2: Checking if already processed...');
            
            if ($this->log->processed) {
                Log::channel('daily')->info('Log already processed, skipping');
                return;
            }

            // =============================================
            // STEP 3: FIND USER
            // =============================================
            Log::channel('daily')->info('Step 3: Finding user...');
            Log::channel('daily')->info('Looking for user_id: ' . $this->log->user_id);
            
            $user = User::find($this->log->user_id);

            if (!$user) {
                Log::channel('daily')->error('❌ USER NOT FOUND', [
                    'user_id' => $this->log->user_id,
                    'log_id' => $this->log->id,
                ]);
                $this->log->update([
                    'processed' => true,
                    'process_error' => "User not found: {$this->log->user_id}",
                ]);
                return;
            }

            Log::channel('daily')->info('✅ User found:', [
                'user_id' => $user->id,
                'name' => $user->name,
                'employee_id' => $user->employee_id,
            ]);

            // =============================================
            // STEP 4: GET DATE AND PUNCH TYPE
            // =============================================
            Log::channel('daily')->info('Step 4: Getting date and punch type...');
            
            $date = $this->log->log_time->format('Y-m-d');
            $isClockIn = in_array($this->log->punch_type, [
                'CheckIn', 'BreakIn', 'OverTimeIn', 'MealIn'
            ]);

            Log::channel('daily')->info('Date: ' . $date);
            Log::channel('daily')->info('Is Clock In: ' . ($isClockIn ? 'Yes' : 'No'));
            Log::channel('daily')->info('Punch Type: ' . $this->log->punch_type);

            // =============================================
            // STEP 5: GET USER SHIFT
            // =============================================
            Log::channel('daily')->info('Step 5: Getting user shift...');
            
            $shift = $this->getUserShift($user->id, $date, $this->device->tenant_id);
            
            Log::channel('daily')->info('Shift result:', [
                'shift_found' => (bool) $shift,
                'shift_id' => $shift?->id,
                'shift_name' => $shift?->name,
                'start_time' => $shift?->start_time,
                'end_time' => $shift?->end_time,
            ]);

            // =============================================
            // STEP 6: GET USER BRANCH
            // =============================================
            Log::channel('daily')->info('Step 6: Getting user branch...');
            
            $branchId = $this->getUserBranch($user->id, $this->device->tenant_id);
            
            Log::channel('daily')->info('Branch result:', [
                'branch_found' => (bool) $branchId,
                'branch_id' => $branchId,
            ]);

            // =============================================
            // STEP 7: GET OR CREATE ATTENDANCE
            // =============================================
            Log::channel('daily')->info('Step 7: Getting or creating attendance record...');
            
            DB::beginTransaction();
            Log::channel('daily')->info('Transaction started');

            $attendance = Attendance::firstOrNew([
                'user_id' => $user->id,
                'date' => $date,
                'tenant_id' => $this->device->tenant_id,
            ]);

            Log::channel('daily')->info('Attendance record:', [
                'exists' => $attendance->exists,
                'id' => $attendance->id ?? 'new',
                'user_id' => $attendance->user_id ?? $user->id,
                'date' => $attendance->date ?? $date,
            ]);

            if (!$attendance->exists) {
                Log::channel('daily')->info('Creating new attendance record...');
                
                $attendance->attendance_type = 'fingerprint';
                $attendance->status = 1;
                $attendance->device_id = $this->device->serial_number;
                $attendance->branch_id = $branchId;
                $attendance->shift_id = $shift ? $shift->id : null;
                $attendance->scheduled_shift_start = $shift ? $shift->start_time : null;
                $attendance->scheduled_shift_end = $shift ? $shift->end_time : null;
                
                Log::channel('daily')->info('New attendance data set:', [
                    'attendance_type' => 'fingerprint',
                    'device_id' => $this->device->serial_number,
                    'branch_id' => $branchId,
                    'shift_id' => $shift ? $shift->id : null,
                ]);
            } else {
                Log::channel('daily')->info('Using existing attendance record');
            }

            // =============================================
            // STEP 8: PROCESS CLOCK IN OR OUT
            // =============================================
            if ($isClockIn) {
                Log::channel('daily')->info('Step 8a: Processing CLOCK-IN...');
                $this->processClockIn($attendance, $user, $shift, $branchId);
            } else {
                Log::channel('daily')->info('Step 8b: Processing CLOCK-OUT...');
                $this->processClockOut($attendance, $user, $shift);
            }

            // =============================================
            // STEP 9: UPDATE LOG AS PROCESSED
            // =============================================
            Log::channel('daily')->info('Step 9: Marking log as processed...');
            
            $this->log->update([
                'processed' => true,
                'user_id' => $user->id,
                'process_error' => null,
            ]);

            Log::channel('daily')->info('Log updated successfully');

            // =============================================
            // STEP 10: COMMIT TRANSACTION
            // =============================================
            DB::commit();
            Log::channel('daily')->info('Transaction committed');

            Log::channel('daily')->info('===== PROCESS FINGERPRINT PUNCH SUCCESS =====');

        } catch (Exception $e) {
            // =============================================
            // ERROR HANDLING
            // =============================================
            Log::channel('daily')->error('===== PROCESS FINGERPRINT PUNCH FAILED =====');
            Log::channel('daily')->error('Error Message: ' . $e->getMessage());
            Log::channel('daily')->error('Error Code: ' . $e->getCode());
            Log::channel('daily')->error('Error File: ' . $e->getFile());
            Log::channel('daily')->error('Error Line: ' . $e->getLine());
            Log::channel('daily')->error('Error Trace: ' . $e->getTraceAsString());
            
            Log::channel('daily')->error('Log Data:', [
                'log_id' => $this->log->id,
                'user_id' => $this->log->user_id,
                'punch_type' => $this->log->punch_type,
            ]);

            try {
                DB::rollBack();
                Log::channel('daily')->info('Transaction rolled back');
            } catch (Exception $rollbackError) {
                Log::channel('daily')->error('Rollback failed: ' . $rollbackError->getMessage());
            }
            
            try {
                $this->log->update([
                    'processed' => true,
                    'process_error' => $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine(),
                ]);
                Log::channel('daily')->info('Log updated with error');
            } catch (Exception $logError) {
                Log::channel('daily')->error('Failed to update log: ' . $logError->getMessage());
            }

            throw $e;
        }
    }

    private function processClockIn($attendance, $user, $shift, $branchId): void
    {
        Log::channel('daily')->info('--- processClockIn START ---');

        try {
            if ($attendance->clock_in) {
                Log::channel('daily')->warning('Already clocked in', [
                    'clock_in' => $attendance->clock_in,
                ]);
                $this->log->update(['process_error' => 'Already clocked in']);
                return;
            }

            Log::channel('daily')->info('Calculating late minutes...');

            // Calculate late minutes
            $lateMinutes = 0;
            if ($shift) {
                $scheduledStart = Carbon::parse($attendance->date . ' ' . $shift->start_time);
                $graceMinutes = $shift->grace_minutes ?? 0;

                Log::channel('daily')->info('Shift schedule:', [
                    'scheduled_start' => $scheduledStart->format('Y-m-d H:i:s'),
                    'actual_time' => $this->log->log_time->format('Y-m-d H:i:s'),
                    'grace_minutes' => $graceMinutes,
                ]);

                if ($this->log->log_time->gt($scheduledStart)) {
                    $minutesLate = $scheduledStart->diffInMinutes($this->log->log_time);
                    Log::channel('daily')->info('Minutes late (before grace): ' . $minutesLate);
                    
                    if ($minutesLate > $graceMinutes) {
                        $lateMinutes = $minutesLate;
                        Log::channel('daily')->info('Late minutes after grace: ' . $lateMinutes);
                    } else {
                        Log::channel('daily')->info('Within grace period');
                    }
                } else {
                    Log::channel('daily')->info('Early or on time');
                }
            } else {
                Log::channel('daily')->warning('No shift found for late calculation');
            }

            Log::channel('daily')->info('Saving clock-in data...');

            // Save clock-in
            $attendance->clock_in = $this->log->log_time;
            $attendance->clock_in_address = 'Fingerprint - ' . ($this->device->label_name ?? $this->device->serial_number);
            $attendance->shift_id = $shift ? $shift->id : null;
            $attendance->scheduled_shift_start = $shift ? $shift->start_time : null;
            $attendance->scheduled_shift_end = $shift ? $shift->end_time : null;
            $attendance->late_minutes = $lateMinutes;
            $attendance->branch_id = $branchId ?? $attendance->branch_id;
            $attendance->attendance_status = $lateMinutes > 0 ? 'late' : 'present';
            $attendance->metadata = json_encode([
                'input_type' => $this->log->input_type,
                'temperature' => $this->log->temperature,
                'face_mask' => $this->log->face_mask,
                'punch_type' => $this->log->punch_type,
            ]);

            Log::channel('daily')->info('Attendance data before save:', $attendance->toArray());

            $attendance->save();
            Log::channel('daily')->info('Attendance saved with ID: ' . $attendance->id);

            Log::channel('daily')->info('Clock-in processed', [
                'user_id' => $user->id,
                'date' => $attendance->date,
                'clock_in' => $this->log->log_time->format('Y-m-d H:i:s'),
                'late_minutes' => $lateMinutes,
                'attendance_id' => $attendance->id,
            ]);

        } catch (Exception $e) {
            Log::channel('daily')->error('processClockIn failed: ' . $e->getMessage());
            Log::channel('daily')->error('Trace: ' . $e->getTraceAsString());
            throw $e;
        }

        Log::channel('daily')->info('--- processClockIn END ---');
    }

    private function processClockOut($attendance, $user, $shift): void
    {
        Log::channel('daily')->info('--- processClockOut START ---');

        try {
            if (!$attendance->clock_in) {
                Log::channel('daily')->warning('No clock-in found');
                $this->log->update(['process_error' => 'No clock-in found']);
                return;
            }

            if ($attendance->clock_out) {
                Log::channel('daily')->warning('Already clocked out', [
                    'clock_out' => $attendance->clock_out,
                ]);
                $this->log->update(['process_error' => 'Already clocked out']);
                return;
            }

            Log::channel('daily')->info('Calculating worked hours...');

            // Calculate worked hours
            $clockIn = Carbon::parse($attendance->clock_in);
            $clockOut = $this->log->log_time;
            $workedSeconds = $clockIn->diffInSeconds($clockOut);
            $workedHours = round($workedSeconds / 3600, 2);

            Log::channel('daily')->info('Worked hours calculated:', [
                'clock_in' => $clockIn->format('Y-m-d H:i:s'),
                'clock_out' => $clockOut->format('Y-m-d H:i:s'),
                'worked_seconds' => $workedSeconds,
                'worked_hours' => $workedHours,
            ]);

            // Calculate early departure minutes
            $earlyDepartureMinutes = 0;
            $overtimeMinutes = 0;
            
            if ($attendance->shift_id) {
                Log::channel('daily')->info('Calculating early departure/overtime...');
                
                $shift = Shift::find($attendance->shift_id);
                if ($shift) {
                    $scheduledEnd = Carbon::parse($attendance->date . ' ' . $shift->end_time);
                    
                    if (Carbon::parse($shift->end_time)->format('H:i') < Carbon::parse($shift->start_time)->format('H:i')) {
                        $scheduledEnd->addDay();
                        Log::channel('daily')->info('Overnight shift detected, adjusted end time');
                    }

                    $graceMinutes = $shift->grace_minutes ?? 0;

                    Log::channel('daily')->info('Schedule:', [
                        'scheduled_end' => $scheduledEnd->format('Y-m-d H:i:s'),
                        'actual_out' => $clockOut->format('Y-m-d H:i:s'),
                        'grace_minutes' => $graceMinutes,
                    ]);

                    if ($clockOut->lt($scheduledEnd)) {
                        $minutesEarly = $clockOut->diffInMinutes($scheduledEnd);
                        if ($minutesEarly > $graceMinutes) {
                            $earlyDepartureMinutes = $minutesEarly;
                            Log::channel('daily')->info('Early departure: ' . $earlyDepartureMinutes . ' minutes');
                        }
                    } elseif ($clockOut->gt($scheduledEnd)) {
                        $overtimeMinutes = $scheduledEnd->diffInMinutes($clockOut);
                        Log::channel('daily')->info('Overtime: ' . $overtimeMinutes . ' minutes');
                    }
                } else {
                    Log::channel('daily')->warning('Shift not found for ID: ' . $attendance->shift_id);
                }
            } else {
                Log::channel('daily')->warning('No shift_id on attendance');
            }

            // Calculate attendance status
            Log::channel('daily')->info('Calculating attendance status...');
            
            $attendanceStatus = $this->getAttendanceStatusByShift($workedHours, $attendance);
            Log::channel('daily')->info('Attendance status: ' . $attendanceStatus);

            Log::channel('daily')->info('Saving clock-out data...');

            // Save clock-out
            $attendance->clock_out = $this->log->log_time;
            $attendance->clock_out_address = 'Fingerprint - ' . ($this->device->label_name ?? $this->device->serial_number);
            $attendance->total_hours = gmdate('H:i:s', $workedSeconds);
            $attendance->worked_hours = $workedHours;
            $attendance->early_departure_minutes = $earlyDepartureMinutes;
            $attendance->overtime_minutes = $overtimeMinutes;
            $attendance->attendance_status = $attendanceStatus;

            $attendance->save();
            Log::channel('daily')->info('Attendance saved with ID: ' . $attendance->id);

            Log::channel('daily')->info('Clock-out processed', [
                'user_id' => $user->id,
                'date' => $attendance->date,
                'clock_out' => $this->log->log_time->format('Y-m-d H:i:s'),
                'worked_hours' => $workedHours,
                'early_departure_minutes' => $earlyDepartureMinutes,
                'attendance_status' => $attendanceStatus,
                'attendance_id' => $attendance->id,
            ]);

        } catch (Exception $e) {
            Log::channel('daily')->error('processClockOut failed: ' . $e->getMessage());
            Log::channel('daily')->error('Trace: ' . $e->getTraceAsString());
            throw $e;
        }

        Log::channel('daily')->info('--- processClockOut END ---');
    }

    private function getUserShift($userId, $date, $tenantId)
    {
        Log::channel('daily')->info('--- getUserShift START ---');
        Log::channel('daily')->info('Looking for shift for user: ' . $userId . ' on date: ' . $date);

        try {
            $userShift = UserShift::where('user_id', $userId)
                ->where('date', $date)
                ->where('tenant_id', $tenantId)
                ->with('shift')
                ->first();

            if ($userShift && $userShift->shift) {
                Log::channel('daily')->info('Shift found in UserShift:', [
                    'user_shift_id' => $userShift->id,
                    'shift_id' => $userShift->shift->id,
                    'shift_name' => $userShift->shift->name,
                ]);
                return $userShift->shift;
            }

            Log::channel('daily')->info('No shift in UserShift, checking UserJobDetail...');

            $jobDetail = UserJobDetail::where('user_id', $userId)
                ->where('tenant_id', $tenantId)
                ->first();

            if ($jobDetail && $jobDetail->shift_id) {
                Log::channel('daily')->info('Shift ID found in UserJobDetail: ' . $jobDetail->shift_id);
                
                $shift = Shift::where('id', $jobDetail->shift_id)
                    ->where('tenant_id', $tenantId)
                    ->where('status', 1)
                    ->first();

                if ($shift) {
                    Log::channel('daily')->info('Shift found in Shift table:', [
                        'shift_id' => $shift->id,
                        'shift_name' => $shift->name,
                    ]);
                    return $shift;
                } else {
                    Log::channel('daily')->warning('Shift not found in Shift table for ID: ' . $jobDetail->shift_id);
                }
            } else {
                Log::channel('daily')->info('No shift_id found in UserJobDetail');
            }

            Log::channel('daily')->info('No shift found, returning null');
            return null;

        } catch (Exception $e) {
            Log::channel('daily')->error('getUserShift error: ' . $e->getMessage());
            Log::channel('daily')->error('Trace: ' . $e->getTraceAsString());
            throw $e;
        }

        Log::channel('daily')->info('--- getUserShift END ---');
    }

    private function getUserBranch($userId, $tenantId)
    {
        Log::channel('daily')->info('--- getUserBranch START ---');
        Log::channel('daily')->info('Looking for branch for user: ' . $userId);

        try {
            $jobDetail = UserJobDetail::where('user_id', $userId)
                ->where('tenant_id', $tenantId)
                ->first();

            if ($jobDetail && $jobDetail->office_branch) {
                Log::channel('daily')->info('Branch found: ' . $jobDetail->office_branch);
                return $jobDetail->office_branch;
            }

            Log::channel('daily')->info('No branch found, returning null');
            return null;

        } catch (Exception $e) {
            Log::channel('daily')->error('getUserBranch error: ' . $e->getMessage());
            throw $e;
        }

        Log::channel('daily')->info('--- getUserBranch END ---');
    }

    private function getAttendanceStatusByShift($totalHours, $attendance)
    {
        Log::channel('daily')->info('--- getAttendanceStatusByShift START ---');
        Log::channel('daily')->info('Total Hours: ' . $totalHours);

        try {
            if ($attendance && $attendance->scheduled_shift_start && $attendance->scheduled_shift_end) {
                Log::channel('daily')->info('Using shift-based calculation');
                Log::channel('daily')->info('Scheduled shift:', [
                    'start' => $attendance->scheduled_shift_start,
                    'end' => $attendance->scheduled_shift_end,
                ]);

                $shiftStart = Carbon::parse($attendance->scheduled_shift_start);
                $shiftEnd = Carbon::parse($attendance->scheduled_shift_end);
                
                if ($shiftEnd->lessThan($shiftStart)) {
                    $shiftEnd->addDay();
                    Log::channel('daily')->info('Overnight shift detected, adjusted end time');
                }
                
                $expectedHours = $shiftStart->diffInHours($shiftEnd);
                Log::channel('daily')->info('Expected hours: ' . $expectedHours);
                
                if ($expectedHours > 0 && $totalHours !== null) {
                    $percentage = ($totalHours / $expectedHours) * 100;
                    Log::channel('daily')->info('Percentage: ' . $percentage . '%');
                    
                    if ($percentage < 20) {
                        Log::channel('daily')->info('Status: Absent (less than 20%)');
                        return 'absent';
                    }
                    if ($percentage < 60) {
                        Log::channel('daily')->info('Status: Half Day (20-60%)');
                        return 'half_day';
                    }
                    Log::channel('daily')->info('Status: Present (greater than 60%)');
                    return 'present';
                } else {
                    Log::channel('daily')->warning('Invalid expected hours or total hours');
                }
            } else {
                Log::channel('daily')->info('Using hours-based fallback calculation');
            }
            
            // Fallback
            if ($totalHours === null || $totalHours < 2) {
                Log::channel('daily')->info('Status: Absent (less than 2 hours)');
                return 'absent';
            }
            if ($totalHours < 6) {
                Log::channel('daily')->info('Status: Half Day (2-6 hours)');
                return 'half_day';
            }
            Log::channel('daily')->info('Status: Present (greater than 6 hours)');
            return 'present';

        } catch (Exception $e) {
            Log::channel('daily')->error('getAttendanceStatusByShift error: ' . $e->getMessage());
            throw $e;
        }

        Log::channel('daily')->info('--- getAttendanceStatusByShift END ---');
    }
}