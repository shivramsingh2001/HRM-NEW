<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Otp;
use App\Services\AuthService;
use App\Services\LoginAttemptService;
use App\Services\AuthAuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use App\Models\UserJobDetail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use App\Mail\PasswordResetMail;
use Illuminate\Support\Facades\Http;
use Exception;
use Carbon\Carbon;

class AuthController extends Controller
{
    public function __construct(
        protected AuthService $service,
        protected LoginAttemptService $loginAttempts,
        protected AuthAuditService $audit
    ) {
    }

    private function currentTenantId(): ?int
    {
        return app()->bound('current_tenant') ? app('current_tenant')->id : null;
    }

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'employee_id' => 'required',
            // Deliberately just "required|string" here, not a length policy
            // — this validates the SUBMITTED password against Hash::check(),
            // it isn't setting one. A min/max here previously caused a real
            // lockout bug: a password legitimately set via resetPassword()
            // (then allowing 6 chars) could fail this rule's min:8 before
            // Hash::check() ever ran, making login impossible. Password
            // *policy* belongs on resetPassword()/changePassword(), where a
            // new password is actually being chosen.
            'password' => 'required|string',
             'fcm_token' => 'nullable|string',  // Added
            'device_id' => 'nullable|string',   // Added
            'platform' => 'nullable|string|in:android,ios',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ], 200);
        }

        $response = $this->service->login(
            $request->employee_id,
            $request->password,
            $request->fcm_token,    // Pass to service
            $request->device_id,     // Pass to service
            $request->platform    
        );

        return response()->json($response, 200);
    }
    
    public function face_register(Request $request)
    {
        $AuthUser = Auth::user();
        
        if (!$AuthUser) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 200);
        }

        try {
            $faceRegister = UserJobDetail::where('user_id',$AuthUser->id)->where('face_register',1)->exists();
            if($faceRegister){
                 return response()->json([
                    'success' => false,
                    'message' => "Already face registered.",
                    'data'=>[
                    'face_register'=> 1, 
                    ]
                ], 200);
            }
             
            $user = UserJobDetail::updateOrCreate(
                ['user_id' => $AuthUser->id],
                ['face_register' => 1]
            );

            return response()->json([
                'success' => true,
                'message' => 'Face registered successfully',
                'data'=>[
                'face_register'=>$user->face_register ?? 0, 
                ]
                    
            ],200);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occured.Please try again later.'
            ],500);
        }
    }
    
    public function changePassword(Request $request)
    {
        $user = Auth::user();
        
        $validator = Validator::make($request->all(), [
            'current_password' => 'required',
            // Unified policy — see resetPassword() for rationale (was
            // min:6|max:10, inconsistent with login()'s former min:8).
            'new_password' => ['required', 'different:current_password', \Illuminate\Validation\Rules\Password::min(8)->mixedCase()->numbers()],
            'confirm_password' => 'required|same:new_password',
        ], [
            'new_password.different' => 'New password must be different from current password',
            'confirm_password.same' => 'Confirm password must match new password',
        ]);
    
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ], 200);
        }
    
        DB::beginTransaction();
        try {
            // Check if current password is correct
            if (!Hash::check($request->current_password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Current password is incorrect'
                ], 200);
            }
            // Update password
            $user->password = Hash::make($request->new_password);
            $user->save();
            DB::commit();
            $this->audit->logPasswordChanged($user->id, $user->tenant_id);
            return response()->json([
                'success' => true,
                'message' => 'Password changed successfully'
            ], 200);
    
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'An error occurred. Please try again later.'
            ], 500);
        }
    }
    
     public function forgotPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);
    
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ], 200);
        }
    
        try {
            $email = $request->email;

            // Find user
            $user = User::where('email', $email)->first();

            // Deliberately generic regardless of whether the email matched —
            // returning a distinct "not found" message here is a classic
            // account-enumeration leak (an attacker can probe which emails
            // exist). If no user matched, skip the token/email work below
            // and return the exact same response either way.
            if (!$user) {
                return response()->json([
                    'success' => true,
                    'message' => 'If that email address is registered, a password reset link has been sent.'
                ], 200);
            }

            $this->audit->logPasswordResetRequested($user->id, $user->tenant_id);

            // Generate token - plain text
            $token = Str::random(60);

            // Store HASHED token in database
            DB::table('password_reset_tokens')->updateOrInsert(
                ['email' => $email],
                [
                    'token' => Hash::make($token),
                    'created_at' => now()
                ]
            );

            // Generate reset link - use rawurlencode for special characters
            $resetLink = url("/reset-password?token=" . rawurlencode($token) . "&email=" . rawurlencode($email));
            
            // Send email - pass PLAIN token to email
            Mail::to($user->email)->send(new PasswordResetMail($user, $resetLink, $token));
    
            return response()->json([
                'success' => true,
                'message' => 'If that email address is registered, a password reset link has been sent.'
            ], 200);

        } catch (Exception $e) {
    
            return response()->json([
                'success' => false,
                'message' => 'Failed to send reset email. Please try again.'
            ], 500);
        }
    }

    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
            'token' => 'required',
            // Unified policy (was min:6|max:10, inconsistent with login()'s
            // former min:8 — that mismatch could make a freshly-reset
            // password unable to log in at all). No artificial max: bcrypt
            // truncates at 72 bytes regardless, that's the real ceiling.
            'password' => ['required', 'confirmed', \Illuminate\Validation\Rules\Password::min(8)->mixedCase()->numbers()],
        ]);
    
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ], 200);
        }
    
        try {
            // Decode URL encoded values
            $email = urldecode($request->email);
            $token = urldecode($request->token);
           
            // Get password reset record
            $resetRecord = DB::table('password_reset_tokens')
                ->where('email', $email)
                ->first();
           
            if (!$resetRecord) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid password reset request'
                ], 200);
            }
         
            // Check if token is valid
            if (!Hash::check($token, $resetRecord->token)) {
                // Try alternative: maybe token got double encoded
                $rawToken = rawurldecode($request->token);
                if ($rawToken !== $token) {
                    if (Hash::check($rawToken, $resetRecord->token)) {
                        $token = $rawToken;
                    } else {
                        \Log::error('Invalid1'.$token);
                        return response()->json([
                            'success' => false,
                            'message' => 'Invalid or expired token'
                        ], 200);
                    }
                } else {
                    \Log::error('Invalid'.$token);
                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid or expired token'
                    ], 200);
                }
            } 
    
            // Check if token is expired. Was addMinutes(5) despite the
            // comment saying 24 hours — a 5-minute real-world window from
            // "request email" to "click link" was almost certainly an
            // accidental typo, not an intentional security choice. 60
            // minutes is a standard industry default for email-based resets.
            $createdAt = Carbon::parse($resetRecord->created_at);
            if ($createdAt->addMinutes(60)->isPast()) {
                DB::table('password_reset_tokens')->where('email', $email)->delete();
                return response()->json([
                    'success' => false,
                    'message' => 'Token has expired. Please request a new password reset.'
                ], 200);
            }
    
            // Find user
            $user = User::where('email', $email)->first();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found'
                ], 200);
            }
    
            // Update password
            $user->password = Hash::make($request->password);
            $user->save();

            // Delete the used token
            DB::table('password_reset_tokens')->where('email', $email)->delete();

            $this->audit->logPasswordResetCompleted($user->id, $user->tenant_id);

            return response()->json([
                'success' => true,
                'message' => 'Password has been reset successfully'
            ], 200);
    
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred. Please try again later.'
            ], 500);
        }
    }
    
    public function logout(Request $request)
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated',
                ], 200);
            }
           
            $fcmToken = $fcm_token ?? 
                    $request->query('fcm_token') ?? 
                    $request->input('fcm_token');
            
            // Remove only this device's token
            if ($fcmToken && $user->fcm_tokens) {
                $tokens = is_string($user->fcm_tokens) 
                    ? json_decode($user->fcm_tokens, true) 
                    : $user->fcm_tokens;
                
                if (is_array($tokens)) {
                    // Filter out the specific token
                    $tokens = array_filter($tokens, function($item) use ($fcmToken) {
                        if (is_array($item)) {
                            return ($item['token'] ?? '') !== $fcmToken;
                        }
                        return $item !== $fcmToken;
                    });
                    
                    // Re-index array
                    $user->fcm_tokens = array_values($tokens);
                    $user->save();
                }
            }
    
            // Invalidate current JWT
            auth('api')->logout();
    
            return response()->json([
                'success' => true,
                'message' => 'Logged out successfully',
            ], 200);
    
        } catch (Exception $e) {
          
            return response()->json([
                'success' => false,
                'message' => 'An error occurred during logout',
            ], 500);
        }
    }
    
    public function delete(Request $request)
    {
       
        try {
            $Authuser = Auth::user();
           
            if (!$Authuser) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated',
                ], 200);
            }
            $user = User::where('id', $Authuser->id)->first();
            $user->status = 0;
            $user->save();
    
            return response()->json([
                'success' => true,
                'message' => 'Account delete successfully',
            ], 200);
    
        } catch (Exception $e) {
            
            return response()->json([
                'success' => false,
                'message' => 'An error occurred during logout',
            ], 500);
        }
    }
    
    public function otp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'mobile_no' => 'required|numeric|digits:10|exists:users,contact',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ], 200);
        }
        try {
            $user = User::where('contact', $request->mobile_no)
                ->whereIn('role', ['employee', 'manager'])
                ->first();
                
            if(!$user){
                return response()->json([
                    'success' => false,
                    'message' => 'Account not found. !!!',
                ], 200);
            }
            
            $mobile = $request->mobile_no;

            $lastOtp = Otp::where('mobile_no', $mobile)
                ->latest()
                ->first();

            if ($lastOtp && $lastOtp->created_at->diffInSeconds(now()) < 30) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please wait before requesting another OTP.'
                ]);
            }
            
            $otp = random_int(100000, 999999);
            // $otp = 123456;
            Otp::create([
                'mobile_no' => $mobile,
                'otp' => $otp,
                'expire_at' => now()->addMinutes(2),
            ]);
            // return response()->json([
            //     'success' => true,
            //     'message' => 'OTP Sent Successfully !!!',
            // ], 200);

            $username = config('sms.airtel.username');
            $password = config('sms.airtel.password');
            $url = "https://iqsms.airtel.in/api/v1/send-prepaid-sms";

            $response = Http::withHeaders([
                'accept' => 'application/json',
                'content-type' => 'application/json',
                'Authorization' => 'Basic ' . base64_encode($username . ':' . $password)
            ])->post($url, [
                "customerId" => config('sms.airtel.customer_id'),
                "destinationAddress" => [$request->mobile_no],
                "dltTemplateId" => config('sms.airtel.dlt_template_id'),
                "entityId" => config('sms.airtel.entity_id'),
                "message" => "Dear user,{$otp}. is your verification code for SHURT HRMS. Please keep this code confidential and do not share it with anyone",
                "messageType" => config('sms.airtel.message_type'),
                "sourceAddress" => config('sms.airtel.source_address')
            ]);
            if ($response->successful()) {
                $this->audit->logOtpRequested($this->currentTenantId(), $mobile);

                return response()->json([
                    'success' => true,
                    'message' => 'Otp Sent Successfully!!!',
                    // 'otp'     => $otp,
                    // 'sms_api_response' => $response->body()
                ], 200);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => "Failed to Send OTP",
                ], 200);
            }
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while sending OTP.',

            ], 500);
        }
    }
    public function login_otp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'mobile_no' => 'required|numeric|digits:10',
            'otp' => 'required|numeric|digits:6',
            'fcm_token' => 'nullable|string',  // Added
            'device_id' => 'nullable|string',   // Added
            'platform' => 'nullable|string|in:android,ios',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ], 200);
        }
        try {
            $lockoutKey = 'otp:' . $request->mobile_no;
            if ($this->loginAttempts->isLockedOut($lockoutKey)) {
                return response()->json([
                    'success' => false,
                    'message' => $this->loginAttempts->lockoutMessage($lockoutKey),
                ], 200);
            }

            $otpRecord = Otp::where('mobile_no', $request->mobile_no)
                ->where('otp', $request->otp)
                ->where('is_used',0)
                ->latest()
                ->first();

            if (!$otpRecord) {
                $this->loginAttempts->recordFailure($lockoutKey);

                return response()->json([
                    'success' => false,
                    'message' => 'Invalid OTP.',
                ], 200);
            }
            if (now()->gt($otpRecord->expire_at)) {
                $this->loginAttempts->recordFailure($lockoutKey);

                return response()->json([
                    'success' => false,
                    'message' => 'OTP has expired.',
                ], 200);
            }
            $this->loginAttempts->clear($lockoutKey);
            $otpRecord->update(['is_used' => true]);
            $user = User::with('jobDetails')->where('contact', $request->mobile_no)
                ->whereIn('role', ['employee', 'manager'])
                ->first();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Account not found',
                ]);
            }

            if ($user->status != 1) {
                return response()->json([
                    'success' => false,
                    'message' => 'Account is inactive.',
                ], 200);
            }

            $token = auth('api')->login($user);

            $fcmToken = $request->fcm_token;
            $deviceId = $request->device_id;
            $platform = $request->platform;

            if ($fcmToken) {
                $this->storeFcmToken($user, $fcmToken, $deviceId, $platform);
            }

            // Single-device-login: see AuthService::login() for the full
            // rationale — same mechanism, mirrored here for the OTP path.
            $user->last_login_token = \Illuminate\Support\Str::random(60);
            $user->save();

            $this->audit->logOtpVerified($user->id, $user->tenant_id);

            return response()->json([
                'success' => true,
                'message' => 'Login Successfully',
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
                        'email_verified_at' => $user->email_verified_at,
                        'last_login_token' => $user->last_login_token,
                        'created_at'    => $user->created_at,
                        'updated_at'    => $user->updated_at,
                        'face_register' => $user->jobDetails->face_register ?? 0,
                        'role'          => $user->role,
                    ],
                    'access' => app(\App\Services\AppAccessService::class)->forUser($user),
                ]
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Login failed due to server error.'.$e->getMessage(),
            ], 500);
        }
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
            
            Log::info('FCM token stored successfully', [
                'user_id' => $user->id,
                'device_id' => $deviceId,
                'platform' => $platform
            ]);
            
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
