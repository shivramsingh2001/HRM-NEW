<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserJobDetail;
use App\Notifications\LocationStatusNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class LocationAlertService
{
    protected $firebaseService;

    public function __construct(FirebaseService $firebaseService)
    {
        $this->firebaseService = $firebaseService;
    }

    /**
     * Send location off alert to reporting head and HR only
     */
    public function sendLocationOffAlert($user, $locationData)
    {
       

        try {
            // Get recipients (ONLY reporting head + HR)
            $recipients = $this->getLocationAlertRecipients($user);
          
            if ($recipients->isEmpty()) {
                
                return false;
            }

            $data = [
                'user_id' => $user->id,
                'user_name' => $user->name,
                'user_email' => $user->email,
                'location' => $locationData,
                'type' => 'location_off_alert',
                'tenant_id' => $user->tenant_id,
                'timestamp' => now()->toDateTimeString()
            ];

            $successCount = 0;
            $failureCount = 0;

            // Send to each recipient
            foreach ($recipients as $recipient) {
                try {
                    // Send FCM notification
                    $fcmResult = $this->sendFcmNotification(
                        $recipient,
                        '📍 Location Disabled',
                        $user->name . ' has turned off location services',
                        array_merge($data, ['recipient_role' => $recipient->role])
                    );

                    // Store in database
                    $recipient->notify(new LocationStatusNotification($user, $locationData, 'off'));
                    
                    if ($fcmResult['success'] ?? false) {
                        $successCount++;
                    } else {
                        $failureCount++;
                    }
                  
                } catch (\Exception $e) {
                    $failureCount++;
                    
                    continue;
                }
            }


            return true;

        } catch (\Exception $e) {
           
            return false;
        }
    }

    /**
     * Get recipients for location alerts - ONLY Reporting Head and HR
     */
    private function getLocationAlertRecipients($user)
    {
        
        $recipients = collect();

        // 1. Get Reporting Head (if exists and active)
        $reportingHead = $this->getReportingHead($user->id);
        if ($reportingHead && $reportingHead->status == 1) {
            $recipients->push($reportingHead);
            
        } else {
            
        }

        // 2. Get HR users from the SAME TENANT
        $hrUsers = User::where('tenant_id', $user->tenant_id)
            ->where('status', 1)
            ->where(function($q) {
                $q->whereRaw('LOWER(role) = ?', ['hr'])
                  ->orWhereRaw('LOWER(role) = ?', ['human resource'])
                  ->orWhereRaw('LOWER(role) = ?', ['human resources'])
                  ->orWhereRaw('LOWER(role) LIKE ?', ['%hr%'])
                  ->orWhereRaw('LOWER(role) = ?', ['h.r'])
                  ->orWhereRaw('LOWER(role) = ?', ['h r'])
                  ->orWhere('role', 'HR')
                  ->orWhere('role', 'Hr');
            })
            ->select(['id', 'name', 'email', 'role', 'status', 'fcm_tokens', 'tenant_id'])
            ->get();

      

        foreach ($hrUsers as $hr) {
            // Don't add if already added (reporting head might also be HR)
            if (!$recipients->contains('id', $hr->id)) {
                $recipients->push($hr);
            }
        }

        // 3. FALLBACK: If still no recipients, notify tenant admins
        if ($recipients->isEmpty()) {
           
            
            $tenantAdmins = User::where('tenant_id', $user->tenant_id)
                ->where('status', 1)
                ->whereIn('role', ['admin', 'super_admin', 'owner'])
                ->select(['id', 'name', 'email', 'role', 'status', 'fcm_tokens', 'tenant_id'])
                ->get();
            
            foreach ($tenantAdmins as $admin) {
                $recipients->push($admin);
            }
            
         
        }

        // 4. FINAL FALLBACK: Get any active user in the tenant
        if ($recipients->isEmpty()) {
           
            
            $anyUser = User::where('tenant_id', $user->tenant_id)
                ->where('status', 1)
                ->where('id', '!=', $user->id)
                ->select(['id', 'name', 'email', 'role', 'status', 'fcm_tokens', 'tenant_id'])
                ->first();
            
            if ($anyUser) {
                $recipients->push($anyUser);
               
            }
        }

        // Remove duplicates just in case
        $uniqueRecipients = $recipients->unique('id')->values();
        return $uniqueRecipients;
    }

    /**
     * Send FCM notification to user
     */
  /**
 * Send FCM notification to user
 */
private function sendFcmNotification($user, $title, $body, $data = [])
{
   

    // Get tokens - could be array or string
    $tokens = $user->fcm_tokens;
    
    // If it's null or empty, return
    if (empty($tokens)) {
       
        return ['success' => false, 'success_count' => 0, 'failure_count' => 0];
    }
    
    // If it's a string, try to decode it
    if (is_string($tokens)) {
        $decoded = json_decode($tokens, true);
        $tokens = is_array($decoded) ? $decoded : [];
    }
    
    // If it's not an array or empty, log and return
    if (!is_array($tokens) || empty($tokens)) {
      
        return ['success' => false, 'success_count' => 0, 'failure_count' => 0];
    }

    // 🔥 FIX: Format data to ensure all values are strings (no nested arrays)
    $formattedData = [];
    foreach ($data as $key => $value) {
        if (is_array($value)) {
            // Convert nested arrays to JSON strings
            $formattedData[$key] = json_encode($value);
        } elseif (is_object($value)) {
            $formattedData[$key] = json_encode($value);
        } elseif (is_bool($value)) {
            $formattedData[$key] = $value ? 'true' : 'false';
        } elseif ($value === null) {
            $formattedData[$key] = '';
        } else {
            $formattedData[$key] = (string) $value;
        }
    }

    // 🔥 FIX: Remove any complex nested structures from the main data
    // Make sure we don't pass the 'location' array directly
    if (isset($formattedData['location'])) {
       
    }

    $successCount = 0;
    $failureCount = 0;

    foreach ($tokens as $tokenData) {
        // Handle both formats: string token or array with 'token' key
        $token = '';
        
        if (is_string($tokenData)) {
            $token = $tokenData;
        } elseif (is_array($tokenData) && isset($tokenData['token'])) {
            $token = $tokenData['token'];
        } else {
           
            $failureCount++;
            continue;
        }
        
        if (empty($token)) {
            $failureCount++;
            continue;
        }
        
        try {
            // Pass the formatted data instead of raw data
            $result = $this->firebaseService->sendToDevice(
                $token,
                $title,
                $body,
                $formattedData  // Use formatted data with all values as strings
            );

            if (isset($result['success']) && $result['success']) {
                $successCount++;
            } else {
                $failureCount++;
               
            }
        } catch (\Exception $e) {
            $failureCount++;
           
        }
    }

  

    return [
        'success' => $successCount > 0,
        'success_count' => $successCount,
        'failure_count' => $failureCount
    ];
}

    /**
     * Get reporting head for an employee
     */
    private function getReportingHead($employeeId)
    {
        $jobDetail = UserJobDetail::where('user_id', $employeeId)->first();
        
        if (!$jobDetail || !$jobDetail->reporting_head) {
            
            return null;
        }

        $reportingHead = User::find($jobDetail->reporting_head);
        
        if ($reportingHead) {
            
        }

        return $reportingHead;
    }

    /**
     * Get all users by role (kept for backward compatibility)
     */
    private function getUsersByRole($role)
    {
        return User::where('role', $role)
            ->where('status', 1)
            ->get();
    }
}