<?php
// app/Http/Controllers/Api/Fingerprint/FingerprintSyncController.php

namespace App\Http\Controllers\Api\Fingerprint;

use App\Http\Controllers\Controller;
use App\Models\FingerprintDevice;
use App\Models\DeviceUserMap;
use App\Models\FingerprintPunchLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class FingerprintSyncController extends Controller
{
    /**
     * List all devices
     */
    public function listDevices(Request $request)
    {
        $tenantId = session('tenant_id') ?? 1;
        
        $devices = FingerprintDevice::where('tenant_id', $tenantId)
            ->with(['branch', 'userMaps.user'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'status' => true,
            'data' => $devices,
        ]);
    }

    /**
     * Register a new device
     */
    public function registerDevice(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'serial_number' => 'required|unique:fingerprint_devices,serial_number',
            'auth_token' => 'required|string|min:32',
            'label_name' => 'nullable|string|max:100',
            'branch_id' => 'nullable|exists:branches,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $tenantId = session('tenant_id') ?? 7;

        $device = FingerprintDevice::create([
            'tenant_id' => $tenantId,
            'serial_number' => $request->serial_number,
            'auth_token' => $request->auth_token,
            'label_name' => $request->label_name,
            'branch_id' => $request->branch_id,
            'status' => 1,
        ]);

        Log::info('Fingerprint device registered', [
            'device_id' => $device->id,
            'serial_number' => $device->serial_number,
            'tenant_id' => $tenantId,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Device registered successfully',
            'data' => $device,
        ]);
    }

    /**
     * Update device
     */
    public function updateDevice(Request $request, $id)
    {
        $device = FingerprintDevice::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'label_name' => 'nullable|string|max:100',
            'branch_id' => 'nullable|exists:branches,id',
            'status' => 'nullable|boolean',
            'auth_token' => 'nullable|string|min:32',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $device->update($request->only(['label_name', 'branch_id', 'status', 'auth_token']));

        return response()->json([
            'status' => true,
            'message' => 'Device updated successfully',
            'data' => $device,
        ]);
    }

    /**
     * Delete device
     */
    public function deleteDevice($id)
    {
        $device = FingerprintDevice::findOrFail($id);
        
        // Delete all mappings first
        DeviceUserMap::where('fingerprint_device_id', $device->id)->delete();
        
        $device->delete();

        return response()->json([
            'status' => true,
            'message' => 'Device deleted successfully',
        ]);
    }

    /**
     * Map user to device
     */
    public function mapUser(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'device_id' => 'required|exists:fingerprint_devices,id',
            'user_id' => 'required|exists:users,id',
            'device_user_id' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $tenantId = session('tenant_id') ?? 1;
        $device = FingerprintDevice::find($request->device_id);

        // Check if mapping already exists
        $existing = DeviceUserMap::where('fingerprint_device_id', $request->device_id)
            ->where('device_user_id', $request->device_user_id)
            ->first();

        if ($existing) {
            return response()->json([
                'status' => false,
                'message' => 'Mapping already exists for this device_user_id',
            ], 409);
        }

        $mapping = DeviceUserMap::create([
            'fingerprint_device_id' => $request->device_id,
            'device_user_id' => $request->device_user_id,
            'user_id' => $request->user_id,
            'tenant_id' => $tenantId,
        ]);

        Log::info('User mapped to fingerprint device', [
            'user_id' => $request->user_id,
            'device_id' => $request->device_id,
            'device_user_id' => $request->device_user_id,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'User mapped successfully',
            'data' => $mapping,
        ]);
    }

    /**
     * Unmap user from device
     */
    public function unmapUser(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'device_id' => 'required|exists:fingerprint_devices,id',
            'user_id' => 'required|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $deleted = DeviceUserMap::where('fingerprint_device_id', $request->device_id)
            ->where('user_id', $request->user_id)
            ->delete();

        if ($deleted) {
            Log::info('User unmapped from fingerprint device', [
                'user_id' => $request->user_id,
                'device_id' => $request->device_id,
            ]);
        }

        return response()->json([
            'status' => true,
            'message' => $deleted ? 'User unmapped successfully' : 'No mapping found',
        ]);
    }

    /**
     * Get users mapped to a device
     */
    public function getDeviceUsers($deviceId)
    {
        $device = FingerprintDevice::findOrFail($deviceId);

        $mappings = DeviceUserMap::where('fingerprint_device_id', $device->id)
            ->with(['user'])
            ->get();

        return response()->json([
            'status' => true,
            'data' => $mappings,
        ]);
    }

    /**
     * Get punch logs
     */
    public function getPunchLogs(Request $request)
    {
        $tenantId = session('tenant_id') ?? 1;

        $query = FingerprintPunchLog::where('tenant_id', $tenantId)
            ->with(['user', 'device']);

        if ($request->filled('device_id')) {
            $query->where('device_id', $request->device_id);
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('from_date')) {
            $query->where('log_time', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->where('log_time', '<=', $request->to_date);
        }

        if ($request->filled('processed')) {
            $query->where('processed', $request->boolean('processed'));
        }

        $logs = $query->orderBy('log_time', 'desc')
            ->paginate($request->per_page ?? 50);

        return response()->json([
            'status' => true,
            'data' => $logs,
        ]);
    }

    /**
     * Get single punch log
     */
    public function getPunchLog($id)
    {
        $log = FingerprintPunchLog::with(['user', 'device'])->findOrFail($id);
        return response()->json([
            'status' => true,
            'data' => $log,
        ]);
    }

    /**
     * Sync employee to Cams/fingerprint machine
     */
    public function syncEmployee(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'device_id' => 'required|exists:fingerprint_devices,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = User::find($request->user_id);
        $device = FingerprintDevice::find($request->device_id);

        // Check if already mapped
        $mapping = DeviceUserMap::where('fingerprint_device_id', $device->id)
            ->where('user_id', $user->id)
            ->first();

        if ($mapping) {
            return response()->json([
                'status' => false,
                'message' => 'User already mapped to this device',
                'data' => $mapping,
            ]);
        }

        // Create mapping
        $mapping = DeviceUserMap::create([
            'fingerprint_device_id' => $device->id,
            'device_user_id' => $user->employee_id,
            'user_id' => $user->id,
            'tenant_id' => $device->tenant_id,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Employee synced successfully',
            'data' => $mapping,
        ]);
    }

    /**
     * Sync multiple employees
     */
    public function syncEmployees(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_ids' => 'required|array',
            'user_ids.*' => 'exists:users,id',
            'device_id' => 'required|exists:fingerprint_devices,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $device = FingerprintDevice::find($request->device_id);
        $results = [];

        foreach ($request->user_ids as $userId) {
            $user = User::find($userId);
            
            $existing = DeviceUserMap::where('fingerprint_device_id', $device->id)
                ->where('user_id', $user->id)
                ->first();

            if ($existing) {
                $results[] = [
                    'user_id' => $userId,
                    'status' => 'already_mapped',
                    'message' => 'User already mapped',
                ];
                continue;
            }

            DeviceUserMap::create([
                'fingerprint_device_id' => $device->id,
                'device_user_id' => $user->employee_id,
                'user_id' => $user->id,
                'tenant_id' => $device->tenant_id,
            ]);

            $results[] = [
                'user_id' => $userId,
                'status' => 'success',
                'message' => 'User mapped successfully',
            ];
        }

        return response()->json([
            'status' => true,
            'message' => 'Sync completed',
            'data' => $results,
        ]);
    }

    /**
     * Delete employee from device
     */
    public function deleteEmployee(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'device_id' => 'required|exists:fingerprint_devices,id',
            'user_id' => 'required|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $deleted = DeviceUserMap::where('fingerprint_device_id', $request->device_id)
            ->where('user_id', $request->user_id)
            ->delete();

        return response()->json([
            'status' => true,
            'message' => $deleted ? 'Employee deleted from device' : 'No mapping found',
        ]);
    }

    /**
     * Get device status
     */
    public function deviceStatus($deviceId)
    {
        $device = FingerprintDevice::findOrFail($deviceId);

        return response()->json([
            'status' => true,
            'data' => [
                'device' => $device,
                'is_online' => $device->last_seen_at && $device->last_seen_at->diffInMinutes(now()) < 10,
                'last_seen' => $device->last_seen_at,
                'total_users' => DeviceUserMap::where('fingerprint_device_id', $device->id)->count(),
                'unprocessed_logs' => FingerprintPunchLog::where('serial_number', $device->serial_number)
                    ->where('processed', false)
                    ->count(),
            ],
        ]);
    }
}