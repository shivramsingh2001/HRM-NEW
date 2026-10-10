<?php

namespace App\Http\Controllers\Api\Attendance;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Shift;
use App\Models\UserWeekoffs;
use App\Support\ShiftWindow;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Traits\AuthorizesByScope;
use Illuminate\Support\Facades\Log;

class AttendanceController extends Controller
{
    use AuthorizesByScope;
    use \App\Http\Controllers\Api\Attendance\Concerns\MobileAttendanceHelpers;

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
            report($e);
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
                'profile_image' => file_url($user->profile_image, 'profile_photo') ?? $baseUrl . '/profile2.jpg',
                'attendance_type' => $attendanceType,
                'user_email' => $user->email,
                'designation' => $user->designation_name,
                'shift' => $shiftInfo,
            ];

            // Additive: tells the app whether to run the continuous GPS tracker
            // for this employee and at what cadence. No existing key changed.
            $data['location_tracking'] = app(\App\Services\FieldTracking\FieldTrackingService::class)
                ->resolveForUser((int) $userId);

            // Additive (multi-shift): every shift today, with its own status.
            $data['shifts'] = $this->shiftsForDay((int) $userId, (int) ($user->tenant_id ?? 0), $today);

            // Additive: lets the app hide Punch In/Out for biometric-only employees.
            $punchBlocked = $this->mobilePunchBlocked((int) $userId);
            $data['can_mark_attendance'] = ! $punchBlocked;
            $data['attendance_block_reason'] = $punchBlocked ? self::BIOMETRIC_ONLY_MESSAGE : null;

            return response()->json([
                'status' => true,
                'message' => 'Data fetch successfully!!!',
                'data' => $data
            ], 200);
        } catch (Exception $e) {
            report($e);
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
                ->get(['id', 'direction', 'punched_at', 'source', 'method', 'lat', 'long', 'address', 'session_seq', 'user_shift_id']);

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
                        'shift' => $this->punchShiftInfo($s['in_punch']),
                    ])->values(),
                    'shifts' => $this->shiftsForDay($userId, $tenantId, $date),
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

    /**
     * Every shift the user works on $date (primary first) with a per-shift
     * status: upcoming | ongoing | completed. Empty when no shift applies.
     */
    private function shiftsForDay(int $userId, int $tenantId, string $date): array
    {
        if (!$tenantId) {
            return [];
        }

        $segments = \App\Models\AttendanceShiftSegment::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)->where('user_id', $userId)->whereDate('date', $date)
            ->get()
            ->keyBy(fn ($seg) => (string) $seg->user_shift_id);

        return app(\App\Services\Attendance\TenantShiftResolver::class)
            ->instancesForUserDate($userId, $tenantId, $date)
            ->map(function ($i) use ($segments) {
                $seg = $segments->get((string) $i['user_shift_id']);

                return [
                    'user_shift_id' => $i['user_shift_id'],
                    'shift_id' => $i['shift']->id,
                    'name' => $i['shift']->name,
                    'start_time' => $i['start']->format('h:i A'),
                    'end_time' => $i['end']->format('h:i A'),
                    'is_overnight' => \App\Support\ShiftWindow::isOvernight($i['shift']),
                    'is_additional' => $i['is_additional'],
                    'status' => !$seg ? 'upcoming' : ($seg->is_open ? 'ongoing' : 'completed'),
                    'clock_in' => $seg?->first_in?->format('Y-m-d H:i:s'),
                    'clock_out' => $seg?->last_out?->format('Y-m-d H:i:s'),
                    'worked_minutes' => (int) ($seg->worked_minutes ?? 0),
                ];
            })
            ->values()
            ->all();
    }

}