<?php

namespace App\Http\Controllers\Attendance;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Attendance;
use App\Models\AttendanceTrackingPoint;
use App\Models\Leave;
use App\Models\Holiday;
use App\Models\UserWeekoffs;
use Carbon\carbon;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class AttendanceContoller extends Controller
{
    public function index(Request $request)
    {
        try {
            $startDate = $request->start_date ?? date('Y-m-d', strtotime(date('Y') . '-01-01'));
            $endDate = $request->end_date ?? date('Y-m-d', strtotime(date('Y') . '-12-31'));
            $userId = Auth::id();
            $departmentId = $request->department_id;
            $teamId = $request->team_id;

            // ✅ FIXED: Get tenant_id from session or Auth user
            $tenantId = session('tenant_id') ?? Auth::user()->tenant_id ?? 1;

            // Validate dates
            if (strtotime($startDate) > strtotime($endDate)) {
                $temp = $startDate;
                $startDate = $endDate;
                $endDate = $temp;
            }

            // Get attendance data - ✅ FIXED: Pass correct number of parameters
            $data = $this->getAttendanceData($startDate, $endDate, $userId, $tenantId, $departmentId, $teamId);

            // Process data for calendar and summary
            $calendarData = [];
            $summary = $this->initializeSummary();

            foreach ($data as $record) {
                $events = $this->createCalendarEvents($record);
                $calendarData = array_merge($calendarData, $events);
                $this->updateSummary($summary, $record);
            }

            return view('client.attendance.attendance', [
                'attendanceData' => $data,
                'calendarData' => $calendarData,
                'summary' => $summary,
                'startDate' => $startDate,
                'endDate' => $endDate,
                'filters' => [
                    'department_id' => $departmentId,
                    'team_id' => $teamId
                ],
                'totalDays' => count($data)
            ]);
        } catch (\Exception $e) {
            \Log::error('Attendance index error: ' . $e->getMessage(), [
                'user_id' => Auth::id(),
                'request' => $request->all()
            ]);

            return back()->with('error', 'An error occurred while loading attendance data.');
        }
    }

    private function getAttendanceData($startDate, $endDate, $userId, $tenantId, $departmentId = null, $teamId = null)
    {
        try {
            // ✅ FIXED: Get tenant_id properly
            if (!$tenantId) {
                $tenantId = session('tenant_id') ?? Auth::user()->tenant_id ?? 1;
            }

            // Get user data with job details
            $user = User::with(['jobDetails.department', 'jobDetails.designation'])
                ->where('id', $userId)
                ->where('status', 1)
                ->first();

            if (!$user) {
                Log::warning('User not found', ['user_id' => $userId, 'tenant_id' => $tenantId]);
                return collect([]);
            }

            // Generate date range
            $start = Carbon::parse($startDate);
            $end = Carbon::parse($endDate);
            $today = Carbon::today();
            $dateRange = [];

            for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
                $dateRange[] = $date->format('Y-m-d');
            }

            // Get all attendances for this user in date range (only past and today)
            $attendances = Attendance::where('user_id', $userId)
                ->whereBetween('date', [$startDate, $endDate])
                ->get()
                ->keyBy('date');

            // Get all approved leaves overlapping this date range
            // (start <= range end AND end >= range start covers every overlap
            // case, including leaves that began before the range).
            $leaves = Leave::where('user_id', $userId)
                ->where('status', 'approved')
                ->where('start_date', '<=', $endDate)
                ->where('end_date', '>=', $startDate)
                ->get();

            // Get all holidays for this tenant. Not filtered by date range at
            // the SQL level: a handful of real holiday rows have end_date
            // entered before start_date, and an overlap filter using end_date
            // would silently drop those from the result set before the
            // per-day loop below gets a chance to apply its own fallback.
            // The holiday table is small (tenant-scoped, a few dozen rows at
            // most), so fetching all of them and filtering in PHP is cheap.
            $holidays = Holiday::where('tenant_id', $tenantId)->get();

            // Get all weekoffs for this user
            $weekoffs = UserWeekoffs::where('user_id', $userId)
                ->where('status', 1)
                ->where('tenant_id', $tenantId)
                ->where(function ($q) use ($startDate, $endDate) {
                    $q->whereBetween('start_date', [$startDate, $endDate])
                        ->orWhereBetween('end_date', [$startDate, $endDate])
                        ->orWhere(function ($q2) use ($startDate, $endDate) {
                            $q2->where('start_date', '<=', $startDate)
                                ->where('end_date', '>=', $endDate);
                        });
                })
                ->get();

            // Build results
            $results = [];

            foreach ($dateRange as $dateStr) {
                $currentDate = Carbon::parse($dateStr);
                $isFutureDate = $currentDate->gt($today);
                $isToday = $currentDate->isToday();

                // Check holiday
                $holiday = null;
                foreach ($holidays as $h) {
                    $holidayStart = Carbon::parse($h->start_date);
                    $holidayEnd = Carbon::parse($h->end_date);
                    // Guard against a mis-entered end_date before start_date
                    // (seen in real data) so a bad record degrades to a
                    // single-day holiday instead of vanishing entirely.
                    if ($holidayEnd->lt($holidayStart)) {
                        $holidayEnd = $holidayStart;
                    }
                    if ($currentDate->between($holidayStart, $holidayEnd)) {
                        $holiday = $h;
                        break;
                    }
                }

                // Check weekoff
                $weekoff = null;
                foreach ($weekoffs as $w) {
                    $weekoffStart = Carbon::parse($w->start_date);
                    $weekoffEnd = Carbon::parse($w->end_date);

                    if ($currentDate->between($weekoffStart, $weekoffEnd)) {
                        if ($w->off_type == 'date_based') {
                            $weekoff = $w;
                            break;
                        } elseif ($w->off_type == 'day_based' && $w->day_name == $currentDate->format('l')) {
                            $weekoff = $w;
                            break;
                        }
                    }
                }

                // Check leave
                $leave = null;
                foreach ($leaves as $l) {
                    $leaveStart = Carbon::parse($l->start_date);
                    $leaveEnd = Carbon::parse($l->end_date);
                    if ($currentDate->between($leaveStart, $leaveEnd)) {
                        $leave = $l;
                        break;
                    }
                }

                // Get attendance (only for past and today)
                $attendance = !$isFutureDate ? $attendances->get($dateStr) : null;

                // Determine statuses
                $primaryStatus = null;
                $secondaryStatus = null;
                $fallbackStatus = null;
                $isUpcoming = false;

                if ($isFutureDate) {
                    // For future dates - check holiday, weekoff, leave first
                    if ($holiday) {
                        $secondaryStatus = 'Holiday';
                        // ✅ Holiday - NOT marked as upcoming
                    } elseif ($weekoff) {
                        $secondaryStatus = 'Week Off';
                        // ✅ Week Off - NOT marked as upcoming
                    } elseif ($leave) {
                        // Check if it's half day or full day
                        $startSession = $leave->start_session;
                        $endSession = $leave->end_session;

                        if ($startSession == 'session1' && $endSession == 'session1') {
                            $primaryStatus = 'First Half Leave';
                        } elseif ($startSession == 'session2' && $endSession == 'session2') {
                            $primaryStatus = 'Second Half Leave';
                        } else {
                            $primaryStatus = 'Full Day Leave';
                        }
                        // ✅ Leave - NOT marked as upcoming
                    } else {
                        // Only regular future days with no events show "Upcoming"
                        $primaryStatus = 'Upcoming';
                        $isUpcoming = true;
                    }
                } else {
                    // Handle past and today dates (existing logic)
                    $manualSecondaryStatus = null;

                    if ($attendance) {
                        if ($attendance->clock_in && $attendance->clock_out) {
                            $primaryStatus = 'Present';
                        } elseif ($attendance->clock_in) {
                            $primaryStatus = 'Checked In Only';
                        } else {
                            // No punches at all — this row exists only because
                            // it was marked by hand (Team screen manual
                            // marking / AttendanceStatus enum). Fall back to
                            // that persisted status instead of leaving the
                            // day with no status at all.
                            [$manualPrimary, $manualSecondaryStatus, $manualFallback] =
                                $this->mapManualAttendanceStatus($attendance);
                            $primaryStatus = $manualPrimary;
                            $fallbackStatus = $manualFallback;
                        }
                    } elseif ($leave) {
                        $startSession = $leave->start_session;
                        $endSession = $leave->end_session;

                        if ($startSession == 'session1' && $endSession == 'session1') {
                            $primaryStatus = 'First Half Leave';
                        } elseif ($startSession == 'session2' && $endSession == 'session2') {
                            $primaryStatus = 'Second Half Leave';
                        } else {
                            $primaryStatus = 'Full Day Leave';
                        }
                    }

                    if ($holiday) {
                        $secondaryStatus = 'Holiday';
                    } elseif ($weekoff) {
                        $secondaryStatus = 'Week Off';
                    } elseif ($manualSecondaryStatus) {
                        $secondaryStatus = $manualSecondaryStatus;
                    }

                    if (!$attendance && !$leave && !$holiday && !$weekoff) {
                        $fallbackStatus = 'Absent';
                    }
                }

                // Create record object
                $record = new \stdClass();
                $record->user_id = $userId;
                $record->name = $user->name;
                $record->email = $user->email;
                $record->employee_id = $user->employee_id;
                $record->designation = $user->jobDetails->designation->name ?? null;
                $record->department = $user->jobDetails->department->name ?? null;
                $record->profile_image = $user->basicDetails->profile_image ?? null;
                $record->date = $dateStr;
                $record->formatted_date = $dateStr;
                $record->day_name = $currentDate->format('l');
                $record->clock_in = $attendance ? $attendance->clock_in : null;
                $record->clock_out = $attendance ? $attendance->clock_out : null;
                $record->total_hours = $attendance ? $attendance->total_hours : null;
                $record->leave_type = $leave ? $leave->leave_type : null;
                $record->leave_reason = $leave ? $leave->reason : null;
                $record->leave_session = $leave ? $leave->start_session : null;
                $record->holiday_name = $holiday ? $holiday->name : null;
                $record->has_holiday = $holiday ? 1 : 0;
                $record->off_type = $weekoff ? $weekoff->off_type : null;
                $record->weekoff_day = $weekoff ? $weekoff->day_name : null;
                $record->weekoff_start_date = $weekoff ? $weekoff->start_date : null;
                $record->weekoff_end_date = $weekoff ? $weekoff->end_date : null;
                $record->has_weekoff = $weekoff ? 1 : 0;
                $record->primary_status = $primaryStatus;
                $record->secondary_status = $secondaryStatus;
                $record->fallback_status = $fallbackStatus;
                $record->is_upcoming = $isUpcoming;
                $record->is_future = $isFutureDate;

                $taskCount = DB::selectOne("
                SELECT COUNT(DISTINCT t.id) as count
                FROM tasks t
                INNER JOIN task_assigns ta ON t.id = ta.task_id
                WHERE ta.assigned_to = ?
                    AND t.task_date <= ?
                    AND t.deadline_date >= ?
                  
            ", [$userId, $dateStr, $dateStr]);

              $record->task_count = $taskCount ? (int)$taskCount->count : 0;

                // Set overall status for easy access
                if ($primaryStatus) {
                    $record->status = $primaryStatus;
                } elseif ($secondaryStatus) {
                    $record->status = $secondaryStatus;
                } else {
                    $record->status = $fallbackStatus ?? 'Unknown';
                }

                // Set punch in/out for display
                $record->punch_in = $attendance ? $attendance->clock_in : null;
                $record->punch_out = $attendance ? $attendance->clock_out : null;

                $results[] = $record;
            }

            // Sort by date descending
            usort($results, function ($a, $b) {
                return strtotime($b->date) - strtotime($a->date);
            });

            Log::info('Attendance data fetched', [
                'user_id' => $userId,
                'date_range' => [$startDate, $endDate],
                'total_records' => count($results),
                'tenant_id' => $tenantId
            ]);

            return collect($results);
        } catch (Exception $e) {
            Log::error('Error in getAttendanceData: ' . $e->getMessage(), [
                'user_id' => $userId,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'trace' => $e->getTraceAsString()
            ]);
            return collect([]);
        }
    }

    /**
     * A row with no clock_in/clock_out only exists because it was marked by
     * hand (Team screen manual marking, see App\Enums\AttendanceStatus) — the
     * clock-time check above has nothing to go on, so read the persisted
     * status directly instead of leaving the day with no status at all.
     *
     * @return array{0: ?string, 1: ?string, 2: ?string} [primary, secondary, fallback]
     */
    private function mapManualAttendanceStatus($attendance): array
    {
        $status = $attendance->effective_status ?: $attendance->attendance_status;

        return match ($status) {
            'present', 'late', 'overtime', 'early_departure' => ['Present', null, null],
            'half_day' => ['Half Day', null, null],
            'on_leave' => ['Full Day Leave', null, null],
            'first_half_leave' => ['First Half Leave', null, null],
            'second_half_leave' => ['Second Half Leave', null, null],
            'holiday' => [null, 'Holiday', null],
            'weekoff' => [null, 'Week Off', null],
            'absent' => [null, null, 'Absent'],
            // Unrecognized/blank persisted status — still resolve to
            // something rather than silently showing no status at all.
            default => [null, null, 'Absent'],
        };
    }

    private function initializeSummary()
    {
        return [
            'present' => 0,
            'absent' => 0,
            'leave' => 0,
            'holiday' => 0,
            'week_off' => 0,
            'checked_in_only' => 0,
            'total' => 0
        ];
    }

    private function updateSummary(&$summary, $record)
    {
        $summary['total']++;

        // Update based on primary status
        if ($record->primary_status) {
            switch ($record->primary_status) {
                case 'Present':
                    $summary['present']++;
                    break;
                case 'Checked In Only':
                    $summary['checked_in_only']++;
                    $summary['present']++;
                    break;
                case 'Half Day':
                    $summary['present']++;
                    break;
                case 'First Half Leave':
                case 'Second Half Leave':
                case 'Full Day Leave':
                    $summary['leave']++;
                    break;
            }
        }

        // Update based on secondary status
        if ($record->secondary_status) {
            switch ($record->secondary_status) {
                case 'Holiday':
                    $summary['holiday']++;
                    break;
                case 'Week Off':
                    $summary['week_off']++;
                    break;
            }
        }

        // Update based on fallback status
        if ($record->fallback_status === 'Absent') {
            $summary['absent']++;
        }
    }

    private function createCalendarEvents($record)
    {
        $events = [];

        // Create event for primary status
        if ($record->primary_status) {
            $events[] = $this->createEventForStatus($record, $record->primary_status, true);
        }

        // Create event for secondary status
        if ($record->secondary_status) {
            $events[] = $this->createEventForStatus($record, $record->secondary_status, false);
        }

        // Create event for fallback status
        if ($record->fallback_status) {
            $events[] = $this->createEventForStatus($record, $record->fallback_status, true);
        }

        return $events;
    }

    private function createEventForStatus($record, $status, $isPrimary = true)
    {
        $title = $this->getEventTitleForStatus($record, $status);
        $color = $this->getEventColor($status);

        $eventId = 'att_' . $record->formatted_date . '_' . strtolower(str_replace(' ', '_', $status));

        return [
            'id' => $eventId,
            'title' => $title,
            'start' => $record->formatted_date,
            'end' => $record->formatted_date,
            'day_status' => $status,
            'status_type' => $isPrimary ? 'primary' : 'secondary',
            'bgColor' => $color,
            'color' => '#ffffff',
            'clock_in' => $record->clock_in,
            'clock_out' => $record->clock_out,
            'total_hours' => $record->total_hours,
            'leave_type' => $record->leave_type,
            'holiday_name' => $record->holiday_name,
            'leave_reason' => $record->leave_reason,
            'weekoff_type' => $record->off_type ?? null,
            'weekoff_day' => $record->weekoff_day ?? null,
            'has_holiday' => $record->has_holiday ?? false,
            'has_weekoff' => $record->has_weekoff ?? false,
             'task_count' => (int)($record->task_count ?? 0),
            'isAllday' => true,
            'borderColor' => $color,
            'dragBgColor' => $color,
            'className' => $isPrimary ? 'primary-status' : 'secondary-status'
        ];
    }

    private function getEventTitleForStatus($record, $status)
    {
        switch ($status) {
            case 'Present':
                return "Present";
            case 'Holiday':
                return $record->holiday_name ?: 'Holiday';
            case 'First Half Leave':
                return "First Half - " . ($record->leave_type ?: 'Leave');
            case 'Second Half Leave':
                return "Second Half - " . ($record->leave_type ?: 'Leave');
            case 'Full Day Leave':
                return "Leave - " . ($record->leave_type ?: 'Leave');
            case 'Week Off':
                $title = "Week Off";
                if ($record->off_type == 'day_based' && $record->weekoff_day) {
                    $title .= " (" . $record->weekoff_day . ")";
                }
                return $title;
            case 'Checked In Only':
                return "Checked In";
            case 'Absent':
                return "Absent";
            default:
                return $status;
        }
    }

    private function getEventColor($status)
    {
        // Single-blue theme: every status is a shade of the app's primary
        // blue (--primary #0D6EFD / --primary-mid #0D6EFD) instead of the
        // usual green/red/amber semantic colors, matching the rest of the app.
        $colorMap = [
            'Absent' => '#172554',
            'Present' => '#0D6EFD',
            'Checked In Only' => '#0D6EFD',
            'Half Day' => '#0D6EFD',
            'Holiday' => '#0B5ED7',
            'First Half Leave' => '#0D6EFD',
            'Second Half Leave' => '#0D6EFD',
            'Full Day Leave' => '#0D6EFD',
            'Week Off' => '#3b82f6',
            'Upcoming' => '#60a5fa',
        ];

        return $colorMap[$status] ?? '#0D6EFD';
    }

    public function calendarData(Request $request)
    {
        try {
            // ✅ FIXED: Use the same date range logic as index method
            $startDate = $request->start_date ?? date('Y-m-d', strtotime(date('Y') . '-01-01'));;
            $endDate = $request->end_date ?? date('Y-m-d', strtotime(date('Y') . '-12-31'));

            $userId = Auth::id();
            $departmentId = $request->department_id;
            $teamId = $request->team_id;
            $tenantId = session('tenant_id') ?? Auth::user()->tenant_id ?? 1;

            // Validate dates
            if (strtotime($startDate) > strtotime($endDate)) {
                return response()->json([
                    'status' => false,
                    'message' => 'Start date cannot be after end date'
                ], 400);
            }

    
            // Get data
            $data = $this->getAttendanceData($startDate, $endDate, $userId, $tenantId, $departmentId, $teamId);

            $calendarEvents = [];
            foreach ($data as $record) {
                $events = $this->createCalendarEvents($record);
                $calendarEvents = array_merge($calendarEvents, $events);
            }

            return response()->json([
                'status' => true,
                'events' => $calendarEvents,
                'total' => count($calendarEvents),
                'date_range' => [
                    'start' => $startDate,
                    'end' => $endDate
                ]
            ]);
        } catch (Exception $e) {
         
            return response()->json([
                'status' => false,
                'message' => 'An error occurred while fetching calendar data: ' . $e->getMessage()
            ], 500);
        }
    }
    public function showAttendanceSessions(Request $request)
    {

        try {
            $validator = Validator::make($request->all(), [
                'date' => 'required|date',
                // 'user_id' => 'nullable|exists:users,id',
            ]);

            if ($validator->fails()) {
                return redirect()->back()->with('error', $validator->errors()->first());
            }

            // Determine which user to fetch
            if ($request->has('user_id') && $request->user_id) {
                $user_id = decrypt($request->user_id);
                $user = User::find($user_id);

                if (!$user) {
                    return redirect()->back()->with('error', 'User not found');
                }
            } else {
                $user = Auth::user();
            }

            if (!$user) {
                return redirect()->back()->with('error', 'User not authenticated');
            }

            $date = $request->date;
            $date1 = date('Y-m-d');

            // Get tasks for the user on this date
            $tasks = DB::select("
            SELECT DISTINCT
                t.id,
                t.task_code,
                t.title,
                t.description,
                t.priority,
                t.status,
                t.deadline_date,
                t.task_date,
                t.created_at,
                p.name as project_name,
                p.project_code,
                CASE 
                    WHEN t.deadline_date = :deadline_equal THEN 'Due Today'
                    WHEN t.deadline_date < :deadline_less THEN 'Overdue'
                    ELSE 'In Progress'
                END as deadline_status,
                CASE
                    WHEN t.deadline_date < :deadline_less2 THEN ABS(DATEDIFF(t.deadline_date, :deadline_diff))
                    ELSE DATEDIFF(t.deadline_date, :deadline_diff2)
                END as days_remaining
            FROM tasks t
            INNER JOIN task_assigns ta ON t.id = ta.task_id
            LEFT JOIN projects p ON t.project_id = p.id
            WHERE ta.assigned_to = :user_id
                AND t.task_date <= :task_date_val
                AND t.deadline_date >= :deadline_date_val
            ORDER BY 
                FIELD(t.priority, 'critical', 'high', 'medium', 'low'),
                t.deadline_date ASC
        ", [
                'user_id' => $user->id,
                'deadline_equal' => $date1,
                'deadline_less' => $date1,
                'deadline_less2' => $date1,
                'deadline_diff' => $date1,
                'deadline_diff2' => $date1,
                'task_date_val' => $date,
                'deadline_date_val' => $date
            ]);

            // Get task counts
            $taskCounts = [
                'total' => count($tasks),
                'critical' => 0,
                'high' => 0,
                'medium' => 0,
                'low' => 0,
                'pending' => 0,
                'in_progress' => 0,
                'due_today' => 0,
                'overdue' => 0
            ];

            foreach ($tasks as $task) {
                if (isset($taskCounts[$task->priority])) {
                    $taskCounts[$task->priority]++;
                }
                if ($task->status == 'pending') {
                    $taskCounts['pending']++;
                } elseif ($task->status == 'in_progress') {
                    $taskCounts['in_progress']++;
                }
                if ($task->deadline_status == 'Due Today') {
                    $taskCounts['due_today']++;
                }
                if ($task->deadline_status == 'Overdue') {
                    $taskCounts['overdue']++;
                }
            }

            // Get attendance for that date
            $attendance = Attendance::where('user_id', $user->id)
                ->whereDate('date', $date)
                ->first();

            if (!$attendance) {
                return view('client.attendance.sessions', [
                    'date' => $date,
                    'user' => $user,
                    'attendance' => null,
                    'locationTracks' => [],
                    'statistics' => null,
                    'tasks' => $tasks,
                    'taskCounts' => $taskCounts,
                    'hasAttendance' => false,
                    'hasTracks' => false,
                    'message' => 'No attendance record found for this date'
                ]);
            }

            // Get location tracks
            $locationTracks = AttendanceTrackingPoint::forAttendanceId($attendance->id, $date)
                ->get()
                ->map(function ($track) {
                    return [
                        'id' => $track->id,
                        'track_time' => $track->track_time?->format('Y-m-d H:i:s'),
                        'latitude' => $track->lat,
                        'longitude' => $track->long,
                        'address' => $track->address,
                        'battery_per' => $track->battery_per,
                    ];
                });

            if ($locationTracks->isEmpty()) {
                return view('client.attendance.sessions', [
                    'date' => $date,
                    'user' => $user,
                    'attendance' => $attendance,
                    'locationTracks' => [],
                    'statistics' => [
                        'total_tracks' => 0,
                        'tracking_duration' => 0
                    ],
                    'tasks' => $tasks,
                    'taskCounts' => $taskCounts,
                    'hasAttendance' => true,
                    'hasTracks' => false,
                    'message' => 'No location tracks found for this date'
                ]);
            }

            // Calculate statistics
            $firstTrack = $locationTracks->first();
            $lastTrack = $locationTracks->last();

            $firstTime = Carbon::parse($firstTrack['track_time']);
            $lastTime = Carbon::parse($lastTrack['track_time']);
            $totalMinutes = $firstTime->diffInMinutes($lastTime);

            $statistics = [
                'total_tracks' => $locationTracks->count(),
                'first_track_time' => $firstTrack['track_time'],
                'last_track_time' => $lastTrack['track_time'],
                'tracking_duration_hours' => round($totalMinutes / 60, 2),
                'tracking_duration_minutes' => $totalMinutes,
            ];

            return view('client.attendance.sessions', [
                'date' => $date,
                'user' => $user,
                'attendance' => $attendance,
                'locationTracks' => $locationTracks,
                'statistics' => $statistics,
                'tasks' => $tasks,
                'taskCounts' => $taskCounts,
                'hasAttendance' => true,
                'hasTracks' => true,
                'message' => null
            ]);
        } catch (Exception $e) {
            Log::error('Error in viewAttendanceSessions: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to load attendance sessions. Please try again.');
        }
    }
}
