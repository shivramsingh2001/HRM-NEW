<?php

namespace App\Http\Controllers\Shift;

use App\Http\Controllers\Controller;
use App\Models\Shift;
use App\Models\UserShift;
use App\Models\UserWeekoffs;
use App\Models\User;
use App\Models\Department;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class ShiftController extends Controller
{
    /**
     * Display a listing of shifts
     */
    public function index(Request $request)
    {
        try {
            $query = Shift::query();

            // Filter by status
            if ($request->filled('status')) {
                $status = (int) $request->status;
                $query->where('status', $status);
                Log::info('Filtering by status: ' . $status);
            }

            // Search by name
            if ($request->filled('search')) {
                $query->where('name', 'LIKE', '%' . $request->search . '%');
            }

            // Filter by date range
            if ($request->filled('from_date') && $request->filled('to_date')) {
                $query->whereBetween('created_at', [$request->from_date, $request->to_date]);
            }

            // Per-shift "assigned this month" count for the list column
            $currentMonth = Carbon::now();
            $shifts = $query->withCount(['userShifts as assigned_this_month' => function ($q) use ($currentMonth) {
                $q->whereMonth('date', $currentMonth->month)->whereYear('date', $currentMonth->year);
            }])->orderBy('id', 'desc')->paginate(12)->withQueryString();

            // Get statistics
            $totalShifts = Shift::count();
            $activeShifts = Shift::where('status', 1)->count();
            $inactiveShifts = Shift::where('status', 0)->count();

            $assignedShiftsCount = UserShift::whereMonth('date', $currentMonth->month)
                ->whereYear('date', $currentMonth->year)
                ->count();

            return view('client.shift.index', compact(
                'shifts',
                'totalShifts',
                'activeShifts',
                'inactiveShifts',
                'assignedShiftsCount'
            ));
        } catch (Exception $e) {
            Log::error('Shift index error: ' . $e->getMessage());

            return back()->with('error', 'Failed to load shifts. Please try again.');
        }
    }

    /**
     * Store a newly created shift
     */
    public function store(Request $request)
    {
       
     
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255|unique:shifts,name',
                'start_time' => 'required|date_format:H:i',
                // No after:start_time — end <= start means the shift crosses midnight.
                'end_time' => 'required|date_format:H:i',
                'description' => 'nullable|string|max:500',
                'grace_minutes' => 'nullable|integer|min:0|max:120',
                'color_code' => 'nullable|string|max:7',
                'break_time' => 'nullable|integer|min:0|max:180'
            ]);
       if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            $shift = Shift::create([
                'name' => $request->name,
                'start_time' => $request->start_time,
                'end_time' => $request->end_time,
                'total_hours' => $this->computeTotalHours($request->start_time, $request->end_time, $request->break_time),
                'description' => $request->description,
                'grace_minutes' => $request->grace_minutes ?? 0,
                'color_code' => $request->color_code ?? '#3b82f6',
                'break_time' => $request->break_time ?? 0,
                'status' => 1,
                'created_by' => Auth::id()
            ]);

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Shift created successfully',
                'data' => $shift
            ], 201);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Shift creation error: ' . $e->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'Shift creation failed. Please try again.'
            ], 500);
        }
    }

    /**
     * Update the specified shift
     */
    public function update(Request $request, $id)
    {
        try {
            $shift = Shift::find($id);

            if (!$shift) {
                return response()->json([
                    'status' => false,
                    'message' => 'Shift not found'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255|unique:shifts,name,' . $id,
                'start_time' => 'required|date_format:H:i',
                // No after:start_time — end <= start means the shift crosses midnight.
                'end_time' => 'required|date_format:H:i',
                'description' => 'nullable|string|max:500',
                'grace_minutes' => 'nullable|integer|min:0|max:120',
                'status' => 'nullable|boolean',
                'color_code' => 'nullable|string|max:7',
                'break_time' => 'nullable|integer|min:0|max:180'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            $shift->update([
                'name' => $request->name,
                'start_time' => $request->start_time,
                'end_time' => $request->end_time,
                'total_hours' => $this->computeTotalHours($request->start_time, $request->end_time, $request->break_time),
                'description' => $request->description,
                'grace_minutes' => $request->grace_minutes ?? 0,
                'color_code' => $request->color_code ?? '#3b82f6',
                'break_time' => $request->break_time ?? 0,
                'status' => $request->status ?? $shift->status
            ]);

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Shift updated successfully',
                'data' => $shift
            ], 200);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Shift update error: ' . $e->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'Shift update failed. Please try again.'
            ], 500);
        }
    }

    /**
     * Working hours for a shift = span (handling midnight crossing) minus break.
     */
    private function computeTotalHours($start, $end, $break): float
    {
        $s = Carbon::parse($start);
        $e = Carbon::parse($end);
        if ($e->lessThanOrEqualTo($s)) {
            $e->addDay(); // crosses midnight
        }

        return round(max(0, $s->diffInMinutes($e) - (int) ($break ?? 0)) / 60, 2);
    }

    /**
     * Change shift status
     */
    public function changeStatus($id)
    {
        try {
            $shift = Shift::find($id);

            if (!$shift) {
                return response()->json([
                    'status' => false,
                    'message' => 'Shift not found'
                ], 404);
            }

            DB::beginTransaction();

            $shift->status = $shift->status == 1 ? 0 : 1;
            $shift->save();

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Status updated successfully',
                'new_status' => $shift->status
            ], 200);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Shift status change error: ' . $e->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'Failed to update status'
            ], 500);
        }
    }

    /**
     * Delete shift
     */
    public function destroy($id)
    {
        try {
            $shift = Shift::find($id);

            if (!$shift) {
                return response()->json([
                    'status' => false,
                    'message' => 'Shift not found'
                ], 404);
            }

            // Check if shift is being used
            $assignedCount = UserShift::where('shift_id', $id)->count();

            if ($assignedCount > 0) {
                return response()->json([
                    'status' => false,
                    'message' => 'Cannot delete shift as it is assigned to ' . $assignedCount . ' users'
                ], 400);
            }

            // The tenant's fixed company shift (used when custom shifts are off).
            $isDefaultShift = \App\Models\Tenant::where('id', $shift->tenant_id)
                ->where('default_shift_id', $id)
                ->exists();

            if ($isDefaultShift) {
                return response()->json([
                    'status' => false,
                    'message' => 'Cannot delete the company default shift. Change it in Shift Settings instead.'
                ], 400);
            }

            DB::beginTransaction();

            $shiftName = $shift->name;
            $shift->delete();

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Shift "' . $shiftName . '" deleted successfully'
            ], 200);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Shift delete error: ' . $e->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'Delete failed. Please try again.'
            ], 500);
        }
    }

    /**
     * The "Shift Roster" page — which employee is on which shift (Day or Week
     * grid), plus the assign-shift modal + the assignments list.
     */
    public function roster(Request $request)
    {
        try {
            $tenantId = Auth::user()->tenant_id;
            $view = in_array($request->input('view'), ['week', 'month'], true) ? $request->input('view') : 'day';
            $anchor = $request->filled('date') ? Carbon::parse($request->date) : Carbon::today();

            if ($view === 'week') {
                $start = $anchor->copy()->startOfWeek(Carbon::MONDAY);
                $dates = collect(range(0, 6))->map(fn ($i) => $start->copy()->addDays($i));
            } elseif ($view === 'month') {
                $start = $anchor->copy()->startOfMonth();
                $dates = collect(range(0, $anchor->daysInMonth - 1))->map(fn ($i) => $start->copy()->addDays($i));
            } else {
                $dates = collect([$anchor->copy()]);
            }
            $dateStrings = $dates->map(fn ($d) => $d->format('Y-m-d'))->all();

            // Employees (paginated)
            $userQuery = User::where('tenant_id', $tenantId)
                ->where('status', 1)
                ->where('role', '!=', 'admin')
                ->with(['jobDetails.department', 'jobDetails.designation', 'basicDetails']);

            // A manager only sees their own direct reportees on the roster.
            if (Auth::user()->role === 'manager') {
                $managerId = Auth::id();
                $userQuery->managedBy($managerId);
            }

            if ($request->filled('search')) {
                $s = $request->search;
                $userQuery->where(function ($q) use ($s) {
                    $q->where('name', 'LIKE', "%{$s}%")->orWhere('employee_id', 'LIKE', "%{$s}%");
                });
            }
            if ($request->filled('department_id')) {
                $deptId = $request->department_id;
                $userQuery->whereHas('jobDetails', fn ($q) => $q->where('department', $deptId));
            }

            $perPage = $view === 'month' ? 12 : ($view === 'week' ? 15 : 25);
            $users = $userQuery->orderBy('name')->paginate($request->input('per_page', $perPage))->withQueryString();
            $userIds = collect($users->items())->pluck('id');

            // Bulk maps
            $assignments = UserShift::with('shift')
                ->where('tenant_id', $tenantId)
                ->whereIn('user_id', $userIds)
                ->whereIn('date', $dateStrings)
                ->get()
                ->keyBy(fn ($r) => $r->user_id . '|' . Carbon::parse($r->date)->format('Y-m-d'));

            $weekoffs = UserWeekoffs::where('tenant_id', $tenantId)
                ->where('status', 1)
                ->whereIn('user_id', $userIds)
                ->get()
                ->groupBy('user_id');

            $clockedIn = DB::table('attendances')
                ->where('tenant_id', $tenantId)
                ->whereIn('user_id', $userIds)
                ->whereIn('date', $dateStrings)
                ->whereNotNull('clock_in')
                ->get(['user_id', 'date'])
                ->map(fn ($r) => $r->user_id . '|' . Carbon::parse($r->date)->format('Y-m-d'))
                ->flip();

            // Build the grid: cells[userId][Y-m-d] = ['type' => shift|weekoff|unassigned, ...]
            $cells = [];
            foreach ($userIds as $uid) {
                foreach ($dates as $d) {
                    $ds = $d->format('Y-m-d');
                    $key = $uid . '|' . $ds;
                    $row = $assignments->get($key);

                    if ($row && $row->shift) {
                        $cells[$uid][$ds] = [
                            'type' => 'shift',
                            'name' => $row->shift->name,
                            'color' => $row->shift->color_code ?: '#4f46e5',
                            'start' => $row->shift->start_time,
                            'end' => $row->shift->end_time,
                            'shift_id' => $row->shift_id,
                            'status' => $row->status,
                        ];
                    } elseif ($this->isWeekOffOn($weekoffs->get($uid), $d)) {
                        $cells[$uid][$ds] = ['type' => 'weekoff'];
                    } else {
                        $cells[$uid][$ds] = ['type' => 'unassigned'];
                    }

                    $cells[$uid][$ds]['clocked_in'] = $clockedIn->has($key);
                }
            }

            $shifts = Shift::where('tenant_id', $tenantId)->where('status', 1)
                ->orderBy('name')->get(['id', 'name', 'color_code', 'start_time', 'end_time']);
            $departments = Department::where('status', 1)->orderBy('name')->get(['id', 'name']);

            // Full employee list for the assign modal + the assignments-list filter.
            $allUsers = User::where('tenant_id', $tenantId)
                ->where('status', 1)
                ->where('role', '!=', 'admin')
                ->orderBy('name')
                ->get(['id', 'name', 'employee_id']);

            $weekdays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

            return view('client.shift.roster', [
                'users' => $users,
                'dates' => $dates,
                'view' => $view,
                'anchor' => $anchor,
                'cells' => $cells,
                'shifts' => $shifts,
                'departments' => $departments,
                'allUsers' => $allUsers,
                'weekdays' => $weekdays,
                'shiftFilter' => $request->input('shift_id'),
            ]);
        } catch (Exception $e) {
            Log::error('Shift roster error: ' . $e->getMessage());
            return back()->with('error', 'Failed to load the shift roster');
        }
    }

    /**
     * Is $date a week-off for a user, given their pre-loaded UserWeekoffs collection?
     * Same predicate as checkDateIsWeekOff(), collection-based.
     */
    private function isWeekOffOn($userWeekoffs, Carbon $date): bool
    {
        if (!$userWeekoffs) {
            return false;
        }

        $ds = $date->format('Y-m-d');
        $dayName = $date->format('l');

        foreach ($userWeekoffs as $wo) {
            if ($wo->off_type === 'day_based') {
                if (strtolower($wo->day_name ?? '') !== strtolower($dayName)) {
                    continue;
                }
                if ($wo->start_date && $ds < Carbon::parse($wo->start_date)->format('Y-m-d')) {
                    continue;
                }
                if ($wo->end_date && $ds > Carbon::parse($wo->end_date)->format('Y-m-d')) {
                    continue;
                }
                return true;
            }
            if ($wo->off_type === 'date_based') {
                if ($wo->start_date && $wo->end_date
                    && $ds >= Carbon::parse($wo->start_date)->format('Y-m-d')
                    && $ds <= Carbon::parse($wo->end_date)->format('Y-m-d')) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Get users by type for AJAX
     */
    public function getUsersByType(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'type' => 'required|in:all,department',
                'department_id' => 'required_if:type,department|exists:departments,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $query = User::query()
                ->where('status', 1)
                ->select('id', 'name', 'email', 'employee_id');

            if ($request->type === 'department' && $request->department_id) {
                $query->whereHas('jobDetails', function ($q) use ($request) {
                    $q->where('department', $request->department_id);
                });
            }

            // Optional search
            if ($request->has('search') && $request->search !== '') {
                $query->where(function ($q) use ($request) {
                    $q->where('name', 'LIKE', '%' . $request->search . '%')
                        ->orWhere('email', 'LIKE', '%' . $request->search . '%')
                        ->orWhere('employee_id', 'LIKE', '%' . $request->search . '%');
                });
            }

            $users = $query->orderBy('name')->get();

            return response()->json([
                'status' => true,
                'data' => $users
            ], 200);
        } catch (Exception $e) {
            Log::error('Get users error: ' . $e->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'Unable to fetch users'
            ], 500);
        }
    }

    /**
     * Assign shift to users
     */
    public function assignShift(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'assign_type' => 'required|in:user,department,all',
            'user_ids' => 'required_if:assign_type,user|array',
            'user_ids.*' => 'exists:users,id',
            'department_id' => 'required_if:assign_type,department|exists:departments,id',
            'shift_id' => 'required|exists:shifts,id',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'week_off_type' => 'nullable|in:day_based,date_based',
            'week_off_days' => 'required_if:week_off_type,day_based|array',
            'week_off_days.*' => 'in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday',
            'week_off_dates' => 'required_if:week_off_type,date_based|array',
            'week_off_dates.*' => 'date',
            'override_existing' => 'nullable|boolean',
            'apply_to_future_only' => 'nullable|boolean'
        ]);

        if ($validator->fails()) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'status' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        DB::beginTransaction();

        try {
            $userIds = $this->getUserIdsForAssignment($request);

            if (empty($userIds)) {
                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json([
                        'status' => false,
                        'message' => 'No users found to assign shift'
                    ], 400);
                }

                return redirect()->back()
                    ->with('error', 'No users found to assign shift')
                    ->withInput();
            }

            $shift = Shift::find($request->shift_id);
            $startDate = Carbon::parse($request->start_date);
            $endDate = $request->end_date ? Carbon::parse($request->end_date) : $startDate;

            if ($startDate->diffInDays($endDate) > 90) {
                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json([
                        'status' => false,
                        'message' => 'Date range cannot exceed 90 days'
                    ], 400);
                }

                return redirect()->back()
                    ->with('error', 'Date range cannot exceed 90 days')
                    ->withInput();
            }

            $assignedCount = 0;
            $skippedCount = 0;
            $alreadyAssignedCount = 0;
            $duplicateSkippedCount = 0;
            $weekOffCount = 0;

            foreach ($userIds as $userId) {
                if ($request->week_off_type) {
                    $weekOffResult = $this->assignWeekOffs($userId, $request);
                    $weekOffCount += $weekOffResult;
                }

                $result = $this->assignShiftsForUser(
                    $userId,
                    $shift->id,
                    $startDate,
                    $endDate,
                    $request
                );

                $assignedCount += $result['assigned'];
                $skippedCount += $result['skipped'];
                $alreadyAssignedCount += $result['already_assigned'];
                $duplicateSkippedCount += $result['duplicate_skipped'] ?? 0;
            }

            DB::commit();

            $message = "Shift assigned successfully! ";
            $message .= "New: $assignedCount, ";

            if ($alreadyAssignedCount > 0) {
                $message .= "Existing: $alreadyAssignedCount, ";
            }

            if ($duplicateSkippedCount > 0) {
                $message .= "Duplicates: $duplicateSkippedCount, ";
            }

            $message .= "Skipped: $skippedCount";

            if ($weekOffCount > 0) {
                $message .= ", Week Offs: $weekOffCount";
            }

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'status' => true,
                    'message' => $message,
                    'data' => [
                        'assigned' => $assignedCount,
                        'already_assigned' => $alreadyAssignedCount,
                        'duplicate_skipped' => $duplicateSkippedCount,
                        'skipped' => $skippedCount,
                        'week_offs' => $weekOffCount
                    ]
                ], 200);
            }

            return redirect()->route('shift.roster')
                ->with('success', $message);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Shift assignment error: ' . $e->getMessage());

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Shift assignment failed: ' . $e->getMessage()
                ], 500);
            }

            return redirect()->back()
                ->with('error', 'Shift assignment failed: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Get user IDs based on assignment type
     */
    private function getUserIdsForAssignment($request)
    {
        if ($request->assign_type === 'all') {
            return User::where('status', 1)->pluck('id')->toArray();
        }

        if ($request->assign_type === 'department') {
            return User::whereHas('jobDetails', function ($query) use ($request) {
                $query->where('department', $request->department_id);
            })->where('status', 1)->pluck('id')->toArray();
        }

        return $request->user_ids ?? [];
    }

    /**
     * Assign week offs to a user
     */
    /**
     * Assign week offs to a user - Now stores with proper date ranges
     */
    private function assignWeekOffs($userId, $request)
    {
        $count = 0;

        // If overriding existing, delete all existing week-offs for this user
        if ($request->override_existing) {
            UserWeekoffs::where('user_id', $userId)->delete();
        }

        // Handle DAY-BASED week-offs (recurring weekly)
        if ($request->week_off_type === 'day_based' && !empty($request->week_off_days)) {
            $startDate = $request->filled('start_date') ? Carbon::parse($request->start_date) : null;
            $endDate = $request->filled('end_date') ? Carbon::parse($request->end_date) : null;

            foreach ($request->week_off_days as $day) {
                // Check if already exists
                $exists = UserWeekoffs::where('user_id', $userId)
                    ->where('off_type', 'day_based')
                    ->where('day_name', $day)
                    ->where('start_date', $startDate ? $startDate->toDateString() : null)
                    ->where('end_date', $endDate ? $endDate->toDateString() : null)
                    ->exists();

                if (!$exists) {
                    UserWeekoffs::create([
                        'user_id' => $userId,
                        'off_type' => 'day_based',
                        'day_name' => $day,
                        'start_date' => $startDate ? $startDate->toDateString() : null,
                        'end_date' => $endDate ? $endDate->toDateString() : null,
                        'status' => 1,
                        'created_by' => Auth::id()
                    ]);
                    $count++;

                    Log::info("Created day-based week-off", [
                        'user_id' => $userId,
                        'day' => $day,
                        'start_date' => $startDate ? $startDate->toDateString() : 'permanent',
                        'end_date' => $endDate ? $endDate->toDateString() : 'permanent'
                    ]);
                }
            }
        }

        // Handle DATE-BASED week-offs (specific dates)
        if ($request->week_off_type === 'date_based' && !empty($request->week_off_dates)) {
            // Group consecutive dates into ranges
            $dates = collect($request->week_off_dates)->map(function ($date) {
                return Carbon::parse($date)->toDateString();
            })->sort()->values()->toArray();

            $ranges = $this->groupDatesIntoRanges($dates);

            foreach ($ranges as $range) {
                $exists = UserWeekoffs::where('user_id', $userId)
                    ->where('off_type', 'date_based')
                    ->whereDate('start_date', $range['start'])
                    ->whereDate('end_date', $range['end'])
                    ->exists();

                if (!$exists) {
                    UserWeekoffs::create([
                        'user_id' => $userId,
                        'off_type' => 'date_based',
                        'start_date' => $range['start'],
                        'end_date' => $range['end'],
                        'status' => 1,
                        'created_by' => Auth::id()
                    ]);
                    $count++;

                    Log::info("Created date-based week-off range", [
                        'user_id' => $userId,
                        'start' => $range['start'],
                        'end' => $range['end']
                    ]);
                }
            }
        }

        return $count;
    }

    /**
     * Assign shifts to a user for a date range - With duplicate handling and week-off checking
     */
    private function assignShiftsForUser($userId, $shiftId, $startDate, $endDate, $request)
    {
        $assigned = 0;
        $skipped = 0;
        $alreadyAssigned = 0;
        $duplicateSkipped = 0;
        $weekOffSkipped = 0;
    
        // Get all active week-offs for this user
        $userWeekoffs = UserWeekoffs::where('user_id', $userId)
            ->where('status', 1)
            ->get();
    
        // Get existing shifts for this user in the date range
        $existingShifts = UserShift::where('user_id', $userId)
            ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
            ->get()
            ->keyBy('date');
    
        $current = clone $startDate;
        while ($current->lte($endDate)) {
            $dateString = $current->toDateString();
    
            // Check if this date is a week-off
            $isWeekOff = $this->checkDateIsWeekOff($userId, $current, $userWeekoffs, $request);
    
            if ($isWeekOff) {
                Log::info("Skipping week-off date", [
                    'user_id' => $userId,
                    'date' => $dateString
                ]);
                $weekOffSkipped++;
                $current->addDay();
                continue;
            }
    
            // Check if shift already exists in our pre-loaded collection
            if (isset($existingShifts[$dateString])) {
                $existingShift = $existingShifts[$dateString];
    
                $shouldUpdate = false;
    
                // If override_existing is true, update the shift
                if ($request->override_existing) {
                    // If apply_to_future_only is true, only update future dates
                    if ($request->apply_to_future_only && $current->isFuture()) {
                        $shouldUpdate = true;
                    } elseif (!$request->apply_to_future_only) {
                        // If apply_to_future_only is false, update all dates (past, present, future)
                        $shouldUpdate = true;
                    }
                }
    
                if ($shouldUpdate) {
                    $existingShift->update([
                        'shift_id' => $shiftId,
                        'status' => 'upcoming'
                    ]);
                    $assigned++;
                   
                } else {
                    $alreadyAssigned++;
                    Log::info("Shift already exists, not updating", [
                        'user_id' => $userId,
                        'date' => $dateString
                    ]);
                }
                $current->addDay();
                continue;
            }
    
            // ====== FIX: When override_existing is true, assign to ALL dates ======
            if ($request->override_existing) {
                // When overriding, assign to ALL dates regardless of past/future
                try {
                    UserShift::create([
                        'user_id' => $userId,
                        'shift_id' => $shiftId,
                        'date' => $dateString,
                        'status' => 'upcoming',
                        'created_by' => Auth::id()
                    ]);
                    $assigned++;
                    // Add to existing shifts collection to prevent duplicate checks
                    $existingShifts[$dateString] = true;
    
                  
                } catch (\Illuminate\Database\QueryException $e) {
                    // Check if it's a duplicate entry error (MySQL error 1062)
                    if ($e->errorInfo[1] == 1062) {
                        $duplicateSkipped++;
                        $existingShifts[$dateString] = true;
                       
                    } else {
                        throw $e;
                    }
                }
            } else {
                // When NOT overriding, only assign for future dates or today
                if ($current->isFuture() || $current->isToday()) {
                    try {
                        UserShift::create([
                            'user_id' => $userId,
                            'shift_id' => $shiftId,
                            'date' => $dateString,
                            'status' => 'upcoming',
                            'created_by' => Auth::id()
                        ]);
                        $assigned++;
                        $existingShifts[$dateString] = true;
    
                       
                    } catch (\Illuminate\Database\QueryException $e) {
                        // Check if it's a duplicate entry error (MySQL error 1062)
                        if ($e->errorInfo[1] == 1062) {
                            $duplicateSkipped++;
                            $existingShifts[$dateString] = true;
                           
                        } else {
                            throw $e;
                        }
                    }
                } else {
                    $skipped++;
                   
                }
            }
    
            $current->addDay();
        }
    
        Log::info('Shift assignment result', [
            'user_id' => $userId,
            'assigned' => $assigned,
            'skipped' => $skipped,
            'already_assigned' => $alreadyAssigned,
            'duplicate_skipped' => $duplicateSkipped,
            'week_off_skipped' => $weekOffSkipped
        ]);
    
        return [
            'assigned' => $assigned,
            'skipped' => $skipped,
            'already_assigned' => $alreadyAssigned,
            'duplicate_skipped' => $duplicateSkipped,
            'week_off_skipped' => $weekOffSkipped
        ];
    }


    /**
     * Update single user shift
     */
    public function updateUserShift(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_shift_id' => 'required|exists:user_shifts,id',
            'shift_id' => 'required|exists:shifts,id',
            'status' => 'nullable|in:upcoming,ongoing,complete',
            'reason' => 'nullable|string|max:500'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $userShift = UserShift::find($request->user_shift_id);

            $userShift->shift_id = $request->shift_id;

            if ($request->has('status') && $request->status !== '') {
                $userShift->status = $request->status;
            }

            $userShift->save();

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Shift updated successfully',
                'data' => $userShift->load('shift')
            ], 200);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Update user shift error: ' . $e->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'Failed to update shift'.$e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk delete user shifts
     */
    public function bulkDeleteUserShifts(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_ids' => 'required|array',
            'user_ids.*' => 'exists:users,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'nullable|string|max:500'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            $deleted = UserShift::whereIn('user_id', $request->user_ids)
                ->whereBetween('date', [$request->start_date, $request->end_date])
                ->delete();

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => $deleted . ' shifts deleted successfully',
                'data' => ['deleted_count' => $deleted]
            ], 200);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Bulk delete user shifts error: ' . $e->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'Failed to delete shifts'
            ], 500);
        }
    }

    /**
     * Get user shifts data for AJAX/datatable
     */
    public function getUserShiftsData(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'from_date' => 'required|date',
                'to_date' => 'required|date|after_or_equal:from_date',
                'user_id' => 'nullable|exists:users,id',
                'shift_id' => 'nullable|exists:shifts,id',
                'status' => 'nullable|in:upcoming,ongoing,complete'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'errors' => $validator->errors(),
                    'message' => 'Please provide valid date range'
                ], 422);
            }

            $query = UserShift::with(['user', 'shift'])
                ->whereBetween('date', [$request->from_date, $request->to_date]);

            if ($request->filled('user_id')) {
                $query->where('user_id', $request->user_id);
            }

            if ($request->filled('shift_id')) {
                $query->where('shift_id', $request->shift_id);
            }

            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            // Summary over the WHOLE filtered set (not just the current page).
            // Cloned Eloquent builder so the tenant global scope still applies.
            $counts = (clone $query)
                ->reorder()
                ->select('status', DB::raw('COUNT(*) as c'))
                ->groupBy('status')
                ->pluck('c', 'status');
            $summary = [
                'total' => (int) $counts->sum(),
                'by_status' => [
                    'upcoming' => (int) ($counts['upcoming'] ?? 0),
                    'ongoing' => (int) ($counts['ongoing'] ?? 0),
                    'complete' => (int) ($counts['complete'] ?? 0),
                ],
            ];

            $userShifts = $query->orderBy('date', 'desc')
                ->orderBy('user_id')
                ->paginate($request->per_page ?? 20);

            return response()->json([
                'status' => true,
                'data' => $userShifts->items(),
                'summary' => $summary,
                'pagination' => [
                    'total' => $userShifts->total(),
                    'per_page' => $userShifts->perPage(),
                    'current_page' => $userShifts->currentPage(),
                    'last_page' => $userShifts->lastPage(),
                    'from' => $userShifts->firstItem(),
                    'to' => $userShifts->lastItem()
                ]
            ], 200);
        } catch (Exception $e) {
            Log::error('Get user shifts data error: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch user shifts: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk update user shifts
     */
    public function bulkUpdateUserShifts(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_shifts' => 'required|array',
            'user_shifts.*.id' => 'required|exists:user_shifts,id',
            'user_shifts.*.shift_id' => 'required|exists:shifts,id',
            'user_shifts.*.status' => 'required|in:upcoming,ongoing,complete'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            $updated = 0;
            foreach ($request->user_shifts as $item) {
                $userShift = UserShift::find($item['id']);
                $userShift->update([
                    'shift_id' => $item['shift_id'],
                    'status' => $item['status']
                ]);
                $updated++;
            }

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => $updated . ' shifts updated successfully',
                'data' => ['updated' => $updated]
            ], 200);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Bulk update user shifts error: ' . $e->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'Failed to update shifts'
            ], 500);
        }
    }

    /**
     * Export user shifts to CSV/Excel
     */
    public function exportUserShifts(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'from_date' => 'required|date',
                'to_date' => 'required|date|after_or_equal:from_date',
            ]);

            if ($validator->fails()) {
                return back()->withErrors($validator);
            }

            $query = UserShift::with(['user', 'shift'])
                ->whereBetween('date', [$request->from_date, $request->to_date]);

            if ($request->filled('user_id')) {
                $query->where('user_id', $request->user_id);
            }

            if ($request->filled('department_id')) {
                $query->whereHas('user.jobDetails', function ($q) use ($request) {
                    $q->where('department', $request->department_id);
                });
            }

            $userShifts = $query->orderBy('date')->orderBy('user_id')->get();

            $filename = 'user_shifts_' . $request->from_date . '_to_' . $request->to_date . '.csv';
            $headers = [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ];

            $callback = function () use ($userShifts) {
                $file = fopen('php://output', 'w');

                fputcsv($file, ['Date', 'Employee ID', 'Employee Name', 'Shift', 'Start Time', 'End Time', 'Status']);

                foreach ($userShifts as $shift) {
                    fputcsv($file, [
                        $shift->date->format('Y-m-d'),
                        $shift->user->employee_id ?? 'N/A',
                        $shift->user->name ?? 'N/A',
                        $shift->shift->name ?? 'N/A',
                        $shift->shift->start_time ?? 'N/A',
                        $shift->shift->end_time ?? 'N/A',
                        $shift->status
                    ]);
                }

                fclose($file);
            };

            return response()->stream($callback, 200, $headers);
        } catch (Exception $e) {
            Log::error('Export user shifts error: ' . $e->getMessage());
            return back()->with('error', 'Failed to export shifts');
        }
    }

    /**
     * Assign shifts in bulk to multiple users
     */
    public function assignBulkShifts(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_ids' => 'required|array',
            'user_ids.*' => 'exists:users,id',
            'shift_id' => 'required|exists:shifts,id',
            'dates' => 'required|array',
            'dates.*' => 'date',
            'override_existing' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            $assigned = 0;
            $skipped = 0;

            foreach ($request->user_ids as $userId) {
                foreach ($request->dates as $date) {
                    // Check if shift already exists
                    $existing = UserShift::where('user_id', $userId)
                        ->where('date', $date)
                        ->first();

                    if ($existing) {
                        if ($request->override_existing) {
                            $existing->update(['shift_id' => $request->shift_id]);
                            $assigned++;
                        } else {
                            $skipped++;
                        }
                    } else {
                        try {
                            UserShift::create([
                                'user_id' => $userId,
                                'shift_id' => $request->shift_id,
                                'date' => $date,
                                'status' => 'upcoming',
                                'created_by' => Auth::id()
                            ]);
                            $assigned++;
                        } catch (\Illuminate\Database\QueryException $e) {
                            if ($e->errorInfo[1] == 1062) {
                                // Duplicate entry - skip silently
                                Log::info("Bulk assign duplicate skipped for user {$userId} on date {$date}");
                                $skipped++;
                            } else {
                                throw $e;
                            }
                        }
                    }
                }
            }

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => "Assigned: $assigned shifts, Skipped: $skipped",
                'data' => [
                    'assigned' => $assigned,
                    'skipped' => $skipped
                ]
            ], 200);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Bulk assign shifts error: ' . $e->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'Failed to assign shifts'
            ], 500);
        }
    }

    /**
     * Delete assigned shift
     */
    public function destroyAssigned($id)
    {
        try {
            $userShift = UserShift::find($id);

            if (!$userShift) {
                return response()->json([
                    'status' => false,
                    'message' => 'Shift assignment not found'
                ], 404);
            }

            DB::beginTransaction();
            $userShift->delete();
            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Shift assignment deleted successfully'
            ], 200);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Delete assigned shift error: ' . $e->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'Failed to delete shift assignment'
            ], 500);
        }
    }

    /**
     * Check if a specific date is a week-off for a user
     * Uses pre-loaded week-offs for better performance
     */
    private function checkDateIsWeekOff($userId, Carbon $date, $userWeekoffs, $request)
    {
        $dateString = $date->toDateString();
        $dayName = $date->format('l');

        foreach ($userWeekoffs as $weekoff) {
            // Check if week-off is active for this date
            if ($weekoff->status != 1) {
                continue;
            }

            // For day-based week-offs
            if ($weekoff->off_type == 'day_based') {
                // Check if day matches
                if ($weekoff->day_name != $dayName) {
                    continue;
                }

                // Check date range if exists
                if ($weekoff->start_date && $dateString < $weekoff->start_date) {
                    continue;
                }
                if ($weekoff->end_date && $dateString > $weekoff->end_date) {
                    continue;
                }

                return true;
            }

            // For date-based week-offs
            if ($weekoff->off_type == 'date_based') {
                if ($dateString >= $weekoff->start_date && $dateString <= $weekoff->end_date) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Group consecutive dates into ranges
     */
    private function groupDatesIntoRanges($dates)
    {
        $ranges = [];
        $i = 0;
        $len = count($dates);

        while ($i < $len) {
            $start = $dates[$i];
            $end = $start;

            $j = $i + 1;
            while ($j < $len) {
                $current = Carbon::parse($dates[$j]);
                $prev = Carbon::parse($dates[$j - 1]);

                if ($current->diffInDays($prev) == 1) {
                    $end = $dates[$j];
                    $j++;
                } else {
                    break;
                }
            }

            $ranges[] = [
                'start' => $start,
                'end' => $end
            ];

            $i = $j;
        }

        return $ranges;
    }
}
