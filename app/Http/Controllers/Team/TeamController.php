<?php

namespace App\Http\Controllers\Team;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Attendance;
use App\Models\Leave;
use App\Models\Department;
use Exception;
use Illuminate\Http\Request;
use App\Support\ShiftWindow;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Traits\AuthorizesByScope;

class TeamController extends Controller
{
    use \App\Http\Controllers\Concerns\SanitizesCsv;
    use AuthorizesByScope;
    use \App\Http\Controllers\Concerns\FiltersReportEmployees;
    use \App\Http\Controllers\Concerns\TeamAttendanceStatus;

    /**
     * Display team members based on user role
     */
    public function team(Request $request, \App\Services\Team\TeamListService $teamList)
    {
        try {
            $authUser = Auth::user();
            $currentDate = $request->date ?? Carbon::now()->format('Y-m-d');
            $dayName = Carbon::now()->format('l');
            $search = $request->get('search');
            $branchId = $request->get('branch_id');

            $list = $teamList->list($authUser, $currentDate, [
                'status' => $request->get('status'),
                'search' => $search,
                'branch_id' => $branchId,
                'department_id' => $request->get('department_id'),
                'designation_id' => $request->get('designation_id'),
            ]);
            $teamData = $list['members'];

            $perPage = $request->get('per_page', 20);
            $currentPage = $request->get('page', 1);
            $teamDataPaginated = new \Illuminate\Pagination\LengthAwarePaginator(
                $teamData->slice(($currentPage - 1) * $perPage, $perPage),
                $teamData->count(),
                $perPage,
                $currentPage,
                ['path' => $request->url(), 'query' => $request->query()]
            );

            $options = $teamList->filterOptions((int) $authUser->tenant_id);

            return view('client.team.view-team-member', [
                'allDepartments' => $options['departments'],
                'allDesignations' => $options['designations'],
                'teamData' => $teamDataPaginated,
                'statusCount' => $list['counts'],
                'currentDate' => $currentDate,
                'dayName' => $dayName,
                'authUser' => $authUser,
                'leaveTypes' => $options['leave_types'],
                'allBranches' => $options['branches'],
                'search' => $search,
                'branchId' => $branchId,
            ]);
        } catch (Exception $e) {
            report($e);

            return response()->json([
                'status' => false,
                'message' => 'An error occurred while fetching team data'
            ], 500);
        }
    }

    // ==================== BRANCH WISE ATTENDANCE REPORT ====================

    /**
     * Mark a day's attendance by hand (admin / HR, or a manager for their own
     * reportees). Supports present / absent / half day / on leave /
     * first- or second-half leave. Delegates to ManualAttendanceService.
     */
    public function markAttendance(\App\Http\Requests\MarkAttendanceRequest $request, \App\Services\Attendance\AttendanceEntryService $service)
    {
        try {
            $actor = Auth::user();

            $result = $service->markStatus([
                'user_id' => (int) $request->input('user_id'),
                'tenant_id' => (int) $actor->tenant_id,
                'date' => $request->input('date'),
                'end_date' => $request->input('end_date'),
                'status' => $request->attendanceStatus(),
                'clock_in' => $request->input('clock_in'),
                'clock_out' => $request->input('clock_out'),
                // Present + clock-in with no clock-out = checked in, still working.
                'clock_in_only' => ! $request->filled('clock_out'),
                // Present + clock-out only = close the employee's own open clock-in.
                'clock_out_only' => $request->clockOutOnly(),
                'clock_out_next_day' => $request->boolean('clock_out_next_day'),
                'leave_type_id' => $request->input('leave_type_id'),
                'remarks' => $request->input('remarks'),
                'user_shift_id' => $request->input('user_shift_id'),
            ], $actor);

            $attendance = $result['attendance'];
            $shift = $result['shift'];
            $dateLabel = \Carbon\Carbon::parse($request->input('date'))->format('d M Y');
            $rangeLabel = ($result['marked'] ?? 1) > 1 ? (' (' . $result['marked'] . ' days)') : '';

            return response()->json([
                'success' => true,
                'message' => ($result['is_update'] ? 'Attendance updated' : 'Attendance marked')
                    . ' successfully for ' . $dateLabel . $rangeLabel,
                'data' => $attendance,
                'is_update' => $result['is_update'],
                'leave_created' => $result['leave'] ? $result['leave']->leave_id : null,
                'shift_details' => $shift ? [
                    'shift_name' => $shift->name ?? null,
                    'shift_start' => $shift->start_time ?? null,
                    'shift_end' => $shift->end_time ?? null,
                    'late_minutes' => $attendance->late_minutes,
                    'attendance_status' => $attendance->attendance_status,
                    'effective_status' => $attendance->effective_status,
                    'clock_in' => $attendance->clock_in,
                    'clock_out' => $attendance->clock_out,
                ] : null,
            ], 200);
        } catch (Exception $e) {
            Log::error('Error in markAttendance: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => 'Failed to mark attendance. Please try again.',
            ], 500);
        }
    }

    /**
     * Recent attendance change-log entries for an employee (audit trail).
     * GET /team/attendance-log?user_id=&from=&to=
     */
    public function attendanceLog(Request $request)
    {
        $actor = Auth::user();
        $userId = (int) $request->input('user_id');
        $from = $request->filled('from') ? Carbon::parse($request->input('from'))->startOfDay() : Carbon::now()->subDays(30)->startOfDay();
        $to = $request->filled('to') ? Carbon::parse($request->input('to'))->endOfDay() : Carbon::now()->endOfDay();

        if (!$userId) {
            return response()->json(['success' => false, 'message' => 'user_id is required'], 422);
        }
        if (!$this->scopeCoversOwner($actor, 'team', 'view', $userId)) {
            return response()->json(['success' => false, 'message' => 'Not your reportee.'], 403);
        }

        $rows = \App\Models\AttendanceLog::withoutGlobalScopes()
            ->with('actor:id,name,role')
            ->where('tenant_id', $actor->tenant_id)
            ->where('user_id', $userId)
            ->whereBetween('event_time', [$from, $to])
            ->orderByDesc('event_time')
            ->limit(200)
            ->get()
            ->map(function ($l) {
                $changes = [];
                foreach ((array) ($l->after ?? []) as $col => $newVal) {
                    $oldVal = ($l->before[$col] ?? null);
                    $changes[] = ['field' => $col, 'from' => $oldVal, 'to' => $newVal];
                }
                return [
                    'id' => $l->id,
                    'event_time' => $l->event_time ? (string) $l->event_time : null,
                    'source' => $l->source ?: $l->event_type,
                    'event_type' => $l->event_type,
                    'actor' => $l->actor?->name ?? ($l->actor_role ?: 'System'),
                    'actor_role' => $l->actor_role ?: ($l->actor?->role),
                    'reason' => $l->reason,
                    'changes' => $changes,
                ];
            });

        return response()->json(['success' => true, 'data' => $rows]);
    }

    public function getUserShift(Request $request)
    {
        try {
            $tenantId = session('tenant_id');
            $userId = $request->user_id;
            $date = $request->date ?? now()->format('Y-m-d');

            if (!$userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'User ID is required'
                ]);
            }

            $userShiftData = $this->getUserShiftForDate($userId, $date, $tenantId);

            if ($userShiftData) {
                $shift = DB::table('shifts')
                    ->where('id', $userShiftData['shift_id'])
                    ->where('tenant_id', $tenantId)
                    ->first();

                if ($shift) {
                    $isOvernight = ShiftWindow::isOvernight($shift);

                    return response()->json([
                        'success' => true,
                        'shift' => [
                            'shift_name' => $shift->name,
                            'start_time' => $shift->start_time,
                            'end_time' => $shift->end_time,
                            'grace_minutes' => $shift->grace_minutes ?? 0,
                            'shift_id' => $shift->id,
                            'is_overnight' => $isOvernight
                        ],
                        // 2+ shifts that day: Mark Attendance lets the admin pick one.
                        'shifts' => app(\App\Services\Attendance\TenantShiftResolver::class)->markableShifts((int) $userId, (int) $tenantId, $date),
                    ]);
                }
            }

            return response()->json([
                'success' => false,
                'message' => 'No shift assigned for this user on this date'
            ]);
        } catch (Exception $e) {
            Log::error('Error in getUserShift: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch shift details'
            ], 500);
        }
    }

  
}
