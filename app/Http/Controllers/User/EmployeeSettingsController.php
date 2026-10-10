<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserJobDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Per-employee switches from the employee list: active / inactive, face registration,
 * attendance type, GPS location tracking (one or bulk), push to biometric device.
 * Moved out of UserController unchanged (code-quality plan, Phase 4); route names
 * are the same.
 */
class EmployeeSettingsController extends Controller
{
    public function toggleStatus(Request $request)
    {
        try {
            $request->validate([
                'id' => 'required|exists:users,id',
                'status' => 'required|in:0,1',
            ]);

            $user = User::findOrFail($request->id);
            $newStatus = $user->status == 1 ? 0 : 1;

            $user->status = $newStatus;
            $user->save();

            return response()->json([
                'success' => true,
                'message' => 'Status updated successfully',
                'new_status' => $newStatus,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Status toggle error: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error updating status. Please try again.',
            ], 500);
        }
    }

    /**
     * Toggle face register status
     */
    public function toggleFaceRegister(Request $request)
    {
        $request->validate([
            'id' => 'required|exists:users,id',
        ]);

        try {
            $user = UserJobDetail::where('user_id', $request->id)->first();
            $newStatus = $user->face_register == 1 ? 0 : 1;

            // Update face_register in user_job_details
            UserJobDetail::where('user_id', $request->id)
                ->update(['face_register' => $newStatus]);

            return response()->json([
                'success' => true,
                'message' => 'Face register status updated successfully',
                'new_status' => $newStatus,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Error updating face register status. Please try again.',
            ], 500);
        }
    }

    public function updateAttendanceType(Request $request)
    {
        try {
            $request->validate([
                'id' => 'required|exists:users,id',
                'attendance_type' => 'required|in:manual_attendance,face_verification,biometric_only',
            ]);

            // Re-check server-side: the dropdown already hides a method the
            // plan doesn't include, but the endpoint must not trust the client.
            // biometric_only = punches come only from the terminal; the mobile
            // app's clock-in/out is blocked (Api\Attendance\AttendanceController).
            $features = app(\App\Services\FeatureService::class);
            $requiredFeature = match ($request->attendance_type) {
                'face_verification' => 'attendance_face',
                'biometric_only' => 'attendance_biometric',
                default => 'attendance',
            };
            if (! $features->enabledForCurrentTenant($requiredFeature)) {
                return response()->json([
                    'success' => false,
                    'message' => 'That attendance method is not included in your current plan.',
                ], 403);
            }

            $user = UserJobDetail::where('user_id', $request->id)->first();
            $user->attendance_type = $request->attendance_type;
            $user->save();

            return response()->json([
                'success' => true,
                'message' => 'Attendance type updated successfully to '.str_replace('_', ' ', $request->attendance_type),
            ]);
        } catch (\Exception $e) {
            Log::error('Attendance type update failed: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to update attendance type: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Turn continuous GPS tracking on/off for one employee. Hard-capped at the
     * tenant's purchased field-tracking seats. Mirrors toggleFaceRegister().
     */
    public function toggleLocationTracking(Request $request)
    {
        $request->validate([
            'id' => 'required|exists:users,id',
        ]);

        try {
            $user = User::findOrFail($request->id);
            $svc = app(\App\Services\FieldTracking\FieldTrackingService::class);
            $jd = UserJobDetail::where('user_id', $user->id)->first();
            $turnOn = ! ($jd && $jd->location_tracking_enabled);

            if ($turnOn) {
                $gate = $svc->canEnable((int) $user->tenant_id);
                if (! $gate['ok']) {
                    return response()->json(['success' => false, 'message' => $gate['reason']], 200);
                }
                $res = $svc->assign($user);
            } else {
                $res = $svc->remove($user);
            }

            return response()->json([
                'success' => true,
                'message' => $turnOn ? 'Field tracking enabled' : 'Field tracking disabled',
                'new_status' => $turnOn ? 1 : 0,
                'seats_used' => $res['seats_used'],
                'seats_purchased' => $res['seats_purchased'],
            ]);
        } catch (\Exception $e) {
            Log::error('toggleLocationTracking failed: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error updating field tracking. Please try again.',
            ], 500);
        }
    }

    /**
     * Enable/disable field tracking for many employees. When enabling under a
     * hard seat cap, fills the remaining seats and reports the rest as skipped.
     */
    public function bulkLocationTracking(Request $request)
    {
        $data = $request->validate([
            'user_ids' => 'required|array|min:1',
            'user_ids.*' => 'integer|exists:users,id',
            'enabled' => 'required|boolean',
        ]);

        try {
            $tenantId = (int) auth()->user()->tenant_id;
            $svc = app(\App\Services\FieldTracking\FieldTrackingService::class);

            $users = User::where('tenant_id', $tenantId)
                ->whereIn('id', $data['user_ids'])
                ->orderBy('id')
                ->get();

            $enabling = (bool) $data['enabled'];
            $updated = 0;
            $skipped = 0;

            DB::transaction(function () use ($users, $enabling, $svc, $tenantId, &$updated, &$skipped) {
                $tenant = \App\Models\Tenant::find($tenantId);
                $remaining = $enabling
                    ? max(0, $svc->seatsPurchased($tenant) - $svc->seatsUsed($tenantId))
                    : PHP_INT_MAX;

                foreach ($users as $user) {
                    $jd = UserJobDetail::where('user_id', $user->id)->first();
                    $isOn = (bool) ($jd && $jd->location_tracking_enabled);

                    if ($enabling) {
                        if ($isOn) {
                            continue;
                        }
                        if ($remaining <= 0) {
                            $skipped++;

                            continue;
                        }
                        $svc->assign($user);
                        $remaining--;
                        $updated++;
                    } else {
                        if (! $isOn) {
                            continue;
                        }
                        $svc->remove($user);
                        $updated++;
                    }
                }
            });

            return response()->json([
                'status' => true,
                'message' => "{$updated} employee(s) updated".($skipped ? ", {$skipped} skipped (no seats)" : ''),
                'data' => [
                    'updated' => $updated,
                    'skipped_no_seats' => $skipped,
                    'seats_used' => $svc->seatsUsed($tenantId),
                    'seats_purchased' => $svc->seatsPurchased(\App\Models\Tenant::find($tenantId)),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('bulkLocationTracking failed: '.$e->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'Error updating field tracking. Please try again.',
            ], 500);
        }
    }

    /**
     * Explicitly queue selected employees for push to a chosen biometric
     * device, regardless of that device's auto_provision setting — bypasses
     * waiting for the background roster sync. The bridge still only applies
     * it on its next poll cycle (no on-demand device write exists).
     */
    public function bulkPushToDevice(Request $request, \App\Services\Biometric\BiometricRosterService $roster)
    {
        $data = $request->validate([
            'user_ids' => 'required|array|min:1',
            'user_ids.*' => 'integer|exists:users,id',
            'device_id' => 'required|integer',
        ]);

        try {
            $tenantId = (int) auth()->user()->tenant_id;

            $device = \App\Models\BiometricDevice::where('id', $data['device_id'])
                ->where('tenant_id', $tenantId)
                ->first();
            if (! $device) {
                return response()->json(['status' => false, 'message' => 'Device not found.']);
            }

            $users = User::where('tenant_id', $tenantId)->whereIn('id', $data['user_ids'])->get();
            $result = DB::transaction(fn () => $roster->pushToDevice($device, $users));

            return response()->json([
                'status' => true,
                'message' => "{$result['queued']} employee(s) queued for push to {$device->name} — applied within ~1 min once the bridge polls."
                    .($result['skipped'] ? " {$result['skipped']} skipped." : ''),
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            Log::error('bulkPushToDevice failed: '.$e->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'Error pushing employees to device. Please try again.',
            ], 500);
        }
    }
}
