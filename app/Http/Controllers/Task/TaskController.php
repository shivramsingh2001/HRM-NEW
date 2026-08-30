<?php

namespace App\Http\Controllers\Task;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskAssign;
use App\Models\TaskUpdate;
use App\Models\TaskApproval;
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

    public function __construct(TaskNotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }
    public function tasksAssignedByMe(Request $request)
    {
        try {
            $authUser = Auth::user();

            if (!in_array($authUser->role, ['manager', 'admin', 'hr'])) {
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
                        in_array($authUser->role, ['admin', 'hr'])
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

            if (in_array($authUser->role, ['admin', 'hr'])) {
                $users = $usersQuery->orderBy('users.name')->get();
            } elseif ($authUser->role == 'manager') {
                $ids = UserJobDetail::where('reporting_head', $authUser->id)->pluck('user_id')->toArray();
                $ids[] = $authUser->id;
                $users = $usersQuery->whereIn('users.id', $ids)->orderBy('users.name')->get();
            } else {
                $users = $usersQuery->where('users.id', $authUser->id)->get();
            }

            $assigners = [];
            if (in_array($authUser->role, ['admin', 'hr'])) {
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
                ->join('task_assigns', 'tasks.id', '=', 'task_assigns.task_id')
                ->leftJoin('users', 'task_assigns.assigned_by', '=', 'users.id')
                ->leftJoin('user_basic_details', 'task_assigns.assigned_by', '=', 'user_basic_details.user_id')
                ->leftJoin('projects', 'tasks.project_id', '=', 'projects.id')
                ->whereExists(function ($q) use ($authUser) {
                    $q->select(DB::raw(1))->from('task_assigns')
                        ->whereColumn('task_assigns.task_id', 'tasks.id')
                        ->where('task_assigns.assigned_to', $authUser->id);
                });

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

            return view('client.task.view-assigned-to-task', compact(
                'tasks',
                'projects',
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
            return redirect()->back()
                ->with('error', 'An error occurred. Please try again later.');
        }
    }

    public function create(Request $request)
    {
        $authUser = Auth::user();

        // if (!in_array($authUser->role, ['manager', 'admin'])) {
        //     return redirect()->back()->with('error', 'Unauthorized access. Only managers or admins can view assigned tasks.');
        // }

        $userId = $authUser->id;

        $query = Project::whereIn('projects.status', ['ongoing', 'pending']);

        // If user is NOT admin, filter projects
        if ($authUser->role !== 'admin') {
            $query->leftJoin('project_assigns', function ($join) use ($userId) {
                $join->on('projects.id', '=', 'project_assigns.project_id')
                    ->where('project_assigns.user_id', $userId)
                    ->where('project_assigns.status', 1);
            })
                ->where(function ($q) use ($userId) {
                    $q->where('projects.project_head', $userId)  // User is project head
                        ->orWhereNotNull('project_assigns.id');    // User is team member
                });
        }

        $data['projects'] = $query->select('projects.*')->distinct()->get();

        if ($authUser->role == 'admin') {
            // Admin can see all active users
            $data['users'] = User::where('role', "!=", "admin")->where('status', 1)->get();
        } else {
            // Show only users who report to this auth user
            $data['users'] = User::select('users.*')
                ->join('user_job_details', 'users.id', '=', 'user_job_details.user_id')
                ->where('users.status', 1)
                ->where('user_job_details.reporting_head', $authUser->id)
                ->get();
        }

        return view('client.task.create-task', $data);
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
            return redirect()->back()->withErrors($validator)->withInput();
        }

        DB::beginTransaction();
        try {
            $taskCode = $this->generateUniqueTaskCode();

            // File uploads (unchanged from your code)
            $filePath = null;
            if ($request->hasFile('file')) {
                $file = $request->file('file');
                $filename = time() . '_' . uniqid() . '.' . strtolower($file->getClientOriginalExtension());
                $dest = public_path('uploads/task/document');
                if (!file_exists($dest)) mkdir($dest, 0755, true);
                $file->move($dest, $filename);
                $filePath = 'uploads/task/document/' . $filename;
            }

            $voiceFilePath = null;
            if ($request->hasFile('voice_file')) {
                $voiceFile = $request->file('voice_file');
                $voiceFileName = time() . '_' . uniqid() . '.' . strtolower($voiceFile->getClientOriginalExtension());
                $dest = public_path('uploads/task/voice');
                if (!file_exists($dest)) mkdir($dest, 0755, true);
                $voiceFile->move($dest, $voiceFileName);
                $voiceFilePath = 'uploads/task/voice/' . $voiceFileName;
            }

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

            // Notify everyone (loop so groups get notified too)
            $assigneeUser = User::find($assignedBy);
            foreach ($memberIds as $uid) {
                try {
                    $assignedUser = User::find($uid);
                    if ($assignedUser && $this->notificationService) {
                        $this->notificationService->notifyTaskAssigned($task, $assignedUser, $assigneeUser);
                    }
                    $this->sendTaskEmails($task, $authUser, $assignedUser);
                } catch (Exception $e) {
                    Log::error('Group notify/email failed for user ' . $uid . ': ' . $e->getMessage());
                }
            }

            $msg = $isGroup
                ? 'Group task created and assigned to ' . count($memberIds) . ' members.'
                : 'Task created and assigned successfully!';

            return redirect()
                ->route($isSelf ? 'task.assigned-to-me' : 'task.assigned-by-me')
                ->with('success', $msg);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Task store error: ' . $e->getMessage());
            return redirect()->back()
                ->with('error', 'An error occurred. ' . $e->getMessage())
                ->withInput();
        }
    }

    private function sendTaskEmails($task, $creator, $assignee)
    {
        $emailsSent = [];
        $emailErrors = [];
        // Email to assignee
        try {
            Log::info('Attempting to send email to assignee: ' . $assignee->email);

            // First try send() to test immediately
            Mail::to($assignee->email)->send(new TaskAssignedMail(
                $task,
                $assignee,
                $creator,
                'assigned'
            ));

            $emailsSent[] = $assignee->email;
            Log::info('✅ Email sent to assignee: ' . $assignee->email);
        } catch (Exception $e) {
            $emailErrors[] = "Failed to send email to {$assignee->name} ({$assignee->email})";
            Log::error('❌ Email to assignee failed: ' . $e->getMessage());
        }

        // Email to creator
        try {
            Log::info('Attempting to send email to creator: ' . $creator->email);

            Mail::to($creator->email)->send(new TaskAssignedMail(
                $task,
                $creator,
                $creator,
                'created'
            ));

            $emailsSent[] = $creator->email;
            Log::info('✅ Email sent to creator: ' . $creator->email);
        } catch (Exception $e) {
            $emailErrors[] = "Failed to send email to {$creator->name} ({$creator->email})";
            Log::error('❌ Email to creator failed: ' . $e->getMessage());
        }

        // Prepare result message
        $result = [
            'success_count' => count($emailsSent),
            'failed_count' => count($emailErrors),
            'success_emails' => $emailsSent,
            'failed_emails' => $emailErrors,
            'message' => $this->generateEmailResultMessage(count($emailsSent), count($emailErrors))
        ];

        Log::info('Email sending result: ' . $result['message']);

        return $result;
    }

    private function generateEmailResultMessage($successCount, $failedCount)
    {
        $total = $successCount + $failedCount;

        if ($total === 0) {
            return "No emails were sent.";
        }

        if ($successCount === $total) {
            return "✅ All {$successCount} email(s) sent successfully!";
        }

        if ($failedCount === $total) {
            return "❌ Failed to send all {$failedCount} email(s).";
        }

        return "⚠️ Sent {$successCount} email(s) successfully, but failed to send {$failedCount} email(s).";
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
            $isAssignedBy = TaskAssign::where('task_id', $id)
                ->where('assigned_by', $authUser->id)
                ->first();

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
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while deleting the task. Please try again later.'
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
                'status' => 'required|in:pending,in_progress,completed,approved,rejected',
                'remarks' => 'nullable|string'
            ]);

            // Get only tasks where the auth user is assigned
            $assignedTaskIds = TaskAssign::whereIn('task_id', $request->task_ids)
                ->where('assigned_to', $authUser->id)
                ->pluck('task_id')
                ->toArray();

            if (empty($assignedTaskIds)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tasks found that are assigned to you'
                ], 400);
            }

            // Update only tasks assigned to the auth user
            Task::whereIn('id', $assignedTaskIds)
                ->where('status', 'pending')
                ->update(['status' => $request->status]);

            return response()->json([
                'success' => true,
                'message' => count($assignedTaskIds) . ' tasks updated successfully',
            ]);
        } catch (Exception $e) {
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

            $oldStatus = $task->status;
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

            // Notify assigner
            try {
                if ($this->notificationService) {
                    $this->notificationService->notifyTaskStatusUpdate(
                        $task,
                        $authUser,
                        $oldStatus,
                        $task->status,
                        $request->remarks
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

            $taskAssign = TaskAssign::where('task_id', $request->task_id)
                ->where('assigned_by', $authUser->id)
                ->first();

            if (!$taskAssign) {
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

            $oldStatus = $task->status;
            DB::beginTransaction();

            // Create task approval
            TaskApproval::create([
                'task_id' => $request->task_id,
                'approved_by' => $authUser->id,
                'completed_by' => $taskAssign->assigned_to,
                'status' => $request->status,
                'remarks' => $request->remarks,
            ]);

            // Update main task
            $task->status = $request->status;
            $task->save();

            $newTask = null;
            $newTaskCode = null;

            if ($request->status == 'rejected') {
                // Generate new unique task code
                $newTaskCode = $this->generateUniqueTaskCode();

                // Create new task with same details
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
                ]);

                // Create new assignment for the same user
                TaskAssign::create([
                    'task_id' => $newTask->id,
                    'assigned_by' => $taskAssign->assigned_by,
                    'assigned_to' => $taskAssign->assigned_to,
                    'status' => 'assigned',
                ]);
            }

            DB::commit();

            // Send notification
            try {
                $assignedUser = User::find($taskAssign->assigned_to);
                if ($assignedUser && $this->notificationService) {

                    $this->notificationService->notifyTaskStatusUpdate(
                        $task,
                        $authUser,
                        $oldStatus,
                        $request->status,
                        $request->remarks
                    );
                }
            } catch (Exception $e) {
                Log::error('Failed to send task notification: ' . $e->getMessage());
            }

            $message = $request->status == 'approved'
                ? 'Task approved successfully'
                : 'Task rejected and new task created successfully. The employee has been reassigned the task.';



            return response()->json([
                'success' => true,
                'message' => $message,
            ], 200);
        } catch (Exception $e) {
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

            $isAdminOrHR = in_array($authUser->role, ['admin', 'hr']);

            // For non-admin/non-HR users, check access
            if (!$isAdminOrHR) {
                $hasAccess = TaskAssign::where('task_id', $id)
                    ->where(function ($q) use ($authUser) {
                        $q->where('assigned_to', $authUser->id)
                            ->orWhere('assigned_by', $authUser->id);
                    })->exists();
                if (!$hasAccess) {
                    return redirect()->route('task.assigned-by-me')
                        ->with('error', 'You do not have access to this task.');
                }
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
                ->first();

            // Calculate days remaining/overdue
            $deadlineDate = $task->deadline_date ? \Carbon\Carbon::parse($task->deadline_date) : null;
            $currentDate = \Carbon\Carbon::now();

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

            return view('client.task.view-task-detail', compact(
                'task',
                'updates',
                'approvals',
                'daysRemaining',
                'isOverdue',
                'formattedTaskDate',
                'formattedDeadlineDate',
                'defaultProfileImage'
            ));
        } catch (Exception $e) {
            Log::error('Task detail error: ' . $e->getMessage());
            return redirect()->back()
                ->with('error', 'An error occurred. Please try again later.' . $e->getMessage());
        }
    }
}
