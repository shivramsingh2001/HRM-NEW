<?php

namespace App\Http\Controllers\Shift;

use App\Http\Controllers\Controller;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\ShiftRotationPattern;
use App\Models\ShiftRotationStep;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Shift\ShiftAssignmentService;
use App\Services\Shift\ShiftChangeRecorder;
use App\Services\Shift\ShiftMaterializer;
use App\Services\Shift\ShiftNotificationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Shift → Rotation Patterns (admin / HR): build repeating cycles of shifts
 * (e.g. 7×Morning, 7×Evening, 7×Night, or 4 on / 3 off) and assign them to
 * employees. An assigned pattern is a 'rotating' shift_assignments row that
 * ShiftMaterializer keeps generated to the rolling horizon (daily
 * shift:roll-permanent-horizon), so the roster never has to be re-entered.
 */
class ShiftRotationController extends Controller
{
    public function index()
    {
        $patterns = ShiftRotationPattern::with('steps')->orderBy('name')->get();
        $activeCounts = ShiftAssignment::where('type', 'rotating')->where('status', 'active')
            ->selectRaw('rotation_pattern_id, COUNT(*) as c')->groupBy('rotation_pattern_id')->pluck('c', 'rotation_pattern_id');

        return view('client.shift.rotations', [
            'patterns' => $patterns,
            'activeCounts' => $activeCounts,
            'shifts' => Shift::where('status', 1)->orderBy('start_time')->get(['id', 'name', 'color_code', 'start_time', 'end_time']),
            'users' => User::where('status', 1)->where('role', '!=', 'admin')->orderBy('name')->get(['id', 'name', 'employee_id']),
            'assignments' => ShiftAssignment::with(['user:id,name,employee_id', 'rotationPattern:id,name,cycle_days'])
                ->where('type', 'rotating')->where('status', 'active')->orderByDesc('id')->limit(500)->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validatePattern($request);
        $pattern = DB::transaction(function () use ($data) {
            $pattern = ShiftRotationPattern::create([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'cycle_days' => count($data['steps']),
                'status' => 1,
                'created_by' => Auth::id(),
            ]);
            $this->saveSteps($pattern, $data['steps']);

            return $pattern;
        });
        $this->audit('shift_rotation.created', $pattern, [], $data);

        return response()->json(['status' => true, 'message' => "Pattern {$pattern->name} saved."]);
    }

    /**
     * Edit a pattern. Employees already on it follow the new cycle from today
     * (their cache is regenerated); past days are not touched.
     */
    public function update(Request $request, $id)
    {
        $pattern = ShiftRotationPattern::with('steps')->findOrFail($id);
        $data = $this->validatePattern($request, $pattern->id);
        $old = ['name' => $pattern->name, 'steps' => $pattern->stepMap()];

        $users = ShiftAssignment::where('type', 'rotating')->where('status', 'active')->where('rotation_pattern_id', $pattern->id)->pluck('user_id')->all();
        $today = Carbon::today();
        $recorder = app(ShiftChangeRecorder::class);

        $recorder->track((int) Auth::user()->tenant_id, $users, $today->toDateString(), $today->copy()->addDays(ShiftMaterializer::PERMANENT_HORIZON_DAYS)->toDateString(),
            ['source' => 'rotation', 'change_type' => 'rotation', 'reason' => "Rotation pattern {$data['name']} changed"],
            function () use ($pattern, $data, $users, $today) {
                DB::transaction(function () use ($pattern, $data, $users, $today) {
                    $pattern->update(['name' => $data['name'], 'description' => $data['description'] ?? null, 'cycle_days' => count($data['steps'])]);
                    $pattern->steps()->delete();
                    $this->saveSteps($pattern, $data['steps']);
                    $materializer = app(ShiftMaterializer::class);
                    foreach ($users as $userId) {
                        $materializer->regenerateFrom((int) Auth::user()->tenant_id, (int) $userId, $today->copy());
                    }
                });
            });
        app(ShiftNotificationService::class)->rosterChanged((int) Auth::user()->tenant_id, $recorder->lastRows, Auth::id());
        $this->audit('shift_rotation.updated', $pattern, $old, $data);

        return response()->json(['status' => true, 'message' => "Pattern {$pattern->name} updated" . ($users ? ' — ' . count($users) . ' employee(s) follow the new cycle from today.' : '.')]);
    }

    /** Delete an unused pattern; one still assigned is only switched off (no new assignments). */
    public function destroy($id)
    {
        $pattern = ShiftRotationPattern::findOrFail($id);
        $inUse = ShiftAssignment::where('type', 'rotating')->where('rotation_pattern_id', $pattern->id)->exists();
        if ($inUse) {
            $pattern->update(['status' => 0]);
            $this->audit('shift_rotation.deactivated', $pattern);

            return response()->json(['status' => true, 'message' => "{$pattern->name} has been used, so it was switched off instead of deleted (history keeps it). End the employees' rotation to stop it."]);
        }
        $pattern->steps()->delete();
        $pattern->delete();
        $this->audit('shift_rotation.deleted', $pattern);

        return response()->json(['status' => true, 'message' => 'Pattern deleted.']);
    }

    /**
     * Assign a pattern to employees from a start date. stagger_days moves each
     * next employee further along the cycle (0 = everyone on the same step).
     */
    public function assign(Request $request)
    {
        $data = $request->validate([
            'pattern_id' => 'required|integer|exists:shift_rotation_patterns,id',
            'user_ids' => 'required|array|min:1',
            'user_ids.*' => 'integer|exists:users,id',
            'start_date' => 'required|date',
            'stagger_days' => 'nullable|integer|min:0|max:365',
            'replace_weekly_offs' => 'nullable|boolean',
        ]);
        $pattern = ShiftRotationPattern::with('steps')->where('status', 1)->findOrFail($data['pattern_id']);
        $tenantId = (int) Auth::user()->tenant_id;
        $start = Carbon::parse($data['start_date'])->startOfDay();
        $stagger = (int) ($data['stagger_days'] ?? 0);
        $replaceWeekly = $request->boolean('replace_weekly_offs', true);
        $userIds = array_values(array_unique(array_map('intval', $data['user_ids'])));
        $recorder = app(ShiftChangeRecorder::class);
        $service = app(ShiftAssignmentService::class);
        $replaced = 0;
        $days = 0;

        DB::transaction(function () use ($recorder, $service, $tenantId, $userIds, $pattern, $start, $stagger, $replaceWeekly, &$replaced, &$days) {
            $to = max($start->toDateString(), Carbon::today()->addDays(ShiftMaterializer::PERMANENT_HORIZON_DAYS)->toDateString());
            $recorder->track($tenantId, $userIds, $start->toDateString(), $to,
                ['source' => 'rotation', 'change_type' => 'rotation', 'reason' => "Rotation pattern {$pattern->name}"],
                function () use ($service, $tenantId, $userIds, $pattern, $start, $stagger, $replaceWeekly, &$replaced, &$days) {
                    foreach ($userIds as $i => $userId) {
                        // Later employees start further into the cycle: their day 1 was earlier.
                        $anchor = $start->copy()->subDays(($i * $stagger) % max(1, $pattern->cycle_days));
                        $r = $service->assignRotating($tenantId, $userId, $pattern, $start->copy(), (int) Auth::id(), $anchor, $replaceWeekly);
                        $replaced += $r['superseded'] ? 1 : 0;
                        $days += $r['materialized']['assigned'];
                    }
                });
        });
        app(ShiftNotificationService::class)->rosterChanged($tenantId, $recorder->lastRows, Auth::id());
        $this->audit('shift_rotation.assigned', $pattern, [], ['user_ids' => $userIds, 'start_date' => $start->toDateString(), 'stagger_days' => $stagger]);

        return response()->json([
            'status' => true,
            'message' => "{$pattern->name} assigned to " . count($userIds) . " employee(s) from " . $start->format('d M Y') . " — {$days} shift day(s) planned."
                . ($replaced ? " {$replaced} previous permanent / rotating assignment(s) were ended and kept in history." : ''),
        ]);
    }

    // ------------------------------------------------------------------

    private function validatePattern(Request $request, ?int $ignoreId = null): array
    {
        $tenantId = (int) Auth::user()->tenant_id;
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('shift_rotation_patterns', 'name')->where('tenant_id', $tenantId)->ignore($ignoreId)],
            'description' => 'nullable|string|max:255',
            'steps' => 'required|array|min:1|max:56',
            'steps.*' => 'nullable|integer',
        ], ['steps.required' => 'Add at least one day to the cycle.']);

        $steps = array_map(fn ($s) => $s === null || $s === '' || (int) $s === 0 ? null : (int) $s, array_values($data['steps']));
        $valid = Shift::whereIn('id', array_filter($steps))->pluck('id')->all();
        if (array_diff(array_filter($steps), $valid)) {
            abort(response()->json(['status' => false, 'message' => 'One of the shifts in the cycle no longer exists.'], 422));
        }
        if (! array_filter($steps)) {
            abort(response()->json(['status' => false, 'message' => 'The cycle needs at least one working day.'], 422));
        }
        $data['steps'] = $steps;

        return $data;
    }

    private function saveSteps(ShiftRotationPattern $pattern, array $steps): void
    {
        foreach ($steps as $i => $shiftId) {
            ShiftRotationStep::create(['pattern_id' => $pattern->id, 'day_index' => $i, 'shift_id' => $shiftId]);
        }
    }

    private function audit(string $action, ShiftRotationPattern $pattern, array $old = [], array $new = []): void
    {
        try {
            app(AuditLogger::class)->record('tenant_user', Auth::id(), (int) Auth::user()->tenant_id, $action, 'shift_rotation_pattern', (int) $pattern->id, $old, $new + ['name' => $pattern->name]);
        } catch (\Throwable $e) {
        report($e);
        }
    }
}
