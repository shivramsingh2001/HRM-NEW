<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use App\Models\AttendanceTrackingPoint;
use App\Services\RbacService;
use Exception;

class AttendanceController extends Controller
{
    public function view_ai_all(Request $request)
    {
        try {
            $authUser = Auth::user();
            $tenantId = (int) ($authUser->tenant_id ?? Session::get('tenant_id') ?? 0);

            if (!$tenantId) {
                return response()->json(['success' => false, 'message' => 'Tenant not found'], 400);
            }

            // Permission-based access: the old role-string allowlist blocked
            // any custom role (e.g. a seeded Finance/Recruiter role) even
            // though they might hold a real attendance:view grant.
            $attendanceScope = app(RbacService::class)->scopeFor($authUser, 'attendance', 'view');
            if ($attendanceScope === null) {
                return response()->json(['success' => false, 'message' => 'Unauthorized access'], 403);
            }

            // Get date range (validated to strict Y-m-d, capped to 366 days)
            $startDate = $this->safeDate($request->input('start_date'), date('Y-m-01'));
            $endDate = $this->safeDate($request->input('end_date'), date('Y-m-d'));
            if ($startDate > $endDate) {
                [$startDate, $endDate] = [$endDate, $startDate];
            }
            if ((strtotime($endDate) - strtotime($startDate)) / 86400 > 366) {
                $endDate = date('Y-m-d', strtotime($startDate . ' +366 days'));
            }

            $includeTracks = filter_var($request->input('include_tracks', true), FILTER_VALIDATE_BOOLEAN);

            // Build scope-based user filter (parameterised)
            [$userFilterSql, $userFilterBindings] = $this->buildUserFilter($authUser, $request->input('user_id'), $attendanceScope);

            // Get attendance data
            $attendances = $this->getAttendanceData($tenantId, $startDate, $endDate, $userFilterSql, $userFilterBindings);

            // Format the data
            $processedData = $this->formatAttendanceData($attendances, $includeTracks);

            // Calculate summary
            $summary = $this->calculateSummary($processedData, $startDate, $endDate, $tenantId);

            return response()->json([
                'success' => true,
                'message' => 'Attendance data fetched successfully',
                'data' => $processedData,
                'summary' => $summary
            ], 200);

        } catch (Exception $e) {
            \Log::error('AI attendance view_ai_all failed', ['user_id' => Auth::id(), 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Unable to fetch attendance data. Please try again later.'
            ], 500);
        }
    }

    /**
     * Return $value as a strict Y-m-d string, or $default when it is not a valid date.
     */
    private function safeDate($value, string $default): string
    {
        if (!is_string($value) || $value === '') {
            return $default;
        }
        try {
            $parsed = \Carbon\Carbon::createFromFormat('Y-m-d', $value);
            return ($parsed && $parsed->format('Y-m-d') === $value) ? $value : $default;
        } catch (\Throwable $e) {
            report($e);
            return $default;
        }
    }

    /**
     * Build a role-based user filter as [sqlFragment, bindings].
     */
    private function buildUserFilter($authUser, $requestUserId, string $scope): array
    {
        $requestUserId = (is_scalar($requestUserId) && ctype_digit((string) $requestUserId) && (int) $requestUserId > 0)
            ? (int) $requestUserId
            : null;

        $me = (int) $authUser->id;

        if ($scope === 'own') {
            return [' AND u.id = ? ', [$me]];
        }

        if ($scope === 'team') {
            $reportsToMe = ' u.id IN (SELECT user_id FROM user_reporting_heads WHERE reporting_head_id = ?) ';
            if ($requestUserId) {
                return [" AND u.id = ? AND ($reportsToMe OR u.id = ?) ", [$requestUserId, $me, $me]];
            }
            return [" AND ($reportsToMe OR u.id = ?) ", [$me, $me]];
        }

        // company
        if ($requestUserId) {
            return [' AND u.id = ? ', [$requestUserId]];
        }

        return ['', []];
    }

    /**
     * Get attendance data from database.
     * $startDate / $endDate are guaranteed ^\d{4}-\d{2}-\d{2}$ by safeDate();
     * every other value is a bound parameter.
     */
    private function getAttendanceData($tenantId, $startDate, $endDate, $userFilterSql, array $userFilterBindings)
    {
        $query = "
        WITH RECURSIVE dates AS (
            SELECT DATE('{$startDate}') as date
            UNION ALL
            SELECT DATE_ADD(date, INTERVAL 1 DAY)
            FROM dates
            WHERE DATE_ADD(date, INTERVAL 1 DAY) <= DATE('{$endDate}')
        )
        SELECT
            u.id as user_id,
            u.name,
            u.employee_id,
            u.role,
            d.date,
            a.clock_in,
            a.clock_out,
            a.total_hours,
            a.clock_in_address,
            a.clock_out_address,
            a.id as attendance_id,
            -- Scalar subqueries (not JOINs) so several matching week-off / leave /
            -- holiday rows can never duplicate a user-day.
            (SELECT wo.id FROM user_weekoffs wo
                WHERE wo.user_id = u.id AND wo.tenant_id = ? AND wo.status = 1
                  AND ((wo.off_type = 'date_based' AND d.date BETWEEN wo.start_date AND wo.end_date)
                    OR (wo.off_type = 'day_based' AND wo.day_name = DAYNAME(d.date)))
                LIMIT 1) as weekoff_id,
            (SELECT l.id FROM leaves l
                WHERE l.user_id = u.id AND l.tenant_id = ? AND l.status = 'approved'
                  AND d.date BETWEEN DATE(l.start_date) AND DATE(l.end_date)
                LIMIT 1) as leave_id,
            (SELECT h.name FROM holidays h
                WHERE h.tenant_id = ? AND h.status = 1
                  AND d.date BETWEEN DATE(h.start_date) AND DATE(h.end_date)
                LIMIT 1) as holiday_name,
            (SELECT COUNT(*) FROM attendance_tracking_points atp
                INNER JOIN attendance_tracking_sessions ats ON ats.id = atp.session_id
                WHERE ats.attendance_id = a.id) as track_count
        FROM dates d
        CROSS JOIN users u
        LEFT JOIN attendances a ON u.id = a.user_id AND a.date = d.date AND a.tenant_id = ?
        WHERE u.status = 1 AND u.tenant_id = ?
        {$userFilterSql}
        ORDER BY d.date DESC, u.id
    ";

        // Placeholder order: weekoff, leave, holiday subqueries, then the attendances join, then WHERE.
        $bindings = array_merge([$tenantId, $tenantId, $tenantId, $tenantId, $tenantId], $userFilterBindings);

        return DB::select($query, $bindings);
    }

    /**
     * Format attendance data
     */
    private function formatAttendanceData($attendances, $includeTracks)
{
    $formatted = [];
    $attendanceIds = [];
    
    // First pass - collect attendance IDs
    foreach ($attendances as $record) {
        $record = (array) $record;
        if (!empty($record['attendance_id'])) {
            $attendanceIds[] = $record['attendance_id'];
        }
    }
    
    // Second pass - format data with tracks
    foreach ($attendances as $record) {
        $record = (array) $record;
        
        // Determine status — a punch wins, then approved leave, holiday, week-off.
        // (Leave and holidays used to fall through to "Absent".)
        if ($record['clock_in'] && $record['clock_out']) {
            $status = 'Present';
            $statusCategory = 'present';
        } elseif ($record['clock_in']) {
            $status = 'Checked In Only';
            $statusCategory = 'present';
        } elseif (!empty($record['leave_id'])) {
            $status = 'On Leave';
            $statusCategory = 'leave';
        } elseif (!empty($record['holiday_name'])) {
            $status = 'Holiday';
            $statusCategory = 'holiday';
        } elseif ($record['weekoff_id']) {
            $status = 'Week Off';
            $statusCategory = 'weekoff';
        } else {
            $status = 'Absent';
            $statusCategory = 'absent';
        }
        
        $formattedRecord = [
            'user_id' => $record['user_id'],
            'name' => $record['name'],
            'employee_id' => $record['employee_id'],
            'date' => $record['date'],
            'day_name' => date('l', strtotime($record['date'])),
            'clock_in' => $record['clock_in'],
            'clock_out' => $record['clock_out'],
            'clock_in_formatted' => $record['clock_in'] ? date('h:i A', strtotime($record['clock_in'])) : null,
            'clock_out_formatted' => $record['clock_out'] ? date('h:i A', strtotime($record['clock_out'])) : null,
            'total_hours' => $record['total_hours'],
            'clock_in_address' => $record['clock_in_address'],
            'clock_out_address' => $record['clock_out_address'],
            'status' => $status,
            'status_category' => $statusCategory,
            'holiday_name' => $record['holiday_name'] ?? null,
        ];

        $formatted[] = $formattedRecord;
    }
    
    return $formatted;
}

    /**
     * Get location tracks for an attendance
     */
    private function getLocationTracks($attendanceId)
    {
        return AttendanceTrackingPoint::forAttendanceId($attendanceId)
            ->get(['track_time', 'lat', 'long', 'address', 'battery_per']);
    }

    /**
     * Calculate summary statistics
     */
    private function calculateSummary($data, $startDate, $endDate, $tenantId)
    {
        $summary = [
            'total_records' => count($data),
            'by_status' => [
                'present' => 0,
                'absent' => 0,
                'weekoff' => 0,
                'checked_in_only' => 0,
                'leave' => 0,
                'holiday' => 0,
            ],
            'by_user' => [],
            'date_range' => [
                'start' => $startDate,
                'end' => $endDate
            ],
            // 'tenant_id' => $tenantId
        ];
        
        $userStats = [];
        
        foreach ($data as $record) {
            $status = $record['status_category'];
            $userId = $record['user_id'];
            
            // Count by status
            if ($status === 'present') {
                if (empty($record['clock_out'])) {
                    $summary['by_status']['checked_in_only']++;
                } else {
                    $summary['by_status']['present']++;
                }
            } else {
                $summary['by_status'][$status]++;
            }
            
            // Count by user
            if (!isset($userStats[$userId])) {
                $userStats[$userId] = [
                    'name' => $record['name'],
                    'total' => 0,
                    'present' => 0,
                    'absent' => 0,
                    'weekoff' => 0,
                    'checked_in_only' => 0,
                    'leave' => 0,
                    'holiday' => 0,
                ];
            }
            
            $userStats[$userId]['total']++;
            
            if ($status === 'present') {
                if (empty($record['clock_out'])) {
                    $userStats[$userId]['checked_in_only']++;
                } else {
                    $userStats[$userId]['present']++;
                }
            } else {
                $userStats[$userId][$status]++;
            }
        }
        
        $summary['by_user'] = array_values($userStats);
        
        return $summary;
    }
}