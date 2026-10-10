<?php

namespace App\Http\Controllers\Task;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskAssign;
use App\Models\TaskUpdate;
use App\Models\TaskApproval;
use App\Models\TaskComment;
use App\Models\TaskAttachment;
use App\Models\User;
use App\Models\UserBasicDetail;
use App\Services\TaskNotificationService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Mail;
use App\Mail\TaskAssignedMail;
use App\Models\UserJobDetail;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\FacadesLog;

class TaskController extends Controller
{
    protected $notificationService;
    protected $permissions;

    public function __construct(TaskNotificationService $notificationService, \App\Services\TaskPermissionService $permissions)
    {
        $this->notificationService = $notificationService;
        $this->permissions = $permissions;
    }
    public function tasksAssignedByMe(Request $request)
    {
        try {
            $authUser = Auth::user();

            if (!$this->permissions->isManagerTier($authUser)) {
                return redirect()->back()->with('error', 'Unauthorized access.');
            }

            $query = Task::query()
                ->select([
                    'tasks.id',
                    'tasks.task_code',
                    'tasks.title',
                    'tasks.description',
                    'tasks.priority',
                    'tasks.status',
                    'tasks.task_date',
                    'tasks.deadline_date',
                    'tasks.created_at',
                    'tasks.file',
                    'tasks.voice_file',
                    'tasks.task_mode',
                    'tasks.group_completion_rule',
                    'tasks.group_lead_id',
                    'projects.name as project_name',
                    'projects.project_code',
                    'projects.id as project_id',
                    DB::raw('DATEDIFF(tasks.deadline_date, CURDATE()) as days_remaining'),
                ])
                ->leftJoin('projects', 'tasks.project_id', '=', 'projects.id')
                ->whereExists(function ($q) use ($authUser, $request) {
                    $q->select(DB::raw(1))
                        ->from('task_assigns')
                        ->whereColumn('task_assigns.task_id', 'tasks.id');

                    if ($authUser->role == 'manager') {
                        $q->where('task_assigns.assigned_by', $authUser->id);
                    }
                    if ($request->filled('assigned_to') && $request->assigned_to != 'all') {
                        $q->where('task_assigns.assigned_to', $request->assigned_to);
                    }
                    if (
                        $this->permissions->isElevated($authUser)
                        && $request->filled('assigned_by') && $request->assigned_by != 'all'
                    ) {
                        $q->where('task_assigns.assigned_by', $request->assigned_by);
                    }
                });

            // Existing filters
            if ($request->filled('status') && $request->status != 'all')
                $query->where('tasks.status', $request->status);
            if ($request->filled('priority') && $request->priority != 'all')
                $query->where('tasks.priority', $request->priority);
            if ($request->filled('project_id') && $request->project_id != 'all')
                $query->where('tasks.project_id', $request->project_id);
            if ($request->filled('date_from'))
                $query->whereDate('tasks.deadline_date', '>=', $request->date_from);
            if ($request->filled('date_to'))
                $query->whereDate('tasks.deadline_date', '<=', $request->date_to);

            // Stats
            $totalTasks      = (clone $query)->count();
            $pendingTasks    = (clone $query)->where('tasks.status', 'pending')->count();
            $inProgressTasks = (clone $query)->where('tasks.status', 'in_progress')->count();
            $completedTasks  = (clone $query)->where('tasks.status', 'completed')->count();
            $approvedTasks   = (clone $query)->where('tasks.status', 'approved')->count();
            $rejectTasks     = (clone $query)->where('tasks.status', 'rejected')->count();
            $cancelledTasks  = (clone $query)->where('tasks.status', 'cancelled')->count();

            // Sorting (same as before)
            $sortBy = $request->get('sort_by', 'priority_desc');
            switch ($sortBy) {
                case 'priority_asc':
                    $query->orderByRaw("FIELD(tasks.priority, 'low', 'medium', 'high', 'critical')");
                    break;
                case 'deadline_asc':
                    $query->orderBy('tasks.deadline_date', 'asc');
                    break;
                case 'deadline_desc':
                    $query->orderBy('tasks.deadline_date', 'desc');
                    break;
                case 'created_desc':
                    $query->orderBy('tasks.task_date', 'desc');
                    break;
                case 'created_asc':
                    $query->orderBy('tasks.task_date', 'asc');
                    break;
                default:
                    $query->orderByRaw("FIELD(tasks.priority, 'critical', 'high', 'medium', 'low')");
            }
            $query->orderBy('tasks.id', 'desc');

            $tasks = $query->paginate(20)->appends($request->query());

            // Attach members to each task in ONE query
            $members = DB::table('task_assigns')
                ->leftJoin('users', 'task_assigns.assigned_to', '=', 'users.id')
                ->leftJoin('user_basic_details', 'users.id', '=', 'user_basic_details.user_id')
                ->whereIn('task_assigns.task_id', $tasks->pluck('id'))
                ->select(
                    'task_assigns.task_id',
                    'task_assigns.assigned_by',
                    'task_assigns.assigned_to',
                    'task_assigns.member_role',
                    'task_assigns.individual_status',
                    'users.id as user_id',
                    'users.name',
                    'users.email',
                    'users.employee_id',
                    'user_basic_details.profile_image'
                )
                ->get()
                ->groupBy('task_id');

            foreach ($tasks as $t) {
                $t->members      = $members->get($t->id, collect());
                $t->member_count = $t->members->count();
                // For individual tasks: convenience fields the blade already expects
                $first = $t->members->first();
                $t->assigned_to_name        = $first->name ?? null;
                $t->assigned_to_email       = $first->email ?? null;
                $t->assigned_to_employee_id = $first->employee_id ?? null;
                $t->assigned_to_id          = $first->user_id ?? null;
                $t->assigned_to_image       = $first->profile_image ?? null;
            }

            // Dropdowns (same as before)
            $usersQuery = User::join('user_basic_details', 'users.id', '=', 'user_basic_details.user_id')
                ->select('users.id', 'users.name', 'users.employee_id', 'users.email')
                ->where('users.status', 1);

            if ($this->permissions->isElevated($authUser)) {
                $users = $usersQuery->orderBy('users.name')->get();
            } elseif ($this->permissions->isManagerTier($authUser)) {
                $ids = User::managedBy($authUser->id)->pluck('id')->toArray();
                $ids[] = $authUser->id;
                $users = $usersQuery->whereIn('users.id', $ids)->orderBy('users.name')->get();
            } else {
                $users = $usersQuery->where('users.id', $authUser->id)->get();
            }

            $assigners = [];
            if ($this->permissions->isElevated($authUser)) {
                $assigners = User::join('user_basic_details', 'users.id', '=', 'user_basic_details.user_id')
                    ->select('users.id', 'users.name', 'users.employee_id', 'users.email')
                    ->where('users.status', 1)
                    ->orderBy('users.name')->get();
            }

            $projects = Project::select('id', 'name')->get();

            return view('client.task.view-assigned-by-task', compact(
                'tasks',
                'projects',
                'users',
                'assigners',
                'totalTasks',
                'pendingTasks',
                'inProgressTasks',
                'completedTasks',
                'approvedTasks',
                'rejectTasks',
                'cancelledTasks',
                'authUser'
            ));
        } catch (Exception $e) {
            Log::error('tasksAssignedByMe error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'An error occurred.');
        }
    }

    public function tasksAssignedToMe(Request $request)
    {
        try {
            $authUser = Auth::user();

            $query = Task::select([
                'tasks.id',
                'tasks.task_code',
                'tasks.title',
                'tasks.priority',
                'tasks.status',
                'tasks.task_date',
                'tasks.deadline_date',
                'tasks.created_at',
                'tasks.description',
                'tasks.file',
                'tasks.voice_file',
                'projects.name as project_name',
                'projects.project_code',
                'projects.id as project_id',
                'users.name as assigned_by_name',
                'users.employee_id as assigned_by_employee_id',
                'users.email as assigned_by_email',
                'user_basic_details.profile_image as assigned_by_image',
                DB::raw('CASE 
                        WHEN tasks.status != "completed" 
                        THEN DATEDIFF(tasks.deadline_date, CURDATE()) 
                        ELSE NULL 
                    END as days_remaining')
            ])
                // Join task_assigns filtered to this user's own row directly —
                // a group task has one task_assigns row per member, so joining
                // unfiltered (and only checking membership via a separate
                // whereExists) produced one duplicate result row per member.
                ->join('task_assigns', function ($join) use ($authUser) {
                    $join->on('tasks.id', '=', 'task_assigns.task_id')
                        ->where('task_assigns.assigned_to', $authUser->id);
                })
                ->leftJoin('users', 'task_assigns.assigned_by', '=', 'users.id')
                ->leftJoin('user_basic_details', 'task_assigns.assigned_by', '=', 'user_basic_details.user_id')
                ->leftJoin('projects', 'tasks.project_id', '=', 'projects.id');

            // Apply filters
            if ($request->has('status') && $request->status != 'all') {
                $query->where('tasks.status', $request->status);
            }

            if ($request->has('priority') && $request->priority != 'all') {
                $query->where('tasks.priority', $request->priority);
            }

            if ($request->has('project_id') && $request->project_id != 'all') {
                $query->where('tasks.project_id', $request->project_id);
            }


            // Get statistics
            $statsQuery = Task::join('task_assigns', 'tasks.id', '=', 'task_assigns.task_id')
                ->where('task_assigns.assigned_to', $authUser->id);

            if ($request->has('status') && $request->status != 'all') {
                $statsQuery->where('tasks.status', $request->status);
            }

            if ($request->has('priority') && $request->priority != 'all') {
                $statsQuery->where('tasks.priority', $request->priority);
            }

            if ($request->has('project_id') && $request->project_id != 'all') {
                $statsQuery->where('tasks.project_id', $request->project_id);
            }

            $totalTasks = $statsQuery->count();
            $pendingTasks = (clone $statsQuery)->where('tasks.status', 'pending')->count();
            $approvedTasks = (clone $statsQuery)->where('tasks.status', 'approved')->count();
            $completedTasks = (clone $statsQuery)->where('tasks.status', 'completed')->count();
            $rejectedTasks = (clone $statsQuery)->where('tasks.status', 'rejected')->count();
            $inProgressTasks = (clone $statsQuery)->where('tasks.status', 'in_progress')->count();
            $cancelledTasks = (clone $statsQuery)->where('tasks.status', 'cancelled')->count();

            $sortBy = $request->get('sort_by', 'priority_desc');

            switch ($sortBy) {
                case 'priority_desc':
                    // High to low: critical, high, medium, low
                    $query->orderByRaw("FIELD(tasks.priority, 'critical', 'high', 'medium', 'low')");
                    break;
                case 'priority_asc':
                    // Low to high: low, medium, high, critical
                    $query->orderByRaw("FIELD(tasks.priority, 'low', 'medium', 'high', 'critical')");
                    break;
                case 'deadline_asc':
                    // Earliest deadline first
                    $query->orderBy('tasks.deadline_date', 'asc');
                    break;
                case 'deadline_desc':
                    // Latest deadline first
                    $query->orderBy('tasks.deadline_date', 'desc');
                    break;
                case 'created_desc':
                    // Newest first
                    $query->orderBy('tasks.task_date', 'desc');
                    break;
                case 'created_asc':
                    // Oldest first
                    $query->orderBy('tasks.task_date', 'asc');
                    break;
                default:
                    // Default: priority high to low
                    $query->orderByRaw("FIELD(tasks.priority, 'critical', 'high', 'medium', 'low')");
                    break;
            }

            // Add secondary sort for consistent ordering
            if (!in_array($sortBy, ['deadline_asc', 'deadline_desc', 'created_asc', 'created_desc'])) {
                $query->orderBy('tasks.deadline_date', 'asc');
            } else {
                $query->orderBy('tasks.id', 'desc');
            }

            // Get paginated tasks
            $tasks = $query->paginate(20)->appends($request->query());


            // Get projects for filter dropdown
            $projects = Project::select('id', 'name')->get();

            // For the Add Task drawer's "assign to someone else"/"group"
            // options (visible to manager/hr, hidden for plain employees).
            // Was admin-only (hr fell through to the empty else branch,
            // despite isElevated() treating admin+hr as equally privileged
            // everywhere else in this controller).
            if ($this->permissions->isElevated($authUser)) {
                $users = User::where('role', '!=', 'admin')->where('status', 1)->get();
            } elseif ($this->permissions->isManagerTier($authUser)) {
                $users = User::managedBy($authUser->id)
                    ->where('users.status', 1)
                    ->get();
            } else {
                $users = collect();
            }

            return view('client.task.view-assigned-to-task', compact(
                'tasks',
                'projects',
                'users',
                'totalTasks',
                'pendingTasks',
                'approvedTasks',
                'rejectedTasks',
                'completedTasks',
                'inProgressTasks',
                'cancelledTasks',
                'authUser'
            ));
        } catch (Exception $e) {
            report($e);
            return redirect()->back()
                ->with('error', 'An error occurred. Please try again later.');
        }
    }

    /**
     * The dedicated "Create Task" page was retired in favor of the Add Task
     * drawer embedded directly in the assigned-by-me / assigned-to-me list
     * pages. This redirect keeps any old bookmark/link to this route working
     * instead of 404ing.
     */
    public function create(Request $request)
    {
        $authUser = Auth::user();

        return redirect()->route(
            $this->permissions->isManagerTier($authUser) ? 'task.assigned-by-me' : 'task.assigned-to-me'
        );
    }

    public function store(Request $request)
    {
        $authUser = Auth::user();

        // self_assigned: 1 = self, 0 = someone else, 2 = group
        $rules = [
            'title'         => 'required|string|max:255',
            'project_id'    => 'nullable|exists:projects,id',
            'description'   => 'required|string',
            'deadline_date' => 'required|date|after_or_equal:today',
            'task_date'     => 'required|date|before_or_equal:today',
            'priority'      => 'required|in:low,medium,high,critical',
            'file'          => 'nullable|file|mimes:jpg,jpeg,png,pdf,doc,docx,xls,xlsx|max:5120',
            'voice_file'    => 'nullable|file|max:10240',
            'self_assigned' => 'required|in:0,1,2',
        ];

        if ($request->self_assigned == 0) {
            $rules['assigned_to'] = 'required|exists:users,id';
        } elseif ($request->self_assigned == 2) {
            $rules['group_members']         = 'required|array|min:2';
            $rules['group_members.*']       = 'exists:users,id|distinct';
            $rules['group_completion_rule'] = 'required|in:all_must_complete,any_one,percentage,lead_decides';
            $rules['completion_threshold']  = 'required_if:group_completion_rule,percentage|nullable|integer|min:1|max:100';
            $rules['group_lead_id']         = 'nullable|exists:users,id';
        }

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        // Re-check server-side: the drawer already hides an assignment type the
        // plan doesn't include, but the endpoint must not trust the client.
        $features = app(\App\Services\FeatureService::class);
        $requiredFeature = $request->self_assigned == 2 ? 'task_group' : 'task_single';
        if (! $features->enabledForCurrentTenant($requiredFeature)) {
            return response()->json([
                'success' => false,
                'message' => 'That task assignment type is not included in your current plan.',
            ], 403);
        }

        DB::beginTransaction();
        try {
            $taskCode = $this->generateUniqueTaskCode();

            // File uploads (unchanged from your code)
            $filePath = $request->hasFile('file')
                ? file_storage()->upload($request->file('file'), 'task_document')->path
                : null;

            $voiceFilePath = $request->hasFile('voice_file')
                ? file_storage()->upload($request->file('voice_file'), 'task_voice')->path
                : null;

            // Decide task_mode + who is assigner
            $isGroup    = $request->self_assigned == 2;
            $isSelf     = $request->self_assigned == 1;
            $assignedBy = $authUser->id;

            if ($isSelf) {
                $job = UserJobDetail::where('user_id', $authUser->id)->first();
                if ($job && $job->reporting_head) {
                    $assignedBy = $job->reporting_head;
                }
            }

            // Create the parent task
            $task = Task::create([
                'task_code'             => $taskCode,
                'title'                 => $request->title,
                'project_id'            => $request->project_id,
                'description'           => $request->description,
                'task_date'             => $request->task_date ?? date('Y-m-d'),
                'deadline_date'         => $request->deadline_date,
                'priority'              => $request->priority,
                'status'                => 'pending',
                'file'                  => $filePath,
                'voice_file'            => $voiceFilePath,
                'task_mode'             => $isGroup ? 'group' : 'individual',
                'group_completion_rule' => $isGroup ? $request->group_completion_rule : null,
                'completion_threshold'  => $isGroup && $request->group_completion_rule === 'percentage'
                    ? $request->completion_threshold : null,
                'group_lead_id'         => $isGroup ? $request->group_lead_id : null,
            ]);

            // Build the list of assignees
            if ($isGroup) {
                $memberIds = array_unique($request->group_members);
                // If group_lead_id was set but not in members, add them
                if ($request->group_lead_id && !in_array($request->group_lead_id, $memberIds)) {
                    $memberIds[] = $request->group_lead_id;
                }
            } elseif ($isSelf) {
                $memberIds = [$authUser->id];
            } else {
                $memberIds = [$request->assigned_to];
            }

            // Create one task_assigns row per member
            foreach ($memberIds as $uid) {
                TaskAssign::create([
                    'task_id'           => $task->id,
                    'assigned_by'       => $assignedBy,
                    'assigned_to'       => $uid,
                    'status'            => 'assigned',
                    'member_role'       => $isGroup && $uid == $request->group_lead_id ? 'lead' : 'contributor',
                    'individual_status' => 'pending',
                ]);
            }

            DB::commit();

            // Notify everyone (loop so groups get notified too). Mail is
            // queued (Mail::to()->queue()) rather than sent synchronously —
            // previously a group task blocked the HTTP response on one
            // synchronous SMTP round-trip per member.
            $assigneeUser = User::find($assignedBy);
            foreach ($memberIds as $uid) {
                try {
                    $assignedUser = User::find($uid);
                    if ($assignedUser && $this->notificationService) {
                        $this->notificationService->notifyTaskAssigned($task, $assignedUser, $assigneeUser);
                    }
                    $this->sendTaskAssignedEmail($task, $authUser, $assignedUser);
                } catch (Exception $e) {
                    Log::error('Group notify/email failed for user ' . $uid . ': ' . $e->getMessage());
                }
            }

            // Creator confirmation — sent once per task, not once per member
            // (previously sendTaskEmails() sent this inside the per-member
            // loop, so a group task creator received N duplicate emails).
            try {
                $this->sendTaskCreatedEmail($task, $authUser);
            } catch (Exception $e) {
                Log::error('Task-created confirmation email failed: ' . $e->getMessage());
            }

            $msg = $isGroup
                ? 'Group task created and assigned to ' . count($memberIds) . ' members.'
                : 'Task created and assigned successfully!';

            return response()->json([
                'success' => true,
                'message' => $msg,
                'data' => $task,
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Task store error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'An error occurred. ' . $e->getMessage(),
            ], 500);
        }
    }

    private function sendTaskAssignedEmail($task, $creator, $assignee)
    {
        try {
            Mail::to($assignee->email)->queue(new TaskAssignedMail(
                $task,
                $assignee,
                $creator,
                'assigned'
            ));
        } catch (Exception $e) {
            Log::error("Failed to queue assignment email to {$assignee->email}: " . $e->getMessage());
        }
    }

    private function sendTaskCreatedEmail($task, $creator)
    {
        try {
            Mail::to($creator->email)->queue(new TaskAssignedMail(
                $task,
                $creator,
                $creator,
                'created'
            ));
        } catch (Exception $e) {
            Log::error("Failed to queue creation email to {$creator->email}: " . $e->getMessage());
        }
    }

    private function generateUniqueTaskCode()
    {
        return DB::transaction(function () {
            // Get the last task code using Task model
            $lastTask = Task::where('task_code', 'like', 'TASK-%')
                ->lockForUpdate()
                ->orderByRaw('CAST(SUBSTRING(task_code, 6) AS UNSIGNED) DESC')
                ->first();

            if (!$lastTask) {
                $nextNumber = 1;
            } else {
                // Extract numeric part from TASK-00001
                $lastNumber = (int) substr($lastTask->task_code, 5);
                $nextNumber = $lastNumber + 1;

                // Reset if exceeds 99999
                if ($nextNumber > 99999) {
                    $nextNumber = 1;
                }
            }

            return 'TASK-' . str_pad($nextNumber, 5, '0', STR_PAD_LEFT);
        });
    }
    /**
     * Recompute the parent task status based on the group completion rule
     * and each member's individual_status in task_assigns.
     */
    private function recomputeGroupStatus(Task $task): void
    {
        if ($task->task_mode !== 'group') {
            return;
        }

        $members = TaskAssign::where('task_id', $task->id)->get();
        if ($members->isEmpty()) {
            return;
        }

        $total      = $members->count();
        $completed  = $members->where('individual_status', 'completed')->count();
        $inProgress = $members->where('individual_status', 'in_progress')->count();
        $blocked    = $members->where('individual_status', 'blocked')->count();

        $rule      = $task->group_completion_rule;
        $threshold = (int) ($task->completion_threshold ?? 100);

        $newStatus = $task->status;

        switch ($rule) {
            case 'all_must_complete':
                if ($completed === $total) {
                    $newStatus = 'completed';
                } elseif ($completed > 0 || $inProgress > 0) {
                    $newStatus = 'in_progress';
                } else {
                    $newStatus = 'pending';
                }
                break;

            case 'any_one':
                if ($completed >= 1) {
                    $newStatus = 'completed';
                } elseif ($inProgress > 0) {
                    $newStatus = 'in_progress';
                } else {
                    $newStatus = 'pending';
                }
                break;

            case 'percentage':
                $pct = $total > 0 ? ($completed / $total) * 100 : 0;
                if ($pct >= $threshold) {
                    $newStatus = 'completed';
                } elseif ($completed > 0 || $inProgress > 0) {
                    $newStatus = 'in_progress';
                } else {
                    $newStatus = 'pending';
                }
                break;

            case 'lead_decides':
                // Lead controls explicitly — don't auto-flip parent status here.
                return;
        }

        // Never overwrite final states from approval workflow
        if (in_array($task->status, ['approved', 'rejected', 'cancelled'])) {
            return;
        }

        if ($newStatus !== $task->status) {
            $task->status = $newStatus;
            $task->save();
        }
    }

    public function delete($id)
    {
        try {
            $authUser = Auth::user();

            // Get task details first for additional checks
            $task = Task::find($id);

            if (!$task) {
                return response()->json([
                    'success' => false,
                    'message' => 'Task not found.'
                ], 404);
            }

            // Check if user is the one who assigned this task
            $isAssignedBy = $this->permissions->isTaskAssigner($authUser, $id);

            if (!$isAssignedBy) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only the person who assigned this task can delete it.'
                ], 403);
            }

            // ✅ FIXED: Only allow deletion if task is pending or in_progress
            $allowedStatuses = ['pending', 'in_progress', 'hold'];

            if (!in_array($task->status, $allowedStatuses)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Task can only be deleted if status is Pending  Hold or In Progress. Current status: ' . ucfirst(str_replace('_', ' ', $task->status))
                ], 422);
            }

            // Start transaction for data integrity
            DB::beginTransaction();

            try {
                // Delete related records first
                TaskAssign::where('task_id', $id)->delete();
                TaskUpdate::where('task_id', $id)->delete();
                TaskApproval::where('task_id', $id)->delete();

                // Now delete the main task
                $deleted = $task->delete();

                if (!$deleted) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => 'Failed to delete task.'
                    ], 500);
                }

                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => 'Task deleted successfully',
                ], 200);
            } catch (Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (Exception $e) {
            report($e);
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while deleting the task. Please try again later.'
            ], 500);
        }
    }

    /**
     * JSON prefill for the Edit Task drawer.
     */
    public function edit($id)
    {
        $authUser = Auth::user();

        $task = Task::find($id);
        if (!$task) {
            return response()->json(['success' => false, 'message' => 'Task not found.'], 404);
        }

        $isAssignedBy = $this->permissions->isTaskAssigner($authUser, $id);

        if (!$isAssignedBy) {
            return response()->json([
                'success' => false,
                'message' => 'Only the person who assigned this task can edit it.'
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id'             => $task->id,
                'title'          => $task->title,
                'description'    => $task->description,
                'project_id'     => $task->project_id,
                'priority'       => $task->priority,
                'deadline_date'  => $task->deadline_date,
                'status'         => $task->status,
                'task_mode'      => $task->task_mode,
            ],
        ]);
    }

    /**
     * Update a task's core fields (title/description/priority/deadline/project).
     * Reassigning members/group composition is a separate flow — not handled here.
     */
    public function update(Request $request, $id)
    {
        $authUser = Auth::user();

        $task = Task::find($id);
        if (!$task) {
            return response()->json(['success' => false, 'message' => 'Task not found.'], 404);
        }

        $isAssignedBy = $this->permissions->isTaskAssigner($authUser, $id);

        if (!$isAssignedBy) {
            return response()->json([
                'success' => false,
                'message' => 'Only the person who assigned this task can edit it.'
            ], 403);
        }

        // Same editable-window rule as delete(): once a task is completed,
        // approved, rejected or cancelled its record is final.
        $editableStatuses = ['pending', 'in_progress', 'hold'];
        if (!in_array($task->status, $editableStatuses)) {
            return response()->json([
                'success' => false,
                'message' => 'Task can only be edited while Pending, In Progress or Hold. Current status: ' . ucfirst(str_replace('_', ' ', $task->status))
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'title'         => 'required|string|max:255',
            'project_id'    => 'nullable|exists:projects,id',
            'description'   => 'required|string',
            'deadline_date' => 'required|date|after_or_equal:today',
            'priority'      => 'required|in:low,medium,high,critical',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $task->title = $request->title;
            $task->description = $request->description;
            $task->project_id = $request->project_id;
            $task->priority = $request->priority;
            $task->deadline_date = $request->deadline_date;
            $task->save();

            return response()->json([
                'success' => true,
                'message' => 'Task updated successfully',
                'data' => $task,
            ]);
        } catch (Exception $e) {
            Log::error('Task update error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update task.'
            ], 500);
        }
    }

    public function bulkStatusUpdate(Request $request)
    {
        try {
            $authUser = Auth::user();

            $request->validate([
                'task_ids' => 'required|array',
                'task_ids.*' => 'exists:tasks,id',
                // Same statuses an assignee may set in TaskUpdate(); approve /
                // reject is only ever the assigner's decision (TaskApproval).
                'status' => 'required|in:pending,in_progress,hold,completed',
                'remarks' => 'nullable|string'
            ]);

            // Get only tasks where the auth user is assigned
            $assigns = TaskAssign::whereIn('task_id', $request->task_ids)
                ->where('assigned_to', $authUser->id)
                ->get()
                ->keyBy('task_id');

            if ($assigns->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tasks found that are assigned to you'
                ], 400);
            }

            $tasks = Task::whereIn('id', $assigns->keys())->get()->keyBy('id');

            $updated = [];
            $skipped = [];
            $notify = [];

            DB::beginTransaction();
            try {
                foreach ($assigns as $taskId => $assign) {
                    $task = $tasks->get($taskId);
                    if (!$task) {
                        $skipped[] = ['task_id' => $taskId, 'reason' => 'Task not found'];
                        continue;
                    }

                    if (in_array($task->status, ['approved', 'rejected', 'cancelled'])) {
                        $skipped[] = ['task_id' => $taskId, 'reason' => 'Cannot update a ' . $task->status . ' task'];
                        continue;
                    }

                    $oldStatus = $task->task_mode === 'group' ? ($assign->individual_status ?? 'pending') : $task->status;

                    if ($task->task_mode === 'group') {
                        // Same per-member path as TaskUpdate(): only this
                        // member's individual_status changes, then the parent
                        // status is recomputed from the group completion rule.
                        $assign->individual_status = $request->status;
                        if ($request->filled('remarks')) {
                            $assign->individual_remarks = $request->remarks;
                        }
                        if ($request->status === 'in_progress' && !$assign->started_at) {
                            $assign->started_at = now();
                        }
                        if ($request->status === 'completed') {
                            $assign->completed_at = now();
                        }
                        $assign->save();
                        $this->recomputeGroupStatus($task);
                    } else {
                        $task->status = $request->status;
                        $task->save();
                    }

                    TaskUpdate::create([
                        'tenant_id'  => $task->tenant_id ?? 1,
                        'task_id'    => $task->id,
                        'updated_by' => $authUser->id,
                        'status'     => $request->status,
                        'remarks'    => '[Bulk update] ' . ($request->remarks ?? ''),
                    ]);

                    $updated[] = $taskId;
                    $notify[] = [$task, $assign, $oldStatus];
                }

                DB::commit();
            } catch (Exception $e) {
                DB::rollBack();
                throw $e;
            }

            // Same notification as a single TaskUpdate(), to each task's assigner.
            foreach ($notify as [$task, $assign, $oldStatus]) {
                try {
                    $task->refresh();
                    $this->notificationService->notifyTaskStatusUpdate(
                        $task,
                        $authUser,
                        $oldStatus,
                        $request->status,
                        $request->remarks,
                        User::find($assign->assigned_by),
                        $task->task_mode === 'group' ? $task->status : null
                    );
                } catch (Exception $e) {
                    Log::error('Bulk task notification failed: ' . $e->getMessage());
                }
            }

            return response()->json([
                'success' => count($updated) > 0,
                'message' => count($updated) . ' task(s) updated' . (count($skipped) ? ', ' . count($skipped) . ' skipped' : ''),
                'data' => [
                    'updated' => $updated,
                    'skipped' => $skipped,
                ],
            ]);
        } catch (Exception $e) {
            report($e);
            return response()->json([
                'success' => false,
                'message' => 'Error updating tasks: ' . $e->getMessage()
            ], 500);
        }
    }

    public function TaskUpdate(Request $request)
    {
        $request->validate([
            'task_id'           => 'required|exists:tasks,id',
            'status'            => 'required|string|in:pending,in_progress,hold,completed,cancelled',
            'remarks'           => 'nullable|string|max:1000',
            'extend_deadline'   => 'nullable|boolean',
            'new_deadline'      => 'required_if:extend_deadline,true|date|after:today',
            'extension_reason'  => 'nullable|string|max:500',
        ]);

        try {
            $authUser = Auth::user();
            $task = Task::find($request->task_id);
            if (!$task) {
                return response()->json(['success' => false, 'message' => 'Task not found.'], 404);
            }

            $assign = TaskAssign::where('task_id', $request->task_id)
                ->where('assigned_to', $authUser->id)
                ->first();

            if (!$assign) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not assigned to this task.'
                ], 403);
            }

            if (in_array($task->status, ['approved', 'rejected', 'cancelled'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot update a ' . $task->status . ' task.'
                ], 400);
            }

            // For a group task the member changes their own part, so the "old"
            // status in the notification is their previous individual status.
            $oldStatus = $task->task_mode === 'group' ? ($assign->individual_status ?? 'pending') : $task->status;
            $oldDeadline = $task->deadline_date;
            $deadlineExtended = false;

            // Deadline extension (only the group lead may extend in group mode)
            if ($request->boolean('extend_deadline') && in_array($request->status, ['pending', 'in_progress', 'hold'])) {

                if ($task->task_mode === 'group' && $task->group_lead_id && $task->group_lead_id != $authUser->id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Only the group lead can extend the deadline for a group task.'
                    ], 403);
                }

                $newDeadline = Carbon::parse($request->new_deadline);
                if ($newDeadline->lte(Carbon::parse($task->deadline_date))) {
                    return response()->json([
                        'success' => false,
                        'message' => 'New deadline must be after the current deadline.'
                    ], 400);
                }

                if (!$task->original_deadline_date) {
                    $task->original_deadline_date = $task->deadline_date;
                }
                $task->deadline_date    = $request->new_deadline;
                $task->extension_count  = ($task->extension_count ?? 0) + 1;
                $task->save();
                $deadlineExtended = true;
            }

            DB::beginTransaction();

            // GROUP MODE: update only this member's individual_status, then recompute parent
            if ($task->task_mode === 'group') {
                $assign->individual_status = $request->status;
                $assign->individual_remarks = $request->remarks;
                if ($request->status === 'in_progress' && !$assign->started_at) {
                    $assign->started_at = now();
                }
                if ($request->status === 'completed') {
                    $assign->completed_at = now();
                }
                $assign->save();

                // Log the change in task_updates (for audit)
                TaskUpdate::create([
                    'tenant_id'           => $task->tenant_id ?? 1,
                    'task_id'             => $task->id,
                    'updated_by'          => $authUser->id,
                    'status'              => $request->status,
                    'remarks'             => '[Member update] ' . ($request->remarks ?? ''),
                    'deadline_extension'  => $deadlineExtended ? 1 : 0,
                    'old_deadline'        => $oldDeadline,
                    'new_deadline'        => $deadlineExtended ? $task->deadline_date : $oldDeadline,
                ]);

                // Re-aggregate parent task status
                $this->recomputeGroupStatus($task);
                $task->refresh();
            } else {
                // INDIVIDUAL MODE: original behavior — update parent task directly
                TaskUpdate::create([
                    'tenant_id'           => $task->tenant_id ?? 1,
                    'task_id'             => $task->id,
                    'updated_by'          => $authUser->id,
                    'status'              => $request->status,
                    'remarks'             => $request->remarks,
                    'deadline_extension'  => $deadlineExtended ? 1 : 0,
                    'old_deadline'        => $oldDeadline,
                    'new_deadline'        => $deadlineExtended ? $task->deadline_date : $oldDeadline,
                ]);
                $task->status = $request->status;
                $task->save();
            }

            DB::commit();

            // Notify the assigner — explicitly resolved from this member's own
            // assignment row, not re-derived from an arbitrary task_assigns row
            // (a group task has one row per member, all sharing the same
            // assigned_by, so $assign here is a safe, correct source).
            try {
                if ($this->notificationService) {
                    // What this member actually set; for a group task also
                    // report where the whole task now stands.
                    $this->notificationService->notifyTaskStatusUpdate(
                        $task,
                        $authUser,
                        $oldStatus,
                        $request->status,
                        $request->remarks,
                        User::find($assign->assigned_by),
                        $task->task_mode === 'group' ? $task->status : null
                    );
                }
            } catch (Exception $e) {
                Log::error('Task update notification failed: ' . $e->getMessage());
            }

            return response()->json([
                'success' => true,
                'message' => $task->task_mode === 'group'
                    ? 'Your progress updated. Group status is now: ' . ucfirst(str_replace('_', ' ', $task->status))
                    : 'Task updated successfully.',
                'data' => [
                    'status'         => $task->status,
                    'status_display' => ucfirst(str_replace('_', ' ', $task->status)),
                    'deadline_date'  => $task->deadline_date,
                ],
            ], 200);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('TaskUpdate error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function TaskApproval(Request $request)
    {
        $request->validate([
            'task_id' => 'required|exists:tasks,id',
            'status' => 'required|string|in:approved,rejected',
            'remarks' => 'required|string|max:1000',
        ]);

        try {
            $authUser = Auth::user();
            $task = Task::find($request->task_id);

            if (!$task) {
                return response()->json([
                    'success' => false,
                    'message' => 'Task not found.'
                ], 400);
            }

            // Any assigner row authorizes the action; the approval itself is
            // recorded against every assignee below, not just this one row.
            $isAssigner = $this->permissions->isTaskAssigner($authUser, $request->task_id);

            if (!$isAssigner) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only the person who assigned this task can approve/reject it.'
                ], 400);
            }

            if ($task->status != 'completed') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot update a ' . $request->status . ' task. Please contact admin if needed.'
                ], 400);
            }

            // Full membership of the task — for a group task this is every
            // member, not an arbitrary single row. For an individual task
            // it's the one assignee.
            $allAssigns = TaskAssign::where('task_id', $request->task_id)->get();
            $completerAssigns = $task->task_mode === 'group'
                ? $allAssigns->where('individual_status', 'completed')
                : $allAssigns;
            if ($completerAssigns->isEmpty()) {
                $completerAssigns = $allAssigns;
            }

            $oldStatus = $task->status;
            DB::beginTransaction();

            // One approval record per member who actually completed their
            // part, so a group task's approval history reflects who did the
            // work instead of an arbitrary first row.
            foreach ($completerAssigns as $completerAssign) {
                TaskApproval::create([
                    'task_id' => $request->task_id,
                    'approved_by' => $authUser->id,
                    'completed_by' => $completerAssign->assigned_to,
                    'status' => $request->status,
                    'remarks' => $request->remarks,
                ]);
            }

            // Update main task
            $task->status = $request->status;
            $task->save();

            $newTask = null;
            $newTaskCode = null;

            if ($request->status == 'rejected') {
                // Generate new unique task code
                $newTaskCode = $this->generateUniqueTaskCode();

                // Create new task with same details, preserving group mode so
                // rework doesn't silently collapse a group task down to a
                // single assignee.
                $newTask = Task::create([
                    'task_code' => $newTaskCode,
                    'title' => $task->title,
                    'project_id' => $task->project_id,
                    'description' => $task->description,
                    'task_date' => $task->task_date,
                    'deadline_date' => $task->deadline_date,
                    'priority' => $task->priority,
                    'status' => 'pending',
                    'file' => $task->file,
                    'voice_file' => $task->voice_file,
                    'parent_task_id' => $task->id,
                    'rejection_remarks' => $request->remarks,
                    'task_mode' => $task->task_mode,
                    'group_completion_rule' => $task->group_completion_rule,
                    'completion_threshold' => $task->completion_threshold,
                    'group_lead_id' => $task->group_lead_id,
                ]);

                // Recreate the full original membership on the reworked task
                // (all group members, not just the completer).
                foreach ($allAssigns as $prevAssign) {
                    TaskAssign::create([
                        'task_id' => $newTask->id,
                        'assigned_by' => $prevAssign->assigned_by,
                        'assigned_to' => $prevAssign->assigned_to,
                        'member_role' => $prevAssign->member_role,
                        'status' => 'assigned',
                        'individual_status' => 'pending',
                    ]);
                }
            }

            DB::commit();

            // Notify every member, not just one — explicit receiver per
            // iteration, since the service can't guess which member a given
            // loop pass concerns.
            foreach ($allAssigns as $notifyAssign) {
                try {
                    $assignedUser = User::find($notifyAssign->assigned_to);
                    if ($assignedUser && $this->notificationService) {
                        $this->notificationService->notifyTaskStatusUpdate(
                            $task,
                            $authUser,
                            $oldStatus,
                            $request->status,
                            $request->remarks,
                            $assignedUser
                        );
                    }
                } catch (Exception $e) {
                    Log::error('Failed to send task notification: ' . $e->getMessage());
                }
            }

            $message = $request->status == 'approved'
                ? 'Task approved successfully'
                : 'Task rejected and new task created successfully. The employee has been reassigned the task.';



            return response()->json([
                'success' => true,
                'message' => $message,
            ], 200);
        } catch (Exception $e) {
            report($e);
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'An error occurred. Please try again later. ' . $e->getMessage(),
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $authUser = Auth::user();

            if (!$this->permissions->canAccessTask($authUser, $id)) {
                return redirect()->route('task.assigned-by-me')
                    ->with('error', 'You do not have access to this task.');
            }

            // Get task basic details with profile images
            $task = Task::select([
                'tasks.id',
                'tasks.task_code',
                'tasks.title',
                'tasks.description',
                'tasks.priority',
                'tasks.status',
                'tasks.task_date',
                'tasks.task_mode',
                'tasks.deadline_date',
                'tasks.file',
                'tasks.voice_file',
                'projects.id as project_id',
                'projects.name as project_name',
                'projects.project_code',
                'projects.description as project_description',

                // Assigner details (who assigned the task)
                'assigner.name as assigned_by_name',
                'assigner.id as assigned_by_id',
                'assigner.email as assigned_by_email',
                'assigner_ubd.profile_image as assigned_by_image',

                // Assignee details (who is assigned to the task)
                'assignee.name as assigned_to_name',
                'assignee.id as assigned_to_id',
                'assignee.email as assigned_to_email',
                'assignee_ubd.profile_image as assigned_to_image'

            ])
                ->leftJoin('projects', 'tasks.project_id', '=', 'projects.id')
                ->leftJoin('task_assigns', 'tasks.id', '=', 'task_assigns.task_id')
                ->leftJoin('users as assigner', 'task_assigns.assigned_by', '=', 'assigner.id')
                ->leftJoin('users as assignee', 'task_assigns.assigned_to', '=', 'assignee.id')
                ->leftJoin('user_basic_details as assigner_ubd', 'assigner.id', '=', 'assigner_ubd.user_id')
                ->leftJoin('user_basic_details as assignee_ubd', 'assignee.id', '=', 'assignee_ubd.user_id')
                ->where('tasks.id', $id)
                ->first();

            if (!$task) {
                return redirect()->route('task.assigned-by-me')
                    ->with('error', 'Task not found.');
            }
            $task->members = DB::table('task_assigns')
                ->leftJoin('users', 'task_assigns.assigned_to', '=', 'users.id')
                ->leftJoin('user_basic_details', 'users.id', '=', 'user_basic_details.user_id')
                ->where('task_assigns.task_id', $task->id)
                ->select(
                    'users.id as user_id',
                    'users.name',
                    'users.email',
                    'users.employee_id',
                    'user_basic_details.profile_image',
                    'task_assigns.member_role',
                    'task_assigns.individual_status',
                    'task_assigns.individual_remarks',
                    'task_assigns.started_at',
                    'task_assigns.completed_at'
                )->get();

            // The main $task query above joins task_assigns unscoped, so for
            // a group task its assigned_to_id/name reflect an arbitrary
            // member row (whichever MySQL returns first), not necessarily
            // the current user. Resolve the current user's own membership
            // explicitly from $task->members (which has every row) instead
            // of trusting the joined single-row fields for "is this task
            // mine to update" checks.
            $myAssignment = $task->members->firstWhere('user_id', $authUser->id);

            // Get all task updates with profile images
            $updates = TaskUpdate::select([
                'task_updates.id',
                'task_updates.status',
                'task_updates.remarks',
                'users.name as updated_by_name',
                'users.email as updated_by_email',
                'user_basic_details.profile_image as updated_by_image',
                'task_updates.created_at as date',
            ])
                ->leftJoin('users', 'task_updates.updated_by', '=', 'users.id')
                ->leftJoin('user_basic_details', 'users.id', '=', 'user_basic_details.user_id')
                ->where('task_updates.task_id', $id)
                ->orderBy('task_updates.created_at', 'desc')
                ->get();

            // Get all approvals for this task with profile images
            $approvals = TaskApproval::select([
                'task_approvals.id',
                'task_approvals.status as approval_status',
                'task_approvals.remarks',
                'task_approvals.created_at as approval_date',
                'requestor.name as requested_by_name',
                'requestor.email as requested_by_email',
                'requestor_ubd.profile_image as requested_by_image',
                'approver.name as approved_by_name',
                'approver.email as approved_by_email',
                'approver_ubd.profile_image as approved_by_image'
            ])
                ->leftJoin('users as requestor', 'task_approvals.completed_by', '=', 'requestor.id')
                ->leftJoin('users as approver', 'task_approvals.approved_by', '=', 'approver.id')
                ->leftJoin('user_basic_details as requestor_ubd', 'requestor.id', '=', 'requestor_ubd.user_id')
                ->leftJoin('user_basic_details as approver_ubd', 'approver.id', '=', 'approver_ubd.user_id')
                ->where('task_approvals.task_id', $id)
                ->orderBy('task_approvals.created_at', 'desc')
                // A group task now gets one approval record per completing
                // member (see TaskController::TaskApproval) — show the full
                // history, not just an arbitrary/latest single record.
                ->get();

            // Calculate days remaining/overdue — calendar-date comparison
            // (using Carbon::now() here would make a task due "today" look
            // overdue/0-days-remaining the moment any time passed today).
            $deadlineDate = $task->deadline_date ? \Carbon\Carbon::parse($task->deadline_date)->startOfDay() : null;
            $currentDate = \Carbon\Carbon::today();

            if ($deadlineDate) {
                $daysRemaining = $currentDate->diffInDays($deadlineDate, false);
                $isOverdue = $currentDate->gt($deadlineDate) && !in_array($task->status, ['completed', 'approved', 'rejected']);
            } else {
                $daysRemaining = null;
                $isOverdue = false;
            }

            // Format dates for display
            $formattedTaskDate = $task->task_date ? \Carbon\Carbon::parse($task->task_date)->format('d M, Y') : 'Not set';
            $formattedDeadlineDate = $task->deadline_date ? \Carbon\Carbon::parse($task->deadline_date)->format('d M, Y') : 'Not set';

            // Get default profile image path
            $defaultProfileImage = asset('assets/images/avatar/1.png');

            $comments = TaskComment::with('user')->where('task_id', $id)->orderBy('created_at', 'desc')->get();
            $attachments = TaskAttachment::with('uploadedBy')->where('task_id', $id)->orderBy('created_at', 'desc')->get();

            return view('client.task.view-task-detail', compact(
                'task',
                'updates',
                'approvals',
                'daysRemaining',
                'isOverdue',
                'formattedTaskDate',
                'formattedDeadlineDate',
                'defaultProfileImage',
                'comments',
                'attachments',
                'myAssignment'
            ));
        } catch (Exception $e) {
            Log::error('Task detail error: ' . $e->getMessage());
            return redirect()->back()
                ->with('error', 'An error occurred. Please try again later.' . $e->getMessage());
        }
    }

    public function addComment(Request $request, $taskId)
    {
        $authUser = Auth::user();

        if (!Task::where('id', $taskId)->exists()) {
            return response()->json(['success' => false, 'message' => 'Task not found.'], 404);
        }

        if (!$this->permissions->canAccessTask($authUser, $taskId)) {
            return response()->json(['success' => false, 'message' => 'You do not have access to this task.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'comment' => 'required|string|max:2000',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $comment = TaskComment::create([
            'task_id' => $taskId,
            'user_id' => $authUser->id,
            'comment' => $request->comment,
        ]);

        $comment->load('user');

        return response()->json([
            'success' => true,
            'message' => 'Comment added.',
            'data' => [
                'id' => $comment->id,
                'comment' => $comment->comment,
                'user_name' => $comment->user->name ?? 'Unknown',
                'created_at' => $comment->created_at->format('d M Y, h:i A'),
                'created_at_human' => $comment->created_at->diffForHumans(),
                'can_delete' => true,
            ],
        ]);
    }

    public function deleteComment($id)
    {
        $authUser = Auth::user();
        $comment = TaskComment::find($id);

        if (!$comment) {
            return response()->json(['success' => false, 'message' => 'Comment not found.'], 404);
        }

        // Comment author, or admin/hr, may delete.
        if (!$this->permissions->canManageOwnedResource($authUser, $comment->user_id)) {
            return response()->json(['success' => false, 'message' => 'You cannot delete this comment.'], 403);
        }

        $comment->delete();

        return response()->json(['success' => true, 'message' => 'Comment deleted.']);
    }

    public function uploadAttachment(Request $request, $taskId)
    {
        $authUser = Auth::user();

        if (!Task::where('id', $taskId)->exists()) {
            return response()->json(['success' => false, 'message' => 'Task not found.'], 404);
        }

        if (!$this->permissions->canAccessTask($authUser, $taskId)) {
            return response()->json(['success' => false, 'message' => 'You do not have access to this task.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'files'   => 'required|array|min:1|max:5',
            'files.*' => 'file|mimes:jpg,jpeg,png,pdf,doc,docx,xls,xlsx|max:10240',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            // All-or-nothing: if one file fails, the ones already stored are removed.
            $storedFiles = file_storage()->uploadMany($request->file('files'), 'task_attachment');
        } catch (\App\Exceptions\FileStorageException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], $e->httpStatus());
        }

        $created = [];
        foreach ($storedFiles as $stored) {
            $attachment = TaskAttachment::create([
                'task_id' => $taskId,
                'uploaded_by' => $authUser->id,
                'file_path' => $stored->path,
                'file_name' => $stored->originalName,
                'file_type' => $stored->extension,
                'file_size' => $stored->size,
                'created_at' => now(),
            ]);
            $attachment->load('uploadedBy');
            $created[] = [
                'id' => $attachment->id,
                'file_name' => $attachment->file_name,
                'file_url' => $attachment->file_url,
                'file_type' => $attachment->file_type,
                'formatted_size' => $attachment->formatted_size,
                'uploaded_by_name' => $attachment->uploadedBy->name ?? 'Unknown',
            ];
        }

        return response()->json([
            'success' => true,
            'message' => count($created) . ' file(s) uploaded.',
            'data' => $created,
        ]);
    }

    public function deleteAttachment($id)
    {
        $authUser = Auth::user();
        $attachment = TaskAttachment::find($id);

        if (!$attachment) {
            return response()->json(['success' => false, 'message' => 'Attachment not found.'], 404);
        }

        if (!$this->permissions->canManageOwnedResource($authUser, $attachment->uploaded_by)) {
            return response()->json(['success' => false, 'message' => 'You cannot delete this attachment.'], 403);
        }

        file_storage()->delete($attachment->file_path, 'task_attachment');

        $attachment->delete();

        return response()->json(['success' => true, 'message' => 'Attachment deleted.']);
    }
}
