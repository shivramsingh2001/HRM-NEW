<?php
// app/Http/Controllers/Api/Fingerprint/FingerprintCallbackController.php

namespace App\Http\Controllers\Api\Fingerprint;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessFingerprintPunch;
use App\Models\FingerprintDevice;
use App\Models\FingerprintPunchLog;
use App\Models\DeviceUserMap;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class FingerprintCallbackController extends Controller
{
    /**
     * Handle Cams Biometric Callback
     */
    public function store(Request $request): JsonResponse
    {
        // =============================================
        // STEP 1: LOG REQUEST RECEIVED
        // =============================================
        Log::channel('daily')->info('===== FINGERPRINT CALLBACK RECEIVED =====');
        Log::channel('daily')->info('Request IP: ' . $request->ip());
        Log::channel('daily')->info('Request Headers: ' . json_encode($request->headers->all()));
        Log::channel('daily')->info('Request Payload: ' . json_encode($request->all()));

        try {
            // =============================================
            // STEP 2: GET DEVICE FROM MIDDLEWARE
            // =============================================
            Log::channel('daily')->info('Step 2: Getting device from middleware...');
            
            $device = $request->attributes->get('fingerprint_device');
            
            if (!$device) {
                Log::channel('daily')->warning('❌ No device found in request', [
                    'ip' => $request->ip(),
                    'headers' => $request->headers->all(),
                ]);
                return response()->json(['status' => 'done']);
            }

            Log::channel('daily')->info('✅ Device found:', [
                'device_id' => $device->id,
                'serial_number' => $device->serial_number,
                'tenant_id' => $device->tenant_id,
                'label_name' => $device->label_name,
                'status' => $device->status,
            ]);

            // =============================================
            // STEP 3: EXTRACT PAYLOAD
            // =============================================
            Log::channel('daily')->info('Step 3: Extracting payload...');
            
            $payload = $request->input('RealTime');
            
            if (!$payload) {
                Log::channel('daily')->warning('❌ No RealTime payload found', [
                    'request_data' => $request->all(),
                ]);
                return response()->json(['status' => 'done']);
            }

            Log::channel('daily')->info('✅ Payload extracted:', [
                'operation_id' => $payload['OperationID'] ?? 'N/A',
                'has_punch_log' => isset($payload['PunchLog']),
                'has_user_updated' => isset($payload['UserUpdated']),
                'has_user_deleted' => isset($payload['UserDeleted']),
            ]);

            // =============================================
            // STEP 4: ROUTE TO APPROPRIATE HANDLER
            // =============================================
            if (isset($payload['PunchLog'])) {
                Log::channel('daily')->info('Step 4a: Processing PunchLog...');
                $this->handlePunchLog($payload, $device);
            } elseif (isset($payload['UserUpdated'])) {
                Log::channel('daily')->info('Step 4b: Processing UserUpdated...');
                $this->handleUserUpdate($payload, $device);
            } elseif (isset($payload['UserDeleted'])) {
                Log::channel('daily')->info('Step 4c: Processing UserDeleted...');
                $this->handleUserDeletion($payload, $device);
            } else {
                Log::channel('daily')->warning('❌ Unknown payload type', [
                    'payload_keys' => array_keys($payload),
                ]);
            }

        } catch (Exception $e) {
            Log::channel('daily')->error('❌ Fingerprint callback error: ' . $e->getMessage(), [
                'device_id' => $device->id ?? null,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        Log::channel('daily')->info('===== FINGERPRINT CALLBACK COMPLETED =====');
        return response()->json(['status' => 'done']);
    }

    /**
     * Handle RealTimePunchLog (Operation #11)
     */
    private function handlePunchLog(array $payload, FingerprintDevice $device): void
    {
        Log::channel('daily')->info('--- handlePunchLog START ---');

        try {
            // =============================================
            // STEP 5: EXTRACT PUNCH DATA
            // =============================================
            Log::channel('daily')->info('Step 5: Extracting punch data...');
            
            $punchLog = $payload['PunchLog'];
            $deviceUserId = $punchLog['UserId'];
            $logTime = Carbon::parse($punchLog['LogTime']);
            $punchType = $punchLog['Type'];
            $inputType = $punchLog['InputType'] ?? null;
            $temperature = $punchLog['Temperature'] ?? null;
            $faceMask = $punchLog['FaceMask'] ?? null;
            $operationId = $payload['OperationID'] ?? null;

            Log::channel('daily')->info('✅ Punch data extracted:', [
                'device_user_id' => $deviceUserId,
                'punch_type' => $punchType,
                'log_time' => $logTime->format('Y-m-d H:i:s'),
                'input_type' => $inputType,
                'temperature' => $temperature,
                'face_mask' => $faceMask,
                'operation_id' => $operationId,
                'serial_number' => $device->serial_number,
                'device_id' => $device->id,
            ]);

            // =============================================
            // STEP 6: FIND USER MAPPING
            // =============================================
            Log::channel('daily')->info('Step 6: Finding user mapping...');
            Log::channel('daily')->info('Query params:', [
                'fingerprint_device_id' => $device->id,
                'device_user_id' => $deviceUserId,
                'tenant_id' => $device->tenant_id,
            ]);

            $userMap = DeviceUserMap::where('fingerprint_device_id', $device->id)
                ->where('device_user_id', $deviceUserId)
                ->where('tenant_id', $device->tenant_id)
                ->first();
             

            if ($userMap) {
                Log::channel('daily')->info('✅ User mapping found:', [
                    'mapping_id' => $userMap->id,
                    'user_id' => $userMap->user_id,
                    'device_user_id' => $deviceUserId,
                ]);
            } else {
                Log::channel('daily')->warning('⚠️ User mapping NOT found', [
                    'device_user_id' => $deviceUserId,
                    'device_id' => $device->id,
                    'tenant_id' => $device->tenant_id,
                ]);
                Log::channel('daily')->info('💡 To fix: Insert mapping');
                Log::channel('daily')->info('INSERT INTO device_user_maps (fingerprint_device_id, device_user_id, user_id, tenant_id) VALUES (' . $device->id . ', "' . $deviceUserId . '", 1, ' . $device->tenant_id . ');');
            }

            $userId = $userMap?->user_id;

            // =============================================
            // STEP 7: CHECK FOR DUPLICATE
            // =============================================
            Log::channel('daily')->info('Step 7: Checking for duplicate...');
            
            $exists = FingerprintPunchLog::where('serial_number', $device->serial_number)
                ->where('device_user_id', $deviceUserId)
                ->where('log_time', $logTime)
                ->exists();

            if ($exists) {
                Log::channel('daily')->warning('⚠️ Duplicate punch detected, skipping', [
                    'device_user_id' => $deviceUserId,
                    'log_time' => $logTime,
                    'serial_number' => $device->serial_number,
                ]);
                return;
            }

            Log::channel('daily')->info('✅ No duplicate found');

            // =============================================
            // STEP 8: CREATE PUNCH LOG
            // =============================================
            Log::channel('daily')->info('Step 8: Creating punch log...');
            
            $logData = [
                'serial_number' => $device->serial_number,
                'device_user_id' => $deviceUserId,
                'user_id' => $userId,
                'tenant_id' => $device->tenant_id,
                'punch_type' => $punchType,
                'input_type' => $inputType,
                'temperature' => $temperature,
                'face_mask' => $faceMask,
                'log_time' => $logTime,
                'operation_id' => $operationId,
                'raw_payload' => $punchLog,
                'processed' => false,
            ];

            Log::channel('daily')->info('Log data to be inserted:', $logData);

            $log = FingerprintPunchLog::create($logData);

            Log::channel('daily')->info('✅ Punch log created successfully:', [
                'log_id' => $log->id,
                'processed' => $log->processed,
                'created_at' => $log->created_at,
                'user_id' => $log->user_id,
            ]);

            // =============================================
            // STEP 9: DISPATCH TO QUEUE
            // =============================================
            Log::channel('daily')->info('Step 9: Dispatching to queue...');
            
            Log::channel('daily')->info('Job details:', [
                'log_id' => $log->id,
                'device_id' => $device->id,
                'job_class' => ProcessFingerprintPunch::class,
            ]);

            ProcessFingerprintPunch::dispatch($log, $device);
            
            Log::channel('daily')->info('✅ Job dispatched successfully', [
                'log_id' => $log->id,
            ]);

        } catch (Exception $e) {
            Log::channel('daily')->error('❌ Failed to handle punch log: ' . $e->getMessage(), [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'payload' => $payload,
                'device_id' => $device->id,
            ]);
        }

        Log::channel('daily')->info('--- handlePunchLog END ---');
    }

    /**
     * Handle UserUpdated (Operations #3-#9)
     */
    private function handleUserUpdate(array $payload, FingerprintDevice $device): void
    {
        Log::channel('daily')->info('--- handleUserUpdate START ---');

        try {
            // =============================================
            // STEP 10: EXTRACT USER UPDATE DATA
            // =============================================
            Log::channel('daily')->info('Step 10: Extracting user update data...');
            
            $userUpdate = $payload['UserUpdated'];
            $deviceUserId = $userUpdate['UserID'];
            $firstName = $userUpdate['FirstName'] ?? '';
            $lastName = $userUpdate['LastName'] ?? '';
            $templates = $userUpdate['Template'] ?? [];

            Log::channel('daily')->info('✅ User update data extracted:', [
                'device_user_id' => $deviceUserId,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'template_count' => count($templates),
                'serial_number' => $device->serial_number,
                'device_id' => $device->id,
            ]);

            if (count($templates) > 0) {
                Log::channel('daily')->info('Templates:', $templates);
            }

            // =============================================
            // STEP 11: CHECK EXISTING MAPPING
            // =============================================
            Log::channel('daily')->info('Step 11: Checking existing mapping...');
            
            $userMap = DeviceUserMap::where('fingerprint_device_id', $device->id)
                ->where('device_user_id', $deviceUserId)
                ->where('tenant_id', $device->tenant_id)
                ->first();

            if ($userMap) {
                Log::channel('daily')->info('✅ User already mapped', [
                    'mapping_id' => $userMap->id,
                    'user_id' => $userMap->user_id,
                    'device_user_id' => $deviceUserId,
                ]);
                return;
            }

            Log::channel('daily')->info('No existing mapping found');

            // =============================================
            // STEP 12: FIND USER IN SYSTEM
            // =============================================
            Log::channel('daily')->info('Step 12: Looking for user in system...');
            Log::channel('daily')->info('Search criteria:', [
                'employee_id' => $deviceUserId,
                'tenant_id' => $device->tenant_id,
                'status' => 1,
            ]);

            $user = User::where('employee_id', $deviceUserId)
                ->where('tenant_id', $device->tenant_id)
                ->where('status', 1)
                ->first();

            if ($user) {
                Log::channel('daily')->info('✅ User found in system:', [
                    'user_id' => $user->id,
                    'name' => $user->name,
                    'employee_id' => $user->employee_id,
                    'email' => $user->email,
                ]);
            } else {
                Log::channel('daily')->warning('⚠️ User NOT found in system', [
                    'employee_id' => $deviceUserId,
                    'tenant_id' => $device->tenant_id,
                ]);

                // Try auto-create if enabled
                if (config('fingerprint.auto_create_users', false)) {
                    Log::channel('daily')->info('Auto-create is enabled, creating user...');
                    
                    $user = User::create([
                        'tenant_id' => $device->tenant_id,
                        'employee_id' => $deviceUserId,
                        'name' => trim($firstName . ' ' . $lastName) ?: $deviceUserId,
                        'email' => $deviceUserId . '@' . ($device->tenant->domain ?? 'company.com'),
                        'status' => 1,
                    ]);
                    
                    Log::channel('daily')->info('✅ User auto-created:', [
                        'user_id' => $user->id,
                        'employee_id' => $user->employee_id,
                        'name' => $user->name,
                    ]);
                } else {
                    Log::channel('daily')->info('Auto-create is disabled. User will not be created.');
                    return;
                }
            }

            // =============================================
            // STEP 13: CREATE USER MAPPING
            // =============================================
            Log::channel('daily')->info('Step 13: Creating user mapping...');
            
            $mappingData = [
                'fingerprint_device_id' => $device->id,
                'device_user_id' => $deviceUserId,
                'user_id' => $user->id,
                'tenant_id' => $device->tenant_id,
            ];

            Log::channel('daily')->info('Mapping data:', $mappingData);

            $mapping = DeviceUserMap::create($mappingData);

            Log::channel('daily')->info('✅ User mapping created:', [
                'mapping_id' => $mapping->id,
                'user_id' => $user->id,
                'device_user_id' => $deviceUserId,
                'device_id' => $device->id,
            ]);

        } catch (Exception $e) {
            Log::channel('daily')->error('❌ Failed to handle user update: ' . $e->getMessage(), [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'payload' => $payload,
                'device_id' => $device->id,
            ]);
        }

        Log::channel('daily')->info('--- handleUserUpdate END ---');
    }

    /**
     * Handle UserDeleted (Operation #1)
     */
    private function handleUserDeletion(array $payload, FingerprintDevice $device): void
    {
        Log::channel('daily')->info('--- handleUserDeletion START ---');

        try {
            // =============================================
            // STEP 14: EXTRACT USER DELETION DATA
            // =============================================
            Log::channel('daily')->info('Step 14: Extracting user deletion data...');
            
            $userDeleted = $payload['UserDeleted'];
            $deviceUserId = $userDeleted['UserID'];

            Log::channel('daily')->info('✅ User deletion data extracted:', [
                'device_user_id' => $deviceUserId,
                'serial_number' => $device->serial_number,
                'device_id' => $device->id,
            ]);

            // =============================================
            // STEP 15: DELETE USER MAPPING
            // =============================================
            Log::channel('daily')->info('Step 15: Deleting user mapping...');
            
            $deleted = DeviceUserMap::where('fingerprint_device_id', $device->id)
                ->where('device_user_id', $deviceUserId)
                ->where('tenant_id', $device->tenant_id)
                ->delete();

            Log::channel('daily')->info('✅ User mapping deleted:', [
                'deleted_count' => $deleted,
                'device_user_id' => $deviceUserId,
                'device_id' => $device->id,
            ]);

            // =============================================
            // STEP 16: OPTIONAL - SOFT DELETE USER
            // =============================================
            if (config('fingerprint.delete_user_on_device_delete', false)) {
                Log::channel('daily')->info('Step 16: Soft deleting user...');
                
                $userMap = DeviceUserMap::withTrashed()
                    ->where('fingerprint_device_id', $device->id)
                    ->where('device_user_id', $deviceUserId)
                    ->first();

                if ($userMap) {
                    User::where('id', $userMap->user_id)
                        ->where('tenant_id', $device->tenant_id)
                        ->update(['status' => 0]);
                    
                    Log::channel('daily')->info('✅ User soft-deleted:', [
                        'user_id' => $userMap->user_id,
                        'device_user_id' => $deviceUserId,
                    ]);
                } else {
                    Log::channel('daily')->warning('⚠️ User mapping not found for soft delete', [
                        'device_user_id' => $deviceUserId,
                        'device_id' => $device->id,
                    ]);
                }
            } else {
                Log::channel('daily')->info('Soft delete disabled, skipping user deletion');
            }

        } catch (Exception $e) {
            Log::channel('daily')->error('❌ Failed to handle user deletion: ' . $e->getMessage(), [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'payload' => $payload,
                'device_id' => $device->id,
            ]);
        }

        Log::channel('daily')->info('--- handleUserDeletion END ---');
    }
}