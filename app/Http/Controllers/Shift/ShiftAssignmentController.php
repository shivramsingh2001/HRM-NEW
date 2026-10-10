<?php

namespace App\Http\Controllers\Shift;

use App\Http\Controllers\Controller;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\User;
use App\Models\UserShift;
use App\Models\UserWeekoffs;
use App\Services\Shift\ShiftAssignmentService;
use App\Services\Shift\ShiftAssignmentValidator;
use App\Services\Shift\ShiftChangeRecorder;
use App\Services\Shift\ShiftMaterializer;
use App\Services\Shift\ShiftOverlapGuard;
use App\Support\ShiftWindow;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Assigning shifts (Permanent / Flexible / additional / several at once / bulk dates),
 * ending a permanent shift, assignment history, change log and conflict check.
 * Moved out of ShiftController unchanged (code-quality plan, Phase 4); route names
 * are the same.
 */
class ShiftAssignmentController extends Controller
{
    use \App\Http\Controllers\Concerns\RecordsShiftChanges;

    public function __construct(
        private ShiftAssignmentService $shiftAssignmentService,
        private ShiftAssignmentValidator $shiftAssignmentValidator,
        private ShiftChangeRecorder $changeRecorder
    ) {}

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

        // Several shifts at once (the Assign Shift multi-select sends shift_ids[]).
        // One selected shift behaves exactly like the old single shift_id.
        $shiftIds = array_values(array_unique(array_filter(array_map('intval', (array) $request->input('shift_ids', [])))));
        if ($shiftIds) {
            $request->merge(['shift_id' => $shiftIds[0]]);
        }

        $rules = [
            'assign_type' => 'required|in:user,department,all',
            'user_ids' => 'required_if:assign_type,user|array',
            'user_ids.*' => 'exists:users,id',
            'department_id' => 'nullable|required_if:assign_type,department|exists:departments,id',
            'shift_id' => 'required|exists:shifts,id',
            'shift_ids' => 'nullable|array',
            'shift_ids.*' => 'integer|exists:shifts,id',
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
                    'errors' => $validator->errors(),
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
                        'message' => 'No users found to assign shift',
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
                        'message' => 'Date range cannot exceed 90 days',
                    ], 400);
                }

                return redirect()->back()
                    ->with('error', 'Date range cannot exceed 90 days')
                    ->withInput();
            }

            $tenantId = Auth::user()->tenant_id;
            // The radio's "None" option submits week_off_type="" — the column is
            // a nullable enum(day_based,date_based), not "", so normalize here
            // rather than pushing an empty string all the way to the DB.
            $weekOffType = $request->week_off_type ?: null;

            $logTo = $type === 'permanent'
                ? max($startDate->toDateString(), Carbon::today()->addDays(ShiftMaterializer::PERMANENT_HORIZON_DAYS)->toDateString())
                : $endDate->toDateString();
            $this->startChangeLog((int) $tenantId, array_map('intval', $userIds), $startDate->toDateString(), $logTo, ['source' => 'roster_assign']);

            if (count($shiftIds) > 1) {
                return $this->assignShiftSet($request, $userIds, $shiftIds, $type, $startDate, $endDate, (int) $tenantId, $weekOffType);
            }

            if ($request->boolean('is_additional')) {
                return $this->assignAdditionalShift($request, $userIds, $shift, $type, $startDate, $endDate, $tenantId);
            }

            [
                'assigned' => $assignedCount,
                'skipped' => $skippedCount,
                'already_assigned' => $alreadyAssignedCount,
                'duplicate_skipped' => $duplicateSkippedCount,
                'week_offs' => $weekOffCount,
                'superseded' => $supersededCount,
            ] = $this->assignMainShift($request, $userIds, $shift, $type, $startDate, $endDate, (int) $tenantId, $weekOffType);

            $this->flushChangeLog();
            DB::commit();

            if ($type === 'permanent') {
                $message = "Permanent shift assigned to $assignedCount day(s) across ".count($userIds).' employee(s).';
                if ($supersededCount > 0) {
                    $message .= " $supersededCount previous permanent assignment(s) were ended and kept in history.";
                }
            } else {
                $message = 'Shift assigned successfully! ';
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
                        'superseded' => $supersededCount,
                    ],
                ], 200);
            }

            return redirect()->route('shift.roster')
                ->with('success', $message);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Shift assignment error: '.$e->getMessage());

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Shift assignment failed: '.$e->getMessage(),
                ], 500);
            }

            return redirect()->back()
                ->with('error', 'Shift assignment failed: '.$e->getMessage())
                ->withInput();
        }
    }

    /**
     * Assign $shift as the employees' main (primary) shift — permanent or
     * flexible — plus the week-offs from the form. Runs inside assignShift()'s
     * transaction; returns the counts for the message.
     *
     * @return array{assigned:int, skipped:int, already_assigned:int, duplicate_skipped:int, week_offs:int, superseded:int}
     */
    private function assignMainShift(Request $request, array $userIds, Shift $shift, string $type, Carbon $startDate, Carbon $endDate, int $tenantId, ?string $weekOffType): array
    {
        $counts = ['assigned' => 0, 'skipped' => 0, 'already_assigned' => 0, 'duplicate_skipped' => 0, 'week_offs' => 0, 'superseded' => 0];

        foreach ($userIds as $userId) {
            if ($request->week_off_type) {
                $counts['week_offs'] += $this->assignWeekOffs($userId, $request);
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

                $counts['assigned'] += $result['materialized']['assigned'];
                if ($result['superseded']) {
                    $counts['superseded']++;
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
                $counts['assigned'] += $m['assigned'];
                $counts['skipped'] += $m['skipped'];
                $counts['already_assigned'] += $m['already_assigned'];
                $counts['duplicate_skipped'] += $m['duplicate_skipped'];
            }
        }

        return $counts;
    }

    /**
     * Several shifts picked at once. The earliest-starting one becomes the main
     * shift (the same rule attendance and payroll use for the day's primary
     * shift) and the others are added as additional shifts on the same days —
     * or, with "Add as additional shift" ticked, every picked shift is added
     * next to the current main shift. All-or-nothing: the picked shifts must
     * not overlap each other, and the additional ones must not overlap a shift
     * the employees already work; otherwise nothing is saved.
     * Runs inside assignShift()'s transaction.
     */
    private function assignShiftSet(Request $request, array $userIds, array $shiftIds, string $type, Carbon $startDate, Carbon $endDate, int $tenantId, ?string $weekOffType)
    {
        $refuse = function (string $message, array $data = []) use ($request) {
            DB::rollBack();
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['status' => false, 'message' => $message, 'data' => $data], 422);
            }

            return redirect()->back()->with('error', $message)->withInput();
        };

        $shifts = Shift::whereIn('id', $shiftIds)->get()
            ->sortBy(fn ($s) => ShiftWindow::toMinutes($s->start_time))
            ->values();
        if ($shifts->count() !== count($shiftIds)) {
            return $refuse('One of the selected shifts was not found.');
        }

        if ($clash = $this->selectedShiftsClash($shifts)) {
            return $refuse($clash);
        }

        $additionalOnly = $request->boolean('is_additional');
        $main = $additionalOnly ? null : $shifts->first();
        $extras = $additionalOnly ? $shifts : $shifts->slice(1)->values();

        $counts = ['assigned' => 0, 'superseded' => 0, 'week_offs' => 0];
        if ($main) {
            $mainCounts = $this->assignMainShift($request, $userIds, $main, $type, $startDate, $endDate, $tenantId, $weekOffType);
            $counts = array_intersect_key($mainCounts, $counts);
        }

        // Each additional shift must fit around what the employees now work (their new main shift included).
        $names = User::whereIn('id', $userIds)->pluck('name', 'id');
        $clashes = [];
        foreach ($extras as $extra) {
            foreach ($userIds as $userId) {
                $conflicts = $this->shiftAssignmentService->additionalConflicts($tenantId, $userId, $extra, $type, $startDate, $type === 'flexible' ? $endDate : null);
                if ($conflicts) {
                    $clashes[] = ($names[$userId] ?? "Employee #{$userId}").' — '.$extra->name.': '.ShiftOverlapGuard::describe($conflicts, 2);
                }
            }
        }
        if ($clashes) {
            return $refuse(
                'A selected shift overlaps a shift already assigned — nothing was assigned. '
                    .implode('; ', array_slice($clashes, 0, 3))
                    .(count($clashes) > 3 ? '; and '.(count($clashes) - 3).' more.' : '.'),
                ['conflicts' => $clashes]
            );
        }

        $added = 0;
        foreach ($extras as $extra) {
            foreach ($userIds as $userId) {
                $result = $this->shiftAssignmentService->assignAdditional(
                    $tenantId, $userId, $extra->id, $type, $startDate, $type === 'flexible' ? $endDate : null, Auth::id()
                );
                $added += $result['materialized']['assigned'];
            }
        }

        $this->flushChangeLog();
        DB::commit();

        $parts = [];
        if ($main) {
            $parts[] = "{$main->name} (main)";
        }
        foreach ($extras as $extra) {
            $parts[] = "{$extra->name} (additional)";
        }
        $message = implode(' + ', $parts).' assigned to '.count($userIds).' employee(s) — '
            .($counts['assigned'] + $added).' shift day(s).'
            .($main ? '' : ' Their main shift is unchanged.')
            .($counts['superseded'] ? " {$counts['superseded']} previous permanent assignment(s) were ended and kept in history." : '')
            .($counts['week_offs'] ? " Week Offs: {$counts['week_offs']}." : '');

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'status' => true,
                'message' => $message,
                'data' => [
                    'type' => $type,
                    'main_shift_id' => $main?->id,
                    'additional_shift_ids' => $extras->pluck('id')->all(),
                    'assigned' => $counts['assigned'] + $added,
                    'superseded' => $counts['superseded'],
                    'week_offs' => $counts['week_offs'],
                ],
            ], 200);
        }

        return redirect()->route('shift.roster')->with('success', $message);
    }

    /**
     * "X and Y overlap …" when two of the picked shifts would run at the same
     * time on a day (overnight shifts are compared across midnight too), else null.
     * Back-to-back shifts (one ends 14:00, the next starts 14:00) are fine.
     */
    private function selectedShiftsClash($shifts): ?string
    {
        $day = '2030-01-02';
        $list = $shifts->values();
        for ($i = 0; $i < $list->count(); $i++) {
            for ($j = $i + 1; $j < $list->count(); $j++) {
                [$aStart, $aEnd] = ShiftWindow::window($day, $list[$i]);
                foreach ([-1, 0, 1] as $offset) {
                    [$bStart, $bEnd] = ShiftWindow::window(Carbon::parse($day)->addDays($offset)->toDateString(), $list[$j]);
                    if ($aStart->lt($bEnd) && $bStart->lt($aEnd)) {
                        return "{$list[$i]->name} and {$list[$j]->name} overlap — choose shifts that don't run at the same time.";
                    }
                }
            }
        }

        return null;
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
                $clashes[] = ($names[$userId] ?? "Employee #{$userId}").': '.ShiftOverlapGuard::describe($conflicts, 2);
            }
        }

        if ($clashes) {
            DB::rollBack();
            $message = "{$shift->name} overlaps a shift already assigned — nothing was assigned. "
                .implode('; ', array_slice($clashes, 0, 3))
                .(count($clashes) > 3 ? '; and '.(count($clashes) - 3).' more employee(s).' : '.');

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

        $this->flushChangeLog();
        DB::commit();

        $message = "Additional shift {$shift->name} added to {$assigned} day(s) across ".count($userIds).' employee(s). Their main shift is unchanged.';

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
        if ($request->week_off_type === 'day_based' && ! empty($request->week_off_days)) {
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

                if (! $exists) {
                    UserWeekoffs::create([
                        'user_id' => $userId,
                        'off_type' => 'day_based',
                        'day_name' => $day,
                        'start_date' => $startDate ? $startDate->toDateString() : null,
                        'end_date' => $endDate ? $endDate->toDateString() : null,
                        'status' => 1,
                        'created_by' => Auth::id(),
                    ]);
                    $count++;

                    Log::info('Created day-based week-off', [
                        'user_id' => $userId,
                        'day' => $day,
                        'start_date' => $startDate ? $startDate->toDateString() : 'permanent',
                        'end_date' => $endDate ? $endDate->toDateString() : 'permanent',
                    ]);
                }
            }
        }

        // Handle DATE-BASED week-offs (specific dates)
        if ($request->week_off_type === 'date_based' && ! empty($request->week_off_dates)) {
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

                if (! $exists) {
                    UserWeekoffs::create([
                        'user_id' => $userId,
                        'off_type' => 'date_based',
                        'start_date' => $range['start'],
                        'end_date' => $range['end'],
                        'status' => 1,
                        'created_by' => Auth::id(),
                    ]);
                    $count++;

                    Log::info('Created date-based week-off range', [
                        'user_id' => $userId,
                        'start' => $range['start'],
                        'end' => $range['end'],
                    ]);
                }
            }
        }

        return $count;
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
                'end' => $end,
            ];

            $i = $j;
        }

        return $ranges;
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
            'override_existing' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        DB::beginTransaction();

        try {
            $tenantId = Auth::user()->tenant_id;
            $assigned = 0;
            $skipped = 0;

            $logDates = collect($request->dates)->map(fn ($d) => Carbon::parse($d)->toDateString())->sort()->values();
            $this->startChangeLog((int) $tenantId, array_map('intval', $request->user_ids), $logDates->first(), $logDates->last(), ['source' => 'bulk_assign']);

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
                                'created_by' => Auth::id(),
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
                if (! empty($assignedDates)) {
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

            $this->flushChangeLog();
            DB::commit();

            return response()->json([
                'status' => true,
                'message' => "Assigned: $assigned shifts, Skipped: $skipped",
                'data' => [
                    'assigned' => $assigned,
                    'skipped' => $skipped,
                ],
            ], 200);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Bulk assign shifts error: '.$e->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'Failed to assign shifts',
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
     * AJAX: the shift change log (shift_change_logs) for one employee — one
     * day (roster cell "History") or a date range (History modal). Who
     * changed what, when, why, from where, and the request behind it. A
     * manager only sees their own reportees.
     */
    public function changeLog(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'date' => 'nullable|date',
            'from' => 'nullable|date',
            'to' => 'nullable|date',
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->errors()], 422);
        }

        $actor = Auth::user();
        if ($actor->role === 'manager' && ! User::whereKey($request->user_id)->managedBy($actor->id)->exists()) {
            return response()->json(['status' => false, 'message' => 'Not one of your team members.'], 403);
        }

        $logs = \App\Models\ShiftChangeLog::with(['fromShift:id,name,color_code', 'toShift:id,name,color_code', 'actor:id,name', 'shiftRequest:id,request_no'])
            ->where('user_id', $request->user_id)
            ->when($request->filled('date'), fn ($q) => $q->whereDate('date', $request->date))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('date', '>=', $request->from))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('date', '<=', $request->to))
            ->orderByDesc('id')
            ->limit(200)
            ->get();

        return response()->json([
            'status' => true,
            'data' => $logs->map(fn ($l) => [
                'date' => $l->date->format('d M Y'),
                'from' => $l->fromShift?->name,
                'to' => $l->toShift?->name,
                'to_color' => $l->toShift?->color_code,
                'is_additional' => $l->is_additional,
                'change_type' => $l->change_type,
                'source' => $l->sourceLabel(),
                'reason' => $l->reason,
                'actor' => $l->actor?->name ?? 'System',
                'actor_role' => $l->actor_role,
                'channel' => $l->channel,
                'request_no' => $l->shiftRequest?->request_no,
                'shift_request_id' => $l->shift_request_id,
                'at' => $l->created_at?->format('d M Y, h:i A'),
            ])->all(),
        ]);
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
            $active = $this->shiftAssignmentValidator->findActiveStanding($tenantId, $userId);

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

        $assignment = ShiftAssignment::whereIn('type', ['permanent', 'rotating'])->where('status', 'active')->find($id);

        if (! $assignment) {
            return response()->json([
                'status' => false,
                'message' => 'Active permanent assignment not found',
            ], 404);
        }

        try {
            $endDate = $request->filled('end_date') ? Carbon::parse($request->end_date) : Carbon::today();

            $from = $endDate->copy()->addDay()->toDateString();
            $to = max($from, Carbon::today()->addDays(ShiftMaterializer::PERMANENT_HORIZON_DAYS)->toDateString());
            $this->changeRecorder->track((int) $assignment->tenant_id, [(int) $assignment->user_id], $from, $to, ['source' => 'end_permanent', 'reason' => $request->reason],
                fn () => $this->shiftAssignmentService->endPermanent($assignment, $endDate, Auth::id(), $request->reason));
            $this->notifyRosterChange((int) $assignment->tenant_id);

            return response()->json([
                'status' => true,
                'message' => 'Permanent shift ended from '.$endDate->addDay()->format('Y-m-d').' onward.',
            ], 200);
        } catch (Exception $e) {
            Log::error('End permanent shift error: '.$e->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'Failed to end permanent shift',
            ], 500);
        }
    }
}
