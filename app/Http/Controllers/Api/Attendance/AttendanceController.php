<?php

namespace App\Http\Controllers\Api\Attendance;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\AttendanceLog;
use App\Models\Branch;
use App\Models\AttendanceTrack;
use App\Models\AttendanceRegularization;
use App\Models\Shift;
use App\Models\UserShift;
use App\Models\UserWeekoffs;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Request as RequestStore;
use App\Services\AttendanceNotificationService;
use Illuminate\Support\Facades\Log;

class AttendanceController extends Controller
{
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

    // Rest of the methods remain exactly the same...
    // getAttendance(), clockIn(), clockOut(), trackLocation(), todayLocations(),
    // regularizationStore(), getMyRegularizations(), getReporteesRegularizations(),
    // regularizationApproval(), calculateDistance(), getUserShiftForDate(), 
    // isWeekoffForUser(), determineDayStatus(), createSuccessLog(), createFailureLog()

    // The only changes are:
    // 1. Added getAttendanceStatusByShift() method at the top
    // 2. Updated SQL queries in index() and history() to use shift-based logic
    // 3. Added a.scheduled_shift_start, a.scheduled_shift_end, a.worked_hours to SELECT

    public function getAttendance()
    {
        try {
            $userId = Auth::id();
            $today = date('Y-m-d');
            $baseUrl = env('APP_URL');
    
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
                $userShift = DB::table('user_shifts')
                    ->join('shifts', 'user_shifts.shift_id', '=', 'shifts.id')
                    ->where('user_shifts.user_id', $userId)
                    ->whereDate('user_shifts.date', $today)
                    ->select('shifts.name', 'shifts.start_time', 'shifts.end_time')
                    ->first();
    
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
    
           
            // NIGHT SHIFT FIX: Determine correct attendance date
            $attendanceDate = $currentDate;
            if ($shift) {
                $shiftStartTime = Carbon::parse($shift->start_time);
                $shiftEndTime = Carbon::parse($shift->end_time);
            
                // Check if night shift (end time < start time or ends before 4 AM)
                $isNightShift = $shiftEndTime->format('H:i') < $shiftStartTime->format('H:i') ||
                    ($shiftEndTime->format('H:i') <= '04:00' && $shiftStartTime->format('H:i') >= '20:00');
            
                // For night shifts clocking in after midnight, use previous day
                if ($isNightShift) {
                    $currentHour = (int)$currentTime->format('H');
                    $shiftStartHour = (int)$shiftStartTime->format('H');
                    
                    // Only use previous day if clocking in after midnight (00:00 - 05:59)
                    // This prevents early clock-ins (like 19:55 for 20:00 shift) from using previous day
                    if ($currentHour >= 0 && $currentHour < 6 && $currentHour < $shiftStartHour) {
                        $attendanceDate = Carbon::parse($currentDate)->subDay()->format('Y-m-d');
                    }
                }
            }
    
            // NIGHT SHIFT FIX: Check for existing active attendance
            $existing = Attendance::where('user_id', $userId)
                ->where('date', $attendanceDate)
                ->whereNull('clock_out')
                ->first();
    
            if ($existing && $existing->clock_in != null) {
                $this->createFailureLog($userId, 'check_in', 'already_clocked_in', [
                    'existing_attendance_id' => $existing->id,
                    'existing_clock_in' => $existing->clock_in,
                    'attendance_date' => $attendanceDate
                ], $request);
    
                return response()->json([
                    'status' => false,
                    'message' => 'You have already checked in for this shift.'
                ], 200);
            }
    
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
                    $allBranches = Branch::where('status', 1)->get();
    
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
                    $branch = Branch::find($officeBranch);
    
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
    
                    if (!$branch->latitude || !$branch->longitude) {
                        $this->createFailureLog($userId, 'check_in', 'branch_coordinates_missing', [
                            'branch_id' => $branch->id,
                            'branch_name' => $branch->name
                        ], $request);
    
                        return response()->json([
                            'status' => false,
                            'message' => 'Branch location coordinates not configured. Please contact admin.'
                        ], 200);
                    }
    
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
            } else {
                $checkInDistance = null;
                $locationVerification = 'verified';
            }
    
            // Determine branch_id for attendance
            $branchId = $branch ? $branch->id : null;
            // =============================================
            // END OF CHANGED: ENHANCED BRANCH HANDLING
            // =============================================
    
            $lateMinutes = 0;
            $attendanceStatus = 'present';
    
            if ($shift) {
                $scheduledStart = Carbon::parse($attendanceDate . ' ' . $shift->start_time);
                $graceMinutes = $shift->grace_minutes ?? 0;
                $minutesAfterShift = $scheduledStart->diffInMinutes($currentTime, false);
    
                if ($currentTime->gt($scheduledStart)) {
                    if ($minutesAfterShift > $graceMinutes) {
                        $lateMinutes = $minutesAfterShift;
                        $attendanceStatus = 'late';
                    } else {
                        $lateMinutes = 0;
                        $attendanceStatus = 'present';
                    }
                } else {
                    $lateMinutes = 0;
                    $attendanceStatus = 'present';
                }
            }
    
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
    
            DB::beginTransaction();
    
            try {
                $attendance = Attendance::create([
                    'user_id' => $userId,
                    'shift_id' => $shift->id ?? null,
                    'branch_id' => $branchId,
                    'date' => $attendanceDate,
                    'scheduled_shift_start' => $shift->start_time ?? null,
                    'scheduled_shift_end' => $shift->end_time ?? null,
                    'clock_in' => $currentDateTime,
                    'clock_in_lat' => $request->lat,
                    'clock_in_long' => $request->long,
                    'clock_in_address' => $request->address,
                    'check_in_distance' => $checkInDistance,
                    'location_verification' => $locationVerification,
                    'device_id' => $request->device_id,
                    'ip_address' => $request->ip(),
                    'wifi_ssid' => $request->wifi_ssid,
                    'late_minutes' => $lateMinutes,
                    'attendance_status' => $attendanceStatus,
                    'status' => 1
                ]);
    
                $this->createSuccessLog(
                    $userId,
                    $attendance->id,
                    'check_in',
                    $request,
                    $checkInDistance
                );
    
                AttendanceTrack::create([
                    'attendance_id' => $attendance->id,
                    'user_id' => $userId,
                    'track_time' => $currentDateTime,
                    'lat' => $request->lat,
                    'long' => $request->long,
                    'address' => $request->address,
                    'battery_per' => $request->battery_per
                ]);
    
                DB::commit();
    
                try {
                    $this->notificationService->notifyClockIn($attendance, $user);
                } catch (Exception $e) {
                    Log::error('Clock-in notification failed: ' . $e->getMessage());
                }
    
                return response()->json([
                    'status' => true,
                    'message' => 'Clock-In successful.',
                ], 200);
            } catch (Exception $e) {
                DB::rollBack();
                return response()->json([
                    'status' => false,
                    'message' => 'Clock-in failed: ' . $e->getMessage()
                ], 500);
            }
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
                    $allBranches = Branch::where('status', 1)->get();
    
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
                    $branch = Branch::find($officeBranch);
    
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
    
                    if (!$branch->latitude || !$branch->longitude) {
                        $this->createFailureLog($userId, 'check_out', 'branch_coordinates_missing', [
                            'branch_id' => $branch->id,
                            'branch_name' => $branch->name
                        ], $request);
    
                        return response()->json([
                            'status' => false,
                            'message' => 'Branch location coordinates not configured. Please contact admin.'
                        ], 200);
                    }
    
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
            } else {
                $checkOutDistance = null;
                $locationVerification = 'verified';
            }
            // =============================================
            // END OF CHANGED: ENHANCED BRANCH HANDLING
            // =============================================
    
            // FIX: Properly handle night shifts crossing midnight
            $clockIn = Carbon::parse($attendance->clock_in);
            $clockOut = $currentDateTime;
            
            // If clock_out time is less than clock_in time, it means it crossed midnight
            // Add a day to clock_out for correct calculation
            if ($clockOut->lt($clockIn)) {
                $clockOut->addDay();
            }
            
            $workedSeconds = $clockIn->diffInSeconds($clockOut);
            $workedHours = round($workedSeconds / 3600, 2);
            
            // Format total hours as H:i:s (e.g., "21:25:00" for 21 hours 25 minutes)
            $totalHoursFormatted = floor($workedSeconds / 3600) . ':' . 
                                   floor(($workedSeconds % 3600) / 60) . ':' . 
                                   ($workedSeconds % 60);
    
            $earlyDepartureMinutes = 0;
            $overtimeMinutes = 0;
            $attendanceStatus = $attendance->attendance_status;
    
            if ($attendance->shift_id) {
                $shift = Shift::find($attendance->shift_id);
                if ($shift) {
                    $attendanceDate = $attendance->date;
                    $scheduledEnd = Carbon::parse($attendanceDate . ' ' . $shift->end_time);
    
                    // NIGHT SHIFT FIX: Add a day to scheduled end for night shifts
                    $scheduledStart = Carbon::parse($attendanceDate . ' ' . $shift->start_time);
                    if (Carbon::parse($shift->end_time)->format('H:i') < $scheduledStart->format('H:i')) {
                        $scheduledEnd->addDay();
                    }
    
                    $graceMinutes = $shift->grace_minutes ?? 0;
    
                    if ($clockOut->lt($scheduledEnd)) {
                        $minutesEarly = $clockOut->diffInMinutes($scheduledEnd);
                        if ($minutesEarly > $graceMinutes) {
                            $earlyDepartureMinutes = $minutesEarly;
                            $attendanceStatus = 'early_departure';
                        }
                    } elseif ($clockOut->gt($scheduledEnd)) {
                        $overtimeMinutes = $scheduledEnd->diffInMinutes($clockOut);
                        $attendanceStatus = 'overtime';
                    }
                }
            }
    
            // Update user_shift status
            $userShift = UserShift::where('user_id', $userId)
                ->where('date', $attendance->date)
                ->first();
    
            if ($userShift) {
                $userShift->status = 'complete';
                $userShift->save();
            }
    
            DB::beginTransaction();
    
            try {
                $attendance->clock_out = $currentDateTime;
                $attendance->clock_out_lat = $request->lat;
                $attendance->clock_out_long = $request->long;
                $attendance->clock_out_address = $request->address;
                $attendance->check_out_distance = $checkOutDistance ?? null;
                $attendance->location_verification = $locationVerification;
                $attendance->total_hours = gmdate('H:i:s', $workedSeconds);
                $attendance->worked_hours = $workedHours;
                $attendance->early_departure_minutes = $earlyDepartureMinutes;
                $attendance->overtime_minutes = $overtimeMinutes;
                $attendance->attendance_status = $attendanceStatus;
    
                $attendance->save();
    
                $this->createSuccessLog(
                    $userId,
                    $attendance->id,
                    'check_out',
                    $request,
                    $checkOutDistance
                );
    
                AttendanceTrack::create([
                    'attendance_id' => $attendance->id,
                    'user_id' => $userId,
                    'track_time' => $currentDateTime,
                    'lat' => $request->lat,
                    'long' => $request->long,
                    'address' => $request->address,
                    'battery_per' => $request->battery_per
                ]);
    
                DB::commit();
    
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
                        'worked_hours' => $workedHours,
                        'attendance_status' => $attendanceStatus,
                        'shift_status' => 'complete'
                    ]
                ], 200);
            } catch (Exception $e) {
                DB::rollBack();
                Log::error('Clock-out transaction error: ' . $e->getMessage());
                return response()->json([
                    'status' => false,
                    'message' => 'An error occured. Please try again later.'
                ], 500);
            }
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
        $userShift = UserShift::with('shift')
            ->where('user_id', $userId)
            ->where('date', $date)
            ->first();

        if ($userShift && $userShift->shift) {
            return [
                'id' => $userShift->shift->id,
                'user_shift_id' => $userShift->id,
                'name' => $userShift->shift->name,
                'start_time' => $userShift->shift->start_time,
                'end_time' => $userShift->shift->end_time,
                'grace_minutes' => $userShift->shift->grace_minutes ?? 0
            ];
        }

        return null;
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
            $today = date('Y-m-d');

            // NIGHT SHIFT FIX: Look for active attendance from today or yesterday
            $attendance = Attendance::where('user_id', $userId)
                ->where(function ($query) use ($today) {
                    $query->where('date', $today)
                        ->orWhere('date', Carbon::parse($today)->subDay()->format('Y-m-d'));
                })
                ->whereNull('clock_out')
                ->orderBy('date', 'desc')
                ->first();

            if (!$attendance) {
                return response()->json([
                    'status' => false,
                    'message' => 'User has not clocked in for any active shift.'
                ], 200);
            }

            if (!is_null($attendance->clock_out)) {
                return response()->json([
                    'status' => false,
                    'message' => 'You have already checked out for this shift.'
                ], 200);
            }

            AttendanceTrack::create([
                'attendance_id' => $attendance->id,
                'user_id' => $userId,
                'track_time' => now(),
                'lat' => $request->lat,
                'long' => $request->long,
                'battery_per' => $request->battery_per,
                'address' => $request->address
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Location saved successfully.',
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

            $locations = AttendanceTrack::where('attendance_id', $attendance->id)
                ->orderBy('track_time', 'asc')
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
                'date' => 'required|date_format:Y-m-d',
                'request_type' => 'required',
                'in_time' => 'nullable|date_format:H:i',
                'out_time' => 'nullable|date_format:H:i|after:in_time',
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

            AttendanceRegularization::create([
                'user_id' => $authUser->id,
                'date' => $request->date,
                'request_type' => $request->request_type,
                'in_time' => $request->in_time,
                'out_time' => $request->out_time,
                'file' => $filePath,
                'reason' => $request->reason,
                'status' => 'pending'
            ]);

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

            if (!in_array($authUser->role, ['manager', 'admin'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access. Only managers, admins or supervisors can approve requests.'
                ], 200);
            }

            $reporteeIds = DB::table('user_job_details as jd')
                ->join('users as u', 'jd.user_id', '=', 'u.id')
                ->where('jd.reporting_head', $authUser->id)
                ->pluck('u.id')
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
                ->where('jd.reporting_head', $authUser->id)
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
        DB::beginTransaction();
        try {
            $authUser = Auth::user();

            if (!in_array($authUser->role, ['manager', 'admin'])) {
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
                ->first();

            if (!$regularization) {
                return response()->json([
                    'success' => false,
                    'message' => 'Regularization request not found.'
                ], 200);
            }

            if (!$regularization->reporting_head) {
                return response()->json([
                    'success' => false,
                    'message' => 'User does not have a reporting head assigned. Please contact admin.'
                ], 200);
            }

            if ($regularization->reporting_head != $authUser->id) {
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

            $attendanceRegularization = AttendanceRegularization::find($regularizationId);
            $attendanceRegularization->status = $request->status;
            $attendanceRegularization->approved_by = $authUser->id;
            $attendanceRegularization->approved_date = now();
            $attendanceRegularization->save();

            if ($request->status == 'rejected') {
                DB::commit();
                return response()->json([
                    'success' => true,
                    'message' => 'Regularization request rejected successfully.'
                ], 200);
            }

            $regularizationDate = Carbon::parse($regularization->date);

            if ($regularizationDate->isFuture()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot create attendance for future date.'
                ], 200);
            }

            $date = Carbon::parse($regularization->date);

            $attendance = Attendance::firstOrNew([
                'user_id' => $regularization->user_id,
                'date'    => $regularization->date
            ]);

            $attendance->regularization_id = $regularization->id;
            $attendance->status = 1;

            if ($regularization->in_time) {
                $attendance->clock_in = Carbon::parse($date->format('Y-m-d') . ' ' . $regularization->in_time);
            }

            if ($regularization->out_time) {
                $attendance->clock_out = Carbon::parse($date->format('Y-m-d') . ' ' . $regularization->out_time);
            }

            if ($regularization->in_time && $regularization->out_time) {
                $seconds = Carbon::parse($attendance->clock_in)
                    ->diffInSeconds(Carbon::parse($attendance->clock_out));
                $attendance->total_hours = gmdate('H:i:s', $seconds);
            }

            $attendance->save();
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Attendance Regularizations status updated successfully.',
            ], 200);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'An error occurred. Please try again later.'
            ], 500);
        }
    }
}