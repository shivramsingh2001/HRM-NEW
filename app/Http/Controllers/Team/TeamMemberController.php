<?php

namespace App\Http\Controllers\Team;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Language;
use App\Models\User;
use App\Traits\AuthorizesByScope;
use DateTime;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * A team member's profile page (team.member-detail) and its attendance calendar /
 * table / stats endpoints. Moved out of TeamController unchanged (code-quality plan,
 * Phase 3); route names are the same.
 */
class TeamMemberController extends Controller
{
    use \App\Http\Controllers\Concerns\FiltersReportEmployees;
    use \App\Http\Controllers\Concerns\SanitizesCsv;
    use \App\Http\Controllers\Concerns\TeamAttendanceStatus;
    use AuthorizesByScope;

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

            if (! $user) {
                return redirect()->back()->with('error', 'User not found.');
            }

            // Check if user has permission to view this profile
            if (! $this->canViewUserProfile($authUser, $user)) {
                return redirect()->back()->with('error', 'Unauthorized access.');
            }

            $userCreatedDate = $user->created_at->format('Y-m-d');
            $today = date('Y-m-d');

            /* --------------------------------------------
             | 1. GET USER PROFILE INFORMATION
             -------------------------------------------- */
            $userInfo = $this->getUserProfileData($id);

            if (! $userInfo) {
                return redirect()->back()->with('error', 'User information not found.');
            }

            /* ---------------- LANGUAGE HANDLING ---------------- */
            $languageIds = $userInfo->language
                ? json_decode($userInfo->language, true)
                : [];

            $languageNames = [];

            if (! empty($languageIds)) {
                $languageNames = Language::whereIn('id', $languageIds)
                    ->pluck('name')
                    ->toArray();
            }

            /* --------------------------------------------
             | 2. GET MONTH FILTER VALUES
             -------------------------------------------- */
            $selectedMonth = request()->get('month', date('Y-m'));

            // Validate month format
            if (! preg_match('/^\d{4}-\d{2}$/', $selectedMonth)) {
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
            $attendanceData = app(\App\Services\Team\TeamMemberAttendance::class)->getAttendanceDataForUser($displayStartDate, $displayEndDate, $id);
            // Add task counts to attendance data
            $attendanceData = app(\App\Services\Team\TeamMemberAttendance::class)->addTaskCountsToAttendance($attendanceData, $id);
            $attendanceSummary = app(\App\Services\Team\TeamMemberAttendance::class)->calculateSummary($attendanceData);

            /* --------------------------------------------
             | 4. PREPARE DATA FOR VIEWS
             -------------------------------------------- */
            // Process calendar data for FullCalendar
            $calendarData = [];
            foreach ($attendanceData as $record) {
                $event = app(\App\Services\Team\TeamMemberAttendance::class)->createCalendarEvent($record);
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
                        ? file_url($userInfo->profile_image, 'profile_photo')
                : asset('/profile2.jpg');

            // Format documents
            $documents = $this->getUserDocuments($userInfo);

            $profileHeader = \App\Services\Dashboard\ProfileHeaderData::fromProfile($userInfo, $profileImage, $todayAttendance);

            // Calculate age from DOB
            $age = null;
            if ($userInfo->dob) {
                $dob = new DateTime($userInfo->dob);
                $todayDate = new DateTime;
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
                'profileHeader',
                'documents',
                'age',
                'months',
                'selectedMonth',
                'monthStartDate',
                'monthEndDate',
                'userCreatedDate'
            ));
        } catch (Exception $e) {
            Log::error('Error in viewUserProfile: '.$e->getMessage());
            Log::error($e->getTraceAsString());

            return redirect()->back()->with('error', 'Something went wrong. Please try again.');
        }
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
            if (! $targetUser) {
                return response()->json([
                    'status' => false,
                    'message' => 'User not found',
                ], 404);
            }

            if (! $this->canViewUserProfile($authUser, $targetUser)) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthorized access',
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

            $attendanceData = app(\App\Services\Team\TeamMemberAttendance::class)->getAttendanceDataForUser($startDate, $endDate, $id);
            $attendanceSummary = app(\App\Services\Team\TeamMemberAttendance::class)->calculateSummary($attendanceData);

            return response()->json([
                'status' => true,
                'summary' => $attendanceSummary,
                'month_name' => $selectedDate->format('F Y'),
                'date_range' => [
                    'start' => $startDate,
                    'end' => $endDate,
                ],
            ]);
        } catch (Exception $e) {
            Log::error('Error in userAttendanceStats: '.$e->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'An error occurred while fetching attendance stats.',
            ], 500);
        }
    }

    public function userAttendanceCalendarData(Request $request, $id)
    {
        try {
            $id = decrypt($id);
            $authUser = Auth::user();

            $targetUser = User::find($id);
            if (! $targetUser) {
                return response()->json([
                    'status' => false,
                    'message' => 'User not found',
                ], 404);
            }

            if (! $this->canViewUserProfile($authUser, $targetUser)) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthorized access',
                ], 403);
            }

            $startDate = $request->start_date ?? date('Y-m-01');
            $endDate = $request->end_date ?? date('Y-m-t');

            $attendanceData = app(\App\Services\Team\TeamMemberAttendance::class)->getAttendanceDataForUser($startDate, $endDate, $id);

            // Add task counts to attendance data
            $attendanceData = app(\App\Services\Team\TeamMemberAttendance::class)->addTaskCountsToAttendance($attendanceData, $id);

            $events = [];
            foreach ($attendanceData as $record) {
                $dateStr = $record->formatted_date ?? $record->date;
                $status = $record->day_status;
                $taskCount = $record->task_count ?? 0;

                if (! $dateStr || ! $status) {
                    continue;
                }

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
                        $bgColor = '#0D6EFD';
                        $borderColor = '#0B5ED7';
                        $color = '#ffffff';
                        $title = 'Present'.$hoursText;
                        break;
                    case 'halfday':
                        $bgColor = '#60a5fa';
                        $borderColor = '#3b82f6';
                        $color = '#ffffff';
                        $title = 'halfday'.$hoursText;
                        break;
                    case 'absent':
                        $bgColor = '#0D6EFD';
                        $borderColor = '#1e293b';
                        $color = '#ffffff';
                        $title = 'Absent';
                        break;
                    case 'first half leave':
                    case 'second half leave':
                    case 'full day leave':
                        $bgColor = '#dbeafe';
                        $borderColor = '#93c5fd';
                        $color = '#0B5ED7';
                        $title = 'Leave';
                        break;
                    case 'holiday':
                        $bgColor = '#bfdbfe';
                        $borderColor = '#93c5fd';
                        $color = '#0B5ED7';
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
                        $color = '#0D6EFD';
                        $title = 'Checked In Only';
                        break;
                    case 'upcoming':
                        $bgColor = '#f8fafc';
                        $borderColor = '#cbd5e1';
                        $color = '#64748b';
                        $title = 'Upcoming';
                        break;
                }

                $clockInTime = $clockOutTime = '--:--';
                $totalHours = '--';

                if ($record->clock_in) {
                    $clockInTime = Carbon::parse($record->clock_in)->format('h:i A');
                }
                if ($record->clock_out) {
                    $clockOutTime = clock_out_time($record->clock_out, $record->date ?? $record->clock_in);
                }
                if ($record->total_hours) {
                    $totalHours = $record->total_hours;
                }

                $events[] = [
                    'id' => 'attendance_'.$dateStr,
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
                    'holiday_name' => $record->holiday_name ?? null,
                    'clock_in_at' => $record->clock_in, 'clock_out_at' => $record->clock_out, 'shifts' => $record->shifts ?? [], 'details' => $record->details ?? null, // Week/Day blocks + hover card
                ];
            }

            return response()->json([
                'status' => true,
                'events' => $events,
                'month' => $startDate,
            ]);
        } catch (Exception $e) {
            Log::error('Error in userAttendanceCalendar: '.$e->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'An error occurred while fetching calendar data.',
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
            if (! $targetUser) {
                return response()->json([
                    'status' => false,
                    'message' => 'User not found',
                ], 404);
            }

            if (! $this->canViewUserProfile($authUser, $targetUser)) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthorized access',
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
                    'message' => 'Start date cannot be after end date',
                ], 400);
            }

            $data = app(\App\Services\Team\TeamMemberAttendance::class)->getAttendanceDataForUser($startDate, $endDate, $id);

            $tableData = [];
            foreach ($data as $record) {
                $tableData[] = [
                    'date' => Carbon::parse($record->date)->format('d M, Y'),
                    'day_name' => $record->day_name,
                    'clock_in' => $record->clock_in ? Carbon::parse($record->clock_in)->format('h:i A') : '--:--',
                    'clock_out' => clock_out_time($record->clock_out, $record->date ?? $record->clock_in, 'h:i A', '--:--'),
                    'total_hours' => $record->total_hours ? $record->total_hours.'h' : '--',
                    'day_status' => $record->day_status,
                    'badge_class' => 'badge-'.strtolower(str_replace(' ', '-', $record->day_status)),
                ];
            }

            return response()->json([
                'status' => true,
                'data' => $tableData,
                'total' => count($tableData),
                'date_range' => [
                    'start' => $startDate,
                    'end' => $endDate,
                ],
            ]);
        } catch (Exception $e) {
            Log::error('Error in userAttendanceTableData: '.$e->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'An error occurred while fetching table data.',
            ], 500);
        }
    }

    /** Same rule as the mobile admin API — see TeamMemberProfile::canView(). */
    private function canViewUserProfile($authUser, $targetUser)
    {
        return app(\App\Services\Team\TeamMemberProfile::class)->canView($authUser, $targetUser);
    }

    private function getUserProfileData($userId)
    {
        return app(\App\Services\Team\TeamMemberProfile::class)->profileData((int) $userId);
    }

    private function getUserDocuments($userInfo)
    {
        return app(\App\Services\Team\TeamMemberProfile::class)->documents($userInfo);
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
}
