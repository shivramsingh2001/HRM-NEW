<?php

namespace App\Services\Dashboard\Cards;

use App\Models\AttendanceRegularization;
use Illuminate\Support\Facades\DB;

/**
 * Dashboard — Employees with the most regularization requests.
 * Moved out of DashboardController unchanged (code-quality plan, Phase 2).
 */
class RegularizationCard
{
    /** @param array|null $between only requests raised in that period (null = all time) */
    public function mostRequests(?array $between = null)
    {
        return AttendanceRegularization::join('users', 'attendance_regularizations.user_id', '=', 'users.id')
            ->when($between, fn ($q) => $q->whereBetween('attendance_regularizations.created_at', $between))
            ->leftJoin('user_basic_details', 'users.id', '=', 'user_basic_details.user_id')
            ->leftJoin('user_job_details', 'users.id', '=', 'user_job_details.user_id')
            ->leftJoin('designations', 'user_job_details.designation', '=', 'designations.id')
            ->leftJoin('departments', 'user_job_details.department', '=', 'departments.id')
            ->select(
                'users.id',
                'users.name',
                'users.email',
                'users.employee_id',
                'user_basic_details.profile_image',
                'designations.name as designation_name',
                'departments.name as department_name',
                DB::raw('COUNT(attendance_regularizations.id) as total_requests'),
                DB::raw('SUM(CASE WHEN attendance_regularizations.status = "approved" THEN 1 ELSE 0 END) as approved_requests'),
                DB::raw('SUM(CASE WHEN attendance_regularizations.status = "pending" THEN 1 ELSE 0 END) as pending_requests'),
                DB::raw('SUM(CASE WHEN attendance_regularizations.status = "rejected" THEN 1 ELSE 0 END) as rejected_requests')
            )
            ->where('users.status', 1)
            ->groupBy(
                'users.id',
                'users.name',
                'users.email',
                'users.employee_id',
                'user_basic_details.profile_image',
                'designations.name',
                'departments.name'
            )
            ->orderBy('total_requests', 'desc')
            ->limit(3)
            ->get();
    }
}
