<?php
// app/Http/Controllers/Performance/ManagerPerformanceReviewController.php

namespace App\Http\Controllers\Performance;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\EmployeeKpiScore;
use App\Models\ManagerPerformanceReview;
use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Services\RbacService;
use App\Traits\AuthorizesByScope;

class ManagerPerformanceReviewController extends Controller
{
    use AuthorizesByScope;


    /**
     * Display list of employees with review modal
     */ public function index(Request $request)
    {
        $user = Auth::user();

        // This is the manager/admin/hr review-management list — an employee
        // (whose performance_reviews grant is scope=own) shouldn't reach it
        // at all. Previously unenforced: any authenticated user hitting this
        // route with no manager/admin/hr role fell through both branches
        // below and saw every employee/manager in the company unfiltered.
        if (!app(RbacService::class)->can($user, 'performance_reviews', 'view', 'team')) {
            abort(403, 'You are not authorized to view this page.');
        }

        $month = $request->get('month', now()->subMonth()->format('Y-m'));
        $reportingMonth = $month . '-01';
        $departmentId = $request->get('department_id');
        $statusFilter = $request->get('status');
        $employeeId = $request->get('employee_id');
        $perPage = $request->get('per_page', 15);

        // Build query for employees
        $query = User::where('status', '1')
            ->whereIn('role', ['employee','manager']);

        // If manager, only show team members
        if ($user->role == 'manager') {
            $query->managedBy($user->id);
        }

        // Filter by department
        if ($departmentId && in_array($user->role, ['admin', 'hr'])) {
            $query->whereHas('jobDetails', function ($q) use ($departmentId) {
                $q->where('department', $departmentId);
            });
        }

        // Filter by specific employee
        if ($employeeId) {
            $query->where('id', $employeeId);
        }

        // Get paginated employees
        $employees = $query->paginate($perPage);

        // Get all employees for dropdown (without pagination)
        $allEmployees = $query->clone()->get();

        // Get review status for each employee in current page
        $reviews = [];
        foreach ($employees as $employee) {
            $review = ManagerPerformanceReview::where('user_id', $employee->id)
                ->where('review_month', $reportingMonth)
                ->first();

            $kpiScore = EmployeeKpiScore::where('user_id', $employee->id)
                ->where('reporting_month', $reportingMonth)
                ->first();

            $status = $review ? $review->status : 'pending';

            // Apply status filter
            if ($statusFilter && $statusFilter != $status) {
                continue;
            }

            $reviews[$employee->id] = [
                'employee' => $employee,
                'review' => $review,
                'kpi_score' => $kpiScore,
                'status' => $status,
                'can_review' => !$review || $review->status == 'draft'
            ];
        }

        // Get departments for filter
        $departments = Department::where('status', 1)->get();

        // Calculate statistics from all employees (not just paginated)
        $allEmployeesList = $query->clone()->get();
        $allReviewsStatus = [];
        foreach ($allEmployeesList as $emp) {
            $review = ManagerPerformanceReview::where('user_id', $emp->id)
                ->where('review_month', $reportingMonth)
                ->first();
            $allReviewsStatus[$emp->id] = $review ? $review->status : 'pending';
        }

        $stats = [
            'total' => $allEmployeesList->count(),
            'completed' => collect($allReviewsStatus)->filter(fn($s) => $s == 'submitted')->count(),
            'pending' => collect($allReviewsStatus)->filter(fn($s) => $s == 'pending')->count(),
            'draft' => collect($allReviewsStatus)->filter(fn($s) => $s == 'draft')->count(),
            'acknowledged' => collect($allReviewsStatus)->filter(fn($s) => $s == 'acknowledged')->count(),
        ];

        return view('client.performance.review.index', compact(
            'reviews',
            'departments',
            'month',
            'stats',
            'departmentId',
            'employees',
            'allEmployees',
            'employeeId',
            'statusFilter',
            'perPage'
        ));
    }

    /**
     * Store or update performance review (via AJAX modal)
     */
    public function store(Request $request, $userId)
    {
        $request->validate([
            'overall_rating' => 'required|numeric|min:1|max:5',
            'strengths' => 'nullable|string|max:5000',
            'areas_for_improvement' => 'nullable|string|max:5000',
            'achievements' => 'nullable|string|max:5000',
            'goals_next_month' => 'nullable|string|max:5000',
            'additional_feedback' => 'nullable|string|max:5000',
            'status' => 'nullable|in:draft,submitted',
        ]);

        $employee = User::findOrFail($userId);
        $month = $request->get('month', now()->subMonth()->format('Y-m'));
        $reportingMonth = $month . '-01';

        // Authorization
        $this->authorizeReview($employee);

        // Get or create KPI score record
        $kpiScore = EmployeeKpiScore::firstOrCreate(
            [
                'user_id' => $employee->id,
                'tenant_id' => $employee->tenant_id,
                'reporting_month' => $reportingMonth,
            ],
            ['calculated_at' => now()]
        );

        DB::beginTransaction();

        try {
            // Create or update review
            $review = ManagerPerformanceReview::updateOrCreate(
                [
                    'user_id' => $employee->id,
                    'review_month' => $reportingMonth,
                ],
                [
                    'tenant_id' => $employee->tenant_id,
                    'reviewer_id' => Auth::id(),
                    'kpi_score_id' => $kpiScore->id,
                    'overall_rating' => $request->overall_rating,
                    'strengths' => $request->strengths,
                    'areas_for_improvement' => $request->areas_for_improvement,
                    'achievements' => $request->achievements,
                    'goals_next_month' => $request->goals_next_month,
                    'additional_feedback' => $request->additional_feedback,
                    'status' => $request->status ?? 'submitted',
                    'submitted_at' => ($request->status == 'submitted') ? now() : null,
                ]
            );

            // If submitted, update KPI score with manager rating
            if ($request->status == 'submitted') {
                $kpiScore->update([
                    'manager_rating_score' => $request->overall_rating * 20,
                    'manager_rating_raw' => $request->overall_rating,
                    'manager_feedback' => $request->additional_feedback,
                    'manager_rated_by' => Auth::id(),
                    'manager_rated_at' => now(),
                    'overall_score' => $this->recalculateOverallScore($kpiScore, $request->overall_rating),
                    'grade' => $this->calculateGrade($this->recalculateOverallScore($kpiScore, $request->overall_rating)),
                ]);
            }

            DB::commit();

            $message = $request->status == 'draft'
                ? 'Review saved as draft successfully!'
                : 'Performance review submitted successfully!';

            return response()->json(['success' => true, 'message' => $message]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Get review details for viewing (via AJAX)
     */
    public function show($id)
    {
        $review = ManagerPerformanceReview::with(['user', 'reviewer', 'kpiScore'])
            ->findOrFail($id);

        // Authorization
        $this->authorizeViewReview($review);

        return response()->json([
            'success' => true,
            'review' => [
                'id' => $review->id,
                'user' => [
                    'id' => $review->user->id,
                    'name' => $review->user->name,
                    'employee_id' => $review->user->employee_id,
                ],
                'reviewer' => [
                    'id' => $review->reviewer->id,
                    'name' => $review->reviewer->name,
                ],
                'overall_rating' => $review->overall_rating,
                'strengths' => $review->strengths,
                'areas_for_improvement' => $review->areas_for_improvement,
                'achievements' => $review->achievements,
                'goals_next_month' => $review->goals_next_month,
                'additional_feedback' => $review->additional_feedback,
                'status' => $review->status,
                'submitted_at' => $review->submitted_at?->format('Y-m-d H:i:s'),
                'employee_acknowledged_at' => $review->employee_acknowledged_at?->format('Y-m-d H:i:s'),
            ]
        ]);
    }

    /**
     * Update review (via AJAX modal)
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'overall_rating' => 'required|numeric|min:1|max:5',
            'strengths' => 'nullable|string|max:5000',
            'areas_for_improvement' => 'nullable|string|max:5000',
            'achievements' => 'nullable|string|max:5000',
            'goals_next_month' => 'nullable|string|max:5000',
            'additional_feedback' => 'nullable|string|max:5000',
            'status' => 'nullable|in:draft,submitted',
        ]);

        $review = ManagerPerformanceReview::findOrFail($id);

        // Authorization
        $this->authorizeReview($review->user);

        DB::beginTransaction();

        try {
            $oldStatus = $review->status;

            $review->update([
                'overall_rating' => $request->overall_rating,
                'strengths' => $request->strengths,
                'areas_for_improvement' => $request->areas_for_improvement,
                'achievements' => $request->achievements,
                'goals_next_month' => $request->goals_next_month,
                'additional_feedback' => $request->additional_feedback,
                'status' => $request->status ?? 'submitted',
                'submitted_at' => ($request->status == 'submitted' && $oldStatus != 'submitted') ? now() : $review->submitted_at,
            ]);

            // If submitted, update KPI score
            if ($request->status == 'submitted') {
                $kpiScore = EmployeeKpiScore::find($review->kpi_score_id);
                if ($kpiScore) {
                    $kpiScore->update([
                        'manager_rating_score' => $request->overall_rating * 20,
                        'manager_rating_raw' => $request->overall_rating,
                        'manager_feedback' => $request->additional_feedback,
                        'manager_rated_by' => Auth::id(),
                        'manager_rated_at' => now(),
                        'overall_score' => $this->recalculateOverallScore($kpiScore, $request->overall_rating),
                        'grade' => $this->calculateGrade($this->recalculateOverallScore($kpiScore, $request->overall_rating)),
                    ]);
                }
            }

            DB::commit();

            $message = $request->status == 'draft'
                ? 'Review saved as draft successfully!'
                : 'Performance review submitted successfully!';

            return response()->json(['success' => true, 'message' => $message]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Delete draft review
     */
    public function destroy($id)
    {
        $review = ManagerPerformanceReview::findOrFail($id);

        // Only allow deleting draft reviews
        if ($review->status != 'draft') {
            return response()->json(['success' => false, 'message' => 'Only draft reviews can be deleted.'], 400);
        }

        // Authorization
        $this->authorizeReview($review->user);

        $review->delete();

        return response()->json(['success' => true, 'message' => 'Draft review deleted successfully.']);
    }

    /**
     * Employee acknowledges the review
     */
    public function acknowledge(Request $request, $id)
    {
        $review = ManagerPerformanceReview::findOrFail($id);

        // Only employee themselves can acknowledge
        if (Auth::id() != $review->user_id) {
            return response()->json(['success' => false, 'message' => 'Only the employee can acknowledge the review.'], 403);
        }

        if ($review->status != 'submitted') {
            return response()->json(['success' => false, 'message' => 'Only submitted reviews can be acknowledged.'], 400);
        }

        $review->update([
            'status' => 'acknowledged',
            'employee_acknowledged_at' => now(),
        ]);

        return response()->json(['success' => true, 'message' => 'Review acknowledged successfully.']);
    }

    /**
     * API: Get pending reviews count for dashboard
     */
    public function pendingCount()
    {
        $user = Auth::user();
        $month = now()->subMonth()->format('Y-m');
        $reportingMonth = $month . '-01';

        if ($user->role == 'manager') {
            $teamIds = User::managedBy($user->id)->pluck('id');

            $pendingCount = ManagerPerformanceReview::whereIn('user_id', $teamIds)
                ->where('review_month', $reportingMonth)
                ->where('status', 'pending')
                ->count();
        } else {
            $pendingCount = ManagerPerformanceReview::where('review_month', $reportingMonth)
                ->where('status', 'pending')
                ->count();
        }

        return response()->json(['pending_count' => $pendingCount]);
    }

    /**
     * Export reviews to CSV
     */
    public function export(Request $request)
    {
        $user = Auth::user();
        $month = $request->get('month', now()->subMonth()->format('Y-m'));
        $reportingMonth = $month . '-01';
        $departmentId = $request->get('department_id');

        $query = ManagerPerformanceReview::with(['user', 'user.jobDetails.department', 'reviewer'])
            ->where('review_month', $reportingMonth);

        // Previously unfiltered by scope entirely — any authenticated user
        // hitting this route got every tenant's review data in the CSV.
        $query = $this->applyScope($query, 'user_id', $user, 'performance_reviews', 'view');

        if ($departmentId) {
            $query->whereHas('user.jobDetails', function ($q) use ($departmentId) {
                $q->where('department', $departmentId);
            });
        }

        $reviews = $query->get();

        // Prepare data for export
        $exportData = [];
        foreach ($reviews as $review) {
            $exportData[] = [
                'Employee Name' => $review->user->name,
                'Employee ID' => $review->user->employee_id,
                'Department' => $review->user->jobDetails?->department?->name ?? 'N/A',
                'Review Month' => $review->review_month->format('F Y'),
                'Overall Rating' => $review->overall_rating . '/5',
                'Rating Percentage' => ($review->overall_rating * 20) . '%',
                'Strengths' => $review->strengths,
                'Areas for Improvement' => $review->areas_for_improvement,
                'Achievements' => $review->achievements,
                'Goals Next Month' => $review->goals_next_month,
                'Additional Feedback' => $review->additional_feedback,
                'Reviewer' => $review->reviewer->name,
                'Status' => ucfirst($review->status),
                'Submitted At' => $review->submitted_at?->format('Y-m-d H:i:s'),
                'Acknowledged At' => $review->employee_acknowledged_at?->format('Y-m-d H:i:s'),
            ];
        }

        // Generate CSV
        $filename = "performance_reviews_{$month}.csv";
        $handle = fopen('php://temp', 'w+');

        // Add UTF-8 BOM for Excel compatibility
        fwrite($handle, "\xEF\xBB\xBF");

        // Add headers
        fputcsv($handle, array_keys($exportData[0] ?? []));

        // Add data
        foreach ($exportData as $row) {
            fputcsv($handle, $row);
        }

        rewind($handle);
        $csvContent = stream_get_contents($handle);
        fclose($handle);

        return response($csvContent, 200)
            ->header('Content-Type', 'text/csv; charset=utf-8')
            ->header('Content-Disposition', "attachment; filename={$filename}");
    }

    // ==================== HELPER METHODS ====================

    private function authorizeReview($employee)
    {
        $user = Auth::user();
        $action = request()->routeIs('*.destroy') ? 'delete' : (request()->routeIs('*.update') ? 'edit' : 'create');

        if ($this->scopeCoversOwner($user, 'performance_reviews', $action, $employee->id)) {
            return true;
        }

        abort(403, 'You are not authorized to review this employee.');
    }

    private function authorizeViewReview($review)
    {
        $user = Auth::user();

        if ($this->scopeCoversOwner($user, 'performance_reviews', 'view', $review->user_id)) {
            return true;
        }

        abort(403, 'You are not authorized to view this review.');
    }

    private function getPerformanceSummary($employee, $month)
    {
        $kpiScore = EmployeeKpiScore::where('user_id', $employee->id)
            ->where('reporting_month', $month . '-01')
            ->first();

        if (!$kpiScore) {
            return null;
        }

        return [
            'attendance_score' => $kpiScore->attendance_score ?? 0,
            'task_completion_score' => $kpiScore->task_completion_score ?? 0,
            'deadline_met_score' => $kpiScore->deadline_met_score ?? 0,
            'regularization_score' => $kpiScore->regularization_score ?? 100,
            'regularization_count' => $kpiScore->regularization_count ?? 0,
            'late_count' => $kpiScore->late_count ?? 0,
            'overtime_hours' => $kpiScore->overtime_hours ?? 0,
            'overall_score' => $kpiScore->overall_score ?? 0,
            'grade' => $kpiScore->grade ?? 'N/A',
        ];
    }

    private function recalculateOverallScore($kpiScore, $managerRating)
    {
        $scores = [];

        if ($kpiScore->attendance_score !== null) $scores[] = $kpiScore->attendance_score;
        if ($kpiScore->task_completion_score !== null) $scores[] = $kpiScore->task_completion_score;
        if ($kpiScore->deadline_met_score !== null) $scores[] = $kpiScore->deadline_met_score;
        if ($kpiScore->regularization_score !== null) $scores[] = $kpiScore->regularization_score;

        // Add manager rating (convert 1-5 to 0-100)
        $scores[] = $managerRating * 20;

        return !empty($scores) ? round(array_sum($scores) / count($scores), 2) : 0;
    }

    private function calculateGrade(float $score): string
    {
        if ($score >= 90) return 'A+';
        if ($score >= 80) return 'A';
        if ($score >= 70) return 'B+';
        if ($score >= 60) return 'B';
        if ($score >= 50) return 'C';
        if ($score >= 40) return 'D';
        return 'F';
    }
}
