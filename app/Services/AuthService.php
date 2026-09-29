<?php

namespace App\Services;

use App\Repositories\Interfaces\AuthRepositoryInterface;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AuthService
{
    public function __construct(
        protected AuthRepositoryInterface $repo,
        protected LoginAttemptService $loginAttempts,
        protected AuthAuditService $audit
    ) {
    }

    public function login($employeeId, $password, $fcmToken = null, $deviceId = null, $platform = null)
    {
        $tenantId = app()->bound('current_tenant') ? app('current_tenant')->id : null;

        // Consecutive-failure lockout, independent of the route-level
        // throttle:login rate limit — see LoginAttemptService docblock.
        if ($this->loginAttempts->isLockedOut($employeeId)) {
            $this->audit->logAccountLockout($tenantId, $employeeId);

            return [
                'success' => false,
                'message' => $this->loginAttempts->lockoutMessage($employeeId),
            ];
        }

        $user = $this->repo->findByEmployeeId($employeeId);

        if (!$user) {
            $this->loginAttempts->recordFailure($employeeId);
            $this->audit->logLoginFailed($tenantId, $employeeId);

            return [
                'success' => false,
                'message' => 'Employee not found.'
            ];
        }
        if (!in_array($user->role, ['employee', 'manager'])) {
            return [
                'success' => false,
                'message' => 'You are not authorized to login.'
            ];
        }

        if (!Hash::check($password, $user->password)) {
            $this->loginAttempts->recordFailure($employeeId);
            $this->audit->logLoginFailed($tenantId, $employeeId);

            return [
                'success' => false,
                'message' => 'Invalid password.'
            ];
        }

        if ($user->status != 1) {
            return [
                'success' => false,
                'message' => 'Account is inactive.'
            ];
        }

        $this->loginAttempts->clear($employeeId);
        $this->audit->logLoginSuccess($user->id, $user->tenant_id);

        // Generate token using JWT
        $token = auth('api')->login($user);
        if ($fcmToken) {
            $this->storeFcmToken($user, $fcmToken, $deviceId, $platform);
        }

        // Single-device-login: a fresh device token is issued on every login
        // and persisted here; CheckSingleDeviceLogin compares this against
        // the client's Device-Token header on every subsequent request, so
        // logging in on a new device invalidates any other active session.
        // The response's `last_login_token` field below is what the client
        // is expected to store and echo back.
        $user->last_login_token = \Illuminate\Support\Str::random(60);
        $user->save();

        Auth::login($user);
        return [
            'success' => true,
            'message' => 'Login successfully!!!',
            'data' => [
                'token' => $token,
                'user' => [
                    'id'            => $user->id,
                    'company_id'    => $user->company_id,
                    'employee_id'   => $user->employee_id,
                    'name'          => $user->name,
                    'email'         => $user->email,
                    'contact'       => $user->contact,
                    'status'        => $user->status,
                    'email_verified_at'=> $user->email_verified_at,
                    'last_login_token' => $user->last_login_token,
                    'created_at'    => $user->created_at,
                    'updated_at'    => $user->updated_at,
                    'face_register' => $user->jobDetails->face_register ?? 0,
                    'role'          => $user->role,],
                // Plan modules + role permissions, so the app shows only what the company bought.
                'access' => app(AppAccessService::class)->forUser($user),
            ]
        ];
    }
    
    private function storeFcmToken($user, $fcmToken, $deviceId = null, $platform = null)
    {
        try {
            // Get existing tokens - handle both array and string
            $tokens = $user->fcm_tokens ?? [];
            
            // If it's a string, decode it
            if (is_string($tokens)) {
                $decoded = json_decode($tokens, true);
                $tokens = is_array($decoded) ? $decoded : [];
            }
            
            // If it's not an array, initialize empty array
            if (!is_array($tokens)) {
                $tokens = [];
            }
            
            // Remove any existing token with same device_id or token
            $tokens = collect($tokens)
                ->filter(function ($item) use ($fcmToken, $deviceId) {
                    // Keep tokens that are NOT this token AND NOT this device_id
                    $tokenMatch = is_array($item) 
                        ? ($item['token'] ?? '') === $fcmToken
                        : $item === $fcmToken;
                        
                    $deviceMatch = $deviceId && is_array($item) 
                        ? ($item['device_id'] ?? '') === $deviceId
                        : false;
                        
                    return !$tokenMatch && !$deviceMatch;
                })
                ->values()
                ->toArray();
            
            // Add new token
            $tokens[] = [
                'token' => $fcmToken,
                'device_id' => $deviceId,
                'platform' => $platform,
                'created_at' => now()->toDateTimeString(),
                'last_used' => now()->toDateTimeString()
            ];
            
            // Keep only last 5 tokens per user (prevent unlimited growth)
            if (count($tokens) > 5) {
                $tokens = array_slice($tokens, -5);
            }
            
            // Save back to user
            $user->fcm_tokens = $tokens;
            $user->save();

            return true;
            
        } catch (\Exception $e) {
            Log::error('Failed to store FCM token', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

}
