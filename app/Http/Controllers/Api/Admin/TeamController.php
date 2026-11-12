<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Team\TeamListService;
use App\Services\Team\TeamMemberProfile;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * Mobile admin API — the Team page (Admin / HR): the day's list with status
 * counts, filter options, one member's profile with the month's attendance (summary
 * + one row per day) in a single call. The list and the member page use the same
 * services as the web Team page; marking
 * attendance is routed straight to the web endpoints (same rules and audit).
 */
class TeamController extends Controller
{
    public function index(Request $request, TeamListService $teamList): JsonResponse
    {
        $data = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
            'status' => ['nullable', 'in:'.implode(',', TeamListService::STATUSES)],
            'search' => ['nullable', 'string', 'max:100'],
            'branch_id' => ['nullable', 'integer'],
            'department_id' => ['nullable', 'integer'],
            'designation_id' => ['nullable', 'integer'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        try {
            $date = $data['date'] ?? Carbon::today()->toDateString();
            $list = $teamList->list(Auth::user(), $date, $data);
            $perPage = (int) ($data['per_page'] ?? 20);
            $page = (int) ($data['page'] ?? 1);
            $total = $list['members']->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'date' => $date,
                    'counts' => $list['counts'],
                    'members' => $list['members']->slice(($page - 1) * $perPage, $perPage)->map(fn ($m) => $this->member($m))->values(),
                    'pagination' => [
                        'page' => $page, 'per_page' => $perPage, 'total' => $total,
                        'last_page' => max(1, (int) ceil($total / $perPage)),
                    ],
                ],
            ]);
        } catch (Exception $e) {
            report($e);

            return response()->json(['success' => false, 'message' => 'Could not load the team. Please try again.'], 500);
        }
    }

    public function filters(TeamListService $teamList): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $teamList->filterOptions((int) Auth::user()->tenant_id) + ['statuses' => TeamListService::STATUSES],
        ]);
    }

    public function show(Request $request, int $id, TeamMemberProfile $profiles): JsonResponse
    {
        $month = (string) $request->query('month', Carbon::today()->format('Y-m'));
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month) || $month > Carbon::today()->format('Y-m')) {
            $month = Carbon::today()->format('Y-m');
        }

        $target = User::find($id);
        if (! $target) {
            return response()->json(['success' => false, 'message' => 'Employee not found.'], 404);
        }
        if (! $profiles->canView(Auth::user(), $target)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
        }

        try {
            $detail = $profiles->detail(Auth::user(), $target, $month);

            return $detail === null
                ? response()->json(['success' => false, 'message' => 'Employee information not found.'], 404)
                : response()->json(['success' => true, 'data' => $detail]);
        } catch (Exception $e) {
            report($e);

            return response()->json(['success' => false, 'message' => 'Could not load the employee. Please try again.'], 500);
        }
    }

    /** One list row as JSON (the web row's fields; photo as a URL). */
    private function member(object $m): array
    {
        return [
            'id' => $m->id,
            'employee_id' => $m->employee_id,
            'name' => $m->name,
            'email' => $m->email,
            'designation' => $m->designation,
            'department' => $m->department,
            'branch' => $m->branch,
            'profile_image_url' => $m->profile_image ? file_url($m->profile_image, 'profile_photo') : null,
            'status' => $m->status,
            'clock_in' => $m->punch_in ? (string) $m->punch_in : null,
            'clock_out' => $m->punch_out ? (string) $m->punch_out : null,
            'total_hours' => $m->total_hours,
            'shifts' => collect($m->shifts)->map(fn ($s) => ['id' => $s->id ?? null, 'name' => $s->name, 'time' => $s->time,
                'color' => $s->color, 'is_extra' => (bool) $s->extra])->values(),
            'shift_sessions' => collect($m->shift_sessions)->map(fn ($s) => ['name' => $s->name, 'in' => $s->in ? (string) $s->in : null,
                'out' => $s->out ? (string) $s->out : null, 'is_extra' => $s->is_extra, 'state' => $s->state])->values(),
            'open_shift' => $m->open_shift,
        ];
    }
}
