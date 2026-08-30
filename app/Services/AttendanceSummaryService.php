<?php

namespace App\Services;

use App\Models\AttendanceSummary;
use App\Models\Attendance;
use App\Models\Leave;
use App\Models\Holiday;
use App\Models\UserWeekoffs;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AttendanceSummaryService
{
    // Attendance status thresholds (in hours)
    const ABSENT_THRESHOLD = 2;      // Less than 2 hours = Absent
    const HALF_DAY_THRESHOLD = 5;    // 2-5 hours = Half Day
    const FULL_DAY_THRESHOLD = 9;    // 5-9 hours = Present (Full Day)
    const OVERTIME_THRESHOLD = 9;    // Above 9 hours = Overtime

    /**
     * Update or create attendance summary for a user for a specific month
     */
    public function updateMonthlySummary($userId, $yearMonth = null)
    {
        if (!$yearMonth) {
            $yearMonth = Carbon::now()->format('Y-m');
        }

        try {
            $startDate = Carbon::parse($yearMonth . '-01')->startOfDay();
            $endDate = Carbon::parse($yearMonth . '-01')->endOfMonth()->endOfDay();

            $totalDaysInMonth = $startDate->daysInMonth;
            $user = \App\Models\User::find($userId);
            if (!$user) {
                return false;
            }

            // Get all attendance records for the month (one row per user/day)
            $attendances = Attendance::where('user_id', $userId)
                ->where('date', '>=', $startDate)
                ->where('date', '<=', $endDate)
                ->get()
                ->groupBy('date');

            // Get leaves for the month
            $leaves = Leave::where('user_id', $userId)
                ->where('status', 'approved')
                ->where(function ($query) use ($startDate, $endDate) {
                    $query->whereBetween('start_date', [$startDate, $endDate])
                        ->orWhereBetween('end_date', [$startDate, $endDate]);
                })
                ->get();

            // Get holidays for the month
            $holidays = Holiday::whereBetween('start_date', [$startDate, $endDate])
                ->get()
                ->pluck('start_date')
                ->map(function ($date) {
                    return Carbon::parse($date)->format('Y-m-d');
                })
                ->toArray();

            // Initialize counters
            $summary = [
                'total_days' => $totalDaysInMonth,
                'present_days' => 0,
                'absent_days' => 0,
                'half_days' => 0,
                'late_days' => 0,
                'early_departure_days' => 0,
                'paid_leaves' => 0,
                'unpaid_leaves' => 0,
                'total_leaves' => 0,
                'holidays' => 0,
                'week_offs' => 0,
                'holiday_work_days' => 0,
                'weekoff_work_days' => 0,
                'total_worked_hours' => 0,
                'total_overtime_hours' => 0,
                'total_late_minutes' => 0,
                'avg_working_hours' => 0,
                'continuous_present_days' => 0,
                'continuous_absent_days' => 0,
                'last_attendance_date' => null,
                'metadata' => []
            ];

            $currentContinuousPresent = 0;
            $currentContinuousAbsent = 0;
            $maxContinuousPresent = 0;
            $maxContinuousAbsent = 0;
            $lastAttendanceDate = null;

            // Store daily details for metadata
            $dailyDetails = [];

            // Iterate through each day of the month
            for ($date = $startDate->copy(); $date <= $endDate; $date->addDay()) {
                $dateString = $date->format('Y-m-d');
                $dayData = $this->getDayStatusWithHours($userId, $dateString, $attendances, $leaves, $holidays);

                $dayStatus = $dayData['status'];
                $workedHours = $dayData['worked_hours'] ?? 0;
                $lateMinutes = $dayData['late_minutes'] ?? 0;
                $earlyDepartureMinutes = $dayData['early_departure_minutes'] ?? 0;

                // Store daily details
                $dailyDetails[$dateString] = [
                    'status' => $dayStatus,
                    'worked_hours' => $workedHours,
                    'late_minutes' => $lateMinutes,
                    'early_departure_minutes' => $earlyDepartureMinutes
                ];

                // Update counts based on status
                switch ($dayStatus) {
                    case 'present':
                        $summary['present_days']++;
                        $currentContinuousPresent++;
                        $currentContinuousAbsent = 0;
                        $lastAttendanceDate = $dateString;

                        // Add worked hours
                        $summary['total_worked_hours'] += $workedHours;
                        $summary['total_late_minutes'] += $lateMinutes;

                        if ($lateMinutes > 0) {
                            $summary['late_days']++;
                        }
                        if ($earlyDepartureMinutes > 0) {
                            $summary['early_departure_days']++;
                        }

                        // Check for overtime (more than 9 hours)
                        if ($workedHours > self::OVERTIME_THRESHOLD) {
                            $summary['total_overtime_hours'] += ($workedHours - self::OVERTIME_THRESHOLD);
                        }
                        break;

                    case 'half_day':
                        $summary['half_days']++;
                        $currentContinuousPresent++;
                        $currentContinuousAbsent = 0;
                        $lastAttendanceDate = $dateString;

                        $summary['total_worked_hours'] += $workedHours;
                        $summary['total_late_minutes'] += $lateMinutes;

                        if ($lateMinutes > 0) {
                            $summary['late_days']++;
                        }
                        break;

                    case 'absent':
                        $summary['absent_days']++;
                        $currentContinuousAbsent++;
                        $currentContinuousPresent = 0;
                        break;

                    case 'paid_leave':
                        $summary['paid_leaves']++;
                        $summary['total_leaves']++;
                        $currentContinuousAbsent++;
                        $currentContinuousPresent = 0;
                        break;

                    case 'unpaid_leave':
                        $summary['unpaid_leaves']++;
                        $summary['total_leaves']++;
                        $currentContinuousAbsent++;
                        $currentContinuousPresent = 0;
                        break;

                    case 'holiday':
                        $summary['holidays']++;
                        // Check if worked on holiday
                        if ($workedHours > 0) {
                            $summary['holiday_work_days']++;
                            // Determine if it's full day or half day work on holiday
                            if ($workedHours >= self::HALF_DAY_THRESHOLD) {
                                $summary['present_days']++;
                            } else if ($workedHours >= self::ABSENT_THRESHOLD) {
                                $summary['half_days']++;
                            }
                            $summary['total_worked_hours'] += $workedHours;
                        }
                        break;

                    case 'week_off':
                        $summary['week_offs']++;
                        // Check if worked on weekoff
                        if ($workedHours > 0) {
                            $summary['weekoff_work_days']++;
                            // Determine if it's full day or half day work on weekoff
                            if ($workedHours >= self::HALF_DAY_THRESHOLD) {
                                $summary['present_days']++;
                            } else if ($workedHours >= self::ABSENT_THRESHOLD) {
                                $summary['half_days']++;
                            }
                            $summary['total_worked_hours'] += $workedHours;
                        }
                        break;
                }

                // Track continuous streaks
                $maxContinuousPresent = max($maxContinuousPresent, $currentContinuousPresent);
                $maxContinuousAbsent = max($maxContinuousAbsent, $currentContinuousAbsent);
            }

            $summary['continuous_present_days'] = $maxContinuousPresent;
            $summary['continuous_absent_days'] = $maxContinuousAbsent;
            $summary['last_attendance_date'] = $lastAttendanceDate;

            // Calculate average working hours
            $workingDaysCount = $summary['present_days'] + $summary['half_days'] + $summary['holiday_work_days'] + $summary['weekoff_work_days'];
            if ($workingDaysCount > 0) {
                $summary['avg_working_hours'] = round($summary['total_worked_hours'] / $workingDaysCount, 2);
            }

            // Add metadata
            $summary['metadata'] = json_encode([
                'calculated_at' => Carbon::now()->toDateTimeString(),
                'date_range' => [
                    'start' => $startDate->format('Y-m-d'),
                    'end' => $endDate->format('Y-m-d')
                ],
                'daily_breakdown' => $dailyDetails,
                'thresholds' => [
                    'absent' => self::ABSENT_THRESHOLD,
                    'half_day' => self::HALF_DAY_THRESHOLD,
                    'full_day' => self::FULL_DAY_THRESHOLD,
                    'overtime' => self::OVERTIME_THRESHOLD
                ]
            ]);

            // Update or create summary record
            return AttendanceSummary::updateOrCreate(
                [
                    'user_id' => $userId,
                    'year_month' => $yearMonth,
                    'tenant_id' => $user->tenant_id
                ],
                $summary
            );
        } catch (\Exception $e) {
            Log::error('Failed to update attendance summary', [
                'user_id' => $userId,
                'year_month' => $yearMonth,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get status for a specific day with worked hours
     */
    private function getDayStatusWithHours($userId, $date, $attendances, $leaves, $holidays)
    {
        // Check if holiday
        if (in_array($date, $holidays)) {
            $workedHours = 0;
            $lateMinutes = 0;
            $earlyDepartureMinutes = 0;

            // Check if worked on holiday
            if (isset($attendances[$date])) {
                $hoursData = $this->calculateDailyHours($attendances[$date]);
                $workedHours = $hoursData['total_hours'];
                $lateMinutes = $hoursData['late_minutes'];
                $earlyDepartureMinutes = $hoursData['early_departure_minutes'];
            }

            return [
                'status' => 'holiday',
                'worked_hours' => $workedHours,
                'late_minutes' => $lateMinutes,
                'early_departure_minutes' => $earlyDepartureMinutes
            ];
        }

        // Check if week off
        if ($this->isWeekOff($userId, $date)) {
            $workedHours = 0;
            $lateMinutes = 0;
            $earlyDepartureMinutes = 0;

            // Check if worked on weekoff
            if (isset($attendances[$date])) {
                $hoursData = $this->calculateDailyHours($attendances[$date]);
                $workedHours = $hoursData['total_hours'];
                $lateMinutes = $hoursData['late_minutes'];
                $earlyDepartureMinutes = $hoursData['early_departure_minutes'];
            }

            return [
                'status' => 'week_off',
                'worked_hours' => $workedHours,
                'late_minutes' => $lateMinutes,
                'early_departure_minutes' => $earlyDepartureMinutes
            ];
        }

        // In getDayStatusWithHours method, update the attendance section:
        if (isset($attendances[$date])) {
            $hoursData = $this->calculateDailyHours($attendances[$date]);
            $workedHours = $hoursData['total_hours'];

            // 🔧 FIX: Ensure worked hours is not negative
            $workedHours = max(0, $workedHours);

            // Determine status based on worked hours
            if ($workedHours >= self::FULL_DAY_THRESHOLD) {
                $status = 'present';
            } elseif ($workedHours >= self::HALF_DAY_THRESHOLD) {
                $status = 'half_day';
            } elseif ($workedHours >= self::ABSENT_THRESHOLD) {
                $status = 'half_day';
            } else {
                $status = 'absent';
            }

            return [
                'status' => $status,
                'worked_hours' => $workedHours,
                'late_minutes' => $hoursData['late_minutes'],
                'early_departure_minutes' => $hoursData['early_departure_minutes']
            ];
        }

        // Check leaves
        foreach ($leaves as $leave) {
            $leaveStart = Carbon::parse($leave->start_date);
            $leaveEnd = Carbon::parse($leave->end_date);
            $currentDate = Carbon::parse($date);

            if ($currentDate->between($leaveStart, $leaveEnd)) {
                $leaveType = $leave->leave_type == 'paid' ? 'paid_leave' : 'unpaid_leave';
                return [
                    'status' => $leaveType,
                    'worked_hours' => 0,
                    'late_minutes' => 0,
                    'early_departure_minutes' => 0
                ];
            }
        }

        return [
            'status' => 'absent',
            'worked_hours' => 0,
            'late_minutes' => 0,
            'early_departure_minutes' => 0
        ];
    }

    /**
     * Calculate daily working hours from attendance entries
     */
    /**
     * Calculate daily working hours from attendance entries
     */
    private function calculateDailyHours($attendanceEntries)
    {
        $totalSeconds = 0;
        $totalLateMinutes = 0;
        $totalOvertimeMinutes = 0;
        $totalEarlyDepartureMinutes = 0;

        foreach ($attendanceEntries as $entry) {
            if ($entry->clock_in && $entry->clock_out) {
                $clockIn = Carbon::parse($entry->clock_in);
                $clockOut = Carbon::parse($entry->clock_out);

                // 🔧 FIX: Ensure we only add positive durations
                if ($clockOut->gt($clockIn)) {
                    $sessionSeconds = $clockOut->timestamp - $clockIn->timestamp;
                    if ($sessionSeconds > 0) {
                        $totalSeconds += $sessionSeconds;
                    } else {
                        Log::warning('Invalid session duration', [
                            'entry_id' => $entry->id,
                            'clock_in' => $entry->clock_in,
                            'clock_out' => $entry->clock_out,
                            'seconds' => $sessionSeconds
                        ]);
                    }
                }
            }

            // 🔧 FIX: Use absolute values for metrics
            $totalLateMinutes += abs($entry->late_minutes ?? 0);
            $totalOvertimeMinutes += abs($entry->overtime_minutes ?? 0);
            $totalEarlyDepartureMinutes += abs($entry->early_departure_minutes ?? 0);
        }

        // 🔧 FIX: Ensure hours are not negative
        $totalHours = max(0, round($totalSeconds / 3600, 2));

        return [
            'total_hours' => $totalHours,
            'total_seconds' => $totalSeconds,
            'late_minutes' => $totalLateMinutes,
            'overtime_hours' => round($totalOvertimeMinutes / 60, 2),
            'early_departure_minutes' => $totalEarlyDepartureMinutes
        ];
    }

    /**
     * Check if a date is week off for user
     */
    private function isWeekOff($userId, $date)
    {
        $dateObj = Carbon::parse($date);
        $dayName = $dateObj->format('l');

        return UserWeekoffs::where('user_id', $userId)
            ->where('status', 1)
            ->where(function ($query) use ($date, $dayName) {
                $query->where(function ($q) use ($date) {
                    $q->where('off_type', 'date_based')
                        ->where('start_date', '<=', $date)
                        ->where('end_date', '>=', $date);
                })->orWhere(function ($q) use ($dayName) {
                    $q->where('off_type', 'day_based')
                        ->where('day_name', $dayName);
                });
            })
            ->exists();
    }
}
