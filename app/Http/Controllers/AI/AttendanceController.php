<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Exception;

class AttendanceController extends Controller
{
    public function view_ai_all(Request $request)
    {
        try {
            $authUser = Auth::user();
            $tenantId = Session::get('tenant_id') ?? $authUser->tenant_id ?? null;
            
            if (!$tenantId) {
                return response()->json(['success' => false, 'message' => 'Tenant not found'], 400);
            }

            // Check role access
            if (!in_array($authUser->role, ['admin', 'hr', 'manager', 'employee'])) {
                return response()->json(['success' => false, 'message' => 'Unauthorized access'], 200);
            }

            // Get date range
            $startDate = $request->start_date ?? date('Y-m-01');
            $endDate = $request->end_date ?? date('Y-m-d');
            $includeTracks = $request->include_tracks ?? true;

            // Build role-based user filter
            $userFilter = $this->buildUserFilter($authUser, $request->user_id);

            // Get attendance data
            $attendances = $this->getAttendanceData($tenantId, $authUser->id, $startDate, $endDate, $userFilter);

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
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Build user filter based on role
     */
    private function buildUserFilter($authUser, $requestUserId)
    {
        if ($authUser->role === 'employee') {
            return "AND u.id = {$authUser->id}";
        }
        
        if ($authUser->role === 'manager') {
            if ($requestUserId) {
                return "AND u.id = $requestUserId AND (jd.reporting_head = {$authUser->id} OR u.id = {$authUser->id})";
            }
            return "AND (jd.reporting_head = {$authUser->id} OR u.id = {$authUser->id})";
        }
        
        // Admin/HR
        if ($requestUserId) {
            return "AND u.id = $requestUserId";
        }
        
        return "";
    }

    /**
     * Get attendance data from database
     */
   private function getAttendanceData($tenantId, $userId, $startDate, $endDate, $userFilter)
{
    $query = "
        WITH RECURSIVE dates AS (
            SELECT DATE('$startDate') as date
            UNION ALL
            SELECT DATE_ADD(date, INTERVAL 1 DAY)
            FROM dates
            WHERE DATE_ADD(date, INTERVAL 1 DAY) <= DATE('$endDate')
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
            wo.id as weekoff_id,
            -- Add track count subquery for efficiency
            (SELECT COUNT(*) FROM attendance_tracks at WHERE at.attendance_id = a.id) as track_count
        FROM dates d
        CROSS JOIN users u
        LEFT JOIN attendances a ON u.id = a.user_id AND a.date = d.date AND a.tenant_id = $tenantId
        LEFT JOIN user_weekoffs wo ON u.id = wo.user_id 
            AND wo.tenant_id = $tenantId
            AND wo.status = 1
            AND (
                (wo.off_type = 'date_based' AND d.date BETWEEN wo.start_date AND wo.end_date)
                OR (wo.off_type = 'day_based' AND wo.day_name = DAYNAME(d.date))
            )
        LEFT JOIN user_job_details jd ON u.id = jd.user_id AND jd.tenant_id = $tenantId
        WHERE u.status = 1 AND u.tenant_id = $tenantId
        $userFilter
        ORDER BY d.date DESC, u.id
    ";
    
    return DB::select($query);
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
        
        // Determine status (same as before)
        if ($record['clock_in'] && $record['clock_out']) {
            $status = 'Present';
            $statusCategory = 'present';
        } elseif ($record['clock_in']) {
            $status = 'Checked In Only';
            $statusCategory = 'present';
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
            'status_category' => $statusCategory
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
        $tracks = DB::table('attendance_tracks')
            ->where('attendance_id', $attendanceId)
            ->orderBy('track_time')
            ->get(['track_time', 'lat', 'long', 'address', 'battery_per']);
            
        return $tracks;
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
                'checked_in_only' => 0
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
                    'checked_in_only' => 0
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