<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserShift;
use App\Models\Shift;
use App\Models\UserWeekoffs;
use App\Models\UserJobDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Exception;

class ShiftController extends Controller
{
    /**
     * Get role-based shift plan
     */
    public function view_ai_all(Request $request)
    {
        try {
            $authUser = Auth::user();

            if (!$authUser) {
                return $this->errorResponse('User not authenticated', 401);
            }

            // Get date parameters
            $startDate = $request->start_date ? Carbon::parse($request->start_date) : now()->startOfMonth();
            $endDate = $request->end_date ? Carbon::parse($request->end_date) : now()->endOfMonth();

            // Validate date range
            if ($endDate->lt($startDate)) {
                return $this->errorResponse('End date cannot be before start date', 400);
            }

            if ($startDate->diffInMonths($endDate) > 6) {
                return $this->errorResponse('Date range cannot exceed 6 months', 400);
            }

            $result = [];

            // Role-based logic
            switch ($authUser->role) {
                case 'admin':
                case 'hr':
                    $result = $this->getAllEmployeesShiftPlan($authUser, $startDate, $endDate);
                    break;

                case 'manager':
                    $result = $this->getManagerTeamShiftPlan($authUser, $startDate, $endDate);
                    break;

                case 'employee':
                    $result = $this->getSingleUserShiftPlan($authUser->id, $startDate, $endDate);
                    break;

                default:
                    return $this->errorResponse('Unauthorized access', 403);
            }

            return response()->json([
                'success' => true,
                'message' => 'Shift plan fetched successfully',
                'data' => $result,
                'meta' => [
                    'user_role' => $authUser->role,
                    'date_range' => [
                        'start' => $startDate->toDateString(),
                        'end' => $endDate->toDateString()
                    ],
                    'total_employees' => count($result)
                ]
            ], 200);
        } catch (Exception $e) {
            Log::error('Role Based Shift Plan Error: ' . $e->getMessage(), [
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
     * Get shift plan for all employees (Admin/HR) including their own data
     */
    private function getAllEmployeesShiftPlan($authUser, $startDate, $endDate)
    {
        $users = User::where('status', 1)
            ->with(['jobDetails.designationRel', 'jobDetails.departmentRel'])
            ->get();

        $result = [];

        foreach ($users as $user) {
            $joiningDate = $this->getUserJoiningDate($user->id);
            $effectiveStartDate = $this->adjustStartDateByJoining($startDate, $joiningDate);

            if ($effectiveStartDate && $effectiveStartDate <= $endDate) {
                $shiftData = $this->fetchUserShiftData($user->id, $effectiveStartDate, $endDate);
                $shifts = $this->combineShiftData($shiftData, $effectiveStartDate, $endDate);

                $result[] = [
                    'user_id' => $user->id,
                    'employee_id' => $user->employee_id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'designation' => $user->jobDetails?->designationRel?->name,
                    'department' => $user->jobDetails?->departmentRel?->name,
                    'joining_date' => $joiningDate ? $joiningDate->toDateString() : null,
                    'shift_plan' => $shifts,
                    'summary' => $this->generateSummary($shifts),
                    'is_current_user' => ($user->id == $authUser->id)
                ];
            }
        }

        return $result;
    }

    /**
     * Get shift plan for manager's team including manager's own shifts
     */
    private function getManagerTeamShiftPlan($authUser, $startDate, $endDate)
    {
        $managerId = $authUser->id;

        $managerUsers = collect();

        $manager = User::where('id', $managerId)
            ->where('status', 1)
            ->with(['jobDetails.designationRel', 'jobDetails.departmentRel'])
            ->first();

        if ($manager) {
            $managerUsers->push($manager);
        }

        $teamMembers = User::managedBy($managerId)
            ->where('status', 1)
            ->with(['jobDetails.designationRel', 'jobDetails.departmentRel'])
            ->get();

        $allUsers = $managerUsers->concat($teamMembers);

        $result = [];

        foreach ($allUsers as $user) {
            $joiningDate = $this->getUserJoiningDate($user->id);
            $effectiveStartDate = $this->adjustStartDateByJoining($startDate, $joiningDate);

            if ($effectiveStartDate && $effectiveStartDate <= $endDate) {
                $shiftData = $this->fetchUserShiftData($user->id, $effectiveStartDate, $endDate);
                $shifts = $this->combineShiftData($shiftData, $effectiveStartDate, $endDate);

                $result[] = [
                    'user_id' => $user->id,
                    'employee_id' => $user->employee_id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'designation' => $user->jobDetails?->designationRel?->name,
                    'department' => $user->jobDetails?->departmentRel?->name,
                    'reporting_head' => $user->jobDetails?->reporting_head,
                    'joining_date' => $joiningDate ? $joiningDate->toDateString() : null,
                    'shift_plan' => $shifts,
                    'summary' => $this->generateSummary($shifts),
                    'is_manager' => ($user->id == $managerId),
                    'is_current_user' => ($user->id == $authUser->id)
                ];
            }
        }

        return $result;
    }

    /**
     * Get shift plan for a single user (Employee)
     */
    private function getSingleUserShiftPlan($userId, $startDate, $endDate)
    {
        $user = User::where('id', $userId)
            ->where('status', 1)
            ->with(['jobDetails.designationRel', 'jobDetails.departmentRel'])
            ->first();

        if (!$user) {
            return [];
        }

        $joiningDate = $this->getUserJoiningDate($user->id);
        $effectiveStartDate = $this->adjustStartDateByJoining($startDate, $joiningDate);

        if (!$effectiveStartDate || $effectiveStartDate > $endDate) {
            return [];
        }

        $shiftData = $this->fetchUserShiftData($user->id, $effectiveStartDate, $endDate);
        $shifts = $this->combineShiftData($shiftData, $effectiveStartDate, $endDate);

        return [[
            'user_id' => $user->id,
            'employee_id' => $user->employee_id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'designation' => $user->jobDetails?->designationRel?->name,
            'department' => $user->jobDetails?->departmentRel?->name,
            'joining_date' => $joiningDate ? $joiningDate->toDateString() : null,
            'shift_plan' => $shifts,
            'summary' => $this->generateSummary($shifts),
            'is_current_user' => true
        ]];
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
     * Adjust start date based on user's joining date
     */
    private function adjustStartDateByJoining($requestedStartDate, $joiningDate)
    {
        if (!$joiningDate) {
            return $requestedStartDate;
        }

        if ($requestedStartDate->lt($joiningDate)) {
            return $joiningDate->copy();
        }

        return $requestedStartDate;
    }

    /**
     * Fetch all user shift data - FIXED (No model casts)
     */
    private function fetchUserShiftData($userId, $startDate, $endDate)
    {
        $tenantId = (int) (optional(Auth::user())->tenant_id
            ?: \Illuminate\Support\Facades\DB::table('users')->where('id', $userId)->value('tenant_id'));

        $resolver = app(\App\Services\Attendance\TenantShiftResolver::class);
        $fixedShift = ($tenantId && !$resolver->isCustomShifts($tenantId))
            ? $resolver->defaultShift($tenantId)
            : null;

        if ($fixedShift) {
            // Custom shifts are off — one fixed company shift for every working
            // date in range; skip the weekly-off weekdays so combineShiftData
            // renders them as Week Off. Keys unchanged.
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
                    'start_time' => $fixedShift->start_time ?? null,
                    'end_time' => $fixedShift->end_time ?? null,
                    'status' => $d->lt($today) ? 'completed' : 'upcoming',
                    'type' => 'Shift',
                    'color_code' => $fixedShift->color_code ?? '#3b82f6',
                ];
            }
        } else {
            // ✅ FIX: Parse date strings to Carbon before formatting
            $shifts = UserShift::with('shift')
                ->where('user_id', $userId)
                ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
                ->orderBy('is_additional')
                ->get()
                ->map(function ($userShift) {
                    // Parse the date string to Carbon
                    $date = Carbon::parse($userShift->date);

                    return [
                        'date' => $date->format('Y-m-d'),
                        'shift_id' => $userShift->shift->id ?? null,
                        'shift_name' => $userShift->shift->name ?? null,
                        'start_time' => $userShift->shift->start_time ?? null,
                        'end_time' => $userShift->shift->end_time ?? null,
                        'status' => $userShift->status ?? 'upcoming',
                        'type' => 'Shift',
                        'color_code' => $userShift->shift->color_code ?? '#3b82f6'
                    ];
                })->groupBy('date')
                // Primary shift stays the day's item; 2nd+ shifts ride along.
                ->map(fn ($items) => $items->first() + ['additional_shifts' => $items->slice(1)->values()->all()]);
        }

        // ✅ FIX: Parse week off dates properly
        $dateWeekOffs = UserWeekoffs::where('user_id', $userId)
            ->where('off_type', 'date_based')
            ->where(function ($query) use ($startDate, $endDate) {
                $query->whereBetween('start_date', [$startDate->toDateString(), $endDate->toDateString()])
                    ->orWhereBetween('end_date', [$startDate->toDateString(), $endDate->toDateString()])
                    ->orWhere(function ($q) use ($startDate, $endDate) {
                        $q->where('start_date', '<=', $startDate->toDateString())
                            ->where('end_date', '>=', $endDate->toDateString());
                    });
            })
            ->get()
            ->map(function ($weekOff) {
                // Parse the date strings to Carbon
                $startDate = Carbon::parse($weekOff->start_date);
                $endDate = Carbon::parse($weekOff->end_date);
                
                // Generate all dates in the range
                $dates = [];
                $current = $startDate->copy();
                while ($current <= $endDate) {
                    $dates[] = [
                        'date' => $current->format('Y-m-d'),
                        'shift_id' => null,
                        'shift_name' => null,
                        'start_time' => null,
                        'end_time' => null,
                        'status' => 'Week Off',
                        'type' => 'Week Off',
                        'color_code' => '#dc3545'
                    ];
                    $current->addDay();
                }
                
                return $dates;
            })
            ->flatten(1)
            ->keyBy('date');

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

        $currentDate = $startDate->copy();

        while ($currentDate <= $endDate) {
            $dateString = $currentDate->toDateString();

            // Priority 1: Existing shift
            if (isset($shifts[$dateString])) {
                $item = $shifts[$dateString];
                
                // ✅ FIX: Safe time formatting with try-catch
                if (!empty($item['start_time'])) {
                    try {
                        $item['start_time'] = Carbon::parse($item['start_time'])->format('h:i A');
                    } catch (\Exception $e) {
                        // Keep as is if parsing fails
                    }
                }
                if (!empty($item['end_time'])) {
                    try {
                        $item['end_time'] = Carbon::parse($item['end_time'])->format('h:i A');
                    } catch (\Exception $e) {
                        // Keep as is if parsing fails
                    }
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