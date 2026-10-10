<?php

namespace App\Http\Controllers\Api\Attendance;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\AttendanceTrackingPoint;
use App\Services\FieldTracking\TrackingPointIngestService;
use App\Traits\AuthorizesByScope;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Mobile API location pings while clocked in (track, track-batch) and today's
 * locations. Moved out of Api\Attendance\AttendanceController unchanged
 * (code-quality plan, Phase 4).
 */
class AttendanceTrackController extends Controller
{
    use AuthorizesByScope;

    public function trackLocation(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'lat' => 'required',
            'long' => 'required',
            'battery_per' => 'nullable',
            'address' => 'required|min:5|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
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
            report($e);

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
            'points' => 'required|array|min:1|max:'.(int) config('location.batch_max', 60),
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
                'message' => "Saved {$saved} of ".($saved + $rejected + $result['duplicates']).' points.',
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
            report($e);

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

            if (! $attendance) {
                return response()->json([
                    'status' => false,
                    'message' => 'No attendance record found for today.',
                ], 200);
            }

            $locations = AttendanceTrackingPoint::forAttendanceId($attendance->id)
                ->get([
                    'track_time',
                    'lat',
                    'long',
                    'address',
                ]);

            return response()->json([
                'status' => true,
                'message' => 'Data fetch successfully!!!',
                'data' => $locations,
            ], 200);
        } catch (Exception $e) {
            report($e);

            return response()->json([
                'status' => false,
                'message' => 'Failed to load location history.',
            ], 500);
        }
    }
}
