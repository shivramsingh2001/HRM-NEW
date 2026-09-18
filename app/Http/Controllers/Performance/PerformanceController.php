<?php
// app/Http/Controllers/Performance/PerformanceController.php

namespace App\Http\Controllers\Performance;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\EmployeeKpiScore;
use App\Models\Department;
use App\Models\Attendance;
use App\Models\Leave;
use App\Models\TaskAssign;
use App\Models\AttendanceRegularization;
use App\Models\ManagerPerformanceReview;
use App\Services\Performance\PerformanceCalculationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Services\RbacService;
use App\Traits\AuthorizesByScope;

class PerformanceController extends Controller
{
    use AuthorizesByScope;


    /**
     * My Performance Dashboard (Employee self-view)
     */
    public function myPerformance(Request $request)
    {
        $user = auth()->user();
        $month = $request->get('month', now()->subMonth()->format('Y-m'));
        $reportingMonth = $month . '-01';

        // Calculate previous and next months for navigation
        $currentDate = Carbon::parse($month . '-01');
        $prevMonth = $currentDate->copy()->subMonth()->format('Y-m');
        $nextMonth = $currentDate->copy()->addMonth()->format('Y-m');

        // Don't allow future months beyond current month
        $currentMonth = now()->format('Y-m');
        if ($nextMonth > $currentMonth) {
            $nextMonth = null;
        }

        $prevReportingMonth = $prevMonth . '-01';

        $kpiScore = EmployeeKpiScore::where('user_id', $user->id)
            ->where('reporting_month', $reportingMonth)
            ->first();

        if (!$kpiScore) {
            $kpiScore = EmployeeKpiScore::where('user_id', $user->id)
                ->orderBy('reporting_month', 'desc')
                ->first();
        }

        // Get previous month score for comparison
        $prevKpiScore = EmployeeKpiScore::where('user_id', $user->id)
            ->where('reporting_month', $prevReportingMonth)
            ->first();

        if ($prevKpiScore) {
            $prevOverallScore = $prevKpiScore->overall_score ?? $this->calculateOverallScore($prevKpiScore);
        } else {
            $prevOverallScore = null;
        }

        // Calculate current overall score and grade
        if ($kpiScore) {
            $overallScore = $kpiScore->overall_score ?? $this->calculateOverallScore($kpiScore);
            $grade = $this->calculateGrade($overallScore);
            $kpiScore->overall_score = $overallScore;
            $kpiScore->grade = $grade;
        }

        // Get history for trend chart
        $history = EmployeeKpiScore::where('user_id', $user->id)
            ->orderBy('reporting_month', 'desc')
            ->take(6)
            ->get()
            ->map(function ($score) {
                $score->overall_score = $score->overall_score ?? $this->calculateOverallScore($score);
                $score->grade = $this->calculateGrade($score->overall_score);
                return $score;
            });

        return view('client.performance.my-dashboard', compact(
            'kpiScore',
            'history',
            'month',
            'prevMonth',
            'nextMonth',
            'prevOverallScore'
        ));
    }

    /**
     * Individual Employee Report (For HR/Managers)
     */
    public function individualReport($userId, Request $request)
    {
        $employee = User::findOrFail($userId);
        $month = $request->get('month', now()->subMonth()->format('Y-m'));
        $reportingMonth = $month . '-01';

        // Calculate previous and next months for navigation
        $currentDate = Carbon::parse($month . '-01');
        $prevMonth = $currentDate->copy()->subMonth()->format('Y-m');
        $nextMonth = $currentDate->copy()->addMonth()->format('Y-m');

        // Don't allow future months beyond current month
        $currentMonth = now()->format('Y-m');
        if ($nextMonth > $currentMonth) {
            $nextMonth = null;
        }

        $prevReportingMonth = $prevMonth . '-01';

        // Authorization
        if (!$this->scopeCoversOwner(auth()->user(), 'performance', 'view', $employee->id)) {
            abort(403, 'Unauthorized access');
        }

        // Get KPI scores
        $kpiScore = EmployeeKpiScore::where('user_id', $userId)
            ->where('reporting_month', $reportingMonth)
            ->first();

        if (!$kpiScore) {
            // Trigger calculation
            $service = app(PerformanceCalculationService::class);
            $scores = $service->calculateForUser($employee, $month);

            $kpiScore = EmployeeKpiScore::create([
                'user_id' => $employee->id,
                'tenant_id' => $employee->tenant_id,
                'reporting_month' => $reportingMonth,
                // Core metrics
                'attendance_score' => $scores['attendance_score'],
                'task_completion_score' => $scores['task_completion_score'],
                'deadline_met_score' => $scores['deadline_met_score'],
                'regularization_score' => $scores['regularization_score'],
                'regularization_count' => $scores['regularization_count'],
                'rejected_regularization_count' => $scores['rejected_regularization_count'],
                'pending_regularization_count' => $scores['pending_regularization_count'],
                // Manager rating
                'manager_rating_score' => $scores['manager_rating_score'],
                'manager_rating_raw' => $scores['manager_rating_raw'],
                'manager_feedback' => $scores['manager_feedback'],
                // Late tracking
                'late_count' => $scores['late_count'],
                'total_late_minutes' => $scores['total_late_minutes'],
                'late_penalty' => $scores['late_penalty'],
                // Overtime (Bonus)
                'overtime_hours' => $scores['overtime_hours'],
                // Details
                'attendance_details' => $scores['attendance_details'],
                'task_details' => $scores['task_details'],
                'leave_details' => $scores['leave_details'],
                // Overall
                'overall_score' => $scores['overall_score'],
                'grade' => $scores['grade'],
                'calculated_at' => now(),
            ]);
        }

        // Calculate current overall score and grade
        $overallScore = $kpiScore->overall_score ?? $this->calculateOverallScore($kpiScore);
        $grade = $kpiScore->grade ?? $this->calculateGrade($overallScore);

        // Get previous month score for comparison
        $prevKpiScore = EmployeeKpiScore::where('user_id', $userId)
            ->where('reporting_month', $prevReportingMonth)
            ->first();

        if ($prevKpiScore) {
            $prevOverallScore = $prevKpiScore->overall_score ?? $this->calculateOverallScore($prevKpiScore);
            $scoreDifference = $overallScore - $prevOverallScore;
            $scoreTrend = $scoreDifference > 0 ? 'up' : ($scoreDifference < 0 ? 'down' : 'stable');
        } else {
            $prevOverallScore = null;
            $scoreDifference = null;
            $scoreTrend = 'stable';
        }

        // Get history
        $history = EmployeeKpiScore::where('user_id', $userId)
            ->orderBy('reporting_month', 'desc')
            ->take(6)
            ->get()
            ->map(function ($score) {
                $score->overall_score = $score->overall_score ?? $this->calculateOverallScore($score);
                $score->grade = $this->calculateGrade($score->overall_score);
                return $score;
            });

        // Get department average
        $departmentId = $employee->jobDetails?->department;
        $departmentAvg = null;
        if ($departmentId) {
            $departmentScores = EmployeeKpiScore::where('reporting_month', $reportingMonth)
                ->whereHas('user.jobDetails', function ($q) use ($departmentId) {
                    $q->where('department', $departmentId);
                })
                ->whereNotNull('overall_score')
                ->get();

            if ($departmentScores->count() > 0) {
                $departmentAvg = round($departmentScores->avg('overall_score'), 2);
            }
        }

        // Get company average
        $companyScores = EmployeeKpiScore::where('reporting_month', $reportingMonth)
            ->whereNotNull('overall_score')
            ->get();

        $companyAvg = $companyScores->count() > 0 ? round($companyScores->avg('overall_score'), 2) : null;

        // Get monthly breakdown data for charts
        $monthlyBreakdown = [
            'attendance' => $kpiScore->attendance_score ?? 0,
            'task_completion' => $kpiScore->task_completion_score ?? 0,
            'deadline_met' => $kpiScore->deadline_met_score ?? 0,
            'regularization' => $kpiScore->regularization_score ?? 0,
            'manager_rating' => $kpiScore->manager_rating_score ?? 0,
        ];

        // Check if next month data exists
        $nextMonthExists = false;
        if ($nextMonth) {
            $nextMonthScore = EmployeeKpiScore::where('user_id', $userId)
                ->where('reporting_month', $nextMonth . '-01')
                ->exists();
            $nextMonthExists = $nextMonthScore;
        }

        // Get regularization details
        $regularizationDetails = null;
        if ($kpiScore->regularization_count > 0) {
            $regularizationDetails = AttendanceRegularization::where('user_id', $userId)
                ->whereBetween('created_at', [Carbon::parse($reportingMonth)->startOfMonth(), Carbon::parse($reportingMonth)->endOfMonth()])
                ->get(['id', 'date', 'request_type', 'status', 'reason']);
        }

        // Get manager review details
        $managerReview = ManagerPerformanceReview::where('user_id', $userId)
            ->where('review_month', $reportingMonth)
            ->where('status', 'submitted')
            ->first();

        return view('client.performance.individual-report', compact(
            'employee',
            'kpiScore',
            'history',
            'month',
            'prevMonth',
            'nextMonth',
            'departmentAvg',
            'companyAvg',
            'overallScore',
            'grade',
            'prevOverallScore',
            'scoreDifference',
            'scoreTrend',
            'monthlyBreakdown',
            'nextMonthExists',
            'regularizationDetails',
            'managerReview'
        ));
    }

    /**
     * Calculate overall score from individual components
     */
    // In app/Http/Controllers/Performance/PerformanceController.php

    // Update the calculateOverallScore method to follow weighted logic
    private function calculateOverallScore($kpiScore): float
    {
        $weights = [
            'attendance' => 35,
            'task_completion' => 35,
            'deadline_met' => 15,
            'regularization' => 5,
            'manager_rating' => 10,
        ];

        $totalWeight = 0;
        $weightedSum = 0;

        $scoreMappings = [
            'attendance_score' => 'attendance',
            'task_completion_score' => 'task_completion',
            'deadline_met_score' => 'deadline_met',
            'regularization_score' => 'regularization',
            'manager_rating_score' => 'manager_rating',
        ];

        foreach ($scoreMappings as $field => $key) {
            $value = $kpiScore->$field ?? null;
            if ($value !== null && $value > 0) {
                $weight = $weights[$key];
                $totalWeight += $weight;
                $weightedSum += $value * ($weight / 100);
            }
        }

        if ($totalWeight == 0) {
            return 0;
        }

        return round(($weightedSum / ($totalWeight / 100)), 2);
    }

    // Update the calculateGrade method for consistency
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

    /**
     * Team Consolidated Report
     */
    public function teamReport(Request $request)
    {
        $user = auth()->user();

        // Employees cannot access team report (requires at least team-level
        // performance:view — the "own" scope employees hold doesn't qualify)
        if (!app(RbacService::class)->can($user, 'performance', 'view', 'team')) {
            abort(403, 'You are not authorized to view team reports.');
        }

        $month = $request->get('month', now()->subMonth()->format('Y-m'));
        $departmentId = $request->get('department_id');
        $reportingMonth = $month . '-01';

        // Calculate previous and next months for navigation
        $currentDate = Carbon::parse($month . '-01');
        $prevMonth = $currentDate->copy()->subMonth()->format('Y-m');
        $nextMonth = $currentDate->copy()->addMonth()->format('Y-m');

        // Don't allow future months beyond current month
        $currentMonth = now()->format('Y-m');
        if ($nextMonth > $currentMonth) {
            $nextMonth = null;
        }

        $query = EmployeeKpiScore::with('user.jobDetails')
            ->where('reporting_month', $reportingMonth)
            ->where('tenant_id', $user->tenant_id);

        if ($departmentId) {
            $query->whereHas('user.jobDetails', function ($q) use ($departmentId) {
                $q->where('department', $departmentId);
            });
        }

        // Filter by employee if selected
        if ($request->get('employee_id')) {
            $query->where('user_id', $request->get('employee_id'));
        }

        // If manager, only show their team
        if ($user->role == 'manager') {
            $query->whereHas('user', function ($q) use ($user) {
                $q->managedBy($user->id);
            });
        }

        $scores = $query->get();

        // Prepare team data with ranks
        $teamData = [];
        foreach ($scores as $score) {
            $overallScore = $score->overall_score ?? $this->calculateOverallScore($score);
            $grade = $this->calculateGrade($overallScore);

            $teamData[] = (object)[
                'user' => $score->user,
                'attendance_score' => $score->attendance_score ?? 0,
                'task_score' => $score->task_completion_score ?? 0,
                'deadline_score' => $score->deadline_met_score ?? 0,
                'regularization_score' => $score->regularization_score ?? 0,
                'manager_rating_score' => $score->manager_rating_score ?? 0,
                'manager_rating_raw' => $score->manager_rating_raw ?? 0,
                'overall_score' => $overallScore,
                'grade' => $grade,
                'overtime' => $score->overtime_hours,
                'late_count' => $score->late_count ?? 0,
                'regularization_count' => $score->regularization_count ?? 0,
            ];
        }

        // Sort by overall score and add ranks
        usort($teamData, fn($a, $b) => $b->overall_score <=> $a->overall_score);
        foreach ($teamData as $index => $data) {
            $data->rank = $index + 1;
        }

        // Calculate totals
        $totals = [
            'avg_attendance' => round($scores->avg('attendance_score'), 2),
            'avg_task' => round($scores->avg('task_completion_score'), 2),
            'avg_deadline' => round($scores->avg('deadline_met_score'), 2),
            'avg_regularization' => round($scores->avg('regularization_score'), 2),
            'avg_manager_rating' => round($scores->avg('manager_rating_score'), 2),
            'total_overtime' => $scores->sum('overtime_hours'),
            'team_size' => $scores->count(),
        ];

        // Calculate average overall score
        $totalOverallScore = 0;
        foreach ($teamData as $member) {
            $totalOverallScore += $member->overall_score;
        }
        $totals['avg_overall'] = $scores->count() > 0 ? round($totalOverallScore / $scores->count(), 2) : 0;

        // Get departments based on role
        if (in_array($user->role, ['admin', 'hr'])) {
            $departments = Department::where('status', 1)->get();
        } else {
            // Manager - only show their department
            $managerDept = $user->jobDetails?->department;
            $departments = $managerDept ? Department::where('id', $managerDept)->get() : collect();
        }

        // Get all employees for dropdown (based on role)
        $employeeQuery = User::where('status', '1')
            ->where('role', 'employee')
            ->where('tenant_id', $user->tenant_id);

        if ($user->role == 'manager') {
            $employeeQuery->managedBy($user->id);
        }

        $allEmployees = $employeeQuery->orderBy('name')->get();

        // Get available months for filter
        $availableMonths = EmployeeKpiScore::where('tenant_id', $user->tenant_id)
            ->select('reporting_month')
            ->distinct()
            ->orderBy('reporting_month', 'desc')
            ->limit(12)
            ->get()
            ->map(function ($item) {
                return Carbon::parse($item->reporting_month)->format('Y-m');
            });

        return view('client.performance.team-report', compact(
            'teamData',
            'totals',
            'departments',
            'month',
            'prevMonth',
            'nextMonth',
            'availableMonths',
            'allEmployees'  // Add this for the dropdown
        ));
    }

    public function performanceComparison(Request $request)
    {
        $userId = $request->get('user_id');
        $month = $request->get('month', now()->subMonth()->format('Y-m'));

        $employee = User::findOrFail($userId);

        // Get current and previous 3 months data
        $months = [];
        for ($i = 0; $i <= 3; $i++) {
            $currentMonth = Carbon::parse($month . '-01')->subMonths($i)->format('Y-m');
            $months[] = $currentMonth;
        }

        $performanceData = [];
        foreach ($months as $m) {
            $score = EmployeeKpiScore::where('user_id', $userId)
                ->where('reporting_month', $m . '-01')
                ->first();

            if ($score) {
                $performanceData[] = [
                    'month' => $m,
                    'overall_score' => $score->overall_score ?? $this->calculateOverallScore($score),
                    'attendance_score' => $score->attendance_score ?? 0,
                    'task_score' => $score->task_completion_score ?? 0,
                    'deadline_score' => $score->deadline_met_score ?? 0,
                    'regularization_score' => $score->regularization_score ?? 0,
                ];
            } else {
                $performanceData[] = [
                    'month' => $m,
                    'overall_score' => null,
                    'attendance_score' => null,
                    'task_score' => null,
                    'deadline_score' => null,
                    'regularization_score' => null,
                ];
            }
        }

        return response()->json([
            'success' => true,
            'data' => $performanceData,
            'employee' => $employee->only(['id', 'name', 'employee_id'])
        ]);
    }

    /**
     * Submit manager rating for employee
     */
    public function submitManagerRating(Request $request, $userId)
    {
        $request->validate([
            'overall_rating' => 'required|numeric|min:1|max:5',
            'strengths' => 'nullable|string',
            'areas_for_improvement' => 'nullable|string',
            'achievements' => 'nullable|string',
            'goals_next_month' => 'nullable|string',
            'additional_feedback' => 'nullable|string',
        ]);

        $employee = User::findOrFail($userId);
        $month = $request->get('month', now()->subMonth()->format('Y-m'));
        $reportingMonth = $month . '-01';

        // Check authorization
        if (!app(RbacService::class)->can(auth()->user(), 'performance', 'approve')) {
            abort(403, 'Unauthorized access');
        }

        // Get or create KPI score record
        $kpiScore = EmployeeKpiScore::firstOrCreate(
            [
                'user_id' => $employee->id,
                'tenant_id' => $employee->tenant_id,
                'reporting_month' => $reportingMonth,
            ],
            ['calculated_at' => now()]
        );

        // Create manager review
        $review = ManagerPerformanceReview::updateOrCreate(
            [
                'user_id' => $employee->id,
                'review_month' => $reportingMonth,
            ],
            [
                'tenant_id' => $employee->tenant_id,
                'reviewer_id' => auth()->id(),
                'kpi_score_id' => $kpiScore->id,
                'overall_rating' => $request->overall_rating,
                'strengths' => $request->strengths,
                'areas_for_improvement' => $request->areas_for_improvement,
                'achievements' => $request->achievements,
                'goals_next_month' => $request->goals_next_month,
                'additional_feedback' => $request->additional_feedback,
                'status' => 'submitted',
                'submitted_at' => now(),
            ]
        );

        // Update KPI score with manager rating
        $kpiScore->update([
            'manager_rating_score' => $request->overall_rating * 20,
            'manager_rating_raw' => $request->overall_rating,
            'manager_feedback' => $request->additional_feedback,
            'manager_rated_by' => auth()->id(),
            'manager_rated_at' => now(),
            'overall_score' => $this->calculateOverallScore($kpiScore),
            'grade' => $this->calculateGrade($this->calculateOverallScore($kpiScore)),
        ]);

        return redirect()->back()->with('success', 'Manager rating submitted successfully!');
    }
}
