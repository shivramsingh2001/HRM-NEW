<?php

namespace App\Http\Controllers\Api\Attendance;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\AttendanceLocation;
use App\Models\AttendanceLog;
use App\Models\Request as RequestStore;
use App\Models\Shift;
use App\Models\User;
use App\Services\AttendanceNotificationService;
use App\Services\FieldTracking\TrackingPointIngestService;
use App\Services\FieldTracking\TrackingSessionService;
use App\Traits\AuthorizesByScope;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Mobile API clock-in / clock-out (POST /api/user/attendance/clock-in|clock-out) with
 * geofence / WFH / network checks and the attendance log. Moved out of
 * Api\Attendance\AttendanceController unchanged (code-quality plan, Phase 4).
 */
class ClockController extends Controller
{
    use \App\Http\Controllers\Api\Attendance\Concerns\MobileAttendanceHelpers;
    use AuthorizesByScope;

    protected $notificationService;

    public function __construct(AttendanceNotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    public function clockIn(Request $request)
    {
        if ($this->mobilePunchBlocked((int) Auth::id())) {
            $this->createFailureLog(Auth::id(), 'check_in', 'biometric_only', [], $request);

            return response()->json([
                'status' => false,
                'message' => self::BIOMETRIC_ONLY_MESSAGE,
            ], 200);
        }

        $validator = Validator::make($request->all(), [
            'lat' => 'required|numeric',
            'long' => 'required|numeric',
            'battery_per' => 'nullable',
            'address' => 'required|string|min:5|max:255',
            'device_id' => 'nullable|string',
            'wifi_ssid' => 'nullable|string',
            'network_type' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 200);
        }

        try {
            $userId = Auth::id();
            $currentDateTime = Carbon::now();
            $currentDate = $currentDateTime->format('Y-m-d');
            $currentTime = $currentDateTime;

            $user = User::with('jobDetails')->find($userId);

            if (! $user) {
                $this->createFailureLog($userId, 'check_in', 'user_not_found', [
                    'user_id' => $userId,
                ], $request);

                return response()->json([
                    'status' => false,
                    'message' => 'User not found.',
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
            if (! $skipLocationCheck && $user->jobDetails && $user->jobDetails->type == 'office') {
                $officeBranch = $user->jobDetails->office_branch;

                // CASE 0: no location assigned (NULL = any location) and the company has no
                // geofenced location configured — nothing to check against, so allow
                // (defaults above already mean "verified, no branch").
                if (! $officeBranch && ! AttendanceLocation::where('status', 1)->where('geofence_enabled', true)->exists()) {
                    // no location check
                }
                // CASE 1: User can mark attendance from ANY branch (office_branch NULL/0)
                elseif ($officeBranch == 0) {
                    // Find the nearest branch to user's location
                    // Disabled locations aren't valid candidates for "any branch".
                    $allBranches = AttendanceLocation::where('status', 1)
                        ->where('geofence_enabled', true)
                        ->get();

                    if ($allBranches->isEmpty()) {
                        $this->createFailureLog($userId, 'check_in', 'no_branches_configured', [
                            'user_id' => $userId,
                        ], $request);

                        return response()->json([
                            'status' => false,
                            'message' => 'No branches configured in the system. Please contact admin.',
                        ], 200);
                    }

                    $nearestBranch = null;
                    $minDistance = PHP_INT_MAX;

                    foreach ($allBranches as $branchItem) {
                        if (! $branchItem->latitude || ! $branchItem->longitude) {
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

                    if (! $nearestBranch) {
                        $this->createFailureLog($userId, 'check_in', 'no_branch_in_range', [
                            'user_lat' => $request->lat,
                            'user_long' => $request->long,
                            'user_address' => $request->address,
                        ], $request);

                        return response()->json([
                            'status' => false,
                            'message' => 'You are not within the allowed radius of any branch. Please move closer to your office location to clock in.',
                        ], 200);
                    }

                    $branch = $nearestBranch;
                    $locationVerification = 'verified';

                }
                // CASE 2: User has a specific branch assigned
                else {
                    $branch = AttendanceLocation::find($officeBranch);

                    if (! $branch) {
                        $this->createFailureLog($userId, 'check_in', 'branch_not_configured', [
                            'user_id' => $userId,
                            'office_branch' => $officeBranch,
                        ], $request);

                        return response()->json([
                            'status' => false,
                            'message' => 'Branch not configured. Please contact admin.',
                        ], 200);
                    }

                    if (! $branch->geofence_enabled) {
                        // Geofencing explicitly disabled for this location — treat as a pass.
                        $checkInDistance = null;
                        $locationVerification = 'verified';
                        $skipGeofenceDistanceCheck = true;
                    } else {
                        $skipGeofenceDistanceCheck = false;
                    }

                    if (! $skipGeofenceDistanceCheck && (! $branch->latitude || ! $branch->longitude)) {
                        $this->createFailureLog($userId, 'check_in', 'branch_coordinates_missing', [
                            'branch_id' => $branch->id,
                            'branch_name' => $branch->name,
                        ], $request);

                        return response()->json([
                            'status' => false,
                            'message' => 'Attendance location coordinates not configured. Please contact admin.',
                        ], 200);
                    }

                    if (! $skipGeofenceDistanceCheck) {
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
                                'accuracy' => $request->accuracy,
                            ], $request);

                            return response()->json([
                                'status' => false,
                                'message' => 'You are '.round($distance)." meters away from your assigned branch '{$branch->name}'. Maximum allowed distance for clock in is {$radius} meters. Please move closer to the office to clock in.",
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

            // user_shifts status ('ongoing') is set by AttendancePunchService on
            // the shift the punch was matched to (multi-shift aware).
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
                report($e);

                return response()->json([
                    'status' => false,
                    'message' => 'Clock-in failed: '.$e->getMessage(),
                ], 500);
            }

            // The punch's own date — a 2nd shift / night shift may belong to another day.
            $attendanceDate = Carbon::parse($capturedPunch->date)->format('Y-m-d');
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
                $this->notificationService->notifyClockIn($attendance, $user, $capturedPunch);
            } catch (Exception $e) {
                Log::error('Clock-in notification failed: '.$e->getMessage());
            }

            $lt = app(\App\Services\FieldTracking\FieldTrackingService::class)->resolveForUser((int) $userId);

            return response()->json([
                'status' => true,
                'message' => 'Clock-In successful.',
                'tracking_enabled' => $lt['enabled'],
                'next_ping_seconds' => $lt['enabled'] ? (int) $lt['ping_seconds'] : 0,
                // Additive: the shift this clock-in counted for.
                'shift' => $this->punchShiftInfo($capturedPunch),
            ], 200);
        } catch (Exception $e) {
            report($e);

            return response()->json([
                'status' => false,
                'message' => 'An error occured. Please try again later.',
            ], 500);
        }
    }

    public function clockOut(Request $request)
    {
        if ($this->mobilePunchBlocked((int) Auth::id())) {
            $this->createFailureLog(Auth::id(), 'check_out', 'biometric_only', [], $request);

            return response()->json([
                'status' => false,
                'message' => self::BIOMETRIC_ONLY_MESSAGE,
            ], 200);
        }

        $validator = Validator::make($request->all(), [
            'lat' => 'required|numeric',
            'long' => 'required|numeric',
            'accuracy' => 'nullable|numeric|min:0|max:100',
            'battery_per' => 'nullable',
            'address' => 'required|string|min:5|max:255',
            'device_id' => 'nullable|string',
            'wifi_ssid' => 'nullable|string',
            'network_type' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            $this->createFailureLog(Auth::id(), 'check_out', 'validation_failed', [
                'errors' => $validator->errors()->toArray(),
                'request_data' => $request->except(['_token']),
            ], $request);

            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
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

            if (! $attendance) {
                $this->createFailureLog($userId, 'check_out', 'no_check_in', [
                    'date' => $currentDate,
                    'checked_dates' => [
                        $currentDate,
                        Carbon::parse($currentDate)->subDay()->format('Y-m-d'),
                    ],
                ], $request);

                return response()->json([
                    'status' => false,
                    'message' => 'You have not checked in for any active shift.',
                ], 200);
            }

            if ($attendance->clock_out != null) {
                $this->createFailureLog($userId, 'check_out', 'already_clocked_out', [
                    'attendance_id' => $attendance->id,
                    'existing_clock_out' => $attendance->clock_out,
                ], $request);

                return response()->json([
                    'status' => false,
                    'message' => 'You have already checked out for this shift.',
                ], 200);
            }

            $user = User::with('jobDetails')->find($userId);

            if (! $user) {
                $this->createFailureLog($userId, 'check_out', 'user_not_found', [
                    'user_id' => $userId,
                ], $request);

                return response()->json([
                    'status' => false,
                    'message' => 'User not found.',
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
            if (! $skipLocationCheck && $user->jobDetails && $user->jobDetails->type == 'office') {
                $officeBranch = $user->jobDetails->office_branch;

                // CASE 0: no location assigned (NULL = any location) and the company has no
                // geofenced location configured — nothing to check against, so allow
                // (defaults above already mean "verified, no branch").
                if (! $officeBranch && ! AttendanceLocation::where('status', 1)->where('geofence_enabled', true)->exists()) {
                    // no location check
                }
                // CASE 1: User can mark attendance from ANY branch (office_branch NULL/0)
                elseif ($officeBranch == 0) {
                    // Find the nearest branch to user's location
                    // Disabled locations aren't valid candidates for "any branch".
                    $allBranches = AttendanceLocation::where('status', 1)
                        ->where('geofence_enabled', true)
                        ->get();

                    if ($allBranches->isEmpty()) {
                        $this->createFailureLog($userId, 'check_out', 'no_branches_configured', [
                            'user_id' => $userId,
                        ], $request);

                        return response()->json([
                            'status' => false,
                            'message' => 'No branches configured in the system. Please contact admin.',
                        ], 200);
                    }

                    $nearestBranch = null;
                    $minDistance = PHP_INT_MAX;

                    foreach ($allBranches as $branchItem) {
                        if (! $branchItem->latitude || ! $branchItem->longitude) {
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

                    if (! $nearestBranch) {
                        $this->createFailureLog($userId, 'check_out', 'no_branch_in_range', [
                            'user_lat' => $request->lat,
                            'user_long' => $request->long,
                            'user_address' => $request->address,
                        ], $request);

                        return response()->json([
                            'status' => false,
                            'message' => 'You are not within the allowed radius of any branch. Please move closer to your office location to clock out.',
                        ], 200);
                    }

                    $branch = $nearestBranch;
                    $locationVerification = 'verified';

                }
                // CASE 2: User has a specific branch assigned
                else {
                    $branch = AttendanceLocation::find($officeBranch);

                    if (! $branch) {
                        $this->createFailureLog($userId, 'check_out', 'branch_not_configured', [
                            'user_id' => $userId,
                            'office_branch' => $officeBranch,
                        ], $request);

                        return response()->json([
                            'status' => false,
                            'message' => 'Branch not configured. Please contact admin.',
                        ], 200);
                    }

                    if (! $branch->geofence_enabled) {
                        // Geofencing explicitly disabled for this location — treat as a pass.
                        $checkOutDistance = null;
                        $locationVerification = 'verified';
                        $skipGeofenceDistanceCheck = true;
                    } else {
                        $skipGeofenceDistanceCheck = false;
                    }

                    if (! $skipGeofenceDistanceCheck && (! $branch->latitude || ! $branch->longitude)) {
                        $this->createFailureLog($userId, 'check_out', 'branch_coordinates_missing', [
                            'branch_id' => $branch->id,
                            'branch_name' => $branch->name,
                        ], $request);

                        return response()->json([
                            'status' => false,
                            'message' => 'Attendance location coordinates not configured. Please contact admin.',
                        ], 200);
                    }

                    if (! $skipGeofenceDistanceCheck) {
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
                                'accuracy' => $request->accuracy,
                            ], $request);

                            return response()->json([
                                'status' => false,
                                'message' => 'You are '.round($distance)." meters away from your assigned branch '{$branch->name}'. Maximum allowed distance for clock out is {$radius} meters. Please move closer to the office to clock out.",
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

            // user_shifts status ('complete') is set by AttendancePunchService on
            // the shift of the session being closed.
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
                Log::error('Clock-out transaction error: '.$e->getMessage());

                return response()->json([
                    'status' => false,
                    'message' => 'An error occured. Please try again later.',
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
                $this->notificationService->notifyClockOut($attendance, $user, $capturedPunch);
            } catch (Exception $e) {
                Log::error('Clock-out notification failed: '.$e->getMessage());
            }

            return response()->json([
                'status' => true,
                'message' => 'Clock-Out successfully.',
                'data' => [
                    'clock_out_time' => $attendance->clock_out,
                    'total_hours' => $attendance->total_hours,
                    'worked_hours' => (float) $attendance->worked_hours,
                    'attendance_status' => $attendance->attendance_status,
                    'shift_status' => 'complete',
                    // Additive: the shift this clock-out closed.
                    'shift' => $this->punchShiftInfo($capturedPunch),
                ],
                // Additive: the app stops the tracker on clock-out regardless.
                'tracking_enabled' => false,
                'next_ping_seconds' => 0,
            ], 200);
        } catch (Exception $e) {
            Log::error('Clock-out error: '.$e->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'An error occurred. Please try again later.',
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
                    'timestamp' => Carbon::now()->toDateTimeString(),
                ]),
            ]);
        } catch (Exception $e) {
            Log::error('Failed to create success log: '.$e->getMessage());
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
                    'timestamp' => Carbon::now()->toDateTimeString(),
                ]),
            ];

            AttendanceLog::create($logData);
        } catch (Exception $e) {
            report($e);
        }
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

    private function getUserShiftForDate($userId, $date)
    {
        $tenantId = (int) (optional(Auth::user())->tenant_id
            ?: DB::table('users')->where('id', $userId)->value('tenant_id'));

        if (! $tenantId) {
            return null;
        }

        // The day's primary shift (fixed company shift when custom shifts are
        // off) — one implementation in TenantShiftResolver. Response keys unchanged.
        $details = app(\App\Services\Attendance\TenantShiftResolver::class)
            ->detailsForUserDate((int) $userId, $tenantId, $date);

        if (! $details) {
            return null;
        }

        return [
            'id' => $details['shift_id'],
            'user_shift_id' => $details['user_shift_id'],
            'name' => $details['name'],
            'start_time' => $details['start_time'],
            'end_time' => $details['end_time'],
            'grace_minutes' => $details['grace_minutes'],
        ];
    }
}
