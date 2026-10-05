<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\AttendanceTrackingPoint;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Services\RbacService;

class   AttendanceLocationController extends Controller
{
    public function view_ai_all(Request $request)
    {
        try {
            $authUser = Auth::user();
            $tenantId = session('tenant_id') ?? $authUser->tenant_id ?? null;

            if (!$tenantId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tenant not found'
                ], 400);
            }

            // Permission-based access (was a fixed role allowlist that
            // silently locked out any custom role holding a real
            // attendance:view grant).
            $locationScope = app(RbacService::class)->scopeFor($authUser, 'attendance', 'view');
            if ($locationScope === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access'
                ], 403);
            }

            // Get parameters
            $userId = $request->user_id;
            $date = $request->date;
            $startDate = $request->start_date;
            $endDate = $request->end_date;
            $departmentId = $request->department_id;

            // Validate date parameters
            if (!$date && !$startDate && !$endDate) {
                // Default to today if no date provided
                $date = date('Y-m-d');
            }

            // Build the query for users based on scope
            $usersQuery = $this->getAuthorizedUsersQuery($authUser, $tenantId, $userId, $departmentId, $locationScope);

            // Get users
            $users = $usersQuery->get();

            if ($users->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'message' => 'No users found',
                    'data' => []
                ], 200);
            }

            // Get attendance and tracks data
            $result = $this->getTracksWithUserDetails($users, $tenantId, $date, $startDate, $endDate);

            // Filter by date range if provided
            // if ($startDate && $endDate) {
            //     $result = $this->filterByDateRange($result, $startDate, $endDate);
            // } elseif ($date) {
            //     $result = $this->filterByDate($result, $date);
            // }

            return response()->json([
                'success' => true,
                'message' => 'Location data fetched successfully.',
                'data' => $result
            ], 200);
        } catch (Exception $e) {
            // Log the detail; never send raw exception / SQL text to the client.
            \Illuminate\Support\Facades\Log::error('AI attendance-locations failed', ['user_id' => Auth::id(), 'error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to fetch location data. Please try again later.'
            ], 500);
        }
    }

    /**
     * Get authorized users query based on role
     */
    private function getAuthorizedUsersQuery($authUser, $tenantId, $specificUserId = null, $departmentId = null, string $scope = 'own')
    {
        $query = DB::table('users as u')
            ->leftJoin('user_job_details as jd', function ($join) use ($tenantId) {
                $join->on('u.id', '=', 'jd.user_id')
                    ->where('jd.tenant_id', '=', $tenantId);
            })
            ->leftJoin('departments as d', 'jd.department', '=', 'd.id')
             ->leftJoin('designations as ds', 'jd.designation', '=', 'ds.id')
            ->where('u.tenant_id', $tenantId)
            ->where('u.status', 1)
            ->select(
                'u.id as user_id',
                'u.employee_id',
                'u.name',
                'd.name as department',
                'ds.name as designation'
            );

        // Scope-based filtering
        if ($scope === 'own') {
            $query->where('u.id', $authUser->id);
        } elseif ($scope === 'team') {
            $query->where(function ($q) use ($authUser) {
                $q->whereIn('u.id', function ($sub) use ($authUser) {
                    $sub->select('user_id')->from('user_reporting_heads')->where('reporting_head_id', $authUser->id);
                })
                    ->orWhere('u.id', $authUser->id);
            });
        }

        // Apply specific user filter
        if ($specificUserId) {
            $query->where('u.id', $specificUserId);
        }

        // Apply department filter (user_job_details.department holds the department id)
        if ($departmentId) {
            $query->where('jd.department', $departmentId);
        }

        $query->orderBy('u.name');

        return $query;
    }

    /**
     * Get tracks with user details
     */
    private function getTracksWithUserDetails($users, $tenantId, $date = null, $startDate = null, $endDate = null)
    {
        $result = [];
        $userIds = $users->pluck('user_id')->toArray();

        // Build attendance query. Date selection: a start_date + end_date range
        // wins; otherwise the single `date` (which defaults to today). The date
        // filter used to be commented out, so every tracked day ever was returned.
        $attendanceQuery = Attendance::whereIn('user_id', $userIds);

        if ($startDate && $endDate) {
            $attendanceQuery->whereBetween('date', [min($startDate, $endDate), max($startDate, $endDate)]);
        } elseif ($date) {
            $attendanceQuery->where('date', $date);
        } else {
            $attendanceQuery->where('date', date('Y-m-d'));
        }

        $attendances = $attendanceQuery->orderBy('date', 'desc')
            ->get(['id', 'user_id', 'date']);

        if ($attendances->isEmpty()) {
            return [];
        }
        // Get all tracks for these attendances
        $attendanceIds = $attendances->pluck('id')->toArray();
        $tracks = AttendanceTrackingPoint::forAttendanceIds($attendanceIds)->groupBy('attendance_id');

        // Group attendances by user and date
        $userAttendanceMap = [];
        foreach ($attendances as $attendance) {
            $key = $attendance->user_id . '_' . $attendance->date;
            if (!isset($userAttendanceMap[$key])) {
                $userAttendanceMap[$key] = [
                    'user_id' => $attendance->user_id,
                    'date' => $attendance->date,
                    'attendance_id' => $attendance->id,
                    'tracks' => []
                ];
            }

            // Add tracks if exists
            if (isset($tracks[$attendance->id])) {
                foreach ($tracks[$attendance->id] as $track) {
                    $userAttendanceMap[$key]['tracks'][] = [
                        'time' => date('H:i:s', strtotime($track->track_time)),
                        'address' => $track->address,
                        'latitude' => $track->lat,
                        'longitude' => $track->long,
                        'battery' => $track->battery_per
                    ];
                }
            }
        }

        // Build final result with user details
        foreach ($userAttendanceMap as $item) {
            $user = $users->firstWhere('user_id', $item['user_id']);
            if ($user && !empty($item['tracks'])) {
                $result[] = [
                    'user_id' => $user->user_id,
                    'employee_id' => $user->employee_id,
                    'name' => $user->name,
                    'designation' => $user->designation ?? 'N/A',
                    'department' => $user->department ?? 'N/A',
                    'date' => $item['date'],
                    'tracks' => $item['tracks']
                ];
            }
        }

        return $result;
    }

    // /**
    //  * Filter result by specific date
    //  */
    // private function filterByDate($data, $date)
    // {
    //     return array_filter($data, function ($item) use ($date) {
    //         return $item['date'] === $date;
    //     });
    // }

    // /**
    //  * Filter result by date range
    //  */
    // private function filterByDateRange($data, $startDate, $endDate)
    // {
    //     return array_filter($data, function ($item) use ($startDate, $endDate) {
    //         return $item['date'] >= $startDate && $item['date'] <= $endDate;
    //     });
    // }
}
