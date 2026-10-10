<?php

namespace App\Http\Controllers\Api\Attendance;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\LocationLog;
use App\Services\LocationAlertService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class LocationTrackingController extends Controller
{
    protected $locationAlertService;

    public function __construct(LocationAlertService $locationAlertService)
    {
        $this->locationAlertService = $locationAlertService;
    }

    /**
     * Update location status and send notifications when location is turned off
     */
    public function locationStatus(Request $request)
    {
        

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:on,off,low_accuracy,denied',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'accuracy' => 'nullable|numeric|min:0',
            'reason' => 'nullable|string|max:255',
            'battery_level' => 'nullable|integer|between:0,100'
        ]);

        if ($validator->fails()) {
           
            
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = Auth::user();
            
            if (!$user) {

                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            // Prepare location data
            $locationData = [
                'latitude' => $request->latitude,
                'longitude' => $request->longitude,
                'accuracy' => $request->accuracy,
                'reason' => $request->reason,
                'battery_level' => $request->battery_level
            ];

           
            // Send notification ONLY when user turns off location
            if ($request->status == 'off') {
               
                try {
                    // Call the service to send notifications
                    $result = $this->locationAlertService->sendLocationOffAlert($user, $locationData);
                    
                } catch (\Exception $e) {
                report($e);
                   
                }
            } else {
               
            }

            return response()->json([
                'success' => true,
                'message' => 'Location status updated successfully',
                'data' => [
                    'status' => $request->status,
                    'timestamp' => now()->toDateTimeString()
                ]
            ], 200);

        } catch (\Exception $e) {
            report($e);
           
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update location status: ' . $e->getMessage()
            ], 500);
        }
    }

 
}