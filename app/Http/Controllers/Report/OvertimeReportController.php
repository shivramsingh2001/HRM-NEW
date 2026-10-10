<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Concerns\SanitizesCsv;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Overtime Hours report (monthly, per employee, with estimated cost).
 * Moved out of AttendanceReportController unchanged (code-quality plan, Phase 3);
 * route names are the same.
 */
class OvertimeReportController extends Controller
{
    use \App\Http\Controllers\Concerns\AttendanceReportHelpers;
    use \App\Http\Controllers\Concerns\FiltersReportEmployees;
    use SanitizesCsv;

    /**
     * Overtime Report (Monthly) — per-employee requested/approved/rejected
     * overtime hours for a month, with an estimated payout cost. Reuses the
     * same dynamic-vs-legacy hourly-rate resolution AttendanceAnalyticsService
     * uses for its overtime-cost widget, but per employee instead of a
     * tenant-wide average.
     */
    public function overtimeMonthlyReport(Request $request)
    {
        try {
            $tenantId = session('tenant_id');

            if (! $tenantId) {
                return back()->with('error', 'Tenant not found. Please login again.');
            }

            $selectedMonth = $request->get('month', now()->format('Y-m'));
            $monthStart = Carbon::parse($selectedMonth.'-01')->startOfMonth()->format('Y-m-d');
            $monthEnd = Carbon::parse($selectedMonth.'-01')->endOfMonth()->format('Y-m-d');

            $search = $request->get('search');
            $departmentFilter = $request->get('department');
            $statusFilter = $request->get('status');
            $branchFilter = $request->get('branch_id');

            $dynamicEnabled = (bool) DB::table('tenants')->where('id', $tenantId)->value('payroll_dynamic_ui_enabled');
            $multiplier = app(\App\Services\Attendance\PolicyResolver::class)
                ->forTenantMonth($tenantId, $selectedMonth)
                ->overtimeMultiplier;

            $usersQuery = DB::table('users as u')
                ->leftJoin('user_job_details as uj', 'u.id', '=', 'uj.user_id')
                ->leftJoin('departments as d', 'uj.department', '=', 'd.id')
                ->leftJoin('designations as ds', 'uj.designation', '=', 'ds.id')
                ->leftJoin('attendance_locations as al', 'uj.office_branch', '=', 'al.id')
                ->leftJoin('company_branches as cb', 'uj.branch_id', '=', 'cb.id')
                ->select('u.id', 'u.name', 'u.employee_id', 'd.name as department_name', 'ds.name as designation_name', 'cb.name as branch_name', 'uj.office_branch', 'al.name as location_name')
                ->tap(fn ($q) => $this->applyReportEmployeeFilters($q, $request))
                ->where('u.tenant_id', $tenantId)
                ->where('u.status', 1)
                ->where('u.role', '!=', 'admin');

            if ($search) {
                $usersQuery->where(function ($q) use ($search) {
                    $q->where('u.name', 'like', "%{$search}%")
                        ->orWhere('u.employee_id', 'like', "%{$search}%");
                });
            }
            if ($departmentFilter) {
                $usersQuery->where('uj.department', $departmentFilter);
            }
            if ($branchFilter) {
                $usersQuery->where('uj.branch_id', $branchFilter);
            }

            $users = $usersQuery->orderBy('u.name')->get();

            // Every approved/pending/rejected OT request this tenant has for the month.
            $requests = DB::table('overtime_requests')
                ->where('tenant_id', $tenantId)
                ->whereBetween('date', [$monthStart, $monthEnd])
                ->get()
                ->groupBy('user_id');

            // Multi-shift: minutes worked in 2nd+ shifts are automatic overtime.
            $extraShiftMinutes = DB::table('attendances')
                ->where('tenant_id', $tenantId)
                ->whereBetween('date', [$monthStart, $monthEnd])
                ->where('extra_shift_minutes', '>', 0)
                ->groupBy('user_id')
                ->pluck(DB::raw('SUM(extra_shift_minutes)'), 'user_id');

            // Per-employee hourly rate, dynamic-or-legacy — same resolution
            // AttendanceAnalyticsService::overtimeCost() uses tenant-wide,
            // done per user here for an accurate per-row estimated cost.
            $dynamicRates = [];
            $legacyRates = [];

            if ($dynamicEnabled) {
                $dynamicRates = DB::table('payroll_employee_structures')
                    ->where('tenant_id', $tenantId)
                    ->where('is_current', 1)
                    ->pluck('ctc', 'user_id');
            } else {
                $legacyRates = DB::table('user_payrolls')
                    ->join('payroll_masters', 'user_payrolls.payroll_master_id', '=', 'payroll_masters.id')
                    ->where('user_payrolls.tenant_id', $tenantId)
                    ->where('user_payrolls.is_current', 1)
                    ->pluck('payroll_masters.hourly_rate_applied', 'user_payrolls.user_id');
            }

            $reportData = collect();
            $totalApprovedHours = 0;
            $totalPendingHours = 0;
            $totalRejectedHours = 0;
            $totalEstimatedCost = 0;
            $totalRequests = 0;

            foreach ($users as $user) {
                $userRequests = $requests->get($user->id, collect());
                $extraShiftHours = round(((int) ($extraShiftMinutes[$user->id] ?? 0)) / 60, 2);

                if ($userRequests->isEmpty() && $extraShiftHours <= 0) {
                    continue;
                }

                $approved = $userRequests->where('status', 'approved');
                $pending = $userRequests->where('status', 'pending');
                $rejected = $userRequests->where('status', 'rejected');

                $approvedHours = (float) $approved->sum(fn ($r) => $r->approved_hours ?? $r->overtime_hours);
                $pendingHours = (float) $pending->sum('overtime_hours');
                $rejectedHours = (float) $rejected->sum('overtime_hours');

                $rate = $dynamicEnabled
                    ? (isset($dynamicRates[$user->id]) ? round(((float) $dynamicRates[$user->id] / 12) / (26 * 8), 2) : 0)
                    : (float) ($legacyRates[$user->id] ?? 0);

                $estimatedCost = round(($approvedHours + $extraShiftHours) * $rate * $multiplier, 2);

                if ($statusFilter) {
                    $countForStatus = $userRequests->where('status', $statusFilter)->count();
                    if ($countForStatus === 0) {
                        continue;
                    }
                }

                $reportData->push([
                    'user_id' => $user->id,
                    'name' => $user->name,
                    'employee_id' => $user->employee_id,
                    'department' => $user->department_name,
                    'designation' => $user->designation_name,
                    'branch' => $user->branch_name,
                    'attendance_location' => \App\Models\AttendanceLocation::labelFor($user->office_branch, $user->location_name),
                    'request_count' => $userRequests->count(),
                    'approved_count' => $approved->count(),
                    'pending_count' => $pending->count(),
                    'rejected_count' => $rejected->count(),
                    'approved_hours' => $approvedHours,
                    'extra_shift_hours' => $extraShiftHours,
                    'pending_hours' => $pendingHours,
                    'rejected_hours' => $rejectedHours,
                    'hourly_rate' => $rate,
                    'estimated_cost' => $estimatedCost,
                ]);

                $totalApprovedHours += $approvedHours;
                $totalPendingHours += $pendingHours;
                $totalRejectedHours += $rejectedHours;
                $totalEstimatedCost += $estimatedCost;
                $totalRequests += $userRequests->count();
            }

            $reportData = $reportData->sortByDesc('approved_hours')->values();

            $departments = DB::table('departments')->where('tenant_id', $tenantId)->orderBy('name')->get(['id', 'name']);
            $branches = DB::table('company_branches')->where('tenant_id', $tenantId)->where('status', 1)->orderBy('name')->get(['id', 'name']);

            $stats = [
                'total_employees' => $reportData->count(),
                'total_requests' => $totalRequests,
                'total_approved_hours' => round($totalApprovedHours, 2),
                'total_pending_hours' => round($totalPendingHours, 2),
                'total_rejected_hours' => round($totalRejectedHours, 2),
                'total_estimated_cost' => round($totalEstimatedCost, 2),
                'multiplier' => $multiplier,
            ];

            // 50 rows per page (stats above cover every row)
            $page = max(1, (int) $request->get('page', 1));
            $reportData = new \Illuminate\Pagination\LengthAwarePaginator(
                $reportData->forPage($page, 50)->values(), $reportData->count(), 50, $page,
                ['path' => $request->url(), 'query' => $request->query()]
            );

            return view('client.report.attendance.overtime-monthly', compact(
                'reportData', 'stats', 'departments', 'branches', 'selectedMonth', 'search', 'departmentFilter', 'statusFilter', 'branchFilter'
            ));
        } catch (Exception $e) {
            Log::error('Overtime Monthly Report Error: '.$e->getMessage());

            return back()->with('error', 'Failed to load overtime report: '.$e->getMessage());
        }
    }
}
