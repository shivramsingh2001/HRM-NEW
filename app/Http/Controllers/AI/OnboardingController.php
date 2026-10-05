<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\AI\Concerns\AiScope;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * GET /api/ai/onboarding — new-joiner onboarding with progress and the checklist (task, category,
 * owner, due date, status). company scope (onboarding:view) = every onboarding; anyone else sees
 * their own onboarding, their reportees', ones where they are the buddy, and ones with a task
 * assigned to them.
 */
class OnboardingController extends Controller
{
    use AiScope;

    public function view_ai_all(Request $request)
    {
        try {
            $authUser = Auth::user();
            $tenantId = (int) $authUser->tenant_id;

            $scope = $this->scopeOf($authUser, 'onboarding');
            $ids = $scope ? $this->visibleUserIds($authUser, $scope) : [(int) $authUser->id];

            $query = DB::table('onboarding_assignments as a')
                ->leftJoin('candidates as c', 'c.id', '=', 'a.candidate_id')
                ->leftJoin('users as u', 'u.id', '=', 'a.user_id')
                ->leftJoin('job_offers as o', 'o.id', '=', 'a.job_offer_id')
                ->leftJoin('designations as g', 'g.id', '=', 'o.designation_id')
                ->leftJoin('departments as d', 'd.id', '=', 'o.department_id')
                ->where('a.tenant_id', $tenantId)
                ->select([
                    'a.id', 'a.assignment_code', 'a.user_id', 'a.candidate_id', 'a.onboarding_status', 'a.start_date',
                    'a.expected_completion_date', 'a.actual_completion_date', 'a.onboarding_buddy', 'a.notes', 'a.created_by',
                    'u.name as user_name', 'u.employee_id as employee_code', 'c.first_name', 'c.last_name', 'c.candidate_code',
                    'o.joining_date', 'g.name as designation', 'd.name as department',
                ]);

            if ($ids !== null) {
                $query->where(fn ($q) => $q->whereIn('a.user_id', $ids ?: [0])
                    ->orWhere('a.onboarding_buddy', $authUser->id)
                    ->orWhereExists(fn ($s) => $s->from('onboarding_task_items as i')->whereColumn('i.onboarding_assignment_id', 'a.id')
                        ->where('i.assigned_to', $authUser->id)));
            }
            if ($request->filled('status')) {
                $query->where('a.onboarding_status', $request->status);
            }

            $assignments = $query->orderByDesc('a.start_date')->get();

            // onboarding_tasks are templates and may be global (tenant_id NULL) — don't tenant-filter the join.
            $items = DB::table('onboarding_task_items as i')
                ->leftJoin('onboarding_tasks as t', 't.id', '=', 'i.onboarding_task_id')
                ->whereIn('i.onboarding_assignment_id', $assignments->pluck('id')->all() ?: [0])
                ->orderBy('t.task_order')
                ->get(['i.onboarding_assignment_id', 'i.status', 'i.assigned_to', 'i.completed_by', 'i.completed_at', 'i.due_date',
                    'i.remarks', 't.task_name', 't.task_category', 't.is_mandatory', 't.assigned_to_role'])
                ->groupBy('onboarding_assignment_id');
            $people = $this->people($tenantId, $assignments->pluck('onboarding_buddy')
                ->merge($items->flatten(1)->pluck('assigned_to'))->merge($items->flatten(1)->pluck('completed_by')));

            $today = Carbon::today()->toDateString();
            $done = ['completed', 'skipped'];

            $data = $assignments->map(function ($a) use ($items, $people, $today, $done, $authUser) {
                $tasks = $items->get($a->id, collect())->map(fn ($i) => [
                    'task' => $i->task_name,
                    'category' => $i->task_category,
                    'mandatory' => (bool) $i->is_mandatory,
                    'owner' => $people[$i->assigned_to] ?? null,
                    'owner_role' => $i->assigned_to_role,
                    'due_date' => $i->due_date,
                    'status' => $i->status,
                    'is_overdue' => $i->status === 'overdue' || (! in_array($i->status, $done, true) && $i->due_date && $i->due_date < $today),
                    'assigned_to_me' => $i->assigned_to == $authUser->id,
                    'completed_by' => $people[$i->completed_by] ?? null,
                    'completed_at' => $i->completed_at,
                    'remarks' => $i->remarks,
                ])->values();
                $completed = $tasks->whereIn('status', $done)->count();

                return [
                    'id' => $a->id,
                    'assignment_code' => $a->assignment_code,
                    'new_joiner' => [
                        'user_id' => $a->user_id,
                        'name' => $a->user_name ?: trim($a->first_name . ' ' . $a->last_name),
                        'employee_id' => $a->employee_code,
                        'candidate_code' => $a->candidate_code,
                        'designation' => $a->designation,
                        'department' => $a->department,
                    ],
                    'status' => $a->onboarding_status,
                    'joining_date' => $a->joining_date,
                    'start_date' => $a->start_date,
                    'expected_completion_date' => $a->expected_completion_date,
                    'actual_completion_date' => $a->actual_completion_date,
                    'buddy' => $people[$a->onboarding_buddy] ?? null,
                    'progress' => [
                        'total_tasks' => $tasks->count(),
                        'completed' => $completed,
                        'overdue' => $tasks->where('is_overdue', true)->count(),
                        'percentage' => $tasks->count() ? (int) round($completed * 100 / $tasks->count()) : 0,
                    ],
                    'tasks' => $tasks,
                    'notes' => $a->notes,
                ];
            })->values();

            $allTasks = $data->pluck('tasks')->flatten(1);

            return response()->json([
                'success' => true,
                'message' => 'Onboarding data fetched successfully',
                'data' => $data,
                'summary' => [
                    'total_onboardings' => $data->count(),
                    'by_status' => $data->countBy('status'),
                    'in_progress' => $data->whereIn('status', ['not_started', 'in_progress'])->count(),
                    'overdue_tasks' => $allTasks->where('is_overdue', true)->count(),
                    'my_pending_tasks' => $allTasks->where('assigned_to_me', true)->whereNotIn('status', $done)->count(),
                ],
                'scope' => $scope ?? 'own',
            ], 200);
        } catch (\Throwable $e) {
            return $this->failed('onboarding', $e);
        }
    }
}
