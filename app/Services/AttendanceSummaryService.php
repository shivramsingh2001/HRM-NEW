<?php

namespace App\Services;

use App\Models\AttendanceSummary;
use App\Models\Attendance;
use App\Models\Leave;
use App\Models\LeaveTransaction;
use App\Models\Holiday;
use App\Models\UserWeekoffs;
use App\Services\Attendance\AttendanceCalculator;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AttendanceSummaryService
{
    /** Hours worked past this on a normal day count as overtime (no-shift fallback). */
    const OVERTIME_THRESHOLD = 9;

    /** ['Y-m-d' => true] week-off dates for the user/month being processed. */
    private array $weekoffDays = [];

    /**
     * Update or create attendance summary for a user for a specific month.
     *
     * Always scoped to the user's own tenant with global scopes removed, so it
     * behaves identically whether called from an HTTP request or a CLI command.
     */
    public function updateMonthlySummary($userId, $yearMonth = null, $tenantId = null)
    {
        if (!$yearMonth) {
            $yearMonth = Carbon::now()->format('Y-m');
        }

        try {
            $startDate = Carbon::parse($yearMonth . '-01')->startOfDay();
            $endDate = Carbon::parse($yearMonth . '-01')->endOfMonth()->endOfDay();

            $totalDaysInMonth = $startDate->daysInMonth;

            $user = \App\Models\User::withoutGlobalScopes()->find($userId);
            if (!$user) {
                return false;
            }
            $tenantId = $tenantId ?: $user->tenant_id;

            $startStr = $startDate->format('Y-m-d');
            $endStr = $endDate->format('Y-m-d');

            // One row per user/day for the month (grouped defensively).
            $attendances = Attendance::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('user_id', $userId)
                ->whereBetween('date', [$startStr, $endStr])
                ->get()
                ->groupBy(fn ($a) => Carbon::parse($a->date)->format('Y-m-d'));

            // Approved leaves that OVERLAP the month (not just those starting in it).
            $leaves = Leave::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('user_id', $userId)
                ->where('status', 'approved')
                ->whereDate('start_date', '<=', $endStr)
                ->whereDate('end_date', '>=', $startStr)
                ->get();

            // paid / unpaid per leave, from the 'sub' leave transaction.
            $leaveDetail = LeaveTransaction::withoutGlobalScopes()
                ->whereIn('leave_id', $leaves->pluck('id'))
                ->where('transaction_type', 'sub')
                ->pluck('leave_detail', 'leave_id');

            // Holidays overlapping the month, expanded to every covered date.
            $holidays = [];
            Holiday::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('status', 1)
                ->whereDate('start_date', '<=', $endStr)
                ->whereDate('end_date', '>=', $startStr)
                ->get()
                ->each(function ($h) use (&$holidays, $startDate, $endDate) {
                    $from = Carbon::parse($h->start_date)->max($startDate);
                    $to = Carbon::parse($h->end_date ?: $h->start_date)->min($endDate);
                    for ($d = $from->copy(); $d->lte($to); $d->addDay()) {
                        $holidays[$d->format('Y-m-d')] = true;
                    }
                });

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

            $this->loadWeekoffDays($userId, $tenantId, $startStr, $endStr);

            $today = Carbon::today();

            // Iterate through each day of the month (never past today — future days
            // are neither present nor absent yet).
            for ($date = $startDate->copy(); $date <= $endDate; $date->addDay()) {
                if ($date->gt($today)) {
                    break;
                }
                $dateString = $date->format('Y-m-d');
                $dayData = $this->getDayStatusWithHours($userId, $dateString, $attendances, $leaves, $holidays, $leaveDetail, $tenantId);

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
                        if ($workedHours > 0) {
                            $summary['holiday_work_days']++;
                            $worked = $this->classifyByHours($workedHours, $dayData['expected_seconds'] ?? 0);
                            if ($worked === 'present') {
                                $summary['present_days']++;
                            } elseif ($worked === 'half_day') {
                                $summary['half_days']++;
                            }
                            $summary['total_worked_hours'] += $workedHours;
                        }
                        break;

                    case 'week_off':
                        $summary['week_offs']++;
                        if ($workedHours > 0) {
                            $summary['weekoff_work_days']++;
                            $worked = $this->classifyByHours($workedHours, $dayData['expected_seconds'] ?? 0);
                            if ($worked === 'present') {
                                $summary['present_days']++;
                            } elseif ($worked === 'half_day') {
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
                    'present_ratio' => config('attendance.ratio.present'),
                    'half_ratio' => config('attendance.ratio.half'),
                    'fallback_present_hours' => config('attendance.fallback_hours.present'),
                    'fallback_half_hours' => config('attendance.fallback_hours.half'),
                    'overtime' => self::OVERTIME_THRESHOLD,
                ]
            ]);

            // Update or create summary record
            return AttendanceSummary::withoutGlobalScopes()->updateOrCreate(
                [
                    'user_id' => $userId,
                    'year_month' => $yearMonth,
                    'tenant_id' => $tenantId
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
     * Status for a single day, with worked hours.
     *
     * Precedence: holiday -> week off -> completed attendance -> approved leave
     * -> (incomplete/empty attendance or nothing) absent.
     */
    private function getDayStatusWithHours($userId, $date, $attendances, $leaves, $holidays, $leaveDetail, $tenantId)
    {
        $hoursData = isset($attendances[$date])
            ? $this->calculateDailyHours($attendances[$date])
            : null;

        $worked = $hoursData['total_hours'] ?? 0;
        $lateMin = $hoursData['late_minutes'] ?? 0;
        $earlyMin = $hoursData['early_departure_minutes'] ?? 0;
        $expectedSeconds = $hoursData['expected_seconds'] ?? 0;
        $effectiveStatus = $hoursData['effective_status'] ?? null;
        $hasCompletedWork = ($hoursData['completed'] ?? false) && $worked > 0;

        if (isset($holidays[$date])) {
            return $this->result('holiday', $worked, $lateMin, $earlyMin, $expectedSeconds);
        }

        if (isset($this->weekoffDays[$date])) {
            return $this->result('week_off', $worked, $lateMin, $earlyMin, $expectedSeconds);
        }

        if ($hasCompletedWork) {
            // LatePolicyService's resolved status wins over the raw hours ladder
            // for the present / half_day / late distinction (Feature B).
            $status = match ($effectiveStatus) {
                'half_day' => 'half_day',
                'present', 'late', 'overtime', 'early_departure' => 'present',
                'absent' => 'absent',
                default => $this->classifyByHours($worked, $expectedSeconds),
            };

            return $this->result($status, $worked, $lateMin, $earlyMin, $expectedSeconds);
        }

        // Approved leave covering this date (checked BEFORE treating an empty
        // attendance row as absent).
        $current = Carbon::parse($date);
        foreach ($leaves as $leave) {
            if ($current->betweenIncluded(Carbon::parse($leave->start_date), Carbon::parse($leave->end_date))) {
                $detail = $leaveDetail[$leave->id] ?? 'paid';
                return $this->result(
                    $detail === 'unpaid' ? 'unpaid_leave' : 'paid_leave',
                    0,
                    0,
                    0,
                    0
                );
            }
        }

        return $this->result('absent', 0, 0, 0, 0);
    }

    private function result(string $status, $worked, $late, $early, $expectedSeconds): array
    {
        return [
            'status' => $status,
            'worked_hours' => $worked,
            'late_minutes' => $late,
            'early_departure_minutes' => $early,
            'expected_seconds' => $expectedSeconds,
        ];
    }

    /**
     * Classify a worked-hours value against the day's scheduled expectation.
     * Falls back to an absolute-hours ladder when no shift expectation is known.
     */
    private function classifyByHours(float $workedHours, int $expectedSeconds): string
    {
        if ($expectedSeconds > 0) {
            $ratio = ($workedHours * 3600) / $expectedSeconds;
            if ($ratio >= (float) config('attendance.ratio.present')) {
                return 'present';
            }
            if ($ratio >= (float) config('attendance.ratio.half')) {
                return 'half_day';
            }
            return 'absent';
        }

        if ($workedHours >= (float) config('attendance.fallback_hours.present')) {
            return 'present';
        }
        if ($workedHours >= (float) config('attendance.fallback_hours.half')) {
            return 'half_day';
        }
        return 'absent';
    }

    /**
     * Aggregate worked hours + stored metrics for a day's attendance row(s).
     */
    private function calculateDailyHours($attendanceEntries)
    {
        $calc = new AttendanceCalculator();

        $totalSeconds = 0;
        $totalLateMinutes = 0;
        $totalOvertimeMinutes = 0;
        $totalEarlyDepartureMinutes = 0;
        $expectedSeconds = 0;
        $completed = false;
        $effectiveStatus = null;

        // A single session cannot sanely exceed 24h — clamp obvious bad data
        // (missed clock-out closed days later) so one row can't wreck the month.
        $maxSessionSeconds = 24 * 3600;

        foreach ($attendanceEntries as $entry) {
            if ($entry->clock_in && $entry->clock_out) {
                $clockIn = Carbon::parse($entry->clock_in);
                $clockOut = Carbon::parse($entry->clock_out);
                $sessionSeconds = $calc->workedSeconds($clockIn, $clockOut);
                if ($sessionSeconds > $maxSessionSeconds) {
                    Log::warning('Attendance session exceeds 24h, clamping', [
                        'entry_id' => $entry->id ?? null,
                        'clock_in' => $entry->clock_in,
                        'clock_out' => $entry->clock_out,
                    ]);
                    $sessionSeconds = $maxSessionSeconds;
                }
                if ($sessionSeconds > 0) {
                    $totalSeconds += $sessionSeconds;
                    $completed = true;
                }
            }

            $totalLateMinutes += abs($entry->late_minutes ?? 0);
            $totalOvertimeMinutes += abs($entry->overtime_minutes ?? 0);
            $totalEarlyDepartureMinutes += abs($entry->early_departure_minutes ?? 0);

            if ($entry->scheduled_shift_start && $entry->scheduled_shift_end) {
                $expectedSeconds = max($expectedSeconds, $calc->expectedWorkSeconds([
                    'start_time' => $entry->scheduled_shift_start,
                    'end_time' => $entry->scheduled_shift_end,
                ]));
            }

            // effective_status is set by LatePolicyService; the last non-null wins.
            if (!empty($entry->effective_status)) {
                $effectiveStatus = $entry->effective_status;
            }
        }

        return [
            'total_hours' => $calc->decimalHours($totalSeconds),
            'total_seconds' => $totalSeconds,
            'late_minutes' => $totalLateMinutes,
            'overtime_hours' => round($totalOvertimeMinutes / 60, 2),
            'early_departure_minutes' => $totalEarlyDepartureMinutes,
            'expected_seconds' => $expectedSeconds,
            'completed' => $completed,
            'effective_status' => $effectiveStatus,
        ];
    }

    /**
     * Pre-load every week-off date for the user in [$startStr, $endStr] into
     * $this->weekoffDays keyed by 'Y-m-d' (avoids a query per day).
     */
    private function loadWeekoffDays($userId, $tenantId, string $startStr, string $endStr): void
    {
        $this->weekoffDays = [];

        $rows = UserWeekoffs::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->where('status', 1)
            ->get();

        $start = Carbon::parse($startStr);
        $end = Carbon::parse($endStr);

        foreach ($rows as $wo) {
            for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
                if ($this->weekoffMatches($wo, $d)) {
                    $this->weekoffDays[$d->format('Y-m-d')] = true;
                }
            }
        }
    }

    private function weekoffMatches($wo, Carbon $d): bool
    {
        if ($wo->off_type === 'date_based') {
            return $wo->start_date && $wo->end_date
                && $d->betweenIncluded(Carbon::parse($wo->start_date), Carbon::parse($wo->end_date));
        }

        if ($wo->off_type === 'day_based') {
            if ($wo->day_name !== $d->format('l')) {
                return false;
            }
            if ($wo->start_date && $d->lt(Carbon::parse($wo->start_date))) {
                return false;
            }
            if ($wo->end_date && $d->gt(Carbon::parse($wo->end_date))) {
                return false;
            }
            return true;
        }

        return false;
    }
}
