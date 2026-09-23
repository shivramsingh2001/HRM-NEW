<?php

namespace App\Http\Controllers\Team;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Attendance;
use App\Models\AttendanceTrack;
use App\Models\Leave;
use App\Models\Holiday;
use App\Models\UserWeekoffs;
use App\Models\UserJobDetail;
use App\Models\UserBasicDetail;
use App\Models\UserBankDetail;
use App\Models\UserLocation;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Country;
use App\Models\State;
use App\Models\City;
use App\Models\Language;
use DateTime;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Carbon\CarbonPeriod;
use App\Services\RbacService;
use App\Traits\AuthorizesByScope;

class TeamController extends Controller
{
    use \App\Http\Controllers\Concerns\SanitizesCsv;
    use AuthorizesByScope;

    /**
     * Display team members based on user role
     */
    public function team(Request $request)
    {
        try {
            $authUser = Auth::user();
            $currentDate = $request->date ?? Carbon::now()->format('Y-m-d');
            $dayName = Carbon::now()->format('l');

            $statusFilter = $request->get('status');
            $search = $request->get('search');
            $branchId = $request->get('branch_id');

            // Get team members based on user role
            $teamData = $this->getTeamMembers($authUser, $currentDate, null, $search, $branchId);
            if ($statusFilter && in_array($statusFilter, ['present', 'absent', 'on_leave', 'holiday', 'weekoff','halfday','checked_in_only'])) {
                $teamData = $teamData->filter(function ($member) use ($statusFilter) {
                    $memberStatus = strtolower($member->status ?? 'absent');

                    switch ($statusFilter) {
                      
                        case 'present':
                            return in_array($memberStatus, ['present', 'halfday', 'checked in only']);
                        case 'absent':
                            return $memberStatus === 'absent';
                        case 'on_leave':
                            return str_contains($memberStatus, 'leave');
                        case 'holiday':
                            return $memberStatus === 'holiday';
                        case 'weekoff':
                            return $memberStatus === 'week off';
                        case 'halfday':
                            return $memberStatus === 'halfday';
                        case 'checked_in_only':
                            return $memberStatus === 'checked in only';
                        default:
                            return true;
                    }
                })->values();
            }
            // Initialize status count
            $statusCount = [
                'present' => 0,
                'absent' => 0,
                'on_leave' => 0,
                'holiday' => 0,
                'weekoff' => 0,
                'checked_in_only' => 0,
                'halfday' => 0,
                'total' => $teamData->count()
            ];

            // Count statuses
            foreach ($teamData as $member) {
                $status = strtolower($member->status ?? 'absent');

                if ($status === 'present') {
                    $statusCount['present']++;
                } elseif ($status === 'halfday') {
                    $statusCount['halfday']++;
                    $statusCount['present']++;
                } elseif ($status === 'checked in only') {
                    $statusCount['checked_in_only']++;
                    $statusCount['present']++;
                } elseif (str_contains($status, 'leave')) {
                    $statusCount['on_leave']++;
                } elseif ($status === 'holiday') {
                    $statusCount['holiday']++;
                } elseif ($status === 'week off') {
                    $statusCount['weekoff']++;
                } elseif ($status === 'absent') {
                    $statusCount['absent']++;
                }
            }
            $perPage = $request->get('per_page', 20);
            $currentPage = $request->get('page', 1);
            $total = $teamData->count();
            $paginated = $teamData->slice(($currentPage - 1) * $perPage, $perPage);

            $teamDataPaginated = new \Illuminate\Pagination\LengthAwarePaginator(
                $paginated,
                $total,
                $perPage,
                $currentPage,
                ['path' => $request->url(), 'query' => $request->query()]
            );


            // Leave types for the manual "mark attendance" modal (leave statuses)
            $leaveTypes = \App\Models\LeaveType::where('tenant_id', $authUser->tenant_id)
                ->where('status', 1)
                ->orderBy('name')
                ->get(['id', 'name']);

            // Branches dropdown for the branch filter
            $allBranches = DB::table('company_branches')
                ->where('tenant_id', $authUser->tenant_id)
                ->select('id', 'name')
                ->orderBy('name')
                ->get();

            // Prepare data for view
            $data = [
                'teamData' => $teamDataPaginated,
                'statusCount' => $statusCount,
                'currentDate' => $currentDate,
                'dayName' => $dayName,
                'authUser' => $authUser,
                'leaveTypes' => $leaveTypes,
                'allBranches' => $allBranches,
                'search' => $search,
                'branchId' => $branchId,
            ];

            return view('client.team.view-team-member', $data);
        } catch (Exception $e) {
            Log::error('Error in team method: ' . $e->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'An error occurred while fetching team data'
            ], 500);
        }
    }

    /**
     * Get team members based on user role
     */
    private function getTeamMembers($authUser, $currentDate, $managerId = null, $search = null, $branchId = null)
    {
        $query = User::query()
            ->with(['jobDetails.department', 'jobDetails.designation', 'jobDetails.branch', 'basicDetails'])
            ->where('status', 1)
            ->where('role', "!=", "admin");

        if ($managerId !== null) {
            // Explicit target manager (e.g. viewing another manager's direct
            // reports from their profile page) — access to that page is
            // already gated by canViewUserProfile()/scopeCoversOwner(), so
            // the RBAC "my team" scope below doesn't apply here.
            $query->managedBy($managerId);
        } else {
            // Permission-based filtering
            $teamScope = app(RbacService::class)->scopeFor($authUser, 'team', 'view');
            if ($teamScope === 'team') {
                $query->managedBy($authUser->id);
            } elseif ($teamScope !== 'company') {
                return collect([]);
            }
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('employee_id', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($branchId) {
            $query->whereHas('jobDetails', fn ($q) => $q->where('branch_id', $branchId));
        }

        $users = $query->get();

        $attendanceRecords = Attendance::whereIn('user_id', $users->pluck('id'))
            ->whereDate('date', $currentDate)
            ->get()
            ->keyBy('user_id');

        // COALESCE(end_date, start_date) so legacy one-row-per-day records
        // (created before leave requests were collapsed to a single row and
        // never had end_date set) still match on their single day.
        $leaveRecords = Leave::where('status', 'approved')
            ->whereDate('start_date', '<=', $currentDate)
            ->whereRaw('COALESCE(end_date, start_date) >= ?', [$currentDate])
            ->whereIn('user_id', $users->pluck('id'))
            ->get()
            ->keyBy('user_id');

        $holiday = Holiday::whereDate('start_date', '<=', $currentDate)
            ->whereDate('start_date', '>=', $currentDate)
            ->first();

        $weekoffs = UserWeekoffs::where('status', 1)
            ->whereIn('user_id', $users->pluck('id'))
            ->where(function ($query) use ($currentDate) {
                $query->where(function ($q) use ($currentDate) {
                    $q->where('off_type', 'date_based')
                        ->whereDate('start_date', '<=', $currentDate)
                        ->whereDate('end_date', '>=', $currentDate);
                })->orWhere(function ($q) use ($currentDate) {
                    $q->where('off_type', 'day_based')
                        ->where('day_name', Carbon::parse($currentDate)->format('l'));
                });
            })
            ->get()
            ->keyBy('user_id');

        // Process each user to add status and attendance info
        $teamData = collect();

        foreach ($users as $user) {
            $attendance = $attendanceRecords->get($user->id);
            $leave = $leaveRecords->get($user->id);
            $weekoff = $weekoffs->get($user->id);

            // Determine status with shift-based logic
            $status = $this->determineUserStatus(
                $attendance,
                $leave,
                $holiday,
                $weekoff,
                $currentDate,
                $user->id
            );

            // Create member object
            $member = new \stdClass();
            $member->id = $user->id;
            $member->employee_id = $user->employee_id;
            $member->name = $user->name;
            $member->email = $user->email;
            $member->designation = $user->jobDetails->Designation->name ?? null;
            $member->department = $user->jobDetails->Department->name ?? null;
            $member->branch = $user->jobDetails->branch->name ?? null;
            $member->profile_image = $user->basicDetails->profile_image ?? null;
            $member->punch_in = $attendance ? $attendance->clock_in : null;
            $member->punch_out = $attendance ? $attendance->clock_out : null;
            $member->total_hours = $attendance ? $attendance->total_hours : null;
            $member->status = $status;

            $teamData->push($member);
        }

        return $teamData;
    }

    /**
     * Calculate attendance status based on shift timings from attendance record
     * @param float $totalHours
     * @param int $userId
     * @param string $date
     * @param object|null $attendance
     * @return string
     */
    private function getAttendanceStatusByShift($totalHours, $userId, $date, $attendance = null)
    {
        // A hand-set (admin/HR/manager) or policy-resolved status is
        // authoritative — do not recompute it from worked hours.
        if ($attendance) {
            $persisted = $this->persistedDayStatus($attendance);
            if ($persisted !== null) {
                return $persisted;
            }
        }

        // Use scheduled shift times from attendance record
        if ($attendance && $attendance->scheduled_shift_start && $attendance->scheduled_shift_end) {
            $shiftStart = Carbon::parse($attendance->scheduled_shift_start);
            $shiftEnd = Carbon::parse($attendance->scheduled_shift_end);
            
            // Handle overnight shifts (e.g., 22:00 to 06:00)
            if ($shiftEnd->lessThan($shiftStart)) {
                $shiftEnd->addDay();
            }
            
            $expectedHours = $shiftStart->diffInHours($shiftEnd);
            
            // If expected hours is 0 or negative, fallback to hours-based logic
            if ($expectedHours <= 0) {
                if ($totalHours === null || $totalHours < 2) {
                    return 'Absent';
                } elseif ($totalHours < 6) {
                    return 'halfday';
                } else {
                    return 'Present';
                }
            }
            
            // Calculate percentage of shift completed
            $percentage = ($totalHours / $expectedHours) * 100;
            
            // Determine status based on percentage
            // < 20% = Absent, 20-60% = halfday, >= 60% = Present
            if ($totalHours === null || $percentage < 20) {
                return 'Absent';
            } elseif ($percentage < 60) {
                return 'halfday';
            } else {
                return 'Present';
            }
        }
        
        // FALLBACK: If attendance doesn't have scheduled shift times, use hours-based logic
        if ($totalHours === null || $totalHours < 2) {
            return 'Absent';
        } elseif ($totalHours < 6) {
            return 'halfday';
        } else {
            return 'Present';
        }
    }

    /**
     * Display status for a row whose status was set by hand or resolved by the
     * late-allowance policy. Returns null when neither applies (fall through to
     * the hours-based calculation).
     */
    private function persistedDayStatus($attendance): ?string
    {
        $isManual = (($attendance->attendance_type ?? null) === 'manual')
            || !empty($attendance->marked_by ?? null);

        // An auto row that has only been clocked into (no clock-out) must still
        // read as "Checked In Only", not the 'present' the policy writes for it.
        if (!$isManual && !empty($attendance->clock_in ?? null) && empty($attendance->clock_out ?? null)) {
            return null;
        }

        $status = $isManual
            ? ($attendance->attendance_status ?? null)
            : ($attendance->effective_status ?? null);

        return match ($status) {
            'present', 'late', 'overtime', 'early_departure' => 'Present',
            'half_day' => 'halfday',
            'absent' => 'Absent',
            'on_leave' => 'Full Day Leave',
            'first_half_leave' => 'First Half Leave',
            'second_half_leave' => 'Second Half Leave',
            'holiday' => 'Holiday',
            'weekoff' => 'Week Off',
            default => null,
        };
    }

    /**
     * Determine user status for a given date - WITH SHIFT-BASED LOGIC
     */
    private function determineUserStatus($attendance, $leave, $holiday, $weekoff, $date, $userId)
    {
        // A hand-set / policy-resolved status wins over everything else.
        if ($attendance) {
            $persisted = $this->persistedDayStatus($attendance);
            if ($persisted !== null) {
                return $persisted;
            }
        }

        // HIGHEST PRIORITY: Attendance with shift-based calculation
        if ($attendance) {
            // ✅ FIX: Use worked_hours for calculation
            $totalHours = null;
            if ($attendance->worked_hours) {
                $totalHours = (float)$attendance->worked_hours;
            } elseif ($attendance->clock_in && $attendance->clock_out) {
                $clockIn = Carbon::parse($attendance->clock_in);
                $clockOut = Carbon::parse($attendance->clock_out);
                $totalHours = $clockIn->diffInHours($clockOut);
            }

            if ($attendance->clock_in && $attendance->clock_out) {
                return $this->getAttendanceStatusByShift($totalHours, $userId, $date, $attendance);
            } elseif ($attendance->clock_in) {
                return 'Checked In Only';
            }
        }

        // THEN: Holiday
        if ($holiday) {
            return 'Holiday';
        }

        // THEN: Leave
        if ($leave) {
            if ($leave->start_session == 1 && $leave->end_session == 1 && $leave->start_date == $leave->start_date) {
                return 'First Half Leave';
            } elseif ($leave->start_session == 2 && $leave->end_session == 2 && $leave->start_date == $leave->start_date) {
                return 'Second Half Leave';
            } else {
                return 'Full Day Leave';
            }
        }

        // THEN: Week Off
        if ($weekoff) {
            if ($weekoff->off_type == 'day_based') {
                $dayName = Carbon::parse($date)->format('l');
                if ($weekoff->day_name == $dayName) {
                    return 'Week Off';
                }
            } else {
                return 'Week Off';
            }
        }

        // FINALLY: Absent
        return 'Absent';
    }

    /**
     * View user profile with attendance
     */
    public function viewUserProfile($id)
    {
        try {
            $id = decrypt($id);
            $authUser = Auth::user();

            // Authorization check
            $user = User::with(['jobDetails'])->find($id);

            if (!$user) {
                return redirect()->back()->with('error', 'User not found.');
            }

            // Check if user has permission to view this profile
            if (!$this->canViewUserProfile($authUser, $user)) {
                return redirect()->back()->with('error', 'Unauthorized access.');
            }

            $userCreatedDate = $user->created_at->format('Y-m-d');
            $today = date('Y-m-d');

            /* --------------------------------------------
             | 1. GET USER PROFILE INFORMATION
             -------------------------------------------- */
            $userInfo = $this->getUserProfileData($id);

            if (!$userInfo) {
                return redirect()->back()->with('error', 'User information not found.');
            }

            /* ---------------- LANGUAGE HANDLING ---------------- */
            $languageIds = $userInfo->language
                ? json_decode($userInfo->language, true)
                : [];

            $languageNames = [];

            if (!empty($languageIds)) {
                $languageNames = Language::whereIn('id', $languageIds)
                    ->pluck('name')
                    ->toArray();
            }

            /* --------------------------------------------
             | 2. GET MONTH FILTER VALUES
             -------------------------------------------- */
            $selectedMonth = request()->get('month', date('Y-m'));

            // Validate month format
            if (!preg_match('/^\d{4}-\d{2}$/', $selectedMonth)) {
                $selectedMonth = date('Y-m');
            }

            // Ensure selected month is not in the future
            $currentDate = Carbon::now();
            $selectedDate = Carbon::createFromFormat('Y-m', $selectedMonth);

            if ($selectedDate->gt($currentDate)) {
                $selectedMonth = $currentDate->format('Y-m');
                $selectedDate = $currentDate->copy()->startOfMonth();
            }
            $selectedDate = Carbon::createFromFormat('Y-m', $selectedMonth);

            // Set date range for selected month
            $monthStartDate = $selectedDate->copy()->startOfMonth()->format('Y-m-d');
            $monthEndDate = $selectedDate->copy()->endOfMonth()->format('Y-m-d');

            // For display, keep the full month range (including future dates for upcoming status)
            $displayStartDate = $monthStartDate;
            $displayEndDate = $monthEndDate;

            // But limit attendance data to today for actual attendance records
            $attendanceEndDate = ($monthEndDate > $today) ? $today : $monthEndDate;

            // Prepare month filter options
            $months = $this->getMonthOptions();
            /* --------------------------------------------
             | 3. GET ATTENDANCE DATA FOR SELECTED MONTH
             -------------------------------------------- */
            $attendanceData = $this->getAttendanceDataForUser($displayStartDate, $displayEndDate, $id);
            // Add task counts to attendance data
            $attendanceData = $this->addTaskCountsToAttendance($attendanceData, $id);
            $attendanceSummary = $this->calculateSummary($attendanceData);

            /* --------------------------------------------
             | 4. PREPARE DATA FOR VIEWS
             -------------------------------------------- */
            // Process calendar data for FullCalendar
            $calendarData = [];
            foreach ($attendanceData as $record) {
                $event = $this->createCalendarEvent($record);
                $calendarData[] = $event;
            }

            // Get today's attendance status
            $todayAttendance = $this->getTodayAttendance($attendanceData, $today);

            // If this employee is a manager, show their direct reports'
            // basic details + today's attendance status.
            $directReports = ($userInfo->role === 'manager')
                ? $this->getTeamMembers($authUser, $today, $userInfo->id)
                : collect();

            // Format profile image URL
            $profileImage = $userInfo->profile_image
                ? asset($userInfo->profile_image)
                : asset('/profile2.jpg');

            // Format documents
            $documents = $this->getUserDocuments($userInfo);

            // Calculate age from DOB
            $age = null;
            if ($userInfo->dob) {
                $dob = new DateTime($userInfo->dob);
                $todayDate = new DateTime();
                $age = $dob->diff($todayDate)->y;
            }

            return view('client.team.team-member-detail', compact(
                'userInfo',
                'languageNames',
                'attendanceData',
                'calendarData',
                'attendanceSummary',
                'todayAttendance',
                'directReports',
                'profileImage',
                'documents',
                'age',
                'months',
                'selectedMonth',
                'monthStartDate',
                'monthEndDate',
                'userCreatedDate'
            ));
        } catch (Exception $e) {
            Log::error('Error in viewUserProfile: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            return redirect()->back()->with('error', 'Something went wrong. Please try again.');
        }
    }

    private function addTaskCountsToAttendance($attendanceData, $userId)
    {
        foreach ($attendanceData as $record) {
            $date = $record->date;

            $taskCount = DB::selectOne("
                SELECT COUNT(DISTINCT t.id) as count
                FROM tasks t
                INNER JOIN task_assigns ta ON t.id = ta.task_id
                WHERE ta.assigned_to = ?
                    AND t.task_date <= ?
                    AND t.deadline_date >= ?
            ", [$userId, $date, $date]);

            $record->task_count = $taskCount ? (int)$taskCount->count : 0;
        }

        return $attendanceData;
    }

    /**
     * Check if user can view another user's profile
     */
    private function canViewUserProfile($authUser, $targetUser)
    {
        return $this->scopeCoversOwner($authUser, 'team', 'view', $targetUser->id);
    }

    /**
     * Get user profile data with relationships
     */
    private function getUserProfileData($userId)
    {
        return User::where('users.id', $userId)
            ->leftJoin('user_basic_details', 'users.id', '=', 'user_basic_details.user_id')
            ->leftJoin('user_bank_details', 'users.id', '=', 'user_bank_details.user_id')
            ->leftJoin('user_job_details', 'users.id', '=', 'user_job_details.user_id')
            ->leftJoin('departments', 'user_job_details.department', '=', 'departments.id')
            ->leftJoin('designations', 'user_job_details.designation', '=', 'designations.id')
            ->leftJoin('users as reporting_users', 'user_job_details.reporting_head', '=', 'reporting_users.id')
            ->leftJoin('user_locations', 'users.id', '=', 'user_locations.user_id')
            ->leftJoin('countries', 'user_locations.country', '=', 'countries.country_code')
            ->leftJoin('states', 'user_locations.state', '=', 'states.state_code')
            ->leftJoin('cities', 'user_locations.city', '=', 'cities.city_code')
            ->select([
                'users.id',
                'users.name',
                'users.employee_id',
                'users.email',
                'users.contact',
                'users.role',
                'users.created_at',

                'user_basic_details.father_name',
                'user_basic_details.mother_name',
                'user_basic_details.dob',
                'user_basic_details.gender',
                'user_basic_details.profile_image',
                'user_basic_details.blood_group',
                'user_basic_details.marital_status',
                'user_basic_details.nationality',
                'user_basic_details.alternate_phone',
                'user_basic_details.personal_email',
                'user_basic_details.aadhaar_no',
                'user_basic_details.pan_no',
                'user_basic_details.language',

                'user_basic_details.experience_letter',
                'user_basic_details.tenth_marksheet',
                'user_basic_details.twelfth_marksheet',
                'user_basic_details.highest_qualification_certificate',

                'user_bank_details.account_number',
                'user_bank_details.ifsc',
                'user_bank_details.bank_name',
                'user_bank_details.branch_name',

                'designations.name as designation',
                'departments.name as department',
                'reporting_users.name as reporting_head',
                'user_job_details.joining_date',
                'user_job_details.employment_type',

                'countries.name as country',
                'states.name as state',
                'cities.name as city',
                'user_locations.address',
                'user_locations.permanent_address',
                'user_locations.pincode',
            ])
            ->first();
    }

    /**
     * Get user documents
     */
    private function getUserDocuments($userInfo)
    {
        return [
            'experience_letter' => $userInfo->experience_letter ? asset($userInfo->experience_letter) : null,
            'tenth_marksheet' => $userInfo->tenth_marksheet ? asset($userInfo->tenth_marksheet) : null,
            'twelfth_marksheet' => $userInfo->twelfth_marksheet ? asset($userInfo->twelfth_marksheet) : null,
            'highest_qualification_certificate' => $userInfo->highest_qualification_certificate ? asset($userInfo->highest_qualification_certificate) : null,
        ];
    }

    /**
     * Get today's attendance
     */
    private function getTodayAttendance($attendanceData, $today)
    {
        foreach ($attendanceData as $record) {
            if ($record->date == $today) {
                return [
                    'date' => $record->date,
                    'day_name' => $record->day_name,
                    'clock_in' => $record->clock_in,
                    'clock_out' => $record->clock_out,
                    'total_hours' => $record->total_hours,
                    'day_status' => $record->day_status,
                    'is_holiday' => $record->holiday_name ? true : false,
                    'holiday_name' => $record->holiday_name,
                    'on_leave' => $record->leave_type ? true : false,
                    'leave_type' => $record->leave_type,
                    'leave_session' => $record->leave_session,
                    'leave_reason' => $record->leave_reason,
                    'message' => $record->message,
                    'has_weekoff' => $record->has_weekoff ?? false,
                    'weekoff_day' => $record->weekoff_day ?? null,
                ];
            }
        }
        return null;
    }

    /**
     * Get attendance data for user within date range - WITH SHIFT-BASED LOGIC
     */
    private function getAttendanceDataForUser($startDate, $endDate, $userId)
    {
        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);
        $currentDateObj = Carbon::now()->startOfDay();
        $dates = [];

        $user = User::find($userId);
        if (!$user) {
            return collect([]);
        }

        $attendances = Attendance::where('user_id', $userId)
            ->whereBetween('date', [$startDate, $endDate])
            ->get()
            ->keyBy('date');

        // Overlap check (with COALESCE fallback for legacy one-row-per-day
        // records that never had end_date set) instead of matching start_date
        // alone, so every day of a multi-day leave is picked up below.
        $leaves = Leave::where('user_id', $userId)
            ->where('status', 'approved')
            ->where('start_date', '<=', $endDate)
            ->whereRaw('COALESCE(end_date, start_date) >= ?', [$startDate])
            ->get();

        $holidays = Holiday::where('start_date', '<=', $endDate)
            ->where('start_date', '>=', $startDate)
            ->get();

        $weekoffs = UserWeekoffs::where('user_id', $userId)
            ->where('status', 1)
            ->where('start_date', '<=', $endDate)
            ->where('end_date', '>=', $startDate)
            ->get();

        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $dateStr = $date->format('Y-m-d');
            $currentDate = Carbon::parse($dateStr);
            $isFutureDate = $currentDate->gt($currentDateObj);

            $attendance = isset($attendances[$dateStr]) ? $attendances[$dateStr] : null;

            // ✅ FIX: Use worked_hours for calculation
            $totalHours = null;
            if ($attendance && $attendance->worked_hours) {
                $totalHours = (float)$attendance->worked_hours;
            } elseif ($attendance && $attendance->clock_in && $attendance->clock_out) {
                $clockIn = Carbon::parse($attendance->clock_in);
                $clockOut = Carbon::parse($attendance->clock_out);
                $totalHours = $clockIn->diffInHours($clockOut);
            }

            // Check holiday
            $holiday = null;
            foreach ($holidays as $h) {
                $holidayStart = Carbon::parse($h->start_date)->startOfDay();
                $holidayEnd = Carbon::parse($h->start_date)->endOfDay();
                if ($currentDate->between($holidayStart, $holidayEnd)) {
                    $holiday = $h;
                    break;
                }
            }

            // Check leave
            $leave = null;
            foreach ($leaves as $l) {
                $leaveStart = Carbon::parse($l->start_date)->startOfDay();
                $leaveEnd = Carbon::parse($l->end_date ?? $l->start_date)->endOfDay();
                if ($currentDate->between($leaveStart, $leaveEnd)) {
                    $leave = $l;
                    break;
                }
            }

            // Check weekoff
            $weekoff = null;
            foreach ($weekoffs as $w) {
                $weekoffStart = Carbon::parse($w->start_date)->startOfDay();
                $weekoffEnd = Carbon::parse($w->end_date)->endOfDay();
                if ($currentDate->between($weekoffStart, $weekoffEnd)) {
                    if ($w->off_type == 'date_based') {
                        $weekoff = $w;
                        break;
                    } elseif ($w->off_type == 'day_based' && $w->day_name == $date->format('l')) {
                        $weekoff = $w;
                        break;
                    }
                }
            }

            // Determine status - WITH SHIFT-BASED LOGIC
            $status = 'Absent';

            if ($isFutureDate) {
                $status = 'Upcoming';
                if ($holiday) {
                    $status = 'Holiday';
                } elseif ($leave) {
                    if ($leave->start_date == $leave->start_date) {
                        if ($leave->start_session == 1 && $leave->end_session == 1) {
                            $status = 'First Half Leave';
                        } elseif ($leave->start_session == 2 && $leave->end_session == 2) {
                            $status = 'Second Half Leave';
                        } else {
                            $status = 'Full Day Leave';
                        }
                    } else {
                        $status = 'Full Day Leave';
                    }
                } elseif ($weekoff) {
                    $status = 'Week Off';
                }
            } else {
                // Updated priority with shift-based logic
                if ($attendance && $attendance->clock_in && $attendance->clock_out) {
                    $status = $this->getAttendanceStatusByShift($totalHours, $userId, $dateStr, $attendance);
                } elseif ($attendance && $attendance->clock_in) {
                    $status = 'Checked In Only';
                } elseif ($holiday) {
                    $status = 'Holiday';
                } elseif ($leave) {
                    if ($leave->start_date == $leave->start_date) {
                        if ($leave->start_session == 1 && $leave->end_session == 1) {
                            $status = 'First Half Leave';
                        } elseif ($leave->start_session == 2 && $leave->end_session == 2) {
                            $status = 'Second Half Leave';
                        } else {
                            $status = 'Full Day Leave';
                        }
                    } else {
                        $status = 'Full Day Leave';
                    }
                } elseif ($weekoff) {
                    $status = 'Week Off';
                }
            }

            $record = new \stdClass();
            $record->user_id = $userId;
            $record->name = $user->name;
            $record->email = $user->email;
            $record->date = $dateStr;
            $record->formatted_date = $dateStr;
            $record->day_name = $date->format('l');
            $record->clock_in = (!$isFutureDate && $attendance) ? $attendance->clock_in : null;
            $record->clock_out = (!$isFutureDate && $attendance) ? $attendance->clock_out : null;
            $record->total_hours = (!$isFutureDate && $attendance) ? number_format($totalHours, 2) : null;
            $record->leave_type = $leave->leave_type ?? null;
            $record->leave_reason = $leave->reason ?? null;
            $record->leave_session = $leave->start_session ?? null;
            $record->holiday_name = $holiday->name ?? null;
            $record->message = $holiday->name ?? null;
            $record->off_type = $weekoff->off_type ?? null;
            $record->weekoff_day = $weekoff->day_name ?? null;
            $record->has_weekoff = $weekoff ? true : false;
            $record->day_status = $status;

            $dates[] = $record;
        }

        usort($dates, function ($a, $b) {
            return strtotime($b->date) - strtotime($a->date);
        });

        return collect($dates);
    }

    /**
     * Calculate summary from attendance data
     */
    private function calculateSummary($attendanceData)
    {
        $summary = [
            'present' => 0,
            'absent' => 0,
            'on_leave' => 0,
            'holiday' => 0,
            'week_off' => 0,
            'checked_in_only' => 0,
            'halfday' => 0,
            'work_days' => 0,
            'total_days' => 0
        ];

        $uniqueDates = [];
        $holidayDates = [];
        $weekOffDates = [];

        foreach ($attendanceData as $record) {
            $dateStr = $record->date;

            if (!in_array($dateStr, $uniqueDates)) {
                $uniqueDates[] = $dateStr;
            }

            switch ($record->day_status) {
                case 'Present':
                    $summary['present']++;
                    break;
                case 'halfday':
                    // 0.5 present only — do not also add 0.5 to absent.
                    $summary['halfday']++;
                    $summary['present'] += 0.5;
                    break;
                case 'Checked In Only':
                    $summary['checked_in_only']++;
                    $summary['present']++;
                    break;
                case 'Absent':
                    $summary['absent']++;
                    break;
                case 'Holiday':
                    $summary['holiday']++;
                    if (!in_array($dateStr, $holidayDates)) {
                        $holidayDates[] = $dateStr;
                    }
                    break;
                case 'Week Off':
                    $summary['week_off']++;
                    if (!in_array($dateStr, $weekOffDates)) {
                        $weekOffDates[] = $dateStr;
                    }
                    break;
                case 'First Half Leave':
                case 'Second Half Leave':
                case 'Full Day Leave':
                    $summary['on_leave']++;
                    break;
            }
        }

        $summary['total_days'] = count($uniqueDates);
        $summary['work_days'] = $summary['total_days'] - count($holidayDates) - count($weekOffDates);

        return $summary;
    }

    /**
     * Create calendar event from attendance record
     */
    private function createCalendarEvent($record)
    {
        $title = $this->getEventTitle($record);
        $color = $this->getEventColor($record->day_status);

        return [
            'id' => 'att_' . $record->formatted_date,
            'title' => $title,
            'start' => $record->formatted_date,
            'end' => $record->formatted_date,
            'day_status' => $record->day_status,
            'bgColor' => $color,
            'color' => '#ffffff',
            'clock_in' => $record->clock_in,
            'clock_out' => $record->clock_out,
            'total_hours' => $record->total_hours,
            'leave_type' => $record->leave_type,
            'holiday_name' => $record->holiday_name,
            'leave_reason' => $record->leave_reason,
            'has_weekoff' => $record->has_weekoff ?? false,
            'weekoff_day' => $record->weekoff_day ?? null,
            'isAllday' => true,
            'borderColor' => $color,
            'dragBgColor' => $color
        ];
    }

    /**
     * Get event title for calendar
     */
    private function getEventTitle($record)
    {
        switch ($record->day_status) {
            case 'Present':
                $time = ($record->clock_in ? substr($record->clock_in, 0, 5) : '') .
                    ($record->clock_out ? ' - ' . substr($record->clock_out, 0, 5) : '');
                $hoursText = $record->total_hours ? " (" . $record->total_hours . " hrs)" : "";
                return "Present" . $hoursText . ($time ? " ($time)" : "");
            case 'halfday':
                $time = ($record->clock_in ? substr($record->clock_in, 0, 5) : '') .
                    ($record->clock_out ? ' - ' . substr($record->clock_out, 0, 5) : '');
                $hoursText = $record->total_hours ? " (" . $record->total_hours . " hrs)" : "";
                return "halfday" . $hoursText . ($time ? " ($time)" : "");
            case 'Holiday':
                return $record->holiday_name ?: 'Holiday';
            case 'First Half Leave':
                return "First Half - " . ($record->leave_type ?: 'Leave');
            case 'Second Half Leave':
                return "Second Half - " . ($record->leave_type ?: 'Leave');
            case 'Full Day Leave':
                return "Leave - " . ($record->leave_type ?: 'Leave');
            case 'Checked In Only':
                $time = $record->clock_in ? substr($record->clock_in, 0, 5) : '';
                return "Checked In" . ($time ? " ($time)" : "");
            case 'Week Off':
                $title = "Week Off";
                if ($record->off_type == 'day_based' && $record->weekoff_day) {
                    $title .= " (" . $record->weekoff_day . ")";
                }
                return $title;
            case 'Absent':
                return "Absent";
            default:
                return $record->day_status;
        }
    }

    /**
     * Get event color based on status
     */
    private function getEventColor($status)
    {
        $colorMap = [
            'Present' => '#28a745',
            'halfday' => '#ffc107',
            'Checked In Only' => '#ffc107',
            'Holiday' => '#0d6efd',
            'First Half Leave' => '#fd7e14',
            'Second Half Leave' => '#fd7e14',
            'Full Day Leave' => '#fd7e14',
            'Week Off' => '#6c757d',
            'Absent' => '#dc3545',
            'Upcoming' => '#17a2b8'
        ];

        return $colorMap[$status] ?? '#6f42c1';
    }

    /**
     * AJAX endpoint for attendance stats
     */
    public function userAttendanceStats(Request $request, $id)
    {
        try {
            $id = decrypt($id);
            $authUser = Auth::user();

            $targetUser = User::with(['jobDetails'])->find($id);
            if (!$targetUser) {
                return response()->json([
                    'status' => false,
                    'message' => 'User not found'
                ], 404);
            }

            if (!$this->canViewUserProfile($authUser, $targetUser)) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthorized access'
                ], 403);
            }

            $selectedMonth = $request->month ?? date('Y-m');
            $userCreatedDate = $targetUser->created_at->format('Y-m-d');
            $today = date('Y-m-d');

            $selectedDate = Carbon::createFromFormat('Y-m', $selectedMonth);
            $monthStartDate = $selectedDate->copy()->startOfMonth()->format('Y-m-d');
            $monthEndDate = $selectedDate->copy()->endOfMonth()->format('Y-m-d');

            $startDate = max($userCreatedDate, $monthStartDate);
            $endDate = min($monthEndDate, $today);

            $attendanceData = $this->getAttendanceDataForUser($startDate, $endDate, $id);
            $attendanceSummary = $this->calculateSummary($attendanceData);

            return response()->json([
                'status' => true,
                'summary' => $attendanceSummary,
                'month_name' => $selectedDate->format('F Y'),
                'date_range' => [
                    'start' => $startDate,
                    'end' => $endDate
                ]
            ]);
        } catch (Exception $e) {
            Log::error('Error in userAttendanceStats: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'An error occurred while fetching attendance stats.'
            ], 500);
        }
    }

    public function userAttendanceCalendarData(Request $request, $id)
    {
        try {
            $id = decrypt($id);
            $authUser = Auth::user();

            $targetUser = User::find($id);
            if (!$targetUser) {
                return response()->json([
                    'status' => false,
                    'message' => 'User not found'
                ], 404);
            }

            if (!$this->canViewUserProfile($authUser, $targetUser)) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthorized access'
                ], 403);
            }

            $startDate = $request->start_date ?? date('Y-m-01');
            $endDate = $request->end_date ?? date('Y-m-t');

            $attendanceData = $this->getAttendanceDataForUser($startDate, $endDate, $id);

            // Add task counts to attendance data
            $attendanceData = $this->addTaskCountsToAttendance($attendanceData, $id);

            $events = [];
            foreach ($attendanceData as $record) {
                $dateStr = $record->formatted_date ?? $record->date;
                $status = $record->day_status;
                $taskCount = $record->task_count ?? 0;

                if (!$dateStr || !$status) continue;

                // Single-color (blue) theme, matching the app's blue-only
                // convention (see the Team Attendance .stats-card family) —
                // statuses are distinguished by shade/fill intensity, not hue.
                $bgColor = '#eff6ff';
                $borderColor = '#dbeafe';
                $title = $status;
                $color = '#475569';

                // Add hours to title for Present and halfday
                $hoursText = '';
                if (in_array($status, ['Present', 'halfday']) && $record->total_hours && $record->total_hours !== '--') {
                    $hoursText = " ({$record->total_hours} hrs)";
                }

                $statusLower = strtolower($status);
                switch ($statusLower) {
                    case 'present':
                        $bgColor = '#2563eb';
                        $borderColor = '#1d4ed8';
                        $color = '#ffffff';
                        $title = 'Present' . $hoursText;
                        break;
                    case 'halfday':
                        $bgColor = '#60a5fa';
                        $borderColor = '#3b82f6';
                        $color = '#ffffff';
                        $title = 'halfday' . $hoursText;
                        break;
                    case 'absent':
                        $bgColor = '#1e3a8a';
                        $borderColor = '#1e293b';
                        $color = '#ffffff';
                        $title = 'Absent';
                        break;
                    case 'first half leave':
                    case 'second half leave':
                    case 'full day leave':
                        $bgColor = '#dbeafe';
                        $borderColor = '#93c5fd';
                        $color = '#1d4ed8';
                        $title = 'Leave';
                        break;
                    case 'holiday':
                        $bgColor = '#bfdbfe';
                        $borderColor = '#93c5fd';
                        $color = '#1d4ed8';
                        $title = $record->holiday_name ?? 'Holiday';
                        break;
                    case 'week off':
                        $bgColor = '#eff6ff';
                        $borderColor = '#dbeafe';
                        $color = '#475569';
                        $title = 'Week Off';
                        break;
                    case 'checked in only':
                        $bgColor = '#93c5fd';
                        $borderColor = '#60a5fa';
                        $color = '#1e3a8a';
                        $title = 'Checked In Only';
                        break;
                    case 'upcoming':
                        $bgColor = '#f8fafc';
                        $borderColor = '#cbd5e1';
                        $color = '#64748b';
                        $title = 'Upcoming';
                        break;
                }

                $clockInTime = '--:--';
                $clockOutTime = '--:--';
                $totalHours = '--';

                if ($record->clock_in) {
                    $clockInTime = Carbon::parse($record->clock_in)->format('h:i A');
                }
                if ($record->clock_out) {
                    $clockOutTime = Carbon::parse($record->clock_out)->format('h:i A');
                }
                if ($record->total_hours) {
                    $totalHours = $record->total_hours;
                }

                $events[] = [
                    'id' => 'attendance_' . $dateStr,
                    'title' => $title,
                    'start' => $dateStr,
                    'bgColor' => $bgColor,
                    'borderColor' => $borderColor,
                    'color' => $color,
                    'day_status' => $status,
                    'clock_in' => $clockInTime,
                    'clock_out' => $clockOutTime,
                    'total_hours' => $totalHours,
                    'task_count' => $taskCount,
                    'holiday_name' => $record->holiday_name ?? null
                ];
            }

            return response()->json([
                'status' => true,
                'events' => $events,
                'month' => $startDate
            ]);
        } catch (Exception $e) {
            Log::error('Error in userAttendanceCalendar: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'An error occurred while fetching calendar data.'
            ], 500);
        }
    }

    /**
     * AJAX endpoint for table data (when month changes)
     */
    public function userAttendanceTableData(Request $request, $id)
    {
        try {
            $id = decrypt($id);
            $authUser = Auth::user();

            $targetUser = User::with(['jobDetails'])->find($id);
            if (!$targetUser) {
                return response()->json([
                    'status' => false,
                    'message' => 'User not found'
                ], 404);
            }

            if (!$this->canViewUserProfile($authUser, $targetUser)) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthorized access'
                ], 403);
            }

            $startDate = $request->start_date ?? date('Y-m-01');
            $endDate = $request->end_date ?? date('Y-m-d');

            $userCreatedDate = $targetUser->created_at->format('Y-m-d');
            if (strtotime($startDate) < strtotime($userCreatedDate)) {
                $startDate = $userCreatedDate;
            }

            if (strtotime($startDate) > strtotime($endDate)) {
                return response()->json([
                    'status' => false,
                    'message' => 'Start date cannot be after end date'
                ], 400);
            }

            $data = $this->getAttendanceDataForUser($startDate, $endDate, $id);

            $tableData = [];
            foreach ($data as $record) {
                $tableData[] = [
                    'date' => Carbon::parse($record->date)->format('d M, Y'),
                    'day_name' => $record->day_name,
                    'clock_in' => $record->clock_in ? Carbon::parse($record->clock_in)->format('h:i A') : '--:--',
                    'clock_out' => $record->clock_out ? Carbon::parse($record->clock_out)->format('h:i A') : '--:--',
                    'total_hours' => $record->total_hours ? $record->total_hours . 'h' : '--',
                    'day_status' => $record->day_status,
                    'badge_class' => 'badge-' . strtolower(str_replace(' ', '-', $record->day_status))
                ];
            }

            return response()->json([
                'status' => true,
                'data' => $tableData,
                'total' => count($tableData),
                'date_range' => [
                    'start' => $startDate,
                    'end' => $endDate
                ]
            ]);
        } catch (Exception $e) {
            Log::error('Error in userAttendanceTableData: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'An error occurred while fetching table data.'
            ], 500);
        }
    }

    public function attendanceSummary(Request $request)
    {
        try {
            $authUser = Auth::user();

            if (!app(RbacService::class)->can($authUser, 'team', 'view', 'team')) {
                return redirect()->back()->with('error', 'Unauthorized access.');
            }

            $selectedMonth = $request->get('month', date('Y-m'));

            if (!preg_match('/^\d{4}-\d{2}$/', $selectedMonth)) {
                $selectedMonth = date('Y-m');
            }

            $selectedDate = Carbon::createFromFormat('Y-m', $selectedMonth);
            $monthStartDate = $selectedDate->copy()->startOfMonth()->format('Y-m-d');
            $monthEndDate = $selectedDate->copy()->endOfMonth()->format('Y-m-d');
            $today = date('Y-m-d');

            $attendanceEndDate = min($monthEndDate, $today);

            $users = $this->getUsersForSummary($authUser);

            $summaryData = [];
            $totalSummary = [
                'total_users' => 0,
                'total_present' => 0,
                'total_absent' => 0,
                'total_leave' => 0,
                'total_holiday' => 0,
                'total_weekoff' => 0,
                'total_halfday' => 0,
                'total_working_days' => 0
            ];

            foreach ($users as $user) {
                $userSummary = $this->getUserMonthlySummary(
                    $user->id,
                    $monthStartDate,
                    $monthEndDate,
                    $attendanceEndDate
                );

                if ($userSummary) {
                    $summaryData[] = $userSummary;

                    $totalSummary['total_users']++;
                    $totalSummary['total_present'] += $userSummary['present'];
                    $totalSummary['total_absent'] += $userSummary['absent'];
                    $totalSummary['total_leave'] += $userSummary['leaves'];
                    $totalSummary['total_holiday'] += $userSummary['holidays'];
                    $totalSummary['total_weekoff'] += $userSummary['weekoffs'];
                    $totalSummary['total_halfday'] += $userSummary['halfday'] ?? 0;
                    $totalSummary['total_working_days'] += $userSummary['working_days'];
                }
            }

            $months = $this->getMonthOptions();

            return view('client.team.attendance-summary', compact(
                'summaryData',
                'totalSummary',
                'months',
                'selectedMonth',
                'authUser'
            ));
        } catch (Exception $e) {
            Log::error('Error in attendanceSummary: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to load attendance summary.');
        }
    }

    private function getUsersForSummary($authUser)
    {
        $query = User::with(['jobDetails.Department', 'jobDetails.Designation'])
            ->where('status', 1)
            ->where('role', "!=", 'admin');

        if (app(RbacService::class)->scopeFor($authUser, 'team', 'view') === 'team') {
            $query->managedBy($authUser->id);
        }

        return $query->orderBy('name')->get();
    }

    private function getMonthOptions()
    {
        $months = [];
        for ($i = 1; $i <= 12; $i++) {
            $monthValue = date('Y-m', mktime(0, 0, 0, $i, 1, date('Y')));
            $months[$monthValue] = date('F Y', mktime(0, 0, 0, $i, 1, date('Y')));
        }
        return $months;
    }

    private function getUserMonthlySummary($userId, $monthStart, $monthEnd, $dataEnd)
    {
        try {
            $user = User::with(['jobDetails.Department', 'jobDetails.Designation'])
                ->find($userId);

            if (!$user) {
                return null;
            }

            $start = Carbon::parse($monthStart);
            $calculationEnd = Carbon::parse($monthEnd);
            $dataEndDate = Carbon::parse($dataEnd);

            $totalDaysInMonth = $start->copy()->daysInMonth;

            // Tier 1 / W3 — read the persisted rollup instead of recomputing from
            // raw rows. Same output keys; gated until `attendance:summary-diff`
            // confirms parity for the tenant.
            if (config('attendance.summary_readthrough')) {
                $adapted = $this->monthlySummaryFromRollup($user, $start, $totalDaysInMonth);
                if ($adapted !== null) {
                    return $adapted;
                }
            }

            $attendances = Attendance::where('user_id', $userId)
                ->whereBetween('date', [$monthStart, $dataEnd])
                ->get()
                ->keyBy(function ($item) {
                    return Carbon::parse($item->date)->format('Y-m-d');
                });

            $leaves = Leave::where('user_id', $userId)
                ->where('status', 'approved')
                ->where(function ($q) use ($monthStart, $monthEnd) {
                    $q->whereBetween('start_date', [$monthStart, $monthEnd])
                        ->orWhereBetween('start_date', [$monthStart, $monthEnd])
                        ->orWhere(function ($q2) use ($monthStart, $monthEnd) {
                            $q2->where('start_date', '<=', $monthStart)
                                ->where('start_date', '>=', $monthEnd);
                        });
                })
                ->get();

            $holidays = Holiday::where(function ($q) use ($monthStart, $monthEnd) {
                $q->whereBetween('start_date', [$monthStart, $monthEnd])
                    ->orWhereBetween('start_date', [$monthStart, $monthEnd])
                    ->orWhere(function ($q2) use ($monthStart, $monthEnd) {
                        $q2->where('start_date', '<=', $monthStart)
                            ->where('start_date', '>=', $monthEnd);
                    });
            })->get();

            $weekoffs = UserWeekoffs::where('user_id', $userId)
                ->where('status', 1)
                ->where(function ($q) use ($monthStart, $monthEnd) {
                    $q->whereBetween('start_date', [$monthStart, $monthEnd])
                        ->orWhereBetween('end_date', [$monthStart, $monthEnd])
                        ->orWhere(function ($q2) use ($monthStart, $monthEnd) {
                            $q2->where('start_date', '<=', $monthStart)
                                ->where('end_date', '>=', $monthEnd);
                        });
                })
                ->get();

            $present = 0;
            $absent = 0;
            $leaveCount = 0;
            $holidayCount = 0;
            $weekoffCount = 0;
            $halfDayCount = 0;

            $countedDates = [];

            // Count holidays
            foreach ($holidays as $holiday) {
                $holidayStart = Carbon::parse($holiday->start_date);
                $holidayEnd = Carbon::parse($holiday->start_date);

                for ($d = $holidayStart->copy(); $d->lte($holidayEnd); $d->addDay()) {
                    $dateStr = $d->format('Y-m-d');
                    if ($d->between($start, $calculationEnd) && !in_array($dateStr, $countedDates)) {
                        $holidayCount++;
                        $countedDates[] = $dateStr;
                    }
                }
            }

            // Count weekoffs
            foreach ($weekoffs as $weekoff) {
                $weekoffStart = Carbon::parse($weekoff->start_date);
                $weekoffEnd = Carbon::parse($weekoff->end_date);

                for ($d = $weekoffStart->copy(); $d->lte($weekoffEnd); $d->addDay()) {
                    $dateStr = $d->format('Y-m-d');

                    if (!$d->between($start, $calculationEnd)) {
                        continue;
                    }

                    if (in_array($dateStr, $countedDates)) {
                        continue;
                    }

                    if ($weekoff->off_type == 'day_based') {
                        $currentDay = $d->format('l');
                        if ($currentDay == $weekoff->day_name) {
                            $weekoffCount++;
                            $countedDates[] = $dateStr;
                        }
                    } else {
                        $weekoffCount++;
                        $countedDates[] = $dateStr;
                    }
                }
            }

            // Count leaves
            foreach ($leaves as $leave) {
                $leaveStart = Carbon::parse($leave->start_date);
                $leaveEnd = Carbon::parse($leave->start_date);

                for ($d = $leaveStart->copy(); $d->lte($leaveEnd); $d->addDay()) {
                    $dateStr = $d->format('Y-m-d');

                    if (!$d->between($start, $calculationEnd)) {
                        continue;
                    }

                    if (in_array($dateStr, $countedDates)) {
                        continue;
                    }

                    $leaveCount++;
                    $countedDates[] = $dateStr;
                }
            }

            // Count present/absent/halfday from attendance with shift-based logic
            for ($date = $start->copy(); $date->lte($dataEndDate); $date->addDay()) {
                $dateStr = $date->format('Y-m-d');

                if (in_array($dateStr, $countedDates)) {
                    continue;
                }

                $attendance = $attendances->get($dateStr);

                if ($attendance && $attendance->clock_in && $attendance->clock_out) {
                    // ✅ FIX: Use worked_hours for calculation
                    $totalHours = null;
                    if ($attendance->worked_hours) {
                        $totalHours = (float)$attendance->worked_hours;
                    } else {
                        $clockIn = Carbon::parse($attendance->clock_in);
                        $clockOut = Carbon::parse($attendance->clock_out);
                        $totalHours = $clockIn->diffInHours($clockOut);
                    }

                    $status = $this->getAttendanceStatusByShift($totalHours, $userId, $dateStr, $attendance);
                    
                    if ($status === 'Present') {
                        $present++;
                    } elseif ($status === 'halfday') {
                        // A worked half day counts as 0.5 present — it must NOT
                        // also add 0.5 to absent (that let present + absent
                        // exceed the number of working days).
                        $halfDayCount++;
                        $present += 0.5;
                    } else {
                        $absent++;
                    }
                } elseif ($attendance && $attendance->clock_in) {
                    $present++;
                } else {
                    $absent++;
                }
            }

            $workingDays = $totalDaysInMonth - $holidayCount - $weekoffCount;

            return [
                'user_id' => $user->id,
                'employee_id' => $user->employee_id,
                'name' => $user->name,
                'email' => $user->email,
                'department' => $user->jobDetails->Department->name ?? 'N/A',
                'designation' => $user->jobDetails->Designation->name ?? 'N/A',
                'joining_date' => $user->jobDetails->joining_date ?? 'N/A',
                'present' => (float)$present,
                'absent' => (float)$absent,
                'leaves' => (float)$leaveCount,
                'holidays' => $holidayCount,
                'weekoffs' => $weekoffCount,
                'halfday' => $halfDayCount,
                'working_days' => $workingDays,
                'total_month_days' => $totalDaysInMonth,
                'attendance_percentage' => $workingDays > 0 ? round(($present / $workingDays) * 100, 2) : 0
            ];
        } catch (Exception $e) {
            Log::error('Error in getUserMonthlySummary: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Tier 1 / W3 — build the getUserMonthlySummary() payload from the persisted
     * attendance_summaries row. Returns null (caller falls back to the live
     * recompute) if no rollup exists yet.
     *
     * Key mapping (must stay identical to the inline path's return array):
     *   present  = present_days + 0.5 * half_days   (a worked half day is 0.5 present)
     *   halfday  = half_days
     *   leaves   = total_leaves
     */
    private function monthlySummaryFromRollup($user, Carbon $start, int $totalDaysInMonth): ?array
    {
        $m = app(\App\Services\AttendanceSummaryService::class)
            ->getMonthly((int) $user->id, $start->format('Y-m'), (int) $user->tenant_id);

        if (($m['source'] ?? null) === 'unavailable') {
            return null;
        }

        $halfDays = (float) $m['half_days'];
        $present = (float) $m['present_days'] + 0.5 * $halfDays;
        $absent = (float) $m['absent_days'];
        $leaveCount = (float) $m['total_leaves'];
        $holidayCount = (int) $m['holidays'];
        $weekoffCount = (int) $m['week_offs'];
        $workingDays = $totalDaysInMonth - $holidayCount - $weekoffCount;

        return [
            'user_id' => $user->id,
            'employee_id' => $user->employee_id,
            'name' => $user->name,
            'email' => $user->email,
            'department' => $user->jobDetails->Department->name ?? 'N/A',
            'designation' => $user->jobDetails->Designation->name ?? 'N/A',
            'joining_date' => $user->jobDetails->joining_date ?? 'N/A',
            'present' => (float) $present,
            'absent' => (float) $absent,
            'leaves' => (float) $leaveCount,
            'holidays' => $holidayCount,
            'weekoffs' => $weekoffCount,
            'halfday' => (int) $halfDays,
            'working_days' => $workingDays,
            'total_month_days' => $totalDaysInMonth,
            'attendance_percentage' => $workingDays > 0 ? round(($present / $workingDays) * 100, 2) : 0,
        ];
    }

    public function exportAttendanceSummary(Request $request)
    {
        try {
            $authUser = Auth::user();

            if (!app(RbacService::class)->can($authUser, 'team', 'view', 'team')) {
                return redirect()->back()->with('error', 'Unauthorized access.');
            }

            $selectedMonth = $request->get('month', date('Y-m'));
            $selectedDate = Carbon::createFromFormat('Y-m', $selectedMonth);
            $monthStartDate = $selectedDate->copy()->startOfMonth()->format('Y-m-d');
            $monthEndDate = $selectedDate->copy()->endOfMonth()->format('Y-m-d');
            $today = date('Y-m-d');

            $attendanceEndDate = min($monthEndDate, $today);

            $users = $this->getUsersForSummary($authUser);

            $summaryData = [];
            foreach ($users as $user) {
                $userSummary = $this->getUserMonthlySummary(
                    $user->id,
                    $monthStartDate,
                    $monthEndDate,
                    $attendanceEndDate
                );
                if ($userSummary) {
                    $summaryData[] = $userSummary;
                }
            }

            $filename = 'attendance_summary_' . $selectedMonth . '.csv';
            $headers = [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ];

            $callback = function () use ($summaryData, $selectedDate) {
                $file = fopen('php://output', 'w');

                $this->writeCsvRow($file, [
                    'Employee ID',
                    'Employee Name',
                    'Email',
                    'Department',
                    'Designation',
                    'Present Days',
                    'halfdays',
                    'Absent Days',
                    'Leaves',
                    'Holidays',
                    'Week Offs',
                    'Working Days',
                    'Attendance %'
                ]);

                foreach ($summaryData as $row) {
                    $this->writeCsvRow($file, [
                        $row['employee_id'],
                        $row['name'],
                        $row['email'],
                        $row['department'],
                        $row['designation'],
                        $row['present'],
                        $row['halfday'] ?? 0,
                        $row['absent'],
                        $row['leaves'],
                        $row['holidays'],
                        $row['weekoffs'],
                        $row['working_days'],
                        $row['attendance_percentage'] . '%'
                    ]);
                }

                $this->writeCsvRow($file, []);
                $this->writeCsvRow($file, ['SUMMARY', '', '', '', '', '', '', '', '', '', '', '']);
                $this->writeCsvRow($file, [
                    'Total Employees: ' . count($summaryData),
                    'Total Present: ' . array_sum(array_column($summaryData, 'present')),
                    'Total halfdays: ' . array_sum(array_column($summaryData, 'halfday') ?? [0]),
                    'Total Absent: ' . array_sum(array_column($summaryData, 'absent')),
                    'Total Leaves: ' . array_sum(array_column($summaryData, 'leaves')),
                    'Total Holidays: ' . array_sum(array_column($summaryData, 'holidays')),
                    'Total Week Offs: ' . array_sum(array_column($summaryData, 'weekoffs')),
                    '',
                    '',
                    '',
                    '',
                    'Month: ' . $selectedDate->format('F Y')
                ]);

                fclose($file);
            };

            return response()->stream($callback, 200, $headers);
        } catch (Exception $e) {
            Log::error('Error in exportAttendanceSummary: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to export attendance summary.');
        }
    }

    public function getQuickSummary(Request $request)
    {
        try {
            $authUser = Auth::user();

            if (!app(RbacService::class)->can($authUser, 'team', 'view', 'team')) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            $selectedMonth = $request->get('month', date('Y-m'));
            $selectedDate = Carbon::createFromFormat('Y-m', $selectedMonth);
            $monthStartDate = $selectedDate->copy()->startOfMonth()->format('Y-m-d');
            $monthEndDate = $selectedDate->copy()->endOfMonth()->format('Y-m-d');
            $today = date('Y-m-d');

            $attendanceEndDate = min($monthEndDate, $today);

            $users = $this->getUsersForSummary($authUser);

            $totalPresent = 0;
            $totalAbsent = 0;
            $totalLeaves = 0;
            $totalHolidays = 0;
            $totalWeekoffs = 0;
            $totalHalfDays = 0;
            $totalWorkingDays = 0;
            $userCount = 0;

            foreach ($users as $user) {
                $summary = $this->getUserMonthlySummary(
                    $user->id,
                    $monthStartDate,
                    $monthEndDate,
                    $attendanceEndDate
                );
                if ($summary) {
                    $userCount++;
                    $totalPresent += $summary['present'];
                    $totalAbsent += $summary['absent'];
                    $totalLeaves += $summary['leaves'];
                    $totalHolidays += $summary['holidays'];
                    $totalWeekoffs += $summary['weekoffs'];
                    $totalHalfDays += $summary['halfday'] ?? 0;
                    $totalWorkingDays += $summary['working_days'];
                }
            }

            $averageAttendance = $userCount > 0 && $totalWorkingDays > 0
                ? round(($totalPresent / $totalWorkingDays) * 100, 2)
                : 0;

            return response()->json([
                'success' => true,
                'data' => [
                    'month' => $selectedDate->format('F Y'),
                    'total_employees' => $userCount,
                    'total_present' => $totalPresent,
                    'total_absent' => $totalAbsent,
                    'total_leaves' => $totalLeaves,
                    'total_holidays' => $totalHolidays,
                    'total_weekoffs' => $totalWeekoffs,
                    'total_halfdays' => $totalHalfDays,
                    'average_attendance' => $averageAttendance,
                    'working_days_per_employee' => $userCount > 0 ? round($totalWorkingDays / $userCount, 1) : 0
                ]
            ]);
        } catch (Exception $e) {
            Log::error('Error in getQuickSummary: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get summary'
            ], 500);
        }
    }

    // ==================== BRANCH WISE ATTENDANCE REPORT ====================

    /**
     * Branch Wise Attendance Report - Main listing page
     */
    public function branchWiseAttendanceReport(Request $request)
    {
        try {
            $tenantId = session('tenant_id');

            if (!$tenantId) {
                return back()->with('error', 'Tenant not found. Please login again.');
            }

            // Get date from request - default to today
            $selectedDate = $request->get('date', now()->format('Y-m-d'));
            $dateObj = Carbon::parse($selectedDate);
            $dateStr = $dateObj->format('Y-m-d');

            // Fetch all active branches with tenant filter
            $branches = DB::table('branches')
                ->where('tenant_id', $tenantId)
                ->where('status', 1)
                ->select('id', 'name', 'description', 'code')
                ->orderBy('name')
                ->get();

            $branchStats = [];

            foreach ($branches as $branch) {
                // Get employees for this branch using office_branch field
                $employeeIds = DB::table('users as u')
                    ->leftJoin('user_job_details as uj', 'u.id', '=', 'uj.user_id')
                    ->where('u.tenant_id', $tenantId)
                    ->where('u.status', 1)
                    ->where('u.role', '!=', 'admin')
                    ->where('uj.office_branch', $branch->id)
                    ->pluck('u.id')
                    ->toArray();

                $branchStats[$branch->id] = [
                    'branch' => $branch,
                    'total_employees' => count($employeeIds),
                    'present' => 0,
                    'absent' => 0,
                    'on_leave' => 0,
                    'holiday' => 0,
                    'week_off' => 0,
                ];

                if (!empty($employeeIds)) {
                    // Fetch attendance for the specific date - use groupBy to handle duplicates
                    $attendances = DB::table('attendances')
                        ->where('tenant_id', $tenantId)
                        ->whereIn('user_id', $employeeIds)
                        ->where('date', $dateStr)
                        ->get()
                        ->groupBy('user_id');

                    // Fetch approved leaves for the specific date
                    $leaves = DB::table('leaves')
                        ->where('tenant_id', $tenantId)
                        ->where('status', 'approved')
                        ->whereIn('user_id', $employeeIds)
                        ->where('start_date', '<=', $dateStr)
                        ->where('end_date', '>=', $dateStr)
                        ->get()
                        ->keyBy('user_id');

                    // Fetch holidays for the specific date
                    $holidays = DB::table('holidays')
                        ->where('tenant_id', $tenantId)
                        ->where('start_date', '<=', $dateStr)
                        ->where('end_date', '>=', $dateStr)
                        ->get();

                    // Fetch user weekoffs
                    $weekoffs = DB::table('user_weekoffs')
                        ->where('tenant_id', $tenantId)
                        ->whereIn('user_id', $employeeIds)
                        ->where('status', 1)
                        ->get()
                        ->groupBy('user_id');

                    // Calculate stats for each employee
                    foreach ($employeeIds as $employeeId) {
                        // Check if ANY attendance record has clock_in
                        $attendanceRecords = $attendances->get($employeeId);
                        $hasClockIn = false;
                        
                        if ($attendanceRecords) {
                            foreach ($attendanceRecords as $record) {
                                if (!is_null($record->clock_in)) {
                                    $hasClockIn = true;
                                    break;
                                }
                            }
                        }

                        $leave = $leaves->get($employeeId);
                        $holiday = $holidays->first();
                        $weekoff = $weekoffs->get($employeeId)?->first(function ($wo) use ($dateObj) {
                            if ($wo->off_type == 'day_based') {
                                return strtolower($wo->day_name) == strtolower($dateObj->format('l'));
                            }
                            if ($wo->off_type == 'date_based') {
                                return $dateObj->format('Y-m-d') >= $wo->start_date &&
                                       $dateObj->format('Y-m-d') <= $wo->end_date;
                            }
                            return false;
                        });

                        // Count attendance based on status - checked_in_only is treated as present
                        if ($hasClockIn) {
                            $branchStats[$branch->id]['present']++;
                        } 
                        elseif ($holiday) {
                            $branchStats[$branch->id]['holiday']++;
                        } 
                        elseif ($leave) {
                            $branchStats[$branch->id]['on_leave']++;
                        } 
                        elseif ($weekoff) {
                            $branchStats[$branch->id]['week_off']++;
                        } 
                        else {
                            $branchStats[$branch->id]['absent']++;
                        }
                    }
                }
            }

            // Calculate overall stats
            $overallStats = [
                'total_branches' => $branches->count(),
                'total_employees' => collect($branchStats)->sum('total_employees'),
                'total_present' => collect($branchStats)->sum('present'),
                'total_absent' => collect($branchStats)->sum('absent'),
                'total_leave' => collect($branchStats)->sum('on_leave'),
                'total_holiday' => collect($branchStats)->sum('holiday'),
                'total_weekoff' => collect($branchStats)->sum('week_off'),
            ];

            return view('client.report.attendance.attendance-branch-wise', [
                'branchStats' => $branchStats,
                'stats' => $overallStats,
                'selectedDate' => $dateStr,
                'dateObj' => $dateObj,
            ]);
        } catch (Exception $e) {
            Log::error('Branch Wise Attendance Report Error: ' . $e->getMessage());
            Log::error('Trace: ' . $e->getTraceAsString());
            return back()->with('error', 'Failed to generate branch-wise attendance report: ' . $e->getMessage());
        }
    }

    /**
     * Branch Wise Detail Report - View employees of a specific branch
     */
    public function branchWiseDetailReport(Request $request, $branchId)
    {
        try {
            $tenantId = session('tenant_id');

            if (!$tenantId) {
                return back()->with('error', 'Tenant not found. Please login again.');
            }

            // Get branch details
            $branch = DB::table('branches')
                ->where('tenant_id', $tenantId)
                ->where('id', $branchId)
                ->where('status', 1)
                ->first();

            if (!$branch) {
                return back()->with('error', 'Branch not found.');
            }

            // Get all branches for the modal dropdown
            $allBranches = DB::table('branches')
                ->where('tenant_id', $tenantId)
                ->where('status', 1)
                ->select('id', 'name', 'code')
                ->orderBy('name')
                ->get();

            // Get date from request - default to today
            $selectedDate = $request->get('date', now()->format('Y-m-d'));
            $dateObj = Carbon::parse($selectedDate);
            $dateStr = $dateObj->format('Y-m-d');

            // Get filter values
            $search = $request->get('search');
            $statusFilter = $request->get('status');

            // Fetch employees for this branch
            $employeesQuery = DB::table('users as u')
                ->leftJoin('user_job_details as uj', 'u.id', '=', 'uj.user_id')
                ->leftJoin('departments as d', 'uj.department', '=', 'd.id')
                ->leftJoin('designations as ds', 'uj.designation', '=', 'ds.id')
                ->leftJoin('user_basic_details as ub', 'u.id', '=', 'ub.user_id')
                ->select(
                    'u.id',
                    'u.name',
                    'u.employee_id',
                    'u.email',
                    'd.name as department_name',
                    'ds.name as designation_name',
                    'ub.profile_image'
                )
                ->where('u.tenant_id', $tenantId)
                ->where('u.status', 1)
                ->where('u.role', '!=', 'admin')
                ->where('uj.office_branch', $branchId);

            // Apply search filter
            if ($search) {
                $employeesQuery->where(function ($q) use ($search) {
                    $q->where('u.name', 'LIKE', '%' . $search . '%')
                        ->orWhere('u.employee_id', 'LIKE', '%' . $search . '%')
                        ->orWhere('u.email', 'LIKE', '%' . $search . '%');
                });
            }

            $employees = $employeesQuery->get();

            // If no employees found
            if ($employees->isEmpty()) {
                return view('client.report.attendance.attendance-branch-wise-detail', [
                    'branch' => $branch,
                    'allBranches' => $allBranches,
                    'reportData' => [],
                    'stats' => [],
                    'selectedDate' => $dateStr,
                    'dateObj' => $dateObj,
                    'search' => $search,
                    'statusFilter' => $statusFilter,
                ]);
            }

            $employeeIds = $employees->pluck('id')->toArray();

            // Fetch attendance records for the specific date - use groupBy to handle duplicates
            $attendanceRows = DB::table('attendances as a')
                ->where('a.tenant_id', $tenantId)
                ->whereIn('a.user_id', $employeeIds)
                ->where('a.date', $dateStr)
                ->get()
                ->groupBy('user_id');

            // Fetch approved leaves for the specific date
            $leaveRows = DB::table('leaves')
                ->where('tenant_id', $tenantId)
                ->where('status', 'approved')
                ->whereIn('user_id', $employeeIds)
                ->where('start_date', '<=', $dateStr)
                ->where('end_date', '>=', $dateStr)
                ->get()
                ->groupBy('user_id');

            // Fetch holidays for the specific date
            $holidayRows = DB::table('holidays')
                ->where('tenant_id', $tenantId)
                ->where('start_date', '<=', $dateStr)
                ->where('end_date', '>=', $dateStr)
                ->get();

            // Fetch user weekoffs
            $weekoffRows = DB::table('user_weekoffs')
                ->where('tenant_id', $tenantId)
                ->whereIn('user_id', $employeeIds)
                ->where('status', 1)
                ->get()
                ->groupBy('user_id');

            // Build report data for each employee
            $reportData = [];
            $stats = [
                'total_employees' => $employees->count(),
                'present' => 0,
                'absent' => 0,
                'on_leave' => 0,
                'holiday' => 0,
                'week_off' => 0,
            ];

            foreach ($employees as $employee) {
                // Check if ANY attendance record has clock_in for this employee
                $attendanceRecords = $attendanceRows->get($employee->id);
                $hasClockIn = false;
                $attendance = null;
                
                if ($attendanceRecords) {
                    foreach ($attendanceRecords as $record) {
                        if (!is_null($record->clock_in)) {
                            $hasClockIn = true;
                            $attendance = $record;
                            break;
                        }
                    }
                }

                $leave = $leaveRows->get($employee->id)?->first();
                $holiday = $holidayRows->first();
                $weekoff = $weekoffRows->get($employee->id)?->first(function ($wo) use ($dateObj) {
                    if ($wo->off_type == 'day_based') {
                        return strtolower($wo->day_name) == strtolower($dateObj->format('l'));
                    }
                    if ($wo->off_type == 'date_based') {
                        return $dateObj->format('Y-m-d') >= $wo->start_date &&
                               $dateObj->format('Y-m-d') <= $wo->end_date;
                    }
                    return false;
                });

                // Determine status - checked_in_only is now treated as present
                $status = 'absent';
                $employeeStats = [
                    'present' => 0,
                    'absent' => 0,
                    'leave' => 0,
                    'holiday' => 0,
                    'weekoff' => 0,
                ];

                // If employee has clock_in (with or without clock_out), count as present
                if ($hasClockIn && $attendance && $attendance->clock_in) {
                    $status = 'present';
                    $employeeStats['present'] = 1;
                    $stats['present']++;
                } elseif ($holiday) {
                    $status = 'holiday';
                    $employeeStats['holiday'] = 1;
                    $stats['holiday']++;
                } elseif ($leave) {
                    $status = 'on_leave';
                    $employeeStats['leave'] = 1;
                    $stats['on_leave']++;
                } elseif ($weekoff) {
                    $status = 'week_off';
                    $employeeStats['weekoff'] = 1;
                    $stats['week_off']++;
                } else {
                    $employeeStats['absent'] = 1;
                    $stats['absent']++;
                }

                // Build daily record for the single date
                $dailyRecords = [
                    $dateStr => [
                        'date' => $dateStr,
                        'day' => $dateObj->format('D'),
                        'status' => $status,
                        'clock_in' => $attendance->clock_in ?? null,
                        'clock_out' => $attendance->clock_out ?? null,
                        'total_hours' => $attendance->total_hours ?? null,
                        'late_minutes' => $attendance->late_minutes ?? 0,
                        'early_exit_minutes' => $attendance->early_departure_minutes ?? 0,
                    ]
                ];

                // Apply status filter
                if ($statusFilter && $status !== $statusFilter) {
                    continue;
                }

                $reportData[] = [
                    'employee' => $employee,
                    'stats' => $employeeStats,
                    'daily_records' => $dailyRecords,
                    'total_days' => 1,
                    'status' => $status,
                ];
            }

            return view('client.report.attendance.attendance-branch-wise-detail', [
                'branch' => $branch,
                'allBranches' => $allBranches,
                'reportData' => $reportData,
                'stats' => $stats,
                'selectedDate' => $dateStr,
                'dateObj' => $dateObj,
                'search' => $search,
                'statusFilter' => $statusFilter,
            ]);
        } catch (Exception $e) {
            Log::error('Branch Wise Detail Report Error: ' . $e->getMessage());
            Log::error('Trace: ' . $e->getTraceAsString());
            return back()->with('error', 'Failed to generate branch detail report: ' . $e->getMessage());
        }
    }

    /**
     * Export Branch Wise Detail Report to CSV
     */
    public function branchWiseDetailExport(Request $request, $branchId)
    {
        try {
            $tenantId = session('tenant_id');

            if (!$tenantId) {
                return back()->with('error', 'Tenant not found. Please login again.');
            }

            // Get branch details
            $branch = DB::table('branches')
                ->where('tenant_id', $tenantId)
                ->where('id', $branchId)
                ->where('status', 1)
                ->first();

            if (!$branch) {
                return back()->with('error', 'Branch not found.');
            }

            // Get date from request - default to today
            $selectedDate = $request->get('date', now()->format('Y-m-d'));
            $dateObj = Carbon::parse($selectedDate);
            $dateStr = $dateObj->format('Y-m-d');

            // Get filter values
            $search = $request->get('search');
            $statusFilter = $request->get('status');

            // Fetch employees for this branch
            $employeesQuery = DB::table('users as u')
                ->leftJoin('user_job_details as uj', 'u.id', '=', 'uj.user_id')
                ->leftJoin('departments as d', 'uj.department', '=', 'd.id')
                ->leftJoin('designations as ds', 'uj.designation', '=', 'ds.id')
                ->select(
                    'u.id',
                    'u.name',
                    'u.employee_id',
                    'u.email',
                    'd.name as department_name',
                    'ds.name as designation_name'
                )
                ->where('u.tenant_id', $tenantId)
                ->where('u.status', 1)
                ->where('u.role', '!=', 'admin')
                ->where('uj.office_branch', $branchId);

            if ($search) {
                $employeesQuery->where(function ($q) use ($search) {
                    $q->where('u.name', 'LIKE', '%' . $search . '%')
                        ->orWhere('u.employee_id', 'LIKE', '%' . $search . '%');
                });
            }

            $employees = $employeesQuery->get();
            $employeeIds = $employees->pluck('id')->toArray();

            // Fetch attendance for the specific date
            $attendanceRows = DB::table('attendances')
                ->where('tenant_id', $tenantId)
                ->whereIn('user_id', $employeeIds)
                ->where('date', $dateStr)
                ->get()
                ->groupBy('user_id');

            // Fetch approved leaves for the specific date
            $leaveRows = DB::table('leaves')
                ->where('tenant_id', $tenantId)
                ->where('status', 'approved')
                ->whereIn('user_id', $employeeIds)
                ->where('start_date', '<=', $dateStr)
                ->where('end_date', '>=', $dateStr)
                ->get()
                ->groupBy('user_id');

            // Fetch holidays for the specific date
            $holidayRows = DB::table('holidays')
                ->where('tenant_id', $tenantId)
                ->where('start_date', '<=', $dateStr)
                ->where('end_date', '>=', $dateStr)
                ->get();

            // Fetch user weekoffs
            $weekoffRows = DB::table('user_weekoffs')
                ->where('tenant_id', $tenantId)
                ->whereIn('user_id', $employeeIds)
                ->where('status', 1)
                ->get()
                ->groupBy('user_id');

            // Generate CSV
            $filename = 'branch_attendance_' . ($branch->code ?? $branch->id) . '_' . $dateStr . '.csv';

            $handle = fopen('php://temp', 'w+');
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));

            // Headers
            $this->writeCsvRow($handle, [
                'Branch: ' . $branch->name . ' (' . ($branch->code ?? 'N/A') . ')',
                'Date: ' . $dateObj->format('d M Y'),
            ]);
            $this->writeCsvRow($handle, []);

            // Main header
            $headers = [
                'SR NO.',
                'Employee ID',
                'Employee Name',
                'Email',
                'Department',
                'Designation',
                'Status',
                'Clock In',
                'Clock Out',
                'Total Hours',
                'Late (mins)',
                'Early Exit (mins)'
            ];
            $this->writeCsvRow($handle, $headers);

            // Data rows
            $srNo = 1;
            $stats = [
                'present' => 0,
                'absent' => 0,
                'on_leave' => 0,
                'holiday' => 0,
                'week_off' => 0,
            ];

            foreach ($employees as $employee) {
                // Get attendance records for this employee
                $attendanceRecords = $attendanceRows->get($employee->id);
                $hasClockIn = false;
                $attendance = null;
                
                if ($attendanceRecords) {
                    foreach ($attendanceRecords as $record) {
                        if (!is_null($record->clock_in)) {
                            $hasClockIn = true;
                            $attendance = $record;
                            break;
                        }
                    }
                }

                $leave = $leaveRows->get($employee->id)?->first();
                $holiday = $holidayRows->first();
                $weekoff = $weekoffRows->get($employee->id)?->first(function ($wo) use ($dateObj) {
                    if ($wo->off_type == 'day_based') {
                        return strtolower($wo->day_name) == strtolower($dateObj->format('l'));
                    }
                    if ($wo->off_type == 'date_based') {
                        return $dateObj->format('Y-m-d') >= $wo->start_date &&
                               $dateObj->format('Y-m-d') <= $wo->end_date;
                    }
                    return false;
                });

                // Determine status - checked_in_only is now treated as present
                $status = 'Absent';
                if ($hasClockIn && $attendance && $attendance->clock_in) {
                    $status = 'Present';
                    $stats['present']++;
                } elseif ($holiday) {
                    $status = 'Holiday';
                    $stats['holiday']++;
                } elseif ($leave) {
                    $status = 'On Leave';
                    $stats['on_leave']++;
                } elseif ($weekoff) {
                    $status = 'Week Off';
                    $stats['week_off']++;
                } else {
                    $stats['absent']++;
                }

                // Apply status filter
                if ($statusFilter) {
                    $filterMap = [
                        'present' => 'Present',
                        'absent' => 'Absent',
                        'on_leave' => 'On Leave',
                        'holiday' => 'Holiday',
                        'week_off' => 'Week Off'
                    ];
                    if (isset($filterMap[$statusFilter]) && $status !== $filterMap[$statusFilter]) {
                        continue;
                    }
                }

                // Format clock times
                $clockIn = $attendance && $attendance->clock_in 
                    ? Carbon::parse($attendance->clock_in)->format('h:i A') 
                    : '—';
                
                $clockOut = $attendance && $attendance->clock_out 
                    ? Carbon::parse($attendance->clock_out)->format('h:i A') 
                    : '—';
                
                $totalHours = $attendance && $attendance->total_hours 
                    ? number_format((float)$attendance->total_hours, 2) . ' hrs' 
                    : '—';

                $this->writeCsvRow($handle, [
                    $srNo++,
                    $employee->employee_id ?? 'N/A',
                    $employee->name,
                    $employee->email ?? 'N/A',
                    $employee->department_name ?? 'N/A',
                    $employee->designation_name ?? 'N/A',
                    $status,
                    $clockIn,
                    $clockOut,
                    $totalHours,
                    $attendance->late_minutes ?? 0,
                    $attendance->early_departure_minutes ?? 0,
                ]);
            }

            // Add summary section
            $this->writeCsvRow($handle, []);
            $this->writeCsvRow($handle, ['SUMMARY']);
            $this->writeCsvRow($handle, [
                'Total Employees',
                'Present',
                'Absent',
                'On Leave',
                'Holiday',
                'Week Off'
            ]);
            $this->writeCsvRow($handle, [
                count($employees),
                $stats['present'],
                $stats['absent'],
                $stats['on_leave'],
                $stats['holiday'],
                $stats['week_off']
            ]);

            rewind($handle);
            $content = stream_get_contents($handle);
            fclose($handle);

            return response($content)
                ->header('Content-Type', 'text/csv; charset=utf-8')
                ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
        } catch (Exception $e) {
            Log::error('Branch Wise Detail Export Error: ' . $e->getMessage());
            Log::error('Trace: ' . $e->getTraceAsString());
            return back()->with('error', 'Failed to export: ' . $e->getMessage());
        }
    }

    /**
     * Update employee branch
     */
    public function updateEmployeeBranch(Request $request)
    {
        try {
            $tenantId = session('tenant_id');

            if (!$tenantId) {
                return response()->json(['success' => false, 'message' => 'Tenant not found. Please login again.'], 401);
            }

            $request->validate([
                'user_id' => 'required|exists:users,id',
                'branch_id' => 'required|exists:branches,id'
            ]);

            // Check if user belongs to the tenant
            $user = DB::table('users')
                ->where('id', $request->user_id)
                ->where('tenant_id', $tenantId)
                ->first();

            if (!$user) {
                return response()->json(['success' => false, 'message' => 'User not found.'], 404);
            }

            // Update the branch in user_job_details
            $updated = DB::table('user_job_details')
                ->where('user_id', $request->user_id)
                ->update(['office_branch' => $request->branch_id]);

            if ($updated) {
                return response()->json([
                    'success' => true,
                    'message' => 'Branch updated successfully!'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to update branch. Please try again.'
                ], 500);
            }
        } catch (Exception $e) {
            Log::error('Update Employee Branch Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    public function attendanceReport(Request $request)
    {
        try {
            // Get tenant_id from session
            $tenantId = session('tenant_id');

            if (!$tenantId) {
                return back()->with('error', 'Tenant not found. Please login again.');
            }

            $month = $request->get('month', now()->format('Y-m'));
            $selectedDate = Carbon::createFromFormat('Y-m', $month);
            $monthStart = $selectedDate->copy()->startOfMonth()->format('Y-m-d');

            // ✅ If selected month is current month, limit to today
            $today = now()->format('Y-m-d');
            if ($selectedDate->format('Y-m') == now()->format('Y-m')) {
                $monthEnd = $today;
            } else {
                $monthEnd = $selectedDate->copy()->endOfMonth()->format('Y-m-d');
            }

            // Get filter values
            $search = $request->get('search');
            $statusFilter = $request->get('status');
            $userIdFilter = $request->get('user_id');

            // Fetch all active employees (non-admin) with tenant filter
            $users = DB::table('users as u')
                ->leftJoin('user_job_details as uj', 'u.id', '=', 'uj.user_id')
                ->leftJoin('departments as d', 'uj.department', '=', 'd.id')
                ->leftJoin('designations as ds', 'uj.designation', '=', 'ds.id')
                ->leftJoin('user_basic_details as ub', 'u.id', '=', 'ub.user_id')
                ->select(
                    'u.id',
                    'u.name',
                    'u.employee_id',
                    'u.email',
                    'u.role',
                    'd.name as department_name',
                    'ds.name as designation_name',
                    'uj.reporting_head',
                    'ub.profile_image'
                )
                ->where('u.tenant_id', $tenantId)
                ->where('u.status', 1)
                ->where('u.role', '!=', 'admin')
                ->get();

            // Fetch attendance records for the month with tenant filter
            $attendanceRows = DB::table('attendances as a')
                ->leftJoin('users as u', 'a.user_id', '=', 'u.id')
                ->leftJoin('user_job_details as uj', 'u.id', '=', 'uj.user_id')
                ->leftJoin('departments as d', 'uj.department', '=', 'd.id')
                ->leftJoin('designations as ds', 'uj.designation', '=', 'ds.id')
                ->select(
                    'a.user_id',
                    'a.date',
                    'a.clock_in',
                    'a.clock_out',
                    'a.total_hours',
                    'a.clock_in_address as clockinlocation',
                    'a.clock_out_address as clockoutlocation',
                    'a.clock_in_lat',
                    'a.clock_in_long',
                    'a.clock_out_lat',
                    'a.clock_out_long',
                    'a.scheduled_shift_start as shiftstarttime',
                    'a.scheduled_shift_end as shiftendtime',
                    'a.late_minutes',
                    'a.early_departure_minutes as earlyexitminutes',
                    'a.overtime_minutes',
                    'a.remarks',
                    'u.name',
                    'u.employee_id',
                    'u.email',
                    'd.name as department_name',
                    'ds.name as designation_name'
                )
                ->where('a.tenant_id', $tenantId)
                ->whereBetween('a.date', [$monthStart, $monthEnd])
                ->get();

            // Fetch approved leaves for the month with tenant filter
            $leaveRows = DB::table('leaves')
                ->where('tenant_id', $tenantId)
                ->where('status', 'approved')
                ->where(function ($q) use ($monthStart, $monthEnd) {
                    $q->whereBetween('start_date', [$monthStart, $monthEnd])
                        ->orWhereBetween('end_date', [$monthStart, $monthEnd])
                        ->orWhere(function ($q2) use ($monthStart, $monthEnd) {
                            $q2->where('start_date', '<=', $monthStart)
                                ->where('end_date', '>=', $monthEnd);
                        });
                })
                ->get();

            // Fetch holidays for the month with tenant filter
            $holidayRows = DB::table('holidays')
                ->where('tenant_id', $tenantId)
                ->where(function ($q) use ($monthStart, $monthEnd) {
                    $q->whereBetween('start_date', [$monthStart, $monthEnd])
                        ->orWhereBetween('end_date', [$monthStart, $monthEnd])
                        ->orWhere(function ($q2) use ($monthStart, $monthEnd) {
                            $q2->where('start_date', '<=', $monthStart)
                                ->where('end_date', '>=', $monthEnd);
                        });
                })
                ->get();

            // Fetch user weekoffs with tenant filter
            $weekoffRows = DB::table('user_weekoffs')
                ->where('tenant_id', $tenantId)
                ->where('status', 1)
                ->get();

            // Fetch approved overtime requests for the month with tenant filter
            $overtimeRows = DB::table('overtime_requests')
                ->where('tenant_id', $tenantId)
                ->where('status', 'approved')
                ->whereBetween('date', [$monthStart, $monthEnd])
                ->get();

            // Build the report
            $report = [];

            foreach ($users as $user) {
                // Get attendances for this user
                $userAttendances = $attendanceRows->groupBy(function ($row) {
                    return $row->user_id . '_' . $row->date;
                })->filter(function ($row) use ($user) {
                    $firstRow = $row->first();
                    return $firstRow && $firstRow->user_id == $user->id;
                });

                // Get overtime for this user
                $userOvertime = $overtimeRows->where('user_id', $user->id);

                foreach (CarbonPeriod::create($monthStart, $monthEnd) as $day) {
                    $dateStr = $day->format('Y-m-d');

                    // Get attendance for this specific date
                    $attendance = $userAttendances->first(function ($rows) use ($dateStr) {
                        $firstRow = $rows->first();
                        return $firstRow && $firstRow->date == $dateStr;
                    });

                    if ($attendance) {
                        $attendance = $attendance->first();
                    }

                    // Check if user is on leave
                    $leave = $leaveRows->first(function ($lv) use ($user, $dateStr) {
                        return $lv->user_id == $user->id
                            && $dateStr >= $lv->start_date
                            && $dateStr <= $lv->end_date;
                    });

                    // Check if it's a holiday
                    $holiday = $holidayRows->first(function ($hl) use ($dateStr) {
                        return $dateStr >= $hl->start_date && $dateStr <= $hl->end_date;
                    });

                    // Check if it's a week off
                    $weekoff = $weekoffRows->first(function ($wo) use ($user, $day) {
                        if ($wo->user_id != $user->id) return false;

                        if ($wo->off_type == 'day_based') {
                            return strtolower($wo->day_name) == strtolower($day->format('l'));
                        }

                        if ($wo->off_type == 'date_based') {
                            return $day->format('Y-m-d') >= $wo->start_date && $day->format('Y-m-d') <= $wo->end_date;
                        }

                        return false;
                    });

                    // Check if user has approved overtime for this date
                    $overtime = $userOvertime->first(function ($ot) use ($dateStr) {
                        return $ot->date == $dateStr;
                    });

                    // Determine status
                    $status = 'absent';
                    if ($attendance && $attendance->clock_in && $attendance->clock_out) {
                        $status = 'present';
                    } elseif ($attendance && $attendance->clock_in && !$attendance->clock_out) {
                        $status = 'checked_in_only';
                    } elseif ($holiday) {
                        $status = 'holiday';
                    } elseif ($leave) {
                        $status = 'on_leave';
                    } elseif ($weekoff) {
                        $status = 'week_off';
                    }

                    // Build report entry
                    $report[] = [
                        'user_id' => $user->id,
                        'employee_id' => $user->employee_id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'department' => $user->department_name,
                        'designation' => $user->designation_name,
                        'date' => $dateStr,
                        'status' => $status,
                        'clock_in' => $attendance->clock_in ?? null,
                        'clock_out' => $attendance->clock_out ?? null,
                        'total_hours' => $attendance->total_hours ?? null,
                        'clock_in_location' => $attendance->clockinlocation ?? null,
                        'clock_out_location' => $attendance->clockoutlocation ?? null,
                        'clock_in_lat' => $attendance->clock_in_lat ?? null,
                        'clock_in_lng' => $attendance->clock_in_long ?? null,
                        'clock_out_lat' => $attendance->clock_out_lat ?? null,
                        'clock_out_lng' => $attendance->clock_out_long ?? null,
                        'shift_start_time' => $attendance->shiftstarttime ?? null,
                        'shift_end_time' => $attendance->shiftendtime ?? null,
                        'late_minutes' => $attendance->late_minutes ?? 0,
                        'early_exit_minutes' => $attendance->earlyexitminutes ?? 0,
                        'overtime_minutes' => $overtime ? ($overtime->approved_hours * 60) : 0,
                        'overtime_hours' => $overtime ? $overtime->approved_hours : null,
                        'overtime_reason' => $overtime ? $overtime->reason : null,
                        'remarks' => $attendance->remarks ?? null,
                        'leave_type' => $leave->leave_type ?? null,
                        'holiday_name' => $holiday->name ?? null,
                        'weekoff_type' => $weekoff->off_type ?? null,
                    ];
                }
            }

            $reportCollection = collect($report);

            // ✅ Sort by date (ascending) - all 1st, then 2nd, etc.
            $reportCollection = $reportCollection->sortBy('date')->values();

            // Apply search filter (by employee name or ID)
            if ($search) {
                $reportCollection = $reportCollection->filter(function ($item) use ($search) {
                    return stripos($item['name'], $search) !== false ||
                        stripos($item['employee_id'], $search) !== false;
                });
            }

            // Apply status filter
            if ($statusFilter) {
                $reportCollection = $reportCollection->where('status', $statusFilter);
            }
            if ($userIdFilter) {
                $reportCollection = $reportCollection->where('user_id', (int)$userIdFilter);
            }

            // Calculate stats from full dataset
            $stats = [
                'totalEmployees' => $reportCollection->pluck('user_id')->unique()->count(),
                'presentCount' => $reportCollection->where('status', 'present')->count(),
                'absentCount' => $reportCollection->where('status', 'absent')->count(),
                'leaveCount' => $reportCollection->where('status', 'on_leave')->count(),
            ];

            // Paginate the report data
            $perPage = $request->get('per_page', 50);
            $currentPage = $request->get('page', 1);
            $total = $reportCollection->count();
            $paginated = $reportCollection->slice(($currentPage - 1) * $perPage, $perPage);

            // Get unique employees for dropdown with tenant filter
            $employees = DB::table('users')
                ->select('id', 'name', 'employee_id', 'email')
                ->where('tenant_id', $tenantId)
                ->where('status', 1)
                ->where('role', '!=', 'admin')
                ->get();

            return view('client.report.attendance.attendance-detail', [
                'reportData' => new \Illuminate\Pagination\LengthAwarePaginator(
                    $paginated,
                    $total,
                    $perPage,
                    $currentPage,
                    ['path' => route('report.attendance.index'), 'query' => $request->query()]
                ),
                'stats' => $stats,
                'employees' => $employees,
                'monthStart' => $monthStart,
                'monthEnd' => $monthEnd,
            ]);
        } catch (Exception $e) {
            \Log::error('Attendance Report Error: ' . $e->getMessage());
            \Log::error('Trace: ' . $e->getTraceAsString());
            return back()->with('error', 'Failed to generate attendance report: ' . $e->getMessage());
        }
    }

    public function exportAttendance(Request $request)
    {
        try {
            // Get tenant_id from session
            $tenantId = session('tenant_id');

            if (!$tenantId) {
                return back()->with('error', 'Tenant not found. Please login again.');
            }

            $month = $request->get('month', now()->format('Y-m'));
            $selectedDate = Carbon::createFromFormat('Y-m', $month);
            $monthStart = $selectedDate->copy()->startOfMonth()->format('Y-m-d');
            $monthEnd = $selectedDate->copy()->endOfMonth()->format('Y-m-d');

            // Get filter values
            $search = $request->get('search');
            $statusFilter = $request->get('status');
            $userIdFilter = $request->get('user_id');

            // Fetch all active employees (non-admin) with tenant filter
            $users = DB::table('users as u')
                ->leftJoin('user_job_details as uj', 'u.id', '=', 'uj.user_id')
                ->leftJoin('departments as d', 'uj.department', '=', 'd.id')
                ->leftJoin('designations as ds', 'uj.designation', '=', 'ds.id')
                ->select(
                    'u.id',
                    'u.name',
                    'u.employee_id',
                    'u.email',
                    'd.name as department_name',
                    'ds.name as designation_name'
                )
                ->where('u.tenant_id', $tenantId)
                ->where('u.status', 1)
                ->where('u.role', '!=', 'admin')
                ->get();

            // Fetch attendance records for the month with tenant filter
            $attendanceRows = DB::table('attendances as a')
                ->where('a.tenant_id', $tenantId)
                ->whereBetween('a.date', [$monthStart, $monthEnd])
                ->get()
                ->groupBy(function ($row) {
                    return $row->user_id . '_' . $row->date;
                });

            // Fetch approved leaves with tenant filter
            $leaveRows = DB::table('leaves')
                ->where('tenant_id', $tenantId)
                ->where('status', 'approved')
                ->where(function ($q) use ($monthStart, $monthEnd) {
                    $q->whereBetween('start_date', [$monthStart, $monthEnd])
                        ->orWhereBetween('end_date', [$monthStart, $monthEnd])
                        ->orWhere(function ($q2) use ($monthStart, $monthEnd) {
                            $q2->where('start_date', '<=', $monthStart)
                                ->where('end_date', '>=', $monthEnd);
                        });
                })
                ->get();

            // Fetch holidays with tenant filter
            $holidayRows = DB::table('holidays')
                ->where('tenant_id', $tenantId)
                ->where(function ($q) use ($monthStart, $monthEnd) {
                    $q->whereBetween('start_date', [$monthStart, $monthEnd])
                        ->orWhereBetween('end_date', [$monthStart, $monthEnd])
                        ->orWhere(function ($q2) use ($monthStart, $monthEnd) {
                            $q2->where('start_date', '<=', $monthStart)
                                ->where('end_date', '>=', $monthEnd);
                        });
                })
                ->get();

            // Fetch user weekoffs with tenant filter
            $weekoffRows = DB::table('user_weekoffs')
                ->where('tenant_id', $tenantId)
                ->where('status', 1)
                ->get();

            // ✅ FETCH APPROVED OVERTIME REQUESTS for export
            $overtimeRows = DB::table('overtime_requests')
                ->where('tenant_id', $tenantId)
                ->where('status', 'approved')
                ->whereBetween('date', [$monthStart, $monthEnd])
                ->get();

            // Build complete report data (ALL records, no pagination)
            $reportData = [];

            foreach ($users as $user) {
                // Get overtime for this user
                $userOvertime = $overtimeRows->where('user_id', $user->id);

                foreach (CarbonPeriod::create($monthStart, $monthEnd) as $day) {
                    $dateStr = $day->format('Y-m-d');
                    $key = $user->id . '_' . $dateStr;
                    $attendance = $attendanceRows->get($key);
                    $attendance = $attendance ? $attendance->first() : null;

                    // Check if user is on leave
                    $leave = $leaveRows->first(function ($lv) use ($user, $dateStr) {
                        return $lv->user_id == $user->id
                            && $dateStr >= $lv->start_date
                            && $dateStr <= $lv->end_date;
                    });

                    // Check if it's a holiday
                    $holiday = $holidayRows->first(function ($hl) use ($dateStr) {
                        return $dateStr >= $hl->start_date && $dateStr <= $hl->end_date;
                    });

                    // Check if it's a week off
                    $weekoff = $weekoffRows->first(function ($wo) use ($user, $day) {
                        if ($wo->user_id != $user->id) return false;

                        if ($wo->off_type == 'day_based') {
                            return strtolower($wo->day_name) == strtolower($day->format('l'));
                        }

                        if ($wo->off_type == 'date_based') {
                            return $day->format('Y-m-d') >= $wo->start_date && $day->format('Y-m-d') <= $wo->end_date;
                        }

                        return false;
                    });

                    // ✅ Check if user has approved overtime for this date
                    $overtime = $userOvertime->first(function ($ot) use ($dateStr) {
                        return $ot->date == $dateStr;
                    });

                    // Determine status
                    $status = 'absent';
                    if ($attendance && $attendance->clock_in && $attendance->clock_out) {
                        $status = 'present';
                    } elseif ($attendance && $attendance->clock_in && !$attendance->clock_out) {
                        $status = 'checked_in_only';
                    } elseif ($holiday) {
                        $status = 'holiday';
                    } elseif ($leave) {
                        $status = 'on_leave';
                    } elseif ($weekoff) {
                        $status = 'week_off';
                    }

                    // Build report entry with same structure as attendanceReport
                    $reportData[] = [
                        'user_id' => $user->id,
                        'employee_name' => $user->name,
                        'employee_id' => $user->employee_id ?? 'N/A',
                        'department' => $user->department_name ?? 'N/A',
                        'designation' => $user->designation_name ?? 'N/A',
                        'date' => $dateStr,
                        'day' => Carbon::parse($dateStr)->format('D'),
                        'status' => $status,
                        'clock_in' => $attendance->clock_in ?? null,
                        'clock_out' => $attendance->clock_out ?? null,
                        'total_hours' => $attendance->total_hours ?? null,
                        'clock_in_location' => $attendance->clock_in_address ?? null,
                        'clock_out_location' => $attendance->clock_out_address ?? null,
                        'shift_start_time' => $attendance->scheduled_shift_start ?? null,
                        'shift_end_time' => $attendance->scheduled_shift_end ?? null,
                        'late_minutes' => $attendance->late_minutes ?? 0,
                        'early_exit_minutes' => $attendance->early_departure_minutes ?? 0,
                        // ✅ Use overtime from approved overtime requests if available
                        'overtime_minutes' => $overtime ? ($overtime->approved_hours * 60) : 0,
                        'overtime_hours' => $overtime ? $overtime->approved_hours : null,
                        'overtime_reason' => $overtime ? $overtime->reason : null,
                        'remarks' => $attendance->remarks ?? null,
                        'leave_type' => $leave->leave_type ?? null,
                        'holiday_name' => $holiday->name ?? null,
                        'weekoff_type' => $weekoff->off_type ?? null,
                    ];
                }
            }

            // Convert to collection for filtering
            $reportCollection = collect($reportData);

            // Apply search filter (by employee name or ID) - same as attendanceReport
            if ($search) {
                $reportCollection = $reportCollection->filter(function ($item) use ($search) {
                    return stripos($item['employee_name'], $search) !== false ||
                        stripos($item['employee_id'], $search) !== false;
                });
            }

            // Apply status filter - same as attendanceReport
            if ($statusFilter) {
                $reportCollection = $reportCollection->where('status', $statusFilter);
            }

            // Apply user filter - same as attendanceReport
            if ($userIdFilter) {
                $reportCollection = $reportCollection->where('user_id', (int)$userIdFilter);
            }

            // Generate CSV
            $filename = 'attendance_report_' . Carbon::now()->format('Y-m-d_H-i') . '.csv';

            // Create a temporary file handle
            $handle = fopen('php://temp', 'w+');

            // Add UTF-8 BOM for Excel compatibility
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));

            // Headers
            $this->writeCsvRow($handle, [
                'SR NO.',
                'Employee Name',
                'Employee ID',
                'Department',
                'Designation',
                'Date',
                'Day',
                'Status',
                'Clock In',
                'Clock Out',
                'Total Hours',
                'Shift',
                'Clock In Location',
                'Clock Out Location',
                'Late (mins)',
                'Early Exit (mins)',
                'OT (mins)',
                'OT Hours',
                'OT Reason',
                'Remarks'
            ]);

            // Data rows - use filtered collection
            $srNo = 1;
            foreach ($reportCollection as $row) {
                // Format status for display
                $statusLabel = match ($row['status']) {
                    'present' => 'Present',
                    'absent' => 'Absent',
                    'on_leave' => 'On Leave' . ($row['leave_type'] ? ' (' . $row['leave_type'] . ')' : ''),
                    'holiday' => 'Holiday' . ($row['holiday_name'] ? ' (' . $row['holiday_name'] . ')' : ''),
                    'week_off' => 'Week Off',
                    'checked_in_only' => 'Checked In Only',
                    default => ucfirst($row['status'])
                };

                // Format shift times
                $shift = '—';
                if ($row['shift_start_time'] || $row['shift_end_time']) {
                    $start = $row['shift_start_time']
                        ? Carbon::parse($row['shift_start_time'])->format('h:i A')
                        : '--';
                    $end = $row['shift_end_time']
                        ? Carbon::parse($row['shift_end_time'])->format('h:i A')
                        : '--';
                    $shift = $start . ' - ' . $end;
                }

                // Format clock in/out
                $clockIn = $row['clock_in']
                    ? Carbon::parse($row['clock_in'])->format('d M Y h:i A')
                    : '—';
                $clockOut = $row['clock_out']
                    ? Carbon::parse($row['clock_out'])->format('d M Y h:i A')
                    : '—';

                $this->writeCsvRow($handle, [
                    $srNo++,
                    $row['employee_name'],
                    $row['employee_id'],
                    $row['department'],
                    $row['designation'],
                    Carbon::parse($row['date'])->format('d M Y'),
                    $row['day'],
                    $statusLabel,
                    $clockIn,
                    $clockOut,
                    $row['total_hours'] ? number_format((float)$row['total_hours'], 2) . ' hrs' : '—',
                    $shift,
                    $row['clock_in_location'] ?? '—',
                    $row['clock_out_location'] ?? '—',
                    $row['late_minutes'] ?? 0,
                    $row['early_exit_minutes'] ?? 0,
                    $row['overtime_minutes'] ?? 0,
                    $row['overtime_hours'] ? number_format((float)$row['overtime_hours'], 2) . ' hrs' : '—',
                    $row['overtime_reason'] ?? '—',
                    $row['remarks'] ?? '—'
                ]);
            }

            // Reset the file pointer
            rewind($handle);

            // Get the content
            $csvContent = stream_get_contents($handle);
            fclose($handle);

            // Return as download
            return response($csvContent, 200, [
                'Content-Type' => 'text/csv; charset=utf-8',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
                'Pragma' => 'no-cache',
                'Expires' => '0',
            ]);
        } catch (Exception $e) {
            \Log::error('Export Error: ' . $e->getMessage());
            \Log::error('Trace: ' . $e->getTraceAsString());
            return back()->with('error', 'Failed to export: ' . $e->getMessage());
        }
    }

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
                'leave_type_id' => $request->input('leave_type_id'),
                'remarks' => $request->input('remarks'),
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

    private function getUserShiftForDate($userId, $date, $tenantId)
    {
        try {
            // Honours the tenant's custom-shifts toggle (fixed company shift when
            // off, the per-date assignment chain when on).
            $shift = app(\App\Services\Attendance\TenantShiftResolver::class)
                ->forUserDate((int) $userId, (int) $tenantId, $date);

            if (!$shift) {
                return null;
            }

            $userShiftId = DB::table('user_shifts')
                ->where('user_id', $userId)
                ->where('tenant_id', $tenantId)
                ->where('date', $date)
                ->value('id');

            return [
                'shift_id' => $shift->id,
                'name' => $shift->name,
                'start_time' => $shift->start_time,
                'end_time' => $shift->end_time,
                'grace_minutes' => $shift->grace_minutes ?? 0,
                'user_shift_id' => $userShiftId,
            ];
        } catch (Exception $e) {
            Log::error('Error getting user shift: ' . $e->getMessage());
            return null;
        }
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
                    $isOvernight = false;
                    $shiftStart = Carbon::parse($shift->start_time);
                    $shiftEnd = Carbon::parse($shift->end_time);
                    if ($shiftEnd->lte($shiftStart)) {
                        $isOvernight = true;
                    }

                    return response()->json([
                        'success' => true,
                        'shift' => [
                            'shift_name' => $shift->name,
                            'start_time' => $shift->start_time,
                            'end_time' => $shift->end_time,
                            'grace_minutes' => $shift->grace_minutes ?? 0,
                            'shift_id' => $shift->id,
                            'is_overnight' => $isOvernight
                        ]
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
