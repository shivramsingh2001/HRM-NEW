<?php

namespace App\Http\Controllers\Api\FCM;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class FCMController extends Controller
{
    public function storeToken(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'fcm_token' => 'required|string',
            'device_id' => 'nullable|string',
            'platform' => 'nullable|string|in:android,ios'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = Auth::user();
            
            // Get existing tokens or initialize empty array
            $tokens = $user->fcm_tokens ?? [];
            
            // Add new token with metadata
            $tokens[] = [
                'token' => $request->fcm_token,
                'device_id' => $request->device_id,
                'platform' => $request->platform,
                'created_at' => now()->toDateTimeString()
            ];
            
            // Remove duplicates (keep only latest token per device)
            $tokens = collect($tokens)
                ->unique('token')
                ->values()
                ->toArray();
            
            $user->fcm_tokens = $tokens;
            $user->save();

            return response()->json([
                'success' => true,
                'message' => 'FCM token stored successfully'
            ], 200);

        } catch (\Exception $e) {
            report($e);
            return response()->json([
                'success' => false,
                'message' => 'Failed to store token: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove FCM token (logout)
     */
    public function removeToken(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'fcm_token' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = Auth::user();
            
            $tokens = $user->fcm_tokens ?? [];
            
            // Remove the specified token
            $tokens = collect($tokens)
                ->filter(function ($item) use ($request) {
                    return $item['token'] !== $request->fcm_token;
                })
                ->values()
                ->toArray();
            
            $user->fcm_tokens = $tokens;
            $user->save();

            return response()->json([
                'success' => true,
                'message' => 'FCM token removed successfully'
            ], 200);

        } catch (\Exception $e) {
            report($e);
            return response()->json([
                'success' => false,
                'message' => 'Failed to remove token: ' . $e->getMessage()
            ], 500);
        }
    }
}
