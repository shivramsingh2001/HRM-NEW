<?php

namespace App\Http\Controllers\Shift;

use App\Http\Controllers\Controller;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\UserShift;
use App\Models\UserWeekoffs;
use App\Models\User;
use App\Models\Department;
use App\Services\Shift\ShiftAssignmentService;
use App\Services\Shift\ShiftAssignmentValidator;
use App\Services\Shift\ShiftOverlapGuard;
use App\Support\ShiftWindow;
use App\Support\WeekOffPredicate;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Log;

class ShiftController extends Controller
{
    public function __construct(
        private ShiftAssignmentService $shiftAssignmentService,
        private ShiftAssignmentValidator $shiftAssignmentValidator,
        private ShiftOverlapGuard $overlapGuard
    ) {
    }

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
            }])->orderBy('id', 'desc')->paginate(15)->withQueryString();

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
                // Shift names are unique per company, not across all companies.
                'name' => ['required', 'string', 'max:255', $this->uniqueShiftName()],
                'start_time' => 'required|date_format:H:i',
                // Overnight shifts end on/before their start time — checked in validateOvernightTimes().
                'end_time' => 'required|date_format:H:i',
                'is_overnight' => 'nullable|boolean',
                'description' => 'nullable|string|max:500',
                'grace_minutes' => 'nullable|integer|min:0|max:120',
                'color_code' => 'nullable|string|max:7',
                'break_time' => 'nullable|integer|min:0|max:180'
            ]);
            $this->validateOvernightTimes($validator, $request);
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
                'is_overnight' => $request->boolean('is_overnight'),
                'total_hours' => ShiftWindow::workingHours($request->start_time, $request->end_time, $request->break_time, $request->boolean('is_overnight')),
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
                'name' => ['required', 'string', 'max:255', $this->uniqueShiftName()->ignore($shift->id)],
                'start_time' => 'required|date_format:H:i',
                'end_time' => 'required|date_format:H:i',
                'is_overnight' => 'nullable|boolean',
                'description' => 'nullable|string|max:500',
                'grace_minutes' => 'nullable|integer|min:0|max:120',
                'status' => 'nullable|boolean',
                'color_code' => 'nullable|string|max:7',
                'break_time' => 'nullable|integer|min:0|max:180'
            ]);
            $this->validateOvernightTimes($validator, $request);

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
                'is_overnight' => $request->boolean('is_overnight'),
                'total_hours' => ShiftWindow::workingHours($request->start_time, $request->end_time, $request->break_time, $request->boolean('is_overnight')),
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
     * Shift name must be unique within the logged-in user's company.
     */
    private function uniqueShiftName(): \Illuminate\Validation\Rules\Unique
    {
        return Rule::unique('shifts', 'name')->where('tenant_id', Auth::user()->tenant_id);
    }

    /**
     * Times must agree with the "Overnight shift" checkbox (ShiftWindow::timesError).
     */
    private function validateOvernightTimes(\Illuminate\Validation\Validator $validator, Request $request): void
    {
        $validator->after(function ($v) use ($request) {
            if ($v->errors()->hasAny(['start_time', 'end_time'])) {
                return;
            }
            $error = ShiftWindow::timesError($request->start_time, $request->end_time, $request->boolean('is_overnight'));
            if ($error) {
                $v->errors()->add('end_time', $error);
            }
        });
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

            $users = $userQuery->orderBy('name')->paginate($request->input('per_page', 15))->withQueryString();
            $userIds = collect($users->items())->pluck('id');

            // Bulk maps — every shift a user works on a date (primary first,
            // then additional shifts by start time).
            $assignments = UserShift::with('shift')
                ->where('tenant_id', $tenantId)
                ->whereIn('user_id', $userIds)
                ->whereIn('date', $dateStrings)
                ->get()
                ->filter(fn ($r) => $r->shift)
                ->sortBy(fn ($r) => [(int) $r->is_additional, (string) $r->shift->start_time])
                ->groupBy(fn ($r) => $r->user_id . '|' . Carbon::parse($r->date)->format('Y-m-d'));

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
                    $dayRows = $assignments->get($key, collect())->values();
                    $row = $dayRows->first();

                    if ($row) {
                        $cells[$uid][$ds] = [
                            'type' => 'shift',
                            'name' => $row->shift->name,
                            'color' => $row->shift->color_code ?: '#4f46e5',
                            'start' => $row->shift->start_time,
                            'end' => $row->shift->end_time,
                            'shift_id' => $row->shift_id,
                            'status' => $row->status,
                            // 2nd+ shifts the same day (multi-shift).
                            'extra' => $dayRows->slice(1)->map(fn ($x) => [
                                'name' => $x->shift->name,
                                'color' => $x->shift->color_code ?: '#4f46e5',
                                'start' => $x->shift->start_time,
                                'end' => $x->shift->end_time,
                                'shift_id' => $x->shift_id,
                            ])->values()->all(),
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

        return WeekOffPredicate::isWeekOff($userWeekoffs, $date);
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
        $type = in_array($request->input('type'), ['permanent', 'flexible'], true)
            ? $request->input('type')
            : 'flexible';

        // The "Weekly offs → Dates" row always has one <input type="date"> in the
        // DOM (only visually hidden when that mode isn't selected), so a plain
        // form serialize sends week_off_dates[]="" whenever it's untouched.
        // Strip blanks before validating so an empty/hidden row never fails the
        // date rule or gets treated as a real week-off date.
        if ($request->has('week_off_dates')) {
            $request->merge([
                'week_off_dates' => array_values(array_filter(
                    (array) $request->input('week_off_dates'),
                    fn ($d) => filled($d)
                )),
            ]);
        }

        $rules = [
            'assign_type' => 'required|in:user,department,all',
            'user_ids' => 'required_if:assign_type,user|array',
            'user_ids.*' => 'exists:users,id',
            'department_id' => 'nullable|required_if:assign_type,department|exists:departments,id',
            'shift_id' => 'required|exists:shifts,id',
            'start_date' => 'required|date',
            'week_off_type' => 'nullable|in:day_based,date_based',
            'week_off_days' => 'required_if:week_off_type,day_based|array',
            'week_off_days.*' => 'in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday',
            'week_off_dates' => 'required_if:week_off_type,date_based|array',
            'week_off_dates.*' => 'date',
            // 2nd+ shift on the same days, kept next to the primary shift.
            'is_additional' => 'nullable|boolean',
        ];

        // Permanent is open-ended by definition — no end date, no 90-day cap,
        // no override flags (auto-supersede is implicit). Flexible keeps every
        // existing rule unchanged.
        if ($type === 'flexible') {
            $rules['end_date'] = 'nullable|date|after_or_equal:start_date';
            $rules['override_existing'] = 'nullable|boolean';
            $rules['apply_to_future_only'] = 'nullable|boolean';
        }

        $validator = Validator::make($request->all(), $rules);

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
            $endDate = $request->end_date ? Carbon::parse($request->end_date) : $startDate->copy();

            if ($type === 'flexible' && $startDate->diffInDays($endDate) > 90) {
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

            $tenantId = Auth::user()->tenant_id;
            $assignedCount = 0;
            $skippedCount = 0;
            $alreadyAssignedCount = 0;
            $duplicateSkippedCount = 0;
            $weekOffCount = 0;
            $supersededCount = 0;
            // The radio's "None" option submits week_off_type="" — the column is
            // a nullable enum(day_based,date_based), not "", so normalize here
            // rather than pushing an empty string all the way to the DB.
            $weekOffType = $request->week_off_type ?: null;

            if ($request->boolean('is_additional')) {
                return $this->assignAdditionalShift($request, $userIds, $shift, $type, $startDate, $endDate, $tenantId);
            }

            foreach ($userIds as $userId) {
                if ($request->week_off_type) {
                    $weekOffCount += $this->assignWeekOffs($userId, $request);
                }

                if ($type === 'permanent') {
                    $result = $this->shiftAssignmentService->assignPermanent(
                        $tenantId,
                        $userId,
                        $shift->id,
                        $startDate,
                        Auth::id(),
                        $weekOffType,
                        $request->week_off_days,
                        $request->week_off_dates
                    );

                    $assignedCount += $result['materialized']['assigned'];
                    if ($result['superseded']) {
                        $supersededCount++;
                    }
                } else {
                    $result = $this->shiftAssignmentService->assignFlexible(
                        $tenantId,
                        $userId,
                        $shift->id,
                        $startDate,
                        $endDate,
                        Auth::id(),
                        (bool) $request->override_existing,
                        (bool) $request->apply_to_future_only,
                        $weekOffType,
                        $request->week_off_days,
                        $request->week_off_dates
                    );

                    $m = $result['materialized'];
                    $assignedCount += $m['assigned'];
                    $skippedCount += $m['skipped'];
                    $alreadyAssignedCount += $m['already_assigned'];
                    $duplicateSkippedCount += $m['duplicate_skipped'];
                }
            }

            DB::commit();

            if ($type === 'permanent') {
                $message = "Permanent shift assigned to $assignedCount day(s) across " . count($userIds) . " employee(s).";
                if ($supersededCount > 0) {
                    $message .= " $supersededCount previous permanent assignment(s) were ended and kept in history.";
                }
            } else {
                $message = "Shift assigned successfully! ";
                $message .= "New: $assignedCount, ";

                if ($alreadyAssignedCount > 0) {
                    $message .= "Existing: $alreadyAssignedCount, ";
                }

                if ($duplicateSkippedCount > 0) {
                    $message .= "Duplicates: $duplicateSkippedCount, ";
                }

                $message .= "Skipped: $skippedCount";
            }

            if ($weekOffCount > 0) {
                $message .= ", Week Offs: $weekOffCount";
            }

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'status' => true,
                    'message' => $message,
                    'data' => [
                        'type' => $type,
                        'assigned' => $assignedCount,
                        'already_assigned' => $alreadyAssignedCount,
                        'duplicate_skipped' => $duplicateSkippedCount,
                        'skipped' => $skippedCount,
                        'week_offs' => $weekOffCount,
                        'superseded' => $supersededCount
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
     * "Add as additional shift": a 2nd+ shift on the same days, next to the
     * employee's primary shift (which is left untouched). All-or-nothing — if
     * the shift would overlap one any selected employee already works, nothing
     * is assigned. Week-offs are not changed. Runs inside assignShift()'s
     * transaction.
     */
    private function assignAdditionalShift(Request $request, array $userIds, Shift $shift, string $type, Carbon $startDate, Carbon $endDate, int $tenantId)
    {
        $names = User::whereIn('id', $userIds)->pluck('name', 'id');
        $clashes = [];
        foreach ($userIds as $userId) {
            $conflicts = $this->shiftAssignmentService->additionalConflicts($tenantId, $userId, $shift, $type, $startDate, $type === 'flexible' ? $endDate : null);
            if ($conflicts) {
                $clashes[] = ($names[$userId] ?? "Employee #{$userId}") . ': ' . ShiftOverlapGuard::describe($conflicts, 2);
            }
        }

        if ($clashes) {
            DB::rollBack();
            $message = "{$shift->name} overlaps a shift already assigned — nothing was assigned. "
                . implode('; ', array_slice($clashes, 0, 3))
                . (count($clashes) > 3 ? '; and ' . (count($clashes) - 3) . ' more employee(s).' : '.');

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['status' => false, 'message' => $message, 'data' => ['conflicts' => $clashes]], 422);
            }

            return redirect()->back()->with('error', $message)->withInput();
        }

        $assigned = 0;
        foreach ($userIds as $userId) {
            $result = $this->shiftAssignmentService->assignAdditional(
                $tenantId, $userId, $shift->id, $type, $startDate, $type === 'flexible' ? $endDate : null, Auth::id()
            );
            $assigned += $result['materialized']['assigned'];
        }

        DB::commit();

        $message = "Additional shift {$shift->name} added to {$assigned} day(s) across " . count($userIds) . ' employee(s). Their main shift is unchanged.';

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'status' => true,
                'message' => $message,
                'data' => ['type' => $type, 'is_additional' => true, 'assigned' => $assigned],
            ], 200);
        }

        return redirect()->route('shift.roster')->with('success', $message);
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

            if ($error = $this->overlapErrorFor($userShift, (int) $request->shift_id)) {
                DB::rollBack();

                return response()->json(['status' => false, 'message' => $error], 422);
            }

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
                'status' => 'nullable|in:upcoming,ongoing,complete',
                'type' => 'nullable|in:permanent,flexible',
                'search' => 'nullable|string|max:100'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'errors' => $validator->errors(),
                    'message' => 'Please provide valid date range'
                ], 422);
            }

            $query = UserShift::with(['user', 'shift', 'shiftAssignment'])
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

            if ($request->filled('type')) {
                $query->whereHas('shiftAssignment', fn ($q) => $q->where('type', $request->type));
            }

            if ($request->filled('search')) {
                $search = $request->search;
                $query->whereHas('user', function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('employee_id', 'like', "%{$search}%");
                });
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
                if ($error = $this->overlapErrorFor($userShift, (int) $item['shift_id'], array_column($request->user_shifts, 'id'))) {
                    DB::rollBack();

                    return response()->json(['status' => false, 'message' => $error], 422);
                }
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

            $query = UserShift::with(['user', 'shift', 'shiftAssignment'])
                ->whereBetween('date', [$request->from_date, $request->to_date]);

            if ($request->filled('user_id')) {
                $query->where('user_id', $request->user_id);
            }

            if ($request->filled('department_id')) {
                $query->whereHas('user.jobDetails', function ($q) use ($request) {
                    $q->where('department', $request->department_id);
                });
            }

            $userShifts = $query->orderBy('date')->orderBy('user_id')->orderBy('is_additional')->get();

            $filename = 'user_shifts_' . $request->from_date . '_to_' . $request->to_date . '.csv';
            $headers = [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ];

            $callback = function () use ($userShifts) {
                $file = fopen('php://output', 'w');

                fputcsv($file, ['Date', 'Employee ID', 'Employee Name', 'Shift', 'Start Time', 'End Time', 'Status', 'Type', 'Additional Shift']);

                foreach ($userShifts as $shift) {
                    fputcsv($file, [
                        // user_shifts.date is a varchar, not a Carbon.
                        Carbon::parse($shift->date)->format('Y-m-d'),
                        $shift->user->employee_id ?? 'N/A',
                        $shift->user->name ?? 'N/A',
                        $shift->shift->name ?? 'N/A',
                        $shift->shift->start_time ?? 'N/A',
                        $shift->shift->end_time ?? 'N/A',
                        $shift->status,
                        $shift->shiftAssignment->type ?? 'N/A',
                        $shift->is_additional ? 'Yes' : 'No',
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
            $tenantId = Auth::user()->tenant_id;
            $assigned = 0;
            $skipped = 0;

            foreach ($request->user_ids as $userId) {
                $assignedDates = [];

                foreach ($request->dates as $date) {
                    // Check if shift already exists
                    $existing = UserShift::where('user_id', $userId)
                        ->where('date', $date)
                        ->where('is_additional', 0)
                        ->first();

                    if ($existing) {
                        if ($request->override_existing) {
                            $existing->update(['shift_id' => $request->shift_id]);
                            $assigned++;
                            $assignedDates[] = $date;
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
                            $assignedDates[] = $date;
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

                // This path is always Flexible (explicit dates, not a
                // range/permanent flow) — one shift_assignments history row
                // per contiguous run of assigned dates, so this shows up
                // correctly in "Existing Assignments" and counts toward
                // shift history like every other Flexible assignment.
                if (!empty($assignedDates)) {
                    sort($assignedDates);
                    foreach ($this->groupDatesIntoRanges($assignedDates) as $range) {
                        $shiftAssignment = ShiftAssignment::create([
                            'tenant_id' => $tenantId,
                            'user_id' => $userId,
                            'shift_id' => $request->shift_id,
                            'type' => 'flexible',
                            'start_date' => $range['start'],
                            'end_date' => $range['end'],
                            'status' => 'active',
                            'source' => 'manual',
                            'created_by' => Auth::id(),
                        ]);

                        UserShift::where('user_id', $userId)
                            ->whereBetween('date', [$range['start'], $range['end']])
                            ->where('is_additional', 0)
                            ->update(['shift_assignment_id' => $shiftAssignment->id]);
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
     * AJAX: full shift_assignments history for one user (any status) — backs
     * the "History" affordance on the Existing Assignments tab. Reads the
     * append-only source-of-truth table directly, not the user_shifts cache,
     * so ended/superseded assignments remain visible even after their cached
     * days have been cleared.
     */
    public function assignmentHistory(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->errors()], 422);
        }

        $history = ShiftAssignment::with('shift:id,name,color_code')
            ->where('user_id', $request->user_id)
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->get(['id', 'shift_id', 'type', 'start_date', 'end_date', 'status', 'superseded_by_id', 'created_at']);

        return response()->json(['status' => true, 'data' => $history], 200);
    }

    /**
     * AJAX: does this user (or set of users) already have an active Permanent
     * shift? Backs the "this will replace X" notice in the assign modal —
     * called before submit, not a validation gate.
     */
    public function checkAssignmentConflicts(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_ids' => 'required|array',
            'user_ids.*' => 'exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->errors()], 422);
        }

        $tenantId = Auth::user()->tenant_id;
        $conflicts = [];

        foreach ($request->user_ids as $userId) {
            $active = $this->shiftAssignmentValidator->findActivePermanent($tenantId, $userId);

            if ($active) {
                $conflicts[] = [
                    'user_id' => $userId,
                    'shift_name' => optional($active->shift)->name,
                    'active_since' => $active->start_date->format('Y-m-d'),
                ];
            }
        }

        return response()->json(['status' => true, 'data' => $conflicts], 200);
    }

    /**
     * Explicitly stop a Permanent assignment ("End Permanent Shift" row
     * action). Closes the shift_assignments history row and lets whatever
     * still-active Flexible/nothing take over from the end date forward.
     */
    public function endPermanentShift(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'end_date' => 'nullable|date',
            'reason' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->errors()], 422);
        }

        $assignment = ShiftAssignment::where('type', 'permanent')->where('status', 'active')->find($id);

        if (!$assignment) {
            return response()->json([
                'status' => false,
                'message' => 'Active permanent assignment not found'
            ], 404);
        }

        try {
            $endDate = $request->filled('end_date') ? Carbon::parse($request->end_date) : Carbon::today();

            $this->shiftAssignmentService->endPermanent($assignment, $endDate, Auth::id(), $request->reason);

            return response()->json([
                'status' => true,
                'message' => 'Permanent shift ended from ' . $endDate->addDay()->format('Y-m-d') . ' onward.'
            ], 200);
        } catch (Exception $e) {
            Log::error('End permanent shift error: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Failed to end permanent shift'
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
     * Error message when changing $userShift to $shiftId would overlap another
     * shift the employee works around that date, else null. $ignoreIds are rows
     * being changed in the same request (bulk edit).
     */
    private function overlapErrorFor(?UserShift $userShift, int $shiftId, array $ignoreIds = []): ?string
    {
        $shift = $userShift ? Shift::find($shiftId) : null;
        if (!$shift || (int) $userShift->shift_id === $shiftId) {
            return null;
        }

        $date = Carbon::parse($userShift->date)->toDateString();
        $ignore = array_unique(array_merge([(int) $userShift->id], array_map('intval', $ignoreIds)));
        $conflicts = $this->overlapGuard->conflicts((int) $userShift->tenant_id, (int) $userShift->user_id, $shift, [$date], $ignore);

        return $conflicts ? "{$shift->name} would overlap " . ShiftOverlapGuard::describe($conflicts) . '.' : null;
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

                // Carbon 3 diffs are signed — compare prev -> current (+1 day).
                if ((int) $prev->diffInDays($current) === 1) {
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
