<?php

namespace App\Http\Controllers\Api\Shift;

use App\Http\Controllers\Controller;
use App\Models\UserShift;
use App\Models\Shift;
use App\Models\UserWeekoffs;
use App\Models\UserJobDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use DatePeriod;
use DateInterval;
use Exception;

class ShiftController extends Controller
{
    /**
     * Get authenticated user's shift plan with week offs
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function myShiftPlan(Request $request)
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return $this->errorResponse('User not authenticated', 401);
            }

            // Get user's joining date from job details
            $joiningDate = $this->getUserJoiningDate($user->id);
            
            // Calculate effective start date based on joining date and request
            $startDate = $this->calculateStartDate($request, $joiningDate);
            
            // Get end date from request or default to end of current month
            $endDate = $request->end_date 
                ? Carbon::parse($request->end_date)->endOfDay()
                : now()->endOfMonth();

            // Validate that end date is after start date
            if ($endDate->lt($startDate)) {
                return $this->errorResponse('End date cannot be before start date', 400);
            }

            // Prevent too large date ranges (max 6 months)
            if ($startDate->diffInMonths($endDate) > 6) {
                return $this->errorResponse('Date range cannot exceed 6 months', 400);
            }

            // Get all data
            $data = $this->fetchUserShiftData($user->id, $startDate, $endDate);
            
            // Combine and format the results
            $allShifts = $this->combineShiftData($data, $startDate, $endDate);
            
            // Generate summary statistics
            $summary = $this->generateSummary($allShifts);

            return response()->json([
                'success' => true,
                'message' => 'Shift plan fetched successfully',
                'data' => [
                    'shifts'=>$allShifts,
                    'summary' => $summary,
                ]
                // 'meta' => [
                //     'date_range' => [
                //         'start' => $startDate->toDateString(),
                //         'end' => $endDate->toDateString()
                //     ],
                //     'joining_date' => $joiningDate ? $joiningDate->toDateString() : null,
                //     'total_days' => $startDate->diffInDays($endDate) + 1
                // ]
            ], 200);

        } catch (Exception $e) {
            Log::error('My Shift Plan Error: ' . $e->getMessage(), [
                'user_id' => Auth::id(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return $this->errorResponse(
                'Failed to fetch shift plan',
                500,
                config('app.debug') ? $e->getMessage() : null
            );
        }
    }

    /**
     * Get user's joining date from job details
     */
    private function getUserJoiningDate($userId)
    {
        $jobDetail = UserJobDetail::where('user_id', $userId)->first();
        
        if ($jobDetail && $jobDetail->joining_date) {
            return Carbon::parse($jobDetail->joining_date)->startOfDay();
        }
        
        return null;
    }

    /**
     * Calculate effective start date based on request and joining date
     */
    private function calculateStartDate($request, $joiningDate)
    {
        // If request has start_date, use it
        if ($request->start_date) {
            $requestedStart = Carbon::parse($request->start_date)->startOfDay();
            
            // If user has joining date and requested start is before joining, use joining date
            if ($joiningDate && $requestedStart->lt($joiningDate)) {
                return $this->getMonthStartDate($joiningDate);
            }
            
            return $requestedStart;
        }
        
        // No request start date - use default logic
        return $this->getDefaultStartDate($joiningDate);
    }

    /**
     * Get default start date based on joining date
     */
    private function getDefaultStartDate($joiningDate)
    {
        $now = now();
        
        // If user has no joining date, return start of current month
        if (!$joiningDate) {
            return $now->startOfMonth();
        }
        
        // If user joined in current month
        if ($joiningDate->isSameMonth($now)) {
            // If joined after 15th, start from next month
            if ($joiningDate->day > 15) {
                return $now->addMonth()->startOfMonth();
            }
            // If joined on or before 15th, start from current month
            return $now->startOfMonth();
        }
        
        // If user joined in previous months, start from current month
        if ($joiningDate->lt($now->startOfMonth())) {
            return $now->startOfMonth();
        }
        
        // If user joins in future months, start from their joining month
        return $this->getMonthStartDate($joiningDate);
    }

    /**
     * Get the start of month for a given date
     */
    private function getMonthStartDate($date)
    {
        return $date->copy()->startOfMonth();
    }

    /**
     * Fetch all user shift data
     */
    private function fetchUserShiftData($userId, $startDate, $endDate)
    {
        $tenantId = (int) (optional(Auth::user())->tenant_id
            ?: DB::table('users')->where('id', $userId)->value('tenant_id'));

        $resolver = app(\App\Services\Attendance\TenantShiftResolver::class);
        $fixedShift = ($tenantId && !$resolver->isCustomShifts($tenantId))
            ? $resolver->defaultShift($tenantId)
            : null;

        if ($fixedShift) {
            // Custom shifts are off — the whole company is on one fixed shift.
            // Emit a "Shift" entry for every working date in range; skip the
            // weekly-off weekdays so combineShiftData renders them as Week Off.
            // Response keys are unchanged.
            $start = $fixedShift->start_time ? Carbon::parse($fixedShift->start_time)->format('h:i A') : null;
            $end = $fixedShift->end_time ? Carbon::parse($fixedShift->end_time)->format('h:i A') : null;
            $today = Carbon::today();

            $dayOffNames = UserWeekoffs::where('user_id', $userId)
                ->where('off_type', 'day_based')
                ->pluck('day_name')
                ->all();

            $shifts = collect();
            for ($d = $startDate->copy(); $d->lte($endDate); $d->addDay()) {
                if (in_array($d->format('l'), $dayOffNames, true)) {
                    continue;
                }
                $ds = $d->toDateString();
                $shifts[$ds] = [
                    'date' => $ds,
                    'shift_id' => $fixedShift->id,
                    'shift_name' => $fixedShift->name,
                    'start_time' => $start,
                    'end_time' => $end,
                    'status' => $d->lt($today) ? 'completed' : 'upcoming',
                    'type' => 'Shift',
                    'color_code' => $fixedShift->color_code ?? '#3b82f6',
                ];
            }
        } else {
            // Get assigned shifts with eager loading
            $shifts = UserShift::with('shift')
                ->where('user_id', $userId)
                ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
                ->get()
                       ->map(function ($userShift) {
                $startTime = $userShift->shift->start_time ?? null;
                $endTime = $userShift->shift->end_time ?? null;

                // Format times if they exist (do it once here)
                if ($startTime) {
                    $startTime = Carbon::parse($startTime)->format('h:i A');
                }
                if ($endTime) {
                    $endTime = Carbon::parse($endTime)->format('h:i A');
                }

                return [
                    'date' => $userShift->date instanceof Carbon
                        ? $userShift->date->format('Y-m-d')
                        : Carbon::parse($userShift->date)->format('Y-m-d'),
                    'shift_id' => $userShift->shift->id ?? null,
                    'shift_name' => $userShift->shift->name ?? null,
                    'start_time' => $startTime,
                    'end_time' => $endTime,
                    'status' => $userShift->status,
                    'type' => 'Shift',
                    'color_code' => $userShift->shift->color_code ?? '#3b82f6'
                ];
            })->keyBy('date');
        }


        // Get date-based week offs
        $dateWeekOffs = UserWeekoffs::where('user_id', $userId)
            ->where('off_type', 'date_based')
            ->whereBetween('start_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->get()
            ->map(function ($weekOff) {
                return [
                    'date' => $weekOff->start_date->format('Y-m-d'),
                    'shift_id' => null,
                    'shift_name' => null,
                    'start_time' => null,
                    'end_time' => null,
                    'status' => 'Week Off',
                    'type' => 'Week Off',
                    'color_code' => '#dc3545'
                ];
            })->keyBy('date');

        // Get day-based week off patterns
        $dayPatterns = UserWeekoffs::where('user_id', $userId)
            ->where('off_type', 'day_based')
            ->pluck('day_name')
            ->toArray();

        return [
            'shifts' => $shifts,
            'dateWeekOffs' => $dateWeekOffs,
            'dayPatterns' => $dayPatterns
        ];
    }

    /**
     * Combine all shift data and generate day-based week offs
     */
    private function combineShiftData($data, $startDate, $endDate)
    {
        $allShifts = collect();
        $shifts = $data['shifts'];
        $dateWeekOffs = $data['dateWeekOffs'];
        $dayPatterns = $data['dayPatterns'];

        // Generate all dates in the range using Carbon
        $currentDate = $startDate->copy();
        
        while ($currentDate <= $endDate) {
            $dateString = $currentDate->toDateString();
            
            // Priority 1: Existing shift
            if (isset($shifts[$dateString])) {
                $item = $shifts[$dateString];
                // Format times
                if ($item['start_time']) {
                    $item['start_time'] = Carbon::parse($item['start_time'])->format('h:i A');
                }
                if ($item['end_time']) {
                    $item['end_time'] = Carbon::parse($item['end_time'])->format('h:i A');
                }
                $allShifts->push($item);
            }
            // Priority 2: Date-based week off
            elseif (isset($dateWeekOffs[$dateString])) {
                $allShifts->push($dateWeekOffs[$dateString]);
            }
            // Priority 3: Day-based week off pattern
            elseif (!empty($dayPatterns) && in_array($currentDate->format('l'), $dayPatterns)) {
                $allShifts->push([
                    'date' => $dateString,
                    'shift_id' => null,
                    'shift_name' => null,
                    'start_time' => null,
                    'end_time' => null,
                    'status' => 'Week Off',
                    'type' => 'Week Off',
                    'color_code' => '#dc3545'
                ]);
            }
            
            $currentDate->addDay();
        }

        return $allShifts->values();
    }

    /**
     * Generate summary statistics
     */
    private function generateSummary($shifts)
    {
        return [
            'total' => $shifts->count(),
            'shifts' => $shifts->where('type', 'Shift')->count(),
            'week_offs' => $shifts->where('type', 'Week Off')->count(),
            'upcoming' => $shifts->where('status', 'upcoming')->count(),
            'ongoing' => $shifts->where('status', 'ongoing')->count(),
            'completed' => $shifts->where('status', 'completed')->count(),
            'missed' => $shifts->where('status', 'missed')->count(),
            'cancelled' => $shifts->where('status', 'cancelled')->count(),
        ];
    }

    /**
     * Return error response
     */
    private function errorResponse($message, $statusCode = 500, $debugError = null)
    {
        $response = [
            'success' => false,
            'message' => $message
        ];

        if ($debugError) {
            $response['error'] = $debugError;
        }

        return response()->json($response, $statusCode);
    }
}