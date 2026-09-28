<?php

namespace App\Http\Controllers\Api\Attendance;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\AttendanceLog;
use App\Models\AttendanceLocation;
use App\Models\AttendanceTrackingPoint;
use App\Models\AttendanceRegularization;
use App\Models\Shift;
use App\Models\UserShift;
use App\Models\UserWeekoffs;
use App\Services\FieldTracking\TrackingPointIngestService;
use App\Services\FieldTracking\TrackingSessionService;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\User;
use App\Models\Request as RequestStore;
use App\Services\AttendanceNotificationService;
use App\Services\RbacService;
use App\Traits\AuthorizesByScope;
use Illuminate\Support\Facades\Log;

class AttendanceController extends Controller
{
    use AuthorizesByScope;

    protected $notificationService;

    public function __construct(AttendanceNotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Calculate attendance status based on shift timings
     * Priority 1: scheduled_shift_start/end from attendance record
     * Priority 2: hours-based fallback (2hrs=Absent, 2-6hrs=Half Day, 6hrs+=Present)
     */
    private function getAttendanceStatusByShift($totalHours, $attendance = null)
    {
        // PRIORITY 1: Use scheduled shift times from attendance record
        if ($attendance && isset($attendance->scheduled_shift_start) && $attendance->scheduled_shift_start && 
            isset($attendance->scheduled_shift_end) && $attendance->scheduled_shift_end) {
            $shiftStart = Carbon::parse($attendance->scheduled_shift_start);
            $shiftEnd = Carbon::parse($attendance->scheduled_shift_end);
            
            // Handle overnight shifts (e.g., 22:00 to 06:00)
            if ($shiftEnd->lessThan($shiftStart)) {
                $shiftEnd->addDay();
            }
            
            $expectedHours = $shiftStart->diffInHours($shiftEnd);
            
            if ($expectedHours > 0 && $totalHours !== null) {
                $percentage = ($totalHours / $expectedHours) * 100;
                
                // < 20% = Absent, 20-60% = Half Day, >= 60% = Present
                if ($percentage < 20) {
                    return 'Absent';
                } elseif ($percentage < 60) {
                    return 'Half Day';
                } else {
                    return 'Present';
                }
            }
        }
        
        // PRIORITY 2: Hours-based fallback
        if ($totalHours === null || $totalHours < 2) {
            return 'Absent';
        } elseif ($totalHours < 6) {
            return 'Half Day';
        } else {
            return 'Present';
        }
    }

    public function history(Request $request)
    {
        try {
            // --- validate & normalise every input so the raw SQL below is injection-safe ---
            $startDate = $this->safeDate($request->input('start_date'), date('Y-m-01', strtotime('-30 days')));
            $endDate   = $this->safeDate($request->input('end_date'), date('Y-m-28', strtotime('+30 days')));

            if ($startDate > $endDate) {
                [$startDate, $endDate] = [$endDate, $startDate];
            }

            // Cap the span so the recursive CTE cannot exceed MySQL's recursion limit
            // or explode the users x dates cross join.
            if ((strtotime($endDate) - strtotime($startDate)) / 86400 > 366) {
                $endDate = date('Y-m-d', strtotime($startDate . ' +366 days'));
            }

            $userId   = (int) Auth::id();
            $tenantId = (int) (optional($request->user())->tenant_id ?? session('tenant_id') ?? 0);
            $today    = date('Y-m-d');

            // Only $startDate/$endDate are inlined and both are guaranteed to match
            // ^\d{4}-\d{2}-\d{2}$ (see safeDate). Everything else is bound.
            $holidayTenantClause = $tenantId ? ' AND h.tenant_id = ? ' : '';

            $sql = "
            WITH RECURSIVE dates AS (
                SELECT DATE('{$startDate}') AS date
                UNION ALL
                SELECT DATE_ADD(date, INTERVAL 1 DAY)
                FROM dates
                WHERE date < DATE('{$endDate}')
            )

            SELECT 
                u.id AS user_id,
                u.name,
                u.email,
                d.date,
                DAYNAME(d.date) AS day_name,
                CASE WHEN d.date > ? THEN 1 ELSE 0 END AS is_future_date,
                (
                    SELECT COUNT(DISTINCT t.id)
                    FROM tasks t
                    INNER JOIN task_assigns ta ON t.id = ta.task_id
                    WHERE ta.assigned_to = u.id
                        AND t.task_date <= d.date
                        AND t.deadline_date >= d.date
                ) AS task_count,
                a.clock_in,
                a.clock_out,
                a.total_hours,
                a.worked_hours,
                a.scheduled_shift_start,
                a.scheduled_shift_end,
                l.leave_type,
                l.reason AS leave_reason,
                l.start_session AS leave_session,
                h.name AS holiday_name,
                h.name AS message,
                CASE 
                    WHEN wo.id IS NOT NULL THEN 'Week Off'
                    ELSE NULL
                END AS week_off,
                -- UPDATED STATUS PRIORITY WITH SHIFT-BASED LOGIC
                CASE
                    WHEN d.date > ? THEN
                        CASE
                            WHEN h.id IS NOT NULL THEN 'Holiday'
                            WHEN l.id IS NOT NULL AND l.start_session = 1 THEN 'First Half Leave'
                            WHEN l.id IS NOT NULL AND l.start_session = 2 THEN 'Second Half Leave'
                            WHEN l.id IS NOT NULL THEN 'Full Day Leave'
                            WHEN wo.id IS NOT NULL THEN 'Week Off'
                            ELSE 'Upcoming'
                        END
                    ELSE
                        CASE 
                            -- HIGHEST PRIORITY: Attendance with shift-based calculation
                            WHEN a.id IS NOT NULL AND a.clock_in IS NOT NULL AND a.clock_out IS NOT NULL THEN
                                CASE 
                                    WHEN a.scheduled_shift_start IS NOT NULL AND a.scheduled_shift_end IS NOT NULL THEN
                                        CASE 
                                            WHEN a.worked_hours IS NOT NULL THEN
                                                CASE 
                                                    WHEN (a.worked_hours / (TIMESTAMPDIFF(HOUR, a.scheduled_shift_start, 
                                                        CASE 
                                                            WHEN a.scheduled_shift_end < a.scheduled_shift_start 
                                                            THEN DATE_ADD(a.scheduled_shift_end, INTERVAL 1 DAY)
                                                            ELSE a.scheduled_shift_end
                                                        END
                                                    )) * 100) < 20 THEN 'Absent'
                                                    WHEN (a.worked_hours / (TIMESTAMPDIFF(HOUR, a.scheduled_shift_start, 
                                                        CASE 
                                                            WHEN a.scheduled_shift_end < a.scheduled_shift_start 
                                                            THEN DATE_ADD(a.scheduled_shift_end, INTERVAL 1 DAY)
                                                            ELSE a.scheduled_shift_end
                                                        END
                                                    )) * 100) < 60 THEN 'Half Day'
                                                    ELSE 'Present'
                                                END
                                            ELSE 
                                                CASE 
                                                    WHEN CAST(a.total_hours AS DECIMAL(10,2)) < 2 THEN 'Absent'
                                                    WHEN CAST(a.total_hours AS DECIMAL(10,2)) < 6 THEN 'Half Day'
                                                    ELSE 'Present'
                                                END
                                        END
                                    ELSE
                                        CASE 
                                            WHEN CAST(a.total_hours AS DECIMAL(10,2)) < 2 THEN 'Absent'
                                            WHEN CAST(a.total_hours AS DECIMAL(10,2)) < 6 THEN 'Half Day'
                                            ELSE 'Present'
                                        END
                                END
                            WHEN a.id IS NOT NULL AND a.clock_in IS NOT NULL AND a.clock_out IS NULL THEN 'Checked In Only'
                            -- THEN: Holiday
                            WHEN h.id IS NOT NULL THEN 'Holiday'
                            -- THEN: Leave
                            WHEN l.id IS NOT NULL AND l.start_session = 1 THEN 'First Half Leave'
                            WHEN l.id IS NOT NULL AND l.start_session = 2 THEN 'Second Half Leave'
                            WHEN l.id IS NOT NULL THEN 'Full Day Leave'
                            -- THEN: Week Off
                            WHEN wo.id IS NOT NULL THEN 'Week Off'
                            -- FINALLY: Absent
                            ELSE 'Absent'
                        END
                END AS day_status

            FROM users u
            CROSS JOIN dates d
            LEFT JOIN attendances a 
                ON a.user_id = u.id AND a.date = d.date
            LEFT JOIN leaves l 
                ON l.user_id = u.id 
                AND d.date BETWEEN l.start_date AND l.start_date
                AND l.status = 'approved'
            LEFT JOIN holidays h
                ON h.start_date = d.date
                AND h.status = 1
                {$holidayTenantClause}
            LEFT JOIN user_weekoffs wo
                ON wo.user_id = u.id
                AND wo.status = 1
                AND d.date BETWEEN wo.start_date AND wo.end_date
                AND (
                    (wo.off_type = 'date_based')
                    OR
                    (wo.off_type = 'day_based' AND wo.day_name = DAYNAME(d.date))
                )
            WHERE u.status = 1
            AND u.id = ?
            ORDER BY d.date DESC
        ";

            // Positional bindings, in SQL order:
            //   is_future_date CASE, day_status CASE, [holiday tenant], user id
            $bindings = [$today, $today];
            if ($tenantId) {
                $bindings[] = $tenantId;
            }
            $bindings[] = $userId;

            $data = DB::select($sql, $bindings);

            $uniqueData = [];
            $seenDates = [];

            foreach ($data as $record) {
                $dateKey = $record->date;
                if (!in_array($dateKey, $seenDates)) {
                    $uniqueData[] = $record;
                    $seenDates[] = $dateKey;
                }
            }

            return response()->json([
                'status'  => true,
                'message' => 'Attendance data fetched successfully for current user',
                'data' => $uniqueData
            ]);
        } catch (Exception $e) {
            Log::error('Attendance history failed', ['user_id' => Auth::id(), 'error' => $e->getMessage()]);
            return response()->json([
                'status' => false,
                'message' => "An error occured. Please try again later."
            ], 500);
        }
    }

    /**
     * Return $value as a strict Y-m-d string, or $default when it is not a valid date.
     * Guarantees the result matches ^\d{4}-\d{2}-\d{2}$ so it is safe to inline in SQL.
     */
    private function safeDate($value, string $default): string
    {
        if (!is_string($value) || $value === '') {
            return $default;
        }

        try {
            $parsed = Carbon::createFromFormat('Y-m-d', $value);

            return ($parsed && $parsed->format('Y-m-d') === $value) ? $value : $default;
        } catch (\Throwable $e) {
            return $default;
        }
    }

    public function getAttendance()
    {
        try {
            $userId = Auth::id();
            $today = date('Y-m-d');
            $baseUrl = config('app.url');
    
            $user = User::where('users.id', $userId)
                ->select('users.*', 'user_basic_details.profile_image as profile_image', 'designations.name as designation_name', 'user_job_details.attendance_type as attendance_type')
                ->leftJoin('user_basic_details', 'users.id', '=', 'user_basic_details.user_id')
                ->leftJoin('user_job_details', 'users.id', '=', 'user_job_details.user_id')
                ->leftJoin('designations', 'user_job_details.designation', '=', 'designations.id')
                ->first();
    
            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'User not found',
                ], 200);
            }
    
            // NIGHT SHIFT FIX: Check for active attendance from today OR yesterday
            $attendance = DB::table('attendances')
                ->where('user_id', $userId)
                ->where(function ($query) use ($today) {
                    $query->where('date', $today)
                        ->orWhere(function ($q) use ($today) {
                            $q->where('date', Carbon::parse($today)->subDay()->format('Y-m-d'))
                                ->whereNull('clock_out');
                        });
                })
                ->orderBy('date', 'desc')
                ->first();
    
            $shiftInfo = '';
            $dayName = date('l');
    
            $isWeekoff = UserWeekoffs::where('user_id', $userId)
                ->where('status', 1)
                ->where(function ($query) use ($today, $dayName) {
                    $query->where(function ($q) use ($today) {
                        $q->where('off_type', 'date_based')
                            ->whereDate('start_date', '<=', $today)
                            ->whereDate('end_date', '>=', $today);
                    })->orWhere(function ($q) use ($dayName) {
                        $q->where('off_type', 'day_based')
                            ->where('day_name', $dayName);
                    });
                })
                ->exists();
    
            if ($isWeekoff) {
                $shiftInfo = 'Week Off';
            } else {
                // Honours the tenant's custom-shifts toggle. Same string format.
                $shiftTenantId = (int) (optional(Auth::user())->tenant_id
                    ?: DB::table('users')->where('id', $userId)->value('tenant_id'));

                $userShift = $shiftTenantId
                    ? app(\App\Services\Attendance\TenantShiftResolver::class)
                        ->forUserDate((int) $userId, $shiftTenantId, $today)
                    : null;

                if ($userShift) {
                    $startTime = date('h:i A', strtotime($userShift->start_time));
                    $endTime = date('h:i A', strtotime($userShift->end_time));
                    $shiftInfo = $userShift->name . ' (' . $startTime . ' - ' . $endTime . ')';
                } else {
                    $shiftInfo = 'Shift not defined';
                }
            }
    
            // ✅ Ensure attendance_type is set with proper enum value
            $attendanceType = isset($user->attendance_type) ? $user->attendance_type : 'manual_attendance';
    
            $data = [
                'id' => $attendance->id ?? 0,
                'date' => $attendance->date ?? $today,
                'clock_in' => $attendance->clock_in ?? "",
                'clock_out' => $attendance->clock_out ?? "",
                'clock_in_lat' => $attendance->clock_in_lat ?? "",
                'clock_in_long' => $attendance->clock_in_long ?? "",
                'clock_in_address' => $attendance->clock_in_address ?? "",
                'clock_out_lat' => $attendance->clock_out_lat ?? "",
                'clock_out_long' => $attendance->clock_out_long ?? "",
                'clock_out_address' => $attendance->clock_out_address ?? "",
                'total_hours' => $attendance->total_hours ?? "",
                'status' => $attendance->status ?? 0,
                'user_name' => $user->name,
                'profile_image' => $baseUrl . ($user->profile_image ?? "/profile2.jpg"),
                'attendance_type' => $attendanceType,
                'user_email' => $user->email,
                'designation' => $user->designation_name,
                'shift' => $shiftInfo,
            ];

            // Additive: tells the app whether to run the continuous GPS tracker
            // for this employee and at what cadence. No existing key changed.
            $data['location_tracking'] = app(\App\Services\FieldTracking\FieldTrackingService::class)
                ->resolveForUser((int) $userId);

            return response()->json([
                'status' => true,
                'message' => 'Data fetch successfully!!!',
                'data' => $data
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'An error occured. Please try again later.',
            ], 500);
        }
    }

    /**
     * The logged-in user's raw punches + paired sessions for one day (default
     * today). Additive — existing endpoints/response shapes are unaffected.
     */
    public function punchHistory(Request $request)
    {
        try {
            $userId = (int) Auth::id();
            $tenantId = (int) (optional(Auth::user())->tenant_id ?: DB::table('users')->where('id', $userId)->value('tenant_id'));
            $date = $this->safeDate($request->input('date'), date('Y-m-d'));

            $punches = \App\Models\AttendancePunch::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('user_id', $userId)
                ->where('date', $date)
                ->where('status', 'active')
                ->orderBy('punched_at')
                ->get(['id', 'direction', 'punched_at', 'source', 'method', 'lat', 'long', 'address', 'session_seq']);

            $shift = $tenantId
                ? app(\App\Services\Attendance\TenantShiftResolver::class)->forUserDate($userId, $tenantId, $date)
                : null;
            $paired = app(\App\Services\Attendance\PunchSessionCalculator::class)->pairSessions($punches, $shift);

            return response()->json([
                'status' => true,
                'data' => [
                    'date' => $date,
                    'punches' => $punches->map(fn ($p) => [
                        'id' => $p->id,
                        'direction' => $p->direction,
                        'punched_at' => optional($p->punched_at)->format('Y-m-d H:i:s'),
                        'source' => $p->source,
                        'method' => $p->method,
                        'lat' => $p->lat,
                        'long' => $p->long,
                        'address' => $p->address,
                    ])->values(),
                    'sessions' => collect($paired['sessions'])->map(fn ($s) => [
                        'clock_in' => $s['in']->format('Y-m-d H:i:s'),
                        'clock_out' => $s['out']->format('Y-m-d H:i:s'),
                        'worked_hours' => round($s['worked_seconds'] / 3600, 2),
                    ])->values(),
                    'open_session' => $paired['open_session'],
                    'session_count' => $paired['session_count'],
                ],
            ], 200);
        } catch (Exception $e) {
            Log::error('Attendance punch history failed', ['user_id' => Auth::id(), 'error' => $e->getMessage()]);

            return response()->json([
                'status' => false,
                'message' => 'An error occured. Please try again later.',
            ], 500);
        }
    }

    /**
     * Cheap open/closed status for the mobile clock-in/out button state.
     * Additive — does not replace today()/getAttendance().
     */
    public function currentSession(Request $request)
    {
        try {
            $userId = (int) Auth::id();
            $tenantId = (int) (optional(Auth::user())->tenant_id ?: DB::table('users')->where('id', $userId)->value('tenant_id'));

            $latest = \App\Models\AttendancePunch::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('user_id', $userId)
                ->where('status', 'active')
                ->orderByDesc('punched_at')
                ->first(['direction', 'punched_at', 'session_seq']);

            $clockedIn = (bool) ($latest && $latest->direction === 'in');

            return response()->json([
                'status' => true,
                'data' => [
                    'clocked_in' => $clockedIn,
                    'session_number' => $clockedIn ? $latest->session_seq : null,
                    'clock_in_at' => $clockedIn ? optional($latest->punched_at)->format('Y-m-d H:i:s') : null,
                    'open_since_seconds' => $clockedIn ? Carbon::parse($latest->punched_at)->diffInSeconds(Carbon::now()) : null,
                ],
            ], 200);
        } catch (Exception $e) {
            Log::error('Attendance current-session failed', ['user_id' => Auth::id(), 'error' => $e->getMessage()]);

            return response()->json([
                'status' => false,
                'message' => 'An error occured. Please try again later.',
            ], 500);
        }
    }

    public function clockIn(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'lat' => 'required|numeric',
            'long' => 'required|numeric',
            'battery_per' => 'nullable',
            'address' => 'required|string|min:5|max:255',
            'device_id' => 'nullable|string',
            'wifi_ssid' => 'nullable|string',
            'network_type' => 'nullable|string'
        ]);
    
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ], 200);
        }
    
        try {
            $userId = Auth::id();
            $currentDateTime = Carbon::now();
            $currentDate = $currentDateTime->format('Y-m-d');
            $currentTime = $currentDateTime;
    
            $user = User::with('jobDetails')->find($userId);
    
            if (!$user) {
                $this->createFailureLog($userId, 'check_in', 'user_not_found', [
                    'user_id' => $userId
                ], $request);
    
                return response()->json([
                    'status' => false,
                    'message' => 'User not found.'
                ], 200);
            }
    
            // Get user's shift for today
            $userShift = $this->getUserShiftForDate($userId, $currentDate);
            $shift = $userShift ? Shift::find($userShift['id']) : null;
    
           
            // NIGHT SHIFT FIX: Determine correct attendance date. Shared with
            // every other punch source via AttendancePunchService.
            $attendanceDate = app(\App\Services\Attendance\AttendanceCalculator::class)
                ->resolveAttendanceDate($currentTime, $shift);

            $hasApprovedRequest = RequestStore::where('user_id', $userId)
                ->where('status', 'APPROVED')
                ->whereDate('start_date', '<=', $attendanceDate)
                ->whereDate('end_date', '>=', $attendanceDate)
                ->exists();
    
            $skipLocationCheck = $hasApprovedRequest;
            $locationVerification = 'verified';
            $checkInDistance = null;
            $branch = null;
    
            // =============================================
            // CHANGED: ENHANCED BRANCH HANDLING
            // =============================================
            if (!$skipLocationCheck && $user->jobDetails && $user->jobDetails->type == 'office') {
                $officeBranch = $user->jobDetails->office_branch;
    
                // CASE 1: User can mark attendance from ANY branch (office_branch == 0)
                if ($officeBranch == 0) {
                    // Find the nearest branch to user's location
                    // Disabled locations aren't valid candidates for "any branch".
                    $allBranches = AttendanceLocation::where('status', 1)
                        ->where('geofence_enabled', true)
                        ->get();
    
                    if ($allBranches->isEmpty()) {
                        $this->createFailureLog($userId, 'check_in', 'no_branches_configured', [
                            'user_id' => $userId
                        ], $request);
    
                        return response()->json([
                            'status' => false,
                            'message' => 'No branches configured in the system. Please contact admin.'
                        ], 200);
                    }
    
                    $nearestBranch = null;
                    $minDistance = PHP_INT_MAX;
    
                    foreach ($allBranches as $branchItem) {
                        if (!$branchItem->latitude || !$branchItem->longitude) {
                            continue;
                        }
    
                        $distance = $this->calculateDistance(
                            $branchItem->latitude,
                            $branchItem->longitude,
                            $request->lat,
                            $request->long
                        );
    
                        $radius = $branchItem->radius ?? 50;
    
                        if ($distance <= $radius && $distance < $minDistance) {
                            $minDistance = $distance;
                            $nearestBranch = $branchItem;
                            $checkInDistance = $distance;
                        }
                    }
    
                    if (!$nearestBranch) {
                        $this->createFailureLog($userId, 'check_in', 'no_branch_in_range', [
                            'user_lat' => $request->lat,
                            'user_long' => $request->long,
                            'user_address' => $request->address,
                        ], $request);
    
                        return response()->json([
                            'status' => false,
                            'message' => 'You are not within the allowed radius of any branch. Please move closer to your office location to clock in.'
                        ], 200);
                    }
    
                    $branch = $nearestBranch;
                    $locationVerification = 'verified';
                    
                } 
                // CASE 2: User has a specific branch assigned
                else {
                    $branch = AttendanceLocation::find($officeBranch);
    
                    if (!$branch) {
                        $this->createFailureLog($userId, 'check_in', 'branch_not_configured', [
                            'user_id' => $userId,
                            'office_branch' => $officeBranch
                        ], $request);

                        return response()->json([
                            'status' => false,
                            'message' => 'Branch not configured. Please contact admin.'
                        ], 200);
                    }

                    if (!$branch->geofence_enabled) {
                        // Geofencing explicitly disabled for this location — treat as a pass.
                        $checkInDistance = null;
                        $locationVerification = 'verified';
                        $skipGeofenceDistanceCheck = true;
                    } else {
                        $skipGeofenceDistanceCheck = false;
                    }

                    if (!$skipGeofenceDistanceCheck && (!$branch->latitude || !$branch->longitude)) {
                        $this->createFailureLog($userId, 'check_in', 'branch_coordinates_missing', [
                            'branch_id' => $branch->id,
                            'branch_name' => $branch->name
                        ], $request);
    
                        return response()->json([
                            'status' => false,
                            'message' => 'Attendance location coordinates not configured. Please contact admin.'
                        ], 200);
                    }
    
                    if (!$skipGeofenceDistanceCheck) {
                        $distance = $this->calculateDistance(
                            $branch->latitude,
                            $branch->longitude,
                            $request->lat,
                            $request->long
                        );

                        $checkInDistance = $distance;
                        $radius = $branch->radius ?? 50;

                        if ($distance > $radius) {
                            $this->createFailureLog($userId, 'check_in', 'location_out_of_bounds', [
                                'distance' => round($distance, 2),
                                'max_allowed' => $radius,
                                'branch_lat' => $branch->latitude,
                                'branch_long' => $branch->longitude,
                                'branch_name' => $branch->name,
                                'user_lat' => $request->lat,
                                'user_long' => $request->long,
                                'user_address' => $request->address,
                                'accuracy' => $request->accuracy
                            ], $request);

                            return response()->json([
                                'status' => false,
                                'message' => "You are " . round($distance) . " meters away from your assigned branch '{$branch->name}'. Maximum allowed distance for clock in is {$radius} meters. Please move closer to the office to clock in.",
                            ], 200);
                        }

                        $locationVerification = 'verified';
                    }
                }
            } else {
                $checkInDistance = null;
                $locationVerification = 'verified';
            }
    
            // Determine branch_id for attendance
            $branchId = $branch ? $branch->id : null;
            // =============================================
            // END OF CHANGED: ENHANCED BRANCH HANDLING
            // =============================================
    
            // Update user_shift status
            $userShiftRecord = UserShift::where('user_id', $userId)
                ->where('date', $attendanceDate)
                ->first();

            if ($userShiftRecord) {
                $userShiftRecord->status = 'ongoing';
                $userShiftRecord->save();
            } elseif ($userShift) {
                DB::table('user_shifts')
                    ->where('id', $userShift['user_shift_id'])
                    ->update(['status' => 'ongoing']);
            }

            try {
                $punchInput = new \App\Services\Attendance\PunchInput(
                    userId: $userId,
                    tenantId: (int) $user->tenant_id,
                    direction: 'in',
                    punchedAt: $currentDateTime,
                    source: 'mobile_app',
                    method: 'gps',
                    lat: is_numeric($request->lat) ? (float) $request->lat : null,
                    long: is_numeric($request->long) ? (float) $request->long : null,
                    address: $request->address,
                    locationVerification: $locationVerification,
                    attendanceLocationId: $branchId,
                    distanceMeters: $checkInDistance,
                    deviceId: $request->device_id,
                    networkType: $request->network_type,
                    wifiSsid: $request->wifi_ssid,
                    ipAddress: $request->ip(),
                    batteryPercent: is_numeric($request->battery_per) ? (int) $request->battery_per : null,
                    audit: new \App\Services\Attendance\AuditContext(
                        actorId: $userId,
                        actorRole: optional(Auth::user())->role,
                        source: 'clock_in',
                        reason: 'Mobile clock-in',
                    ),
                );

                $capturedPunch = app(\App\Services\Attendance\AttendancePunchService::class)->capture($punchInput);
            } catch (\App\Exceptions\OpenPunchSessionException $e) {
                $this->createFailureLog($userId, 'check_in', 'already_clocked_in', [
                    'attendance_date' => $attendanceDate,
                ], $request);

                return response()->json([
                    'status' => false,
                    'message' => $e->getMessage(),
                ], 200);
            } catch (\App\Exceptions\PeriodLockedException $e) {
                return response()->json([
                    'status' => false,
                    'message' => $e->getMessage(),
                ], 200);
            } catch (Exception $e) {
                return response()->json([
                    'status' => false,
                    'message' => 'Clock-in failed: ' . $e->getMessage()
                ], 500);
            }

            $attendance = Attendance::where('user_id', $userId)->where('date', $attendanceDate)->first();

            $this->createSuccessLog(
                $userId,
                $attendance->id ?? null,
                'check_in',
                $request,
                $checkInDistance
            );

            $trackingSession = app(TrackingSessionService::class)->resolveSessionForPunch($capturedPunch);
            if ($trackingSession) {
                app(TrackingPointIngestService::class)->ingestBatch(
                    (int) $trackingSession->tenant_id,
                    $userId,
                    [[
                        'point_id' => (string) Str::uuid(),
                        'lat' => $request->lat,
                        'long' => $request->long,
                        'track_time' => $currentDateTime,
                        'address' => $request->address,
                        'battery_per' => $request->battery_per,
                    ]]
                );
            }

            try {
                $this->notificationService->notifyClockIn($attendance, $user);
            } catch (Exception $e) {
                Log::error('Clock-in notification failed: ' . $e->getMessage());
            }

            $lt = app(\App\Services\FieldTracking\FieldTrackingService::class)->resolveForUser((int) $userId);

            return response()->json([
                'status' => true,
                'message' => 'Clock-In successful.',
                'tracking_enabled' => $lt['enabled'],
                'next_ping_seconds' => $lt['enabled'] ? (int) $lt['ping_seconds'] : 0,
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'An error occured. Please try again later.',
            ], 500);
        }
    }

    private function createSuccessLog($userId, $attendanceId, $eventType, $request, $distance = null)
    {
        try {
            AttendanceLog::create([
                'user_id' => $userId,
                'actor_id' => $userId,
                'actor_role' => optional(Auth::user())->role,
                'source' => $eventType === 'check_out' ? 'clock_out' : 'clock_in',
                'attendance_id' => $attendanceId,
                'event_type' => $eventType,
                'event_time' => Carbon::now(),
                'latitude' => $request->lat,
                'longitude' => $request->long,
                'address' => $request->address,
                'distance_from_branch' => $distance,
                'device_id' => $request->device_id,
                'wifi_ssid' => $request->wifi_ssid,
                'ip_address' => $request->ip(),
                'battery_level' => $request->battery_per,
                'network_type' => $request->network_type,
                'is_mock_location' => false,
                'verification_method' => 'gps',
                'user_agent' => $request->userAgent(),
                'raw_data' => json_encode([
                    'success' => true,
                    'timestamp' => Carbon::now()->toDateTimeString()
                ])
            ]);
        } catch (Exception $e) {
            Log::error('Failed to create success log: ' . $e->getMessage());
        }
    }

    private function createFailureLog($userId, $eventType, $failureReason, $details = [], $request = null)
    {
        try {
            $distanceFromBranch = null;
            if (isset($details['branch_lat']) && isset($details['branch_long']) && $request) {
                $distanceFromBranch = $this->calculateDistance(
                    $details['branch_lat'],
                    $details['branch_long'],
                    $request->lat,
                    $request->long
                );
            } elseif (isset($details['distance'])) {
                $distanceFromBranch = $details['distance'];
            }

            $logData = [
                'user_id' => $userId,
                'actor_id' => $userId,
                'actor_role' => optional(Auth::user())->role,
                'source' => $eventType === 'check_out' ? 'clock_out' : 'clock_in',
                'reason' => is_string($failureReason) ? mb_substr($failureReason, 0, 500) : null,
                'attendance_id' => null,
                'event_type' => $eventType,
                'event_time' => Carbon::now(),
                'latitude' => $request->lat ?? null,
                'longitude' => $request->long ?? null,
                'accuracy' => $request->accuracy ?? null,
                'altitude' => $request->altitude ?? null,
                'speed' => $request->speed ?? null,
                'bearing' => $request->bearing ?? null,
                'address' => $request->address ?? null,
                'distance_from_branch' => $distanceFromBranch,
                'device_id' => $request->device_id ?? null,
                'device_model' => $request->device_model ?? null,
                'os_version' => $request->os_version ?? null,
                'app_version' => $request->app_version ?? null,
                'battery_level' => $request->battery_per ?? null,
                'network_type' => $request->network_type ?? null,
                'wifi_ssid' => $request->wifi_ssid ?? null,
                'ip_address' => $request->ip(),
                'is_mock_location' => $request->is_mock_location ?? false,
                'location_confidence' => $request->location_confidence ?? null,
                'verification_method' => 'gps',
                'user_agent' => $request->userAgent(),
                'raw_data' => json_encode([
                    'failure_reason' => $failureReason,
                    'details' => $details,
                    'timestamp' => Carbon::now()->toDateTimeString()
                ])
            ];

            AttendanceLog::create($logData);
        } catch (Exception $e) {
        }
    }

    public function clockOut(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'lat' => 'required|numeric',
            'long' => 'required|numeric',
            'accuracy' => 'nullable|numeric|min:0|max:100',
            'battery_per' => 'nullable',
            'address' => 'required|string|min:5|max:255',
            'device_id' => 'nullable|string',
            'wifi_ssid' => 'nullable|string',
            'network_type' => 'nullable|string'
        ]);
    
        if ($validator->fails()) {
            $this->createFailureLog(Auth::id(), 'check_out', 'validation_failed', [
                'errors' => $validator->errors()->toArray(),
                'request_data' => $request->except(['_token'])
            ], $request);
    
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ], 200);
        }
    
        try {
            $userId = Auth::id();
            $currentDateTime = Carbon::now();
            $currentDate = $currentDateTime->format('Y-m-d');
    
            // NIGHT SHIFT FIX: Look for active attendance from today OR yesterday
            $attendance = Attendance::where('user_id', $userId)
                ->where(function ($query) use ($currentDate) {
                    $query->where('date', $currentDate)
                        ->orWhere('date', Carbon::parse($currentDate)->subDay()->format('Y-m-d'));
                })
                ->whereNull('clock_out')
                ->orderBy('date', 'desc')
                ->first();
    
            if (!$attendance) {
                $this->createFailureLog($userId, 'check_out', 'no_check_in', [
                    'date' => $currentDate,
                    'checked_dates' => [
                        $currentDate,
                        Carbon::parse($currentDate)->subDay()->format('Y-m-d')
                    ]
                ], $request);
    
                return response()->json([
                    'status' => false,
                    'message' => 'You have not checked in for any active shift.'
                ], 200);
            }
    
            if ($attendance->clock_out != null) {
                $this->createFailureLog($userId, 'check_out', 'already_clocked_out', [
                    'attendance_id' => $attendance->id,
                    'existing_clock_out' => $attendance->clock_out
                ], $request);
    
                return response()->json([
                    'status' => false,
                    'message' => 'You have already checked out for this shift.'
                ], 200);
            }
    
            $user = User::with('jobDetails')->find($userId);
    
            if (!$user) {
                $this->createFailureLog($userId, 'check_out', 'user_not_found', [
                    'user_id' => $userId
                ], $request);
    
                return response()->json([
                    'status' => false,
                    'message' => 'User not found.'
                ], 200);
            }
    
            $hasApprovedRequest = RequestStore::where('user_id', $userId)
                ->where('status', 'APPROVED')
                ->whereDate('start_date', '<=', $attendance->date)
                ->whereDate('end_date', '>=', $attendance->date)
                ->exists();
    
            $skipLocationCheck = $hasApprovedRequest;
            $locationVerification = 'verified';
            $checkOutDistance = null;
            $branch = null;
    
            // =============================================
            // CHANGED: ENHANCED BRANCH HANDLING
            // =============================================
            if (!$skipLocationCheck && $user->jobDetails && $user->jobDetails->type == 'office') {
                $officeBranch = $user->jobDetails->office_branch;
    
                // CASE 1: User can mark attendance from ANY branch (office_branch == 0)
                if ($officeBranch == 0) {
                    // Find the nearest branch to user's location
                    // Disabled locations aren't valid candidates for "any branch".
                    $allBranches = AttendanceLocation::where('status', 1)
                        ->where('geofence_enabled', true)
                        ->get();
    
                    if ($allBranches->isEmpty()) {
                        $this->createFailureLog($userId, 'check_out', 'no_branches_configured', [
                            'user_id' => $userId
                        ], $request);
    
                        return response()->json([
                            'status' => false,
                            'message' => 'No branches configured in the system. Please contact admin.'
                        ], 200);
                    }
    
                    $nearestBranch = null;
                    $minDistance = PHP_INT_MAX;
    
                    foreach ($allBranches as $branchItem) {
                        if (!$branchItem->latitude || !$branchItem->longitude) {
                            continue;
                        }
    
                        $distance = $this->calculateDistance(
                            $branchItem->latitude,
                            $branchItem->longitude,
                            $request->lat,
                            $request->long
                        );
    
                        $radius = $branchItem->radius ?? 50;
    
                        if ($distance <= $radius && $distance < $minDistance) {
                            $minDistance = $distance;
                            $nearestBranch = $branchItem;
                            $checkOutDistance = $distance;
                        }
                    }
    
                    if (!$nearestBranch) {
                        $this->createFailureLog($userId, 'check_out', 'no_branch_in_range', [
                            'user_lat' => $request->lat,
                            'user_long' => $request->long,
                            'user_address' => $request->address,
                        ], $request);
    
                        return response()->json([
                            'status' => false,
                            'message' => 'You are not within the allowed radius of any branch. Please move closer to your office location to clock out.'
                        ], 200);
                    }
    
                    $branch = $nearestBranch;
                    $locationVerification = 'verified';
                    
                } 
                // CASE 2: User has a specific branch assigned
                else {
                    $branch = AttendanceLocation::find($officeBranch);
    
                    if (!$branch) {
                        $this->createFailureLog($userId, 'check_out', 'branch_not_configured', [
                            'user_id' => $userId,
                            'office_branch' => $officeBranch
                        ], $request);

                        return response()->json([
                            'status' => false,
                            'message' => 'Branch not configured. Please contact admin.'
                        ], 200);
                    }

                    if (!$branch->geofence_enabled) {
                        // Geofencing explicitly disabled for this location — treat as a pass.
                        $checkOutDistance = null;
                        $locationVerification = 'verified';
                        $skipGeofenceDistanceCheck = true;
                    } else {
                        $skipGeofenceDistanceCheck = false;
                    }

                    if (!$skipGeofenceDistanceCheck && (!$branch->latitude || !$branch->longitude)) {
                        $this->createFailureLog($userId, 'check_out', 'branch_coordinates_missing', [
                            'branch_id' => $branch->id,
                            'branch_name' => $branch->name
                        ], $request);

                        return response()->json([
                            'status' => false,
                            'message' => 'Attendance location coordinates not configured. Please contact admin.'
                        ], 200);
                    }

                    if (!$skipGeofenceDistanceCheck) {
                        $distance = $this->calculateDistance(
                            $branch->latitude,
                            $branch->longitude,
                            $request->lat,
                            $request->long
                        );

                        $checkOutDistance = $distance;
                        $radius = $branch->radius ?? 50;

                        if ($distance > $radius) {
                            $this->createFailureLog($userId, 'check_out', 'location_out_of_bounds', [
                                'distance' => round($distance, 2),
                                'max_allowed' => $radius,
                                'branch_lat' => $branch->latitude,
                                'branch_long' => $branch->longitude,
                                'branch_name' => $branch->name,
                                'user_lat' => $request->lat,
                                'user_long' => $request->long,
                                'user_address' => $request->address,
                                'accuracy' => $request->accuracy
                            ], $request);

                            return response()->json([
                                'status' => false,
                                'message' => "You are " . round($distance) . " meters away from your assigned branch '{$branch->name}'. Maximum allowed distance for clock out is {$radius} meters. Please move closer to the office to clock out.",
                            ], 200);
                        }

                        $locationVerification = 'verified';
                    }
                }
            } else {
                $checkOutDistance = null;
                $locationVerification = 'verified';
            }
            // =============================================
            // END OF CHANGED: ENHANCED BRANCH HANDLING
            // =============================================
    
            // Update user_shift status
            $userShift = UserShift::where('user_id', $userId)
                ->where('date', $attendance->date)
                ->first();

            if ($userShift) {
                $userShift->status = 'complete';
                $userShift->save();
            }

            try {
                $punchInput = new \App\Services\Attendance\PunchInput(
                    userId: $userId,
                    tenantId: (int) $attendance->tenant_id,
                    direction: 'out',
                    punchedAt: $currentDateTime,
                    source: 'mobile_app',
                    method: 'gps',
                    lat: is_numeric($request->lat) ? (float) $request->lat : null,
                    long: is_numeric($request->long) ? (float) $request->long : null,
                    address: $request->address,
                    locationVerification: $locationVerification,
                    attendanceLocationId: $branch->id ?? null,
                    distanceMeters: $checkOutDistance,
                    accuracyMeters: is_numeric($request->accuracy) ? (float) $request->accuracy : null,
                    deviceId: $request->device_id,
                    networkType: $request->network_type,
                    wifiSsid: $request->wifi_ssid,
                    ipAddress: $request->ip(),
                    batteryPercent: is_numeric($request->battery_per) ? (int) $request->battery_per : null,
                    audit: new \App\Services\Attendance\AuditContext(
                        actorId: $userId,
                        actorRole: optional(Auth::user())->role,
                        source: 'clock_out',
                        reason: 'Mobile clock-out',
                    ),
                );

                $capturedPunch = app(\App\Services\Attendance\AttendancePunchService::class)->capture($punchInput);
            } catch (\App\Exceptions\NoOpenPunchSessionException $e) {
                $this->createFailureLog($userId, 'check_out', 'already_clocked_out', [
                    'attendance_id' => $attendance->id,
                ], $request);

                return response()->json([
                    'status' => false,
                    'message' => $e->getMessage(),
                ], 200);
            } catch (\App\Exceptions\PeriodLockedException $e) {
                return response()->json([
                    'status' => false,
                    'message' => $e->getMessage(),
                ], 200);
            } catch (Exception $e) {
                Log::error('Clock-out transaction error: ' . $e->getMessage());
                return response()->json([
                    'status' => false,
                    'message' => 'An error occured. Please try again later.'
                ], 500);
            }

            $attendance = $attendance->fresh();

            $this->createSuccessLog(
                $userId,
                $attendance->id,
                'check_out',
                $request,
                $checkOutDistance
            );

            $trackingSession = app(TrackingSessionService::class)->resolveSessionForPunch($capturedPunch);
            if ($trackingSession) {
                app(TrackingPointIngestService::class)->ingestBatch(
                    (int) $trackingSession->tenant_id,
                    $userId,
                    [[
                        'point_id' => (string) Str::uuid(),
                        'lat' => $request->lat,
                        'long' => $request->long,
                        'track_time' => $currentDateTime,
                        'accuracy_meters' => is_numeric($request->accuracy) ? (float) $request->accuracy : null,
                        'address' => $request->address,
                        'battery_per' => $request->battery_per,
                    ]]
                );
            }

            try {
                $this->notificationService->notifyClockOut($attendance, $user);
            } catch (Exception $e) {
                Log::error('Clock-out notification failed: ' . $e->getMessage());
            }

            return response()->json([
                'status' => true,
                'message' => 'Clock-Out successfully.',
                'data' => [
                    'clock_out_time' => $attendance->clock_out,
                    'total_hours' => $attendance->total_hours,
                    'worked_hours' => (float) $attendance->worked_hours,
                    'attendance_status' => $attendance->attendance_status,
                    'shift_status' => 'complete'
                ],
                // Additive: the app stops the tracker on clock-out regardless.
                'tracking_enabled' => false,
                'next_ping_seconds' => 0,
            ], 200);
        } catch (Exception $e) {
            Log::error('Clock-out error: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'An error occurred. Please try again later.'
            ], 500);
        }
    }

    private function determineDayStatus($attendance, $holiday, $leave, $weekoff)
    {
        // UPDATED: With shift-based logic
        if ($attendance && $attendance->clock_in && $attendance->clock_out) {
            $totalHours = null;
            if ($attendance->worked_hours) {
                $totalHours = (float)$attendance->worked_hours;
            } elseif ($attendance->total_hours) {
                $totalHours = (float)$attendance->total_hours;
            } elseif ($attendance->clock_in && $attendance->clock_out) {
                $clockIn = Carbon::parse($attendance->clock_in);
                $clockOut = Carbon::parse($attendance->clock_out);
                $totalHours = $clockIn->diffInHours($clockOut);
            }
            return $this->getAttendanceStatusByShift($totalHours, $attendance);
        }
        if ($attendance && $attendance->clock_in) {
            return 'Checked In Only';
        }
        if ($holiday) {
            return 'Holiday';
        }
        if ($leave) {
            return $leave->start_session == 1 ? 'First Half Leave' : ($leave->start_session == 2 ? 'Second Half Leave' : 'Full Day Leave');
        }
        if ($weekoff) {
            return 'Week Off';
        }
        return 'Absent';
    }

    private function getUserShiftForDate($userId, $date)
    {
        $tenantId = (int) (optional(Auth::user())->tenant_id
            ?: DB::table('users')->where('id', $userId)->value('tenant_id'));

        if (!$tenantId) {
            return null;
        }

        // Honours the tenant's custom-shifts toggle (fixed company shift when
        // off, the per-date assignment chain when on). Response keys unchanged.
        $shift = app(\App\Services\Attendance\TenantShiftResolver::class)
            ->forUserDate((int) $userId, $tenantId, $date);

        if (!$shift) {
            return null;
        }

        $userShiftId = UserShift::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->where('date', $date)
            ->value('id');

        return [
            'id' => $shift->id,
            'user_shift_id' => $userShiftId,
            'name' => $shift->name,
            'start_time' => $shift->start_time,
            'end_time' => $shift->end_time,
            'grace_minutes' => $shift->grace_minutes ?? 0
        ];
    }

    private function isWeekoffForUser($userId, $date)
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

    private function calculateDistance($lat1, $lon1, $lat2, $lon2)
    {
        $earthRadius = 6371000;
        $lat1 = deg2rad($lat1);
        $lon1 = deg2rad($lon1);
        $lat2 = deg2rad($lat2);
        $lon2 = deg2rad($lon2);
        $latDelta = $lat2 - $lat1;
        $lonDelta = $lon2 - $lon1;
        $a = sin($latDelta / 2) * sin($latDelta / 2) +
            cos($lat1) * cos($lat2) *
            sin($lonDelta / 2) * sin($lonDelta / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return $earthRadius * $c;
    }

    public function trackLocation(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'lat' => 'required',
            'long' => 'required',
            'battery_per' => 'nullable',
            'address' => 'required|min:5|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ], 200);
        }

        try {
            $userId = Auth::id();
            $tenantId = (int) (optional(Auth::user())->tenant_id ?: DB::table('users')->where('id', $userId)->value('tenant_id'));

            $lt = app(\App\Services\FieldTracking\FieldTrackingService::class)->resolveForUser((int) $userId);
            $pingOn = $lt['enabled'] ? (int) $lt['ping_seconds'] : 0;

            // When enforcement is on, an employee without the tracking flag is
            // told to stop and nothing is written (protects the DB from stale apps).
            if (config('location.enforce_enabled') && ! $lt['enabled']) {
                return response()->json([
                    'status' => false,
                    'message' => 'Location tracking is not enabled for your account.',
                    'tracking_enabled' => false,
                    'next_ping_seconds' => 0,
                ], 200);
            }

            // Single-ping mode is a 1-point batch through the same session-aware,
            // idempotent ingest path as trackBatch — the server mints the point_id
            // since single-ping predates the client-generated id.
            $result = app(TrackingPointIngestService::class)->ingestBatch($tenantId, (int) $userId, [[
                'point_id' => (string) Str::uuid(),
                'lat' => $request->lat,
                'long' => $request->long,
                'track_time' => now(),
                'address' => $request->address,
                'battery_per' => $request->battery_per,
            ]]);

            if ($result['no_session'] || $result['saved'] === 0) {
                return response()->json([
                    'status' => false,
                    'message' => $result['no_session']
                        ? 'User has not clocked in for any active shift.'
                        : 'You have already checked out for this shift.',
                    'tracking_enabled' => $lt['enabled'],
                    'next_ping_seconds' => 0,
                ], 200);
            }

            return response()->json([
                'status' => true,
                'message' => 'Location saved successfully.',
                'tracking_enabled' => $lt['enabled'],
                'next_ping_seconds' => $pingOn,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'An error occured. Please try again later',
            ], 500);
        }
    }

    /**
     * Buffered GPS upload — the app collects points locally and flushes a batch
     * every few minutes. One request + one bulk insert instead of one per ping.
     * points[].point_id is an optional idempotency key: when omitted the server
     * derives one from lat/long/track_time, so a retried batch (same readings or
     * same client point_ids) is safe by construction via the atp_session_point_uq
     * DB constraint — see TrackingPointIngestService.
     */
    public function trackBatch(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'points' => 'required|array|min:1|max:' . (int) config('location.batch_max', 60),
            'points.*.point_id' => 'nullable|string|max:64',
            'points.*.lat' => 'required|numeric',
            'points.*.long' => 'required|numeric',
            'points.*.accuracy_meters' => 'nullable|numeric|min:0|max:1000',
            'points.*.battery_per' => 'nullable',
            'points.*.address' => 'nullable|string|max:255',
            'points.*.track_time' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first()], 200);
        }

        try {
            $userId = Auth::id();
            $tenantId = (int) (optional(Auth::user())->tenant_id ?: DB::table('users')->where('id', $userId)->value('tenant_id'));

            $lt = app(\App\Services\FieldTracking\FieldTrackingService::class)->resolveForUser((int) $userId);
            $pingOn = $lt['enabled'] ? (int) $lt['ping_seconds'] : 0;

            if (config('location.enforce_enabled') && ! $lt['enabled']) {
                return response()->json([
                    'status' => false,
                    'message' => 'Location tracking is not enabled for your account.',
                    'tracking_enabled' => false,
                    'next_ping_seconds' => 0,
                ], 200);
            }

            $result = app(TrackingPointIngestService::class)->ingestBatch($tenantId, (int) $userId, $request->input('points'));

            if ($result['no_session']) {
                return response()->json([
                    'status' => false,
                    'message' => 'No active shift to attach location to.',
                    'tracking_enabled' => $lt['enabled'],
                    'next_ping_seconds' => 0,
                ], 200);
            }

            $saved = $result['saved'];
            $rejected = $result['rejected'];

            return response()->json([
                'status' => true,
                'message' => "Saved {$saved} of " . ($saved + $rejected + $result['duplicates']) . ' points.',
                'data' => [
                    'saved' => $saved,
                    'rejected' => $rejected,
                    'duplicates' => $result['duplicates'],
                    'session_id' => $result['session_id'],
                ],
                'tracking_enabled' => $lt['enabled'],
                'next_ping_seconds' => $pingOn,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'An error occured. Please try again later',
            ], 500);
        }
    }

    public function todayLocations()
    {
        try {
            $userId = Auth::id();
            $today = date('Y-m-d');

            $attendance = Attendance::where('user_id', $userId)
                ->where('date', $today)
                ->first();

            if (!$attendance) {
                return response()->json([
                    'status' => false,
                    'message' => 'No attendance record found for today.'
                ], 200);
            }

            $locations = AttendanceTrackingPoint::forAttendanceId($attendance->id)
                ->get([
                    'track_time',
                    'lat',
                    'long',
                    'address'
                ]);

            return response()->json([
                'status' => true,
                'message' => 'Data fetch successfully!!!',
                'data' => $locations
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to load location history.',
            ], 500);
        }
    }

    public function regularizationStore(Request $request)
    {
        try {
            $authUser = Auth::user();

            $validator = Validator::make($request->all(), [
                'date' => 'required|date_format:Y-m-d|before_or_equal:today',
                'request_type' => 'required|in:in_time,out_time,both,full_day,wfh_not_marked,technical_issue',
                'in_time' => 'required_if:request_type,in_time,both|nullable|date_format:H:i',
                'out_time' => 'required_if:request_type,out_time,both|nullable|date_format:H:i|after:in_time',
                'file' => 'nullable|file|mimes:jpg,jpeg,png,pdf,doc,docx|max:2048',
                'reason' => 'required|string|max:500',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => $validator->errors()->first()
                ], 200);
            }

            if (AttendanceRegularization::where('user_id', $authUser->id)
                ->where('date', $request->date)
                ->where('request_type', $request->request_type)
                ->exists()
            ) {
                return response()->json([
                    'success' => false,
                    'message' => 'Request already exists for this date.'
                ], 200);
            }

            $filePath = null;
            if ($request->hasFile('file')) {
                $filePath = $request->file('file')->store('attendance_files', 'public');
            }

            $reg = AttendanceRegularization::create([
                'tenant_id' => $authUser->tenant_id,
                'user_id' => $authUser->id,
                'date' => $request->date,
                'request_type' => $request->request_type,
                'in_time' => $request->in_time,
                'out_time' => $request->out_time,
                'file' => $filePath,
                'reason' => $request->reason,
                'status' => 'pending'
            ]);

            // Parity with the web submit path.
            try {
                app(\App\Services\AttendanceRegularizationNotificationService::class)
                    ->notifyRegularizationSubmitted($reg);
            } catch (\Throwable $e) {
                Log::error('Regularization submit notification failed: ' . $e->getMessage());
            }

            return response()->json([
                'success' => true,
                'message' => 'Request submitted successfully',
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred. Please try again later.'
            ], 500);
        }
    }

    public function getMyRegularizations(Request $request)
    {
        try {
            $authUser = Auth::user();

            $query = DB::table('attendance_regularizations as ar')
                ->select([
                    'ar.id',
                    'ar.date',
                    'ar.request_type',
                    'ar.in_time',
                    'ar.out_time',
                    'ar.reason',
                    'ar.file',
                    'ar.status',
                    'um.name as approved_by',
                    'ar.approved_date',
                    'u.id as user_id',
                    'u.employee_id as employee_id',
                    'u.name as user_name',
                    'u.email as user_email',
                    'ar.created_at',
                    'd.name as designation',
                    'bd.profile_image'
                ])
                ->where('ar.user_id', $authUser->id)
                ->leftJoin('users as u', 'ar.user_id', '=', 'u.id')
                ->leftJoin('users as um', 'ar.approved_by', '=', 'um.id')
                ->leftJoin('user_job_details as jd', 'u.id', '=', 'jd.user_id')
                ->leftJoin('user_basic_details as bd', 'u.id', '=', 'bd.user_id')
                ->leftJoin('designations as d', 'jd.designation', '=', 'd.id');

            if ($request->has('status') && $request->status != 'all') {
                $query->where('status', $request->status);
            }

            if ($request->has('request_type') && $request->request_type != 'all') {
                $query->where('request_type', $request->request_type);
            }

            if ($request->has('search') && !empty($request->search)) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('reason', 'like', "%{$search}%")
                        ->orWhere('date', 'like', "%{$search}%")
                        ->orWhere('request_type', 'like', "%{$search}%");
                });
            }

            if ($request->has('start_date') && !empty($request->start_date)) {
                $query->where('date', '>=', $request->start_date);
            }

            if ($request->has('end_date') && !empty($request->end_date)) {
                $query->where('date', '<=', $request->end_date);
            }

            $perPage = $request->get('per_page', 15);
            $regularizations = $query->orderBy('created_at', 'desc')
                ->paginate($perPage);

            $formattedRequests = $regularizations->map(function ($request) {
                return [
                    'id' => $request->id,
                    'date' => $request->date,
                    'request_type' => $request->request_type,
                    'in_time' => $request->in_time,
                    'out_time' => $request->out_time,
                    'reason' => $request->reason,
                    'file' => $request->file ? asset($request->file) : null,
                    'status' => $request->status,
                    'submit_date' => $request->created_at,
                    'user_id' => $request->user_id,
                    'employee_id' => $request->employee_id,
                    'user_name' => $request->user_name,
                    'user_email' => $request->user_email,
                    'profile_image' => $request->profile_image ? asset($request->profile_image) : null,
                    'designation' => $request->designation,
                    'approved_by' => $request->approved_by,
                    'approved_date' => $request->approved_date
                ];
            });

            $summaryQuery = DB::table('attendance_regularizations')
                ->where('user_id', $authUser->id);

            $summary = [
                'total' => $summaryQuery->count(),
                'pending' => $summaryQuery->clone()->where('status', 'pending')->count(),
                'approved' => $summaryQuery->clone()->where('status', 'approved')->count(),
                'rejected' => $summaryQuery->clone()->where('status', 'rejected')->count(),
            ];

            return response()->json([
                'success' => true,
                'message' => 'Regularization requests fetched successfully',
                'data' => $formattedRequests,
                'summary' => $summary,
                'links' => [
                    'first' => $regularizations->url(1),
                    'last' => $regularizations->url($regularizations->lastPage()),
                    'prev' => $regularizations->previousPageUrl(),
                    'next' => $regularizations->nextPageUrl(),
                ]
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred. Please try again later.'
            ], 500);
        }
    }

    public function getReporteesRegularizations(Request $request)
    {
        try {
            $authUser = Auth::user();

            if (!app(RbacService::class)->can($authUser, 'attendance', 'approve')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access. Only managers, admins or supervisors can approve requests.'
                ], 200);
            }

            $reporteeIds = DB::table('user_reporting_heads')
                ->where('reporting_head_id', $authUser->id)
                ->pluck('user_id')
                ->toArray();

            if (empty($reporteeIds)) {
                return response()->json([
                    'success' => true,
                    'message' => 'No reportees found',
                    'data' => [],
                    'summary' => [
                        'total' => 0,
                        'pending' => 0,
                        'approved' => 0,
                        'rejected' => 0,
                    ],
                    'links' => [
                        'first' => null,
                        'last' => null,
                        'prev' => null,
                        'next' => null,
                    ]
                ], 200);
            }

            $query = DB::table('attendance_regularizations as ar')
                ->select([
                    'ar.id',
                    'ar.date',
                    'ar.request_type',
                    'ar.in_time',
                    'ar.out_time',
                    'ar.reason',
                    'ar.file',
                    'ar.status',
                    'um.name as approved_by',
                    'ar.approved_date',
                    'ar.created_at',
                    'u.id as user_id',
                    'u.employee_id as employee_id',
                    'u.name as user_name',
                    'u.email as user_email',
                    'd.name as designation',
                    'bd.profile_image'
                ])
                ->leftJoin('users as u', 'ar.user_id', '=', 'u.id')
                ->leftJoin('users as um', 'ar.approved_by', '=', 'um.id')
                ->leftJoin('user_job_details as jd', 'u.id', '=', 'jd.user_id')
                ->leftJoin('user_basic_details as bd', 'u.id', '=', 'bd.user_id')
                ->leftJoin('designations as d', 'jd.designation', '=', 'd.id')
                ->whereIn('ar.user_id', $reporteeIds);

            if ($request->has('status') && $request->status != 'all') {
                $query->where('ar.status', $request->status);
            }

            if ($request->has('request_type') && $request->request_type != 'all') {
                $query->where('ar.request_type', $request->request_type);
            }

            if ($request->has('search') && !empty($request->search)) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('ar.reason', 'like', "%{$search}%")
                        ->orWhere('ar.date', 'like', "%{$search}%")
                        ->orWhere('ar.request_type', 'like', "%{$search}%")
                        ->orWhere('u.name', 'like', "%{$search}%")
                        ->orWhere('u.employee_id', 'like', "%{$search}%");
                });
            }

            if ($request->has('start_date') && !empty($request->start_date)) {
                $query->where('ar.date', '>=', $request->start_date);
            }

            if ($request->has('end_date') && !empty($request->end_date)) {
                $query->where('ar.date', '<=', $request->end_date);
            }

            if ($request->has('user_id') && !empty($request->user_id)) {
                $query->where('ar.user_id', $request->user_id);
            }

            $perPage = $request->get('per_page', 15);
            $regularizations = $query->orderBy('ar.created_at', 'desc')
                ->paginate($perPage);

            $formattedRequests = $regularizations->map(function ($request) {
                return [
                    'id' => $request->id,
                    'date' => $request->date,
                    'request_type' => $request->request_type,
                    'in_time' => $request->in_time,
                    'out_time' => $request->out_time,
                    'reason' => $request->reason,
                    'file' => $request->file ? asset($request->file) : null,
                    'status' => $request->status,
                    'submit_date' => $request->created_at,
                    'user_id' => $request->user_id,
                    'employee_id' => $request->employee_id,
                    'user_name' => $request->user_name,
                    'user_email' => $request->user_email,
                    'profile_image' => $request->profile_image ? asset($request->profile_image) : null,
                    'designation' => $request->designation,
                    'approved_by' => $request->approved_by,
                    'approved_date' => $request->approved_date
                ];
            });

            $reportees = DB::table('users as u')
                ->select('u.id', 'u.name', 'u.employee_id', 'jd.designation')
                ->leftJoin('user_job_details as jd', 'u.id', '=', 'jd.user_id')
                ->whereIn('u.id', function ($q) use ($authUser) {
                    $q->select('user_id')->from('user_reporting_heads')->where('reporting_head_id', $authUser->id);
                })
                ->orderBy('u.name')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Reportees regularization requests fetched successfully',
                'data' => $formattedRequests,
                'reportees' => $reportees,
                'links' => [
                    'first' => $regularizations->url(1),
                    'last' => $regularizations->url($regularizations->lastPage()),
                    'prev' => $regularizations->previousPageUrl(),
                    'next' => $regularizations->nextPageUrl(),
                ]
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred. Please try again later.'
            ], 500);
        }
    }

    public function regularizationApproval(Request $request)
    {
        try {
            $authUser = Auth::user();
            $tenantId = $authUser->tenant_id;

            if (!app(RbacService::class)->can($authUser, 'attendance', 'approve')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access. Only managers, admins can approve requests.'
                ], 200);
            }

            $validator = Validator::make($request->all(), [
                'id' => 'required|exists:attendance_regularizations,id',
                'status' => 'required|string|in:approved,rejected',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => $validator->errors()->first()
                ], 200);
            }

            $regularizationId = $request->id;

            // Tenant-scoped lookup — never trust a bare id.
            $regularization = DB::table('attendance_regularizations as ar')
                ->select([
                    'ar.*',
                    'u.id as user_id',
                    'u.name as user_name',
                    'u.email as user_email',
                    'jd.reporting_head',
                    'rh.name as reporting_head_name'
                ])
                ->join('users as u', 'ar.user_id', '=', 'u.id')
                ->leftJoin('user_job_details as jd', 'u.id', '=', 'jd.user_id')
                ->leftJoin('users as rh', 'jd.reporting_head', '=', 'rh.id')
                ->where('ar.id', $regularizationId)
                ->where('ar.tenant_id', $tenantId)
                ->where('u.tenant_id', $tenantId)
                ->first();

            if (!$regularization) {
                return response()->json([
                    'success' => false,
                    'message' => 'Regularization request not found.'
                ], 200);
            }

            // Admin / HR may process any request in their tenant; a manager may
            // only process their own reportees'.
            if (!$this->scopeCoversOwner($authUser, 'attendance', 'approve', (int) $regularization->user_id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not authorized to approve/reject this request. Only the reporting head can process it.'
                ], 200);
            }

            if ($regularization->status != 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot update a ' . $regularization->status . ' Attendance Regularizations. Please contact admin if needed.'
                ], 200);
            }

            // Reject a future-dated approval BEFORE any write.
            $regularizationDate = Carbon::parse($regularization->date);
            if ($request->status == 'approved' && $regularizationDate->isFuture()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot create attendance for future date.'
                ], 200);
            }

            $model = AttendanceRegularization::withoutGlobalScopes()
                ->where('id', $regularizationId)->where('tenant_id', $tenantId)->first();

            // Tier 2 / T2-A — route through the approval workflow when the tenant
            // has one; returns null → legacy single-approver path below.
            try {
                $ar = app(\App\Services\Approvals\ApprovalService::class)
                    ->decide('regularization', $model, $authUser, $request->status, $request->input('remarks'));
                if ($ar !== null) {
                    return response()->json([
                        'success' => true,
                        'message' => $ar->status === 'pending'
                            ? 'Recorded. Awaiting the next approval level.'
                            : 'Attendance Regularizations status updated successfully.',
                    ], 200);
                }
            } catch (\RuntimeException $e) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 200);
            }

            DB::beginTransaction();
            try {
                $model->status = $request->status;
                $model->approved_by = $authUser->id;
                $model->approved_date = now();
                $model->save();

                if ($request->status == 'approved') {
                    // Complete + audited + refreshed via the write funnel.
                    app(\App\Services\Attendance\AttendanceEntryService::class)
                        ->applyRegularization($model, $authUser);
                }

                DB::commit();
            } catch (\Throwable $e) {
                DB::rollBack();
                throw $e;
            }

            // Parity with the web approval path.
            try {
                $notifier = app(\App\Services\AttendanceRegularizationNotificationService::class);
                if ($request->status == 'approved') {
                    $notifier->notifyRegularizationApproved($model);
                } else {
                    $notifier->notifyRegularizationRejected($model);
                }
            } catch (\Throwable $e) {
                Log::error('Regularization notification failed: ' . $e->getMessage());
            }

            return response()->json([
                'success' => true,
                'message' => $request->status == 'rejected'
                    ? 'Regularization request rejected successfully.'
                    : 'Attendance Regularizations status updated successfully.',
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred. Please try again later.'
            ], 500);
        }
    }
}