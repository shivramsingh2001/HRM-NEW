<?php

namespace App\Http\Controllers\Shift;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Shift;
use App\Models\User;
use App\Models\UserShift;
use App\Models\UserWeekoffs;
use App\Services\Shift\ShiftAssignmentService;
use App\Services\Shift\ShiftChangeGuard;
use App\Services\Shift\ShiftChangeRecorder;
use App\Services\Shift\ShiftOverlapGuard;
use App\Support\WeekOffPredicate;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Shift roster (shift.roster) and the per-day assignment list: day / week / month grid,
 * editing / deleting / exporting user_shifts rows. Moved out of ShiftController
 * unchanged (code-quality plan, Phase 4); route names are the same.
 */
class RosterController extends Controller
{
    use \App\Http\Controllers\Concerns\RecordsShiftChanges;

    public function __construct(
        private ShiftAssignmentService $shiftAssignmentService,
        private ShiftOverlapGuard $overlapGuard,
        private ShiftChangeRecorder $changeRecorder,
        private ShiftChangeGuard $changeGuard
    ) {}

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
            $assignments = UserShift::with(['shift', 'shiftAssignment:id,type,is_override,source,shift_request_id', 'shiftAssignment.shiftRequest:id,request_no'])
                ->where('tenant_id', $tenantId)
                ->whereIn('user_id', $userIds)
                ->whereIn('date', $dateStrings)
                ->get()
                ->filter(fn ($r) => $r->shift)
                ->sortBy(fn ($r) => [(int) $r->is_additional, (string) $r->shift->start_time])
                ->groupBy(fn ($r) => $r->user_id.'|'.Carbon::parse($r->date)->format('Y-m-d'));

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
                ->map(fn ($r) => $r->user_id.'|'.Carbon::parse($r->date)->format('Y-m-d'))
                ->flip();

            // Build the grid: cells[userId][Y-m-d] = ['type' => shift|weekoff|unassigned, ...]
            $cells = [];
            foreach ($userIds as $uid) {
                foreach ($dates as $d) {
                    $ds = $d->format('Y-m-d');
                    $key = $uid.'|'.$ds;
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
                            // One-day exception: roster day edit, swap or approved change request.
                            'changed' => $row->shiftAssignment?->is_override
                                ? ['source' => $row->shiftAssignment->source, 'request_no' => $row->shiftAssignment->shiftRequest?->request_no]
                                : null,
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

            // Swap / change modals: employees the viewer may change (a manager: own reportees).
            $manageableUsers = Auth::user()->role === 'manager'
                ? User::where('tenant_id', $tenantId)->where('status', 1)->managedBy(Auth::id())->orderBy('name')->get(['id', 'name', 'employee_id'])
                : $allUsers;

            return view('client.shift.roster', [
                'manageableUsers' => $manageableUsers,
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
            Log::error('Shift roster error: '.$e->getMessage());

            return back()->with('error', 'Failed to load the shift roster');
        }
    }

    /**
     * Is $date a week-off for a user, given their pre-loaded UserWeekoffs collection?
     * Same predicate as checkDateIsWeekOff(), collection-based.
     */
    private function isWeekOffOn($userWeekoffs, Carbon $date): bool
    {
        if (! $userWeekoffs) {
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
                'department_id' => 'required_if:type,department|exists:departments,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'errors' => $validator->errors(),
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
                    $q->where('name', 'LIKE', '%'.$request->search.'%')
                        ->orWhere('email', 'LIKE', '%'.$request->search.'%')
                        ->orWhere('employee_id', 'LIKE', '%'.$request->search.'%');
                });
            }

            $users = $query->orderBy('name')->get();

            return response()->json([
                'status' => true,
                'data' => $users,
            ], 200);
        } catch (Exception $e) {
            Log::error('Get users error: '.$e->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'Unable to fetch users',
            ], 500);
        }
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
            'reason' => 'required|string|max:500',
        ], [
            'reason.required' => 'Please enter the reason for changing this shift.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            DB::beginTransaction();

            $userShift = UserShift::find($request->user_shift_id);
            if (! $userShift) {
                DB::rollBack();

                return response()->json(['status' => false, 'message' => 'Shift assignment not found'], 404);
            }

            if ($error = $this->overlapErrorFor($userShift, (int) $request->shift_id)) {
                DB::rollBack();

                return response()->json(['status' => false, 'message' => $error], 422);
            }

            $date = Carbon::parse($userShift->date)->toDateString();
            if ((int) $userShift->shift_id !== (int) $request->shift_id
                && ($error = $this->changeGuard->dateError((int) $userShift->tenant_id, (int) $userShift->user_id, $date, true))) {
                DB::rollBack();

                return response()->json(['status' => false, 'message' => $error], 422);
            }

            $userShift = $this->editDay($userShift, (int) $request->shift_id, $request->filled('status') ? $request->status : null, $request->reason, 'roster_edit');
            $this->notifyRosterChange((int) $userShift->tenant_id);

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Shift updated successfully',
                'data' => $userShift->load('shift'),
            ], 200);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Update user shift error: '.$e->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'Failed to update shift'.$e->getMessage(),
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
            'reason' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        DB::beginTransaction();

        try {
            $deleted = $this->changeRecorder->track(
                (int) Auth::user()->tenant_id,
                array_map('intval', $request->user_ids),
                Carbon::parse($request->start_date)->toDateString(),
                Carbon::parse($request->end_date)->toDateString(),
                ['source' => 'roster_delete', 'reason' => $request->reason],
                fn () => UserShift::whereIn('user_id', $request->user_ids)
                    ->whereBetween('date', [$request->start_date, $request->end_date])
                    ->delete()
            );
            $this->notifyRosterChange((int) Auth::user()->tenant_id);

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => $deleted.' shifts deleted successfully',
                'data' => ['deleted_count' => $deleted],
            ], 200);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Bulk delete user shifts error: '.$e->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'Failed to delete shifts',
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
                'type' => 'nullable|in:permanent,flexible,rotating',
                'search' => 'nullable|string|max:100',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'errors' => $validator->errors(),
                    'message' => 'Please provide valid date range',
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
                    'to' => $userShifts->lastItem(),
                ],
            ], 200);
        } catch (Exception $e) {
            Log::error('Get user shifts data error: '.$e->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch user shifts: '.$e->getMessage(),
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
            'user_shifts.*.status' => 'required|in:upcoming,ongoing,complete',
            'reason' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors(),
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
                if ((int) $userShift->shift_id !== (int) $item['shift_id']
                    && ($error = $this->changeGuard->dateError((int) $userShift->tenant_id, (int) $userShift->user_id, Carbon::parse($userShift->date)->toDateString(), true, optional($userShift->user)->name))) {
                    DB::rollBack();

                    return response()->json(['status' => false, 'message' => $error], 422);
                }
                $this->editDay($userShift, (int) $item['shift_id'], $item['status'], $request->reason, 'bulk_edit');
                $updated++;
            }
            $this->notifyRosterChange((int) Auth::user()->tenant_id);

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => $updated.' shifts updated successfully',
                'data' => ['updated' => $updated],
            ], 200);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Bulk update user shifts error: '.$e->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'Failed to update shifts',
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

            $filename = 'user_shifts_'.$request->from_date.'_to_'.$request->to_date.'.csv';
            $headers = [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="'.$filename.'"',
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
            Log::error('Export user shifts error: '.$e->getMessage());

            return back()->with('error', 'Failed to export shifts');
        }
    }

    /**
     * Delete assigned shift
     */
    public function destroyAssigned($id)
    {
        try {
            $userShift = UserShift::find($id);

            if (! $userShift) {
                return response()->json([
                    'status' => false,
                    'message' => 'Shift assignment not found',
                ], 404);
            }

            DB::beginTransaction();
            $date = Carbon::parse($userShift->date)->toDateString();
            $this->changeRecorder->track((int) $userShift->tenant_id, [(int) $userShift->user_id], $date, $date, ['source' => 'roster_delete', 'reason' => request('reason')], fn () => $userShift->delete());
            $this->notifyRosterChange((int) $userShift->tenant_id);
            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Shift assignment deleted successfully',
            ], 200);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Delete assigned shift error: '.$e->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'Failed to delete shift assignment',
            ], 500);
        }
    }

    /**
     * Change one cached day (roster edit / bulk edit) and log it. A main-shift
     * change goes through ShiftAssignmentService::applyDayOverride() so it
     * survives a later rebuild of the cache (it used to be written straight
     * into user_shifts and was silently lost on the next regenerate). A 2nd+
     * (additional) shift row, or a status-only edit, is updated in place.
     * Runs inside the caller's transaction.
     */
    private function editDay(UserShift $userShift, int $shiftId, ?string $status, ?string $reason, string $source): UserShift
    {
        $tenantId = (int) $userShift->tenant_id;
        $userId = (int) $userShift->user_id;
        $date = Carbon::parse($userShift->date)->toDateString();

        return $this->changeRecorder->track($tenantId, [$userId], $date, $date, ['source' => $source, 'reason' => $reason], function () use ($userShift, $shiftId, $status, $reason, $tenantId, $userId, $date) {
            if ($userShift->is_additional || (int) $userShift->shift_id === $shiftId) {
                $userShift->shift_id = $shiftId;
                if ($status) {
                    $userShift->status = $status;
                }
                $userShift->save();

                return $userShift;
            }

            $this->shiftAssignmentService->applyDayOverride($tenantId, $userId, Carbon::parse($date), $shiftId, (int) Auth::id(), 'day_override', $reason, null, $status);

            return UserShift::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('user_id', $userId)
                ->where('date', $date)->where('is_additional', 0)->first() ?? $userShift;
        });
    }

    /**
     * Error message when changing $userShift to $shiftId would overlap another
     * shift the employee works around that date, else null. $ignoreIds are rows
     * being changed in the same request (bulk edit).
     */
    private function overlapErrorFor(?UserShift $userShift, int $shiftId, array $ignoreIds = []): ?string
    {
        $shift = $userShift ? Shift::find($shiftId) : null;
        if (! $shift || (int) $userShift->shift_id === $shiftId) {
            return null;
        }

        $date = Carbon::parse($userShift->date)->toDateString();
        $ignore = array_unique(array_merge([(int) $userShift->id], array_map('intval', $ignoreIds)));
        $conflicts = $this->overlapGuard->conflicts((int) $userShift->tenant_id, (int) $userShift->user_id, $shift, [$date], $ignore);

        return $conflicts ? "{$shift->name} would overlap ".ShiftOverlapGuard::describe($conflicts).'.' : null;
    }
}
