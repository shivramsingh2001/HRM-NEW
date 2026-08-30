<?php

namespace App\Services\Performance;

use App\Models\User;
use App\Models\TaskAssign;
use App\Models\Attendance;
use App\Models\Leave;
use App\Models\Holiday;
use App\Models\AttendanceRegularization;
use App\Models\OvertimeRequest;
use App\Models\ManagerPerformanceReview;
use App\Models\MonthlyPayroll;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PerformanceCalculationService
{
    /**
     * Main calculation method
     */
    public function calculateForUser(User $user, string $month): array
    {
        $startDate = Carbon::parse($month . '-01')->startOfDay();
        $endDate = $startDate->copy()->endOfMonth()->endOfDay();
        
        // Calculate all metrics
        $attendance = $this->calculateAttendanceScore($user->id, $startDate, $endDate);
        $taskCompletion = $this->calculateTaskCompletionScore($user->id, $startDate, $endDate);
        $deadlineMet = $this->calculateDeadlineMetScore($user->id, $startDate, $endDate);
        $regularization = $this->calculateRegularizationScore($user->id, $startDate, $endDate);
        $managerRating = $this->getManagerRating($user->id, $month);
        $overtime = $this->calculateOvertime($user->id, $startDate, $endDate);
        $leaveDetails = $this->getLeaveDetails($user->id, $startDate, $endDate);
        $payrollCost = $this->getPayrollCost($user->id, $month);
        
        // Calculate overall score with weights
        $overallScore = $this->calculateOverallScore([
            'attendance' => $attendance['score'],
            'task_completion' => $taskCompletion['score'],
            'deadline_met' => $deadlineMet['score'],
            'regularization' => $regularization['score'],
            'manager_rating' => $managerRating['score'],
        ]);
        
        $grade = $this->calculateGrade($overallScore);
        
        // IMPORTANT: Return array with EXACT keys that command expects
        return [
            // Core scores (MUST match command expectations)
            'attendance_score' => $attendance['score'],
            'task_completion_score' => $taskCompletion['score'],
            'deadline_met_score' => $deadlineMet['score'],
            'regularization_score' => $regularization['score'],  // This will be 100 when no requests
            'manager_rating_score' => $managerRating['score'],
            
            // Overall
            'overall_score' => round($overallScore, 2),
            'grade' => $grade,
            
            // Attendance breakdown
            'present_days' => $attendance['present_days'],
            'absent_days' => $attendance['absent_days'],
            'half_days' => $attendance['half_days'],
            'late_days' => $attendance['late_count'],
            'early_departure_days' => $attendance['early_departure_days'],
            'paid_leaves' => $attendance['paid_leave_days'],
            'unpaid_leaves' => $attendance['unpaid_leave_days'],
            'holidays' => $attendance['holidays'],
            'weekoffs' => $attendance['weekoffs'],
            
            // Task metrics
            'assigned_tasks' => $taskCompletion['total_assigned'],
            'completed_tasks' => $taskCompletion['total_completed'],
            'on_time_completed_tasks' => $deadlineMet['on_time'],
            
            // Regularization metrics (MUST match command expectations)
            'regularization_count' => $regularization['total_requests'],
            'approved_regularization_count' => $regularization['approved_requests'],
            'rejected_regularization_count' => $regularization['rejected_requests'],
            'pending_regularization_count' => $regularization['pending_requests'],
            
            // Late tracking
            'late_count' => $attendance['late_count'],
            'total_late_minutes' => $attendance['total_late_minutes'],
            'late_penalty' => $attendance['late_penalty'],
            
            // Overtime
            'overtime_hours' => $overtime['total_hours'],
            'payroll_cost' => $payrollCost,
            
            // Manager rating details
            'manager_rating_raw' => $managerRating['raw_rating'],
            'manager_feedback' => $managerRating['feedback'],
            'manager_rated_by' => $managerRating['reviewer_id'],
            'manager_rated_at' => $managerRating['submitted_at'],
            
            // JSON details
            'attendance_details' => json_encode($attendance),
            'leave_details' => json_encode($leaveDetails),
            'task_details' => json_encode($taskCompletion),
        ];
    }

    /**
     * Calculate Attendance Score
     */
    public function calculateAttendanceScore($userId, $startDate, $endDate): array
    {
        // Get attendance records
        $attendances = Attendance::where('user_id', $userId)
            ->whereBetween('date', [$startDate, $endDate])
            ->get()
            ->keyBy(function ($item) {
                return $item->date instanceof Carbon ? $item->date->format('Y-m-d') : Carbon::parse($item->date)->format('Y-m-d');
            });
        
        // Get leaves
        $leaves = Leave::where('user_id', $userId)
            ->where('status', 'approved')
            ->where(function($q) use ($startDate, $endDate) {
                $q->whereBetween('start_date', [$startDate, $endDate])
                  ->orWhereBetween('end_date', [$startDate, $endDate]);
            })
            ->get();
        
        // Get holidays
        $holidays = Holiday::where('status', 1)
            ->where(function($q) use ($startDate, $endDate) {
                $q->whereBetween('start_date', [$startDate, $endDate])
                  ->orWhereBetween('end_date', [$startDate, $endDate]);
            })
            ->get();
        
        // Get user's week-offs
        $userWeekoffs = DB::table('user_weekoffs')
            ->where('user_id', $userId)
            ->where('status', 1)
            ->get();
        
        // Calculate holiday dates
        $holidayDates = [];
        $currentDate = $startDate->copy();
        while ($currentDate <= $endDate) {
            $dateStr = $currentDate->format('Y-m-d');
            foreach ($holidays as $holiday) {
                $holidayStart = Carbon::parse($holiday->start_date);
                $holidayEnd = Carbon::parse($holiday->end_date);
                if ($currentDate->between($holidayStart, $holidayEnd)) {
                    $holidayDates[] = $dateStr;
                    break;
                }
            }
            $currentDate->addDay();
        }
        
        // Calculate weekoff dates
        $weekoffDates = [];
        $currentDate = $startDate->copy();
        while ($currentDate <= $endDate) {
            $dateStr = $currentDate->format('Y-m-d');
            $dayName = $currentDate->format('l');
            
            foreach ($userWeekoffs as $weekoff) {
                if ($weekoff->off_type == 'date_based') {
                    if ($dateStr >= $weekoff->start_date && $dateStr <= $weekoff->end_date) {
                        $weekoffDates[] = $dateStr;
                        break;
                    }
                } elseif ($weekoff->off_type == 'day_based') {
                    if ($weekoff->day_name == $dayName) {
                        $weekoffDates[] = $dateStr;
                        break;
                    }
                }
            }
            $currentDate->addDay();
        }
        
        // Create leave dates map
        $leaveDates = [];
        foreach ($leaves as $leave) {
            $leaveStart = Carbon::parse($leave->start_date);
            $leaveEnd = Carbon::parse($leave->end_date);
            $current = $leaveStart->copy();
            while ($current <= $leaveEnd) {
                $leaveDates[] = $current->format('Y-m-d');
                $current->addDay();
            }
        }
        
        // Calculate metrics
        $presentDays = 0;
        $absentDays = 0;
        $halfDays = 0;
        $lateCount = 0;
        $totalLateMinutes = 0;
        $paidLeaveDays = 0;
        $unpaidLeaveDays = 0;
        $totalWorkingDays = 0;
        
        $currentDate = $startDate->copy();
        while ($currentDate <= $endDate) {
            $dateStr = $currentDate->format('Y-m-d');
            
            $isHoliday = in_array($dateStr, $holidayDates);
            $isWeekoff = in_array($dateStr, $weekoffDates);
            
            if (!$isHoliday && !$isWeekoff) {
                $totalWorkingDays++;
                
                $isOnLeave = in_array($dateStr, $leaveDates);
                
                if ($isOnLeave) {
                    foreach ($leaves as $leave) {
                        $leaveStart = Carbon::parse($leave->start_date);
                        $leaveEnd = Carbon::parse($leave->end_date);
                        if ($currentDate->between($leaveStart, $leaveEnd)) {
                            $isPaid = $this->isPaidLeave($leave->leave_type);
                            if ($isPaid) {
                                $paidLeaveDays++;
                                $presentDays++;
                            } else {
                                $unpaidLeaveDays++;
                            }
                            break;
                        }
                    }
                } 
                elseif (isset($attendances[$dateStr])) {
                    $attendance = $attendances[$dateStr];
                    
                    if ($attendance->clock_in && $attendance->clock_in != '00:00:00') {
                        $presentDays++;
                        
                        if (isset($attendance->is_late) && $attendance->is_late == 1) {
                            $lateCount++;
                            $totalLateMinutes += $attendance->late_minutes ?? 15;
                        }
                    } else {
                        $absentDays++;
                    }
                } 
                else {
                    $absentDays++;
                }
            }
            
            $currentDate->addDay();
        }
        
        // Calculate score
        $attendancePercentage = $totalWorkingDays > 0 ? ($presentDays / $totalWorkingDays) * 100 : 100;
        $absentPenalty = $absentDays * 10;
        $latePenalty = min($lateCount * 2, 20);
        $unpaidLeavePenalty = $unpaidLeaveDays * 5;
        $totalPenalty = $absentPenalty + $latePenalty + $unpaidLeavePenalty;
        $finalScore = max(0, $attendancePercentage - $totalPenalty);
        
        return [
            'score' => round($finalScore, 2),
            'working_days' => $totalWorkingDays,
            'present_days' => $presentDays,
            'absent_days' => $absentDays,
            'half_days' => $halfDays,
            'late_count' => $lateCount,
            'total_late_minutes' => $totalLateMinutes,
            'late_penalty' => $latePenalty,
            'paid_leave_days' => $paidLeaveDays,
            'unpaid_leave_days' => $unpaidLeaveDays,
            'holidays' => count($holidayDates),
            'weekoffs' => count($weekoffDates),
            'early_departure_days' => 0,
        ];
    }
    
    /**
     * Calculate Task Completion Score
     * RULE: If assigned tasks = 0, score = 0%
     */
    public function calculateTaskCompletionScore($userId, $startDate, $endDate): array
    {
        $assignedTasks = TaskAssign::where('assigned_to', $userId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get();
        
        $totalAssigned = $assignedTasks->count();
        
        $completedTasks = TaskAssign::where('assigned_to', $userId)
            ->where('individual_status', 'completed')
            ->whereBetween('completed_at', [$startDate, $endDate])
            ->get();
        
        $totalCompleted = $completedTasks->count();
        
        // RULE: If assigned tasks = 0, score = 0%
        $taskCompletionScore = 0;
        if ($totalAssigned > 0) {
            $taskCompletionScore = round(($totalCompleted / $totalAssigned) * 100, 2);
        }
        
        return [
            'score' => $taskCompletionScore,
            'total_assigned' => $totalAssigned,
            'total_completed' => $totalCompleted,
            'in_progress' => $assignedTasks->where('individual_status', 'in_progress')->count(),
            'pending' => $assignedTasks->where('individual_status', 'pending')->count(),
        ];
    }
    
    /**
     * Calculate Deadline Met Score
     * RULE: If completed tasks = 0, score = 0%
     */
    public function calculateDeadlineMetScore($userId, $startDate, $endDate): array
    {
        $completedTasks = TaskAssign::where('assigned_to', $userId)
            ->where('individual_status', 'completed')
            ->whereBetween('completed_at', [$startDate, $endDate])
            ->with('task')
            ->get();
        
        $totalCompleted = $completedTasks->count();
        $onTimeCount = 0;
        
        foreach ($completedTasks as $assign) {
            $task = $assign->task;
            if ($task && $task->deadline_date) {
                $completedAt = $assign->completed_at ?? $task->updated_at;
                $deadlineDate = Carbon::parse($task->deadline_date);
                $completedDate = Carbon::parse($completedAt);
                
                if ($completedDate <= $deadlineDate) {
                    $onTimeCount++;
                }
            } else {
                $onTimeCount++;
            }
        }
        
        // RULE: If completed tasks = 0, score = 0%
        $deadlineMetScore = 0;
        if ($totalCompleted > 0) {
            $deadlineMetScore = round(($onTimeCount / $totalCompleted) * 100, 2);
        }
        
        return [
            'score' => $deadlineMetScore,
            'total_completed' => $totalCompleted,
            'on_time' => $onTimeCount,
            'late' => $totalCompleted - $onTimeCount,
        ];
    }
    
    /**
     * Calculate Regularization Score
     * RULE: If no requests, score = 100%
     */
    public function calculateRegularizationScore($userId, $startDate, $endDate): array
    {
        $regularizations = AttendanceRegularization::where('user_id', $userId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get();
        
        $totalRequests = $regularizations->count();
        $approvedRequests = $regularizations->where('status', 'approved')->count();
        $rejectedRequests = $regularizations->where('status', 'rejected')->count();
        $pendingRequests = $regularizations->where('status', 'pending')->count();
        
        // CRITICAL: If no requests, score = 100%
        if ($totalRequests == 0) {
            return [
                'score' => 100,
                'total_requests' => 0,
                'approved_requests' => 0,
                'rejected_requests' => 0,
                'pending_requests' => 0,
            ];
        }
        
        /// Count-based penalty: more requests = lower score
        $regularizationScore = max(0, 100 - ($totalRequests * 10));
        
        return [
            'score' => round($regularizationScore, 2),
            'total_requests' => $totalRequests,
            'approved_requests' => $approvedRequests,
            'rejected_requests' => $rejectedRequests,
            'pending_requests' => $pendingRequests,
        ];
    }
    
    /**
     * Get Manager Rating
     */
    public function getManagerRating($userId, $month): array
    {
        $reviewMonth = Carbon::parse($month . '-01');
        
        $review = ManagerPerformanceReview::where('user_id', $userId)
            ->where('review_month', $reviewMonth)
            ->where('status', 'submitted')
            ->first();
        
        if (!$review) {
            return [
                'score' => null,
                'raw_rating' => null,
                'feedback' => null,
                'reviewer_id' => null,
                'submitted_at' => null,
            ];
        }
        
        $managerRatingScore = $review->overall_rating * 20;
        
        return [
            'score' => round($managerRatingScore, 2),
            'raw_rating' => $review->overall_rating,
            'feedback' => $review->additional_feedback,
            'reviewer_id' => $review->reviewer_id,
            'submitted_at' => $review->submitted_at,
        ];
    }
    
    /**
     * Calculate Overtime Hours
     */
    public function calculateOvertime($userId, $startDate, $endDate): array
    {
        try {
            $overtimeRequests = OvertimeRequest::where('user_id', $userId)
                ->where('status', 'approved')
                ->whereBetween('date', [$startDate, $endDate])
                ->get();
            
            $totalHours = $overtimeRequests->sum('overtime_hours');
            
            return [
                'total_hours' => round($totalHours, 2),
                'total_requests' => $overtimeRequests->count(),
            ];
        } catch (\Exception $e) {
            return ['total_hours' => 0, 'total_requests' => 0];
        }
    }
    
    /**
     * Get Leave Details
     */
    public function getLeaveDetails($userId, $startDate, $endDate): array
    {
        try {
            $leaves = Leave::where('user_id', $userId)
                ->where('status', 'approved')
                ->where(function($q) use ($startDate, $endDate) {
                    $q->whereBetween('start_date', [$startDate, $endDate])
                      ->orWhereBetween('end_date', [$startDate, $endDate]);
                })
                ->get();
            
            $paidLeaves = 0;
            $unpaidLeaves = 0;
            
            foreach ($leaves as $leave) {
                $isPaid = $this->isPaidLeave($leave->leave_type);
                $start = Carbon::parse($leave->start_date);
                $end = Carbon::parse($leave->end_date);
                $days = $start->diffInDays($end) + 1;
                
                if ($isPaid) {
                    $paidLeaves += $days;
                } else {
                    $unpaidLeaves += $days;
                }
            }
            
            return [
                'total_leaves' => $leaves->count(),
                'total_days' => $paidLeaves + $unpaidLeaves,
                'paid_leaves' => round($paidLeaves, 1),
                'unpaid_leaves' => round($unpaidLeaves, 1),
                'leaves' => $leaves->map(function($leave) {
                    return [
                        'id' => $leave->id,
                        'type' => $leave->leave_type,
                        'start_date' => $leave->start_date,
                        'end_date' => $leave->end_date,
                        'days' => $leave->leave_count ?? 1,
                        'reason' => $leave->reason,
                    ];
                })->toArray()
            ];
        } catch (\Exception $e) {
            return ['total_leaves' => 0, 'total_days' => 0, 'paid_leaves' => 0, 'unpaid_leaves' => 0, 'leaves' => []];
        }
    }
    
    /**
     * Get Payroll Cost
     */
    public function getPayrollCost($userId, $month): ?float
    {
        try {
            $payroll = MonthlyPayroll::where('user_id', $userId)
                ->where('payroll_month', $month)
                ->first();
            
            return $payroll ? floatval($payroll->net_payable) : null;
        } catch (\Exception $e) {
            return null;
        }
    }
    
    /**
     * Calculate Overall Score with weights
     */
protected function calculateOverallScore(array $scores): float
{
    $values = [
        $scores['attendance'] ?? 0,
        $scores['task_completion'] ?? 0,
        $scores['deadline_met'] ?? 0,
        $scores['regularization'] ?? 0,
        $scores['manager_rating'] ?? 0,
    ];

    $count = count($values);

    if ($count === 0) {
        return 0;
    }

    return round(array_sum($values) / $count, 2);
}
    
    private function isPaidLeave($leaveTypeId): bool
    {
        try {
            $leaveType = DB::table('leave_types')->where('id', $leaveTypeId)->first();
            if (!$leaveType) return true;
            return !str_contains(strtolower($leaveType->name ?? ''), 'unpaid');
        } catch (\Exception $e) {
            return true;
        }
    }
    
    private function calculateGrade(float $score): string
    {
        if ($score >= 90) return 'A+';
        if ($score >= 85) return 'A';
        if ($score >= 80) return 'A-';
        if ($score >= 75) return 'B+';
        if ($score >= 70) return 'B';
        if ($score >= 65) return 'B-';
        if ($score >= 60) return 'C+';
        if ($score >= 55) return 'C';
        if ($score >= 50) return 'C-';
        if ($score >= 45) return 'D';
        return 'F';
    }
}