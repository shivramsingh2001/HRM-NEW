<?php

namespace App\Http\Controllers\Api\Task;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\TaskAssign;
use App\Models\TaskUpdate;
use App\Models\TaskApproval;
use App\Models\UserJobDetail;
use App\Models\Project;
use App\Models\User;
use App\Services\TaskNotificationService;
use App\Services\TaskPermissionService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class TaskController extends Controller
{
    protected $notificationService;

    public function __construct(TaskNotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }
    public function fetch_user(Request $request)
    {
        try {
            $authUser = Auth::user();
            $baseUrl = config('app.url');

            $query = User::where('status', 1)
                ->with([
                    'basicDetails',
                    'jobDetails.designationRel',
                    'jobDetails.departmentRel',
                    'jobDetails.reportingHead',
                ]);

            // Role-based filtering
            switch ($authUser->role) {
                case 'admin':
                case 'hr':
                    $query->whereNotIn('role', ['admin']);
                    break;

                case 'manager':
                    $query->where(function ($q) use ($authUser) {
                        $q->managedBy($authUser->id)
                            ->orWhere('users.id', $authUser->id);
                    });
                    break;

                case 'employee':
                    $query->where('users.id', $authUser->id);
                    break;

                default:
                    return response()->json([
                        'success' => false,
                        'message' => 'Unauthorized access. Invalid role.'
                    ], 200);
            }

            $teamMembers = $query->get();

            $teamData = [];

            foreach ($teamMembers as $member) {
                $teamData[] = [
                    // Basic Information
                    'id' => $member->id,
                    'employee_id' => $member->employee_id,
                    'name' => $member->name,
                    'email' => $member->email,
                    'contact' => $member->contact,
                    'profile_image' => $member->basicDetails?->profile_image
                        ? $baseUrl . $member->basicDetails->profile_image
                        : $baseUrl . '/profile2.jpg',

                ];
            }
            return response()->json([
                'success' => true,
                'message' => 'Team data fetched successfully',
                'data' => $teamData,
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred. Please try again later.'
            ], 500);
        }
    }

    public function tasksAssignedByMe(Request $request)
    {
        try {
            $authUser = Auth::user();

            // if (!in_array($authUser->role, ['manager', 'admin', 'hr'])) {
            //     return response()->json([
            //         'success' => false,
            //         'message' => 'Unauthorized access. Only managers or admins can view assigned tasks.'
            //     ], 200);
            // }

            $query = Task::with(['project', 'assignments.assignedTo'])
                ->whereHas('assignments', function ($q) use ($authUser, $request) {
                    $q->where('assigned_by', $authUser->id);

                    // ADDED: Filter by assigned_to
                    if ($request->filled('assigned_to') && $request->assigned_to != 'all') {
                        $q->where('assigned_to', $request->assigned_to);
                    }
                });

            // ADDED: Filter by project_id
            if ($request->filled('project_id') && $request->project_id != 'all') {
                $query->where('project_id', $request->project_id);
            }

            // ADDED: Filter by date range
            if ($request->filled('date_from')) {
                $query->whereDate('deadline_date', '>=', $request->date_from);
            }
            if ($request->filled('date_to')) {
                $query->whereDate('deadline_date', '<=', $request->date_to);
            }

            // Apply filters
            if ($request->has('status') && $request->status != 'all') {
                $query->where('status', $request->status);
            }

            if ($request->has('priority') && $request->priority != 'all') {
                $query->where('priority', $request->priority);
            }

            // ADDED: Sorting options
            $sortBy = $request->get('sort_by', 'priority_desc');
            switch ($sortBy) {
                case 'priority_asc':
                    $query->orderByRaw("FIELD(priority, 'low', 'medium', 'high', 'critical')");
                    break;
                case 'deadline_asc':
                    $query->orderBy('deadline_date', 'asc');
                    break;
                case 'deadline_desc':
                    $query->orderBy('deadline_date', 'desc');
                    break;
                case 'created_desc':
                    $query->orderBy('task_date', 'desc');
                    break;
                case 'created_asc':
                    $query->orderBy('task_date', 'asc');
                    break;
                default:
                    $query->orderByRaw("FIELD(priority, 'critical', 'high', 'medium', 'low')");
            }

            $tasks = $query->paginate($request->get('per_page', 15));

            // ADDED: Get all members for tasks (instead of just first assignee)
            $allMembers = DB::table('task_assigns')
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

            // ADDED: Stats calculation
            $statsQuery = Task::whereHas('assignments', function ($q) use ($authUser) {
                $q->where('assigned_by', $authUser->id);
            });

            if ($request->filled('project_id') && $request->project_id != 'all') {
                $statsQuery->where('project_id', $request->project_id);
            }
            if ($request->filled('date_from')) {
                $statsQuery->whereDate('deadline_date', '>=', $request->date_from);
            }
            if ($request->filled('date_to')) {
                $statsQuery->whereDate('deadline_date', '<=', $request->date_to);
            }

            // $totalTasks = (clone $statsQuery)->count();
            // $pendingTasks = (clone $statsQuery)->where('status', 'pending')->count();
            // $inProgressTasks = (clone $statsQuery)->where('status', 'in_progress')->count();
            // $completedTasks = (clone $statsQuery)->where('status', 'completed')->count();
            // $approvedTasks = (clone $statsQuery)->where('status', 'approved')->count();
            // $rejectTasks = (clone $statsQuery)->where('status', 'rejected')->count();
            // $cancelledTasks = (clone $statsQuery)->where('status', 'cancelled')->count();

            $formattedTasks = $tasks->map(function ($task) use ($authUser, $allMembers) {
                $assignee = $task->assignments->first()?->assignedTo;
                $taskMembers = $allMembers->get($task->id, collect());
                $firstMember = $taskMembers->first();

                // Format members data (ADDED)
                $membersData = $taskMembers->map(function ($member) {
                    return [
                        'user_id' => $member->user_id,
                        'name' => $member->name,
                        'email' => $member->email,
                        'employee_id' => $member->employee_id,
                        'profile_image' => $member->profile_image,
                        'member_role' => $member->member_role,
                        'individual_status' => $member->individual_status,
                        'assigned_by' => $member->assigned_by,
                    ];
                });

                return [
                    // OLD KEYS (kept exactly as is)
                    'id' => $task->id,
                    'task_code' => $task->task_code,
                    'title' => $task->title,
                    'priority' => $task->priority,
                    'status' => $task->status,
                    'task_date' => $task->task_date,
                    'deadline_date' => $task->deadline_date,
                    'project_name' => $task->project->name ?? null,
                    'project_code' => $task->project->project_code ?? null,
                    'assigned_to' => $assignee->name ?? null,
                    'assigned_by' => $authUser->name ?? "NA",

                    // NEW KEYS ADDED (without removing any old keys)
                    // 'description' => $task->description,
                    // 'created_at' => $task->created_at,
                    // 'file' => $task->file,
                    // 'voice_file' => $task->voice_file,
                    'task_mode' => $task->task_mode,
                    // 'group_completion_rule' => $task->group_completion_rule,
                    // 'group_lead_id' => $task->group_lead_id,
                    // 'project_id' => $task->project_id,
                    // 'days_remaining' => $task->deadline_date ? now()->diffInDays($task->deadline_date, false) : null,
                    // 'member_count' => $taskMembers->count(),
                    // 'members' => $membersData,
                    // 'assigned_to_name' => $firstMember->name ?? null,
                    // 'assigned_to_email' => $firstMember->email ?? null,
                    // 'assigned_to_employee_id' => $firstMember->employee_id ?? null,
                    // 'assigned_to_id' => $firstMember->user_id ?? null,
                    // 'assigned_to_image' => $firstMember->profile_image ?? null,
                    // 'assigned_by_name' => $authUser->name ?? null,
                    // 'assigned_by_id' => $authUser->id ?? null,
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Tasks assigned by you fetched successfully',
                'data' => $formattedTasks,
                // ADDED: Stats in response
                // 'stats' => [
                //     'total' => $totalTasks,
                //     'pending' => $pendingTasks,
                //     'in_progress' => $inProgressTasks,
                //     'completed' => $completedTasks,
                //     'approved' => $approvedTasks,
                //     'rejected' => $rejectTasks,
                //     'cancelled' => $cancelledTasks,
                // ],
                'pagination' => [
                    'current_page' => $tasks->currentPage(),
                    'last_page' => $tasks->lastPage(),
                    'per_page' => $tasks->perPage(),
                    'total' => $tasks->total(),
                    'first_page_url' => $tasks->url(1),
                    'last_page_url' => $tasks->url($tasks->lastPage()),
                    'prev_page_url' => $tasks->previousPageUrl(),
                    'next_page_url' => $tasks->nextPageUrl(),
                ]
            ], 200);
        } catch (Exception $e) {
            Log::error('tasksAssignedByMe API error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'An error occurred. Please try again later.',
            ], 500);
        }
    }
    public function tasksAssignedToMe(Request $request)
    {
        try {
            $authUser = Auth::user();

            $tasks = Task::with(['project', 'assignments.assignedBy'])
                ->whereHas('assignments', function ($q) use ($authUser) {
                    $q->where('assigned_to', $authUser->id);
                })
                ->orderByRaw("FIELD(priority, 'critical', 'high', 'medium', 'low')")
                ->orderBy('deadline_date', 'asc')
                ->paginate($request->get('per_page', 15));

            $formattedTasks = $tasks->map(function ($task) use ($authUser) {
                $assigner = $task->assignments->first()?->assignedBy;

                return [
                    'id' => $task->id,
                    'task_code' => $task->task_code,
                    'task_mode' => $task->task_mode,
                    'title' => $task->title,
                    'priority' => $task->priority,
                    'status' => $task->status,
                    'task_date' => $task->task_date,
                    'deadline_date' => $task->deadline_date,
                    'project_name' => $task->project->name ?? null,
                    'project_code' => $task->project->project_code ?? null,
                    'assigned_by' => $assigner->name ?? null,
                    'assigned_to' => $authUser->name ?? "NA",
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Tasks assigned to you fetched successfully',
                'data' => $formattedTasks,
                'pagination' => [
                    'current_page' => $tasks->currentPage(),
                    'last_page' => $tasks->lastPage(),
                    'per_page' => $tasks->perPage(),
                    'total' => $tasks->total(),
                    'first_page_url' => $tasks->url(1),
                    'last_page_url' => $tasks->url($tasks->lastPage()),
                    'prev_page_url' => $tasks->previousPageUrl(),
                    'next_page_url' => $tasks->nextPageUrl(),
                ]
            ]);
        } catch (Exception $e) {

            return response()->json([
                'success' => false,
                'message' => 'An error occurred. Please try again later.'
            ], 500);
        }
    }
    public function store(Request $request)
    {

        $authUser = Auth::user();

        // Only managers or admins can create tasks
        // if (!in_array($authUser->role, ['manager', 'admin'])) {
        //     return response()->json([
        //         'success' => false,
        //         'message' => 'Unauthorized access. Only managers or admins can create tasks.'
        //     ], 200);
        // }
       

        // Validation rules
        $rules = [
            'title' => 'required|string|max:255',
            'project_id' => 'nullable|exists:projects,id',
            'description' => 'required|string',
            'deadline_date' => 'nullable|date|after_or_equal:today',
            'task_date' => 'required|date|after_or_equal:today',
            'priority' => 'required|in:low,medium,high,critical',
            'assigned_to' => 'nullable|exists:users,id',
            'file' => 'nullable|file|mimes:jpg,jpeg,png,pdf,doc,docx,xls,xlsx|max:5120',
            'voice_file' => 'nullable|file|max:10240',
            'self_assigned' => 'required|in:0,1,2',
        ];

        // Conditional validation for assigned_to
        if ($request->self_assigned == 0) {
            $rules['assigned_to'] = 'required|exists:users,id';
        } elseif ($request->self_assigned == 2) {
            $rules['group_members'] = 'required|array|min:2';
            $rules['group_members.*'] = 'exists:users,id|distinct';
            $rules['group_completion_rule'] = 'required|in:all_must_complete,any_one,percentage,lead_decides';
            $rules['completion_threshold'] = 'required_if:group_completion_rule,percentage|nullable|integer|min:1|max:100';
            $rules['group_lead_id'] = 'required_if:group_completion_rule,lead_decides|exists:users,id';
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ], 200);
        }

        DB::beginTransaction();
        try {
            // Generate unique task code
            $taskCode = $this->generateUniqueTaskCode();

            // Handle file upload
            $filePath = null;
            if ($request->hasFile('file')) {
                $file = $request->file('file');
                $extension = strtolower($file->getClientOriginalExtension());
                $filename = time() . '_' . uniqid() . '.' . $extension;
                $destinationPath = public_path('uploads/task/document');
                if (!file_exists($destinationPath)) {
                    mkdir($destinationPath, 0755, true);
                }
                $file->move($destinationPath, $filename);
                $filePath = 'uploads/task/document/' . $filename;
            }

            // Handle voice file upload
            $voiceFilePath = null;
            if ($request->hasFile('voice_file')) {
                $voiceFile = $request->file('voice_file');
                $extension = strtolower($voiceFile->getClientOriginalExtension());
                $voiceFileName = time() . '_' . uniqid() . '.' . $extension;
                $destinationPath = public_path('uploads/task/voice');
                if (!file_exists($destinationPath)) {
                    mkdir($destinationPath, 0755, true);
                }
                $voiceFile->move($destinationPath, $voiceFileName);
                $voiceFilePath = 'uploads/task/voice/' . $voiceFileName;
            }

            $isGroup = $request->self_assigned == 2;
            $isSelf = $request->self_assigned == 1;
            $assignedBy = $authUser->id;

            if ($isSelf) {
                $job = UserJobDetail::where('user_id', $authUser->id)->first();
                if ($job && $job->reporting_head) {
                    $assignedBy = $job->reporting_head;
                }
            }

            // Create task
            $task = Task::create([
                'task_code' => $taskCode,
                'title' => $request->title,
                'project_id' => $request->project_id,
                'description' => $request->description,
                'task_date' => $request->task_date ?? date('Y-m-d'),
                'deadline_date' => $request->deadline_date,
                'original_deadline_date' => $request->deadline_date,
                'extension_count' => 0,
                'priority' => $request->priority,
                'status' => 'pending',
                'file' => $filePath,
                'voice_file' => $voiceFilePath,
                'task_mode' => $isGroup ? 'group' : 'individual',
                'group_completion_rule' => $isGroup ? $request->group_completion_rule : null,
                'completion_threshold' => $isGroup && $request->group_completion_rule === 'percentage'
                    ? $request->completion_threshold : null,
                'group_lead_id' => $isGroup ? $request->group_lead_id : null,
            ]);

            if ($isGroup) {
                $memberIds = array_unique($request->group_members);
                if ($request->group_lead_id && !in_array($request->group_lead_id, $memberIds)) {
                    $memberIds[] = $request->group_lead_id;
                }
            } elseif ($isSelf) {
                $memberIds = [$authUser->id];
            } else {
                $memberIds = [$request->assigned_to];
            }
            // Create task assignment
            foreach ($memberIds as $uid) {
                TaskAssign::create([
                    'task_id' => $task->id,
                    'assigned_by' => $assignedBy,
                    'assigned_to' => $uid,
                    'status' => 'assigned',
                    'member_role' => $isGroup && $uid == $request->group_lead_id ? 'lead' : 'contributor',
                    'individual_status' => 'pending',
                ]);
            }
            DB::commit();

            $assigneeUser = User::find($assignedBy);
            foreach ($memberIds as $uid) {
                try {
                    $assignedUser = User::find($uid);
                    if ($assignedUser && $this->notificationService) {
                        $this->notificationService->notifyTaskAssigned($task, $assignedUser, $assigneeUser);
                    }
                } catch (Exception $e) {
                    Log::error('Group notify failed for user ' . $uid . ': ' . $e->getMessage());
                }
            }
            $message = $isGroup
                ? 'Group task created and assigned to ' . count($memberIds) . ' members.'
                : 'Task created successfully!';

            return response()->json([
                'success' => true,
                'message' => $message,
            ], 200);
        } catch (Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'An error occurred. Please try again later. ',
            ], 500);
        }
    }

    private function generateUniqueTaskCode()
    {
        return DB::transaction(function () {
            $lastTask = Task::where('task_code', 'like', 'TASK-%')
                ->lockForUpdate()
                ->orderByRaw('CAST(SUBSTRING(task_code, 6) AS UNSIGNED) DESC')
                ->first();

            if (!$lastTask) {
                $nextNumber = 1;
            } else {
                $lastNumber = (int) substr($lastTask->task_code, 5);
                $nextNumber = $lastNumber + 1;
                if ($nextNumber > 99999) {
                    $nextNumber = 1;
                }
            }

            return 'TASK-' . str_pad($nextNumber, 5, '0', STR_PAD_LEFT);
        });
    }

    public function taskUpdate(Request $request)
    {
        try {
            $authUser = Auth::user();
            $input = $request->all();
            if ($request->isJson() && empty($input)) {
                $input = $request->json()->all();
            }
            $validator = Validator::make($input, [
                'id' => 'required|exists:tasks,id',
                'status' => 'required|string|in:pending,in_progress,hold,completed,cancelled',
                'remarks' => 'required|string|max:1000',
                'extend_deadline' => 'required|boolean',
                'new_deadline' => 'required_if:extend_deadline,true',
                'extension_reason' => 'nullable|string|max:500'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => $validator->errors()->first()
                ], 200);
            }

            DB::beginTransaction();

            $taskId = $input['id'];
            $task = Task::findOrFail($taskId);

            // Check if user is assigned to this task
            $assign = TaskAssign::where('task_id', $taskId)
                ->where('assigned_to', $authUser->id)
                ->first();

            if (!$assign) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not assigned to this task. Only assigned users can update tasks.'
                ], 200);
            }

            // Prevent updating if task is in final state
            if (in_array($task->status, ['completed', 'approved', 'rejected', 'cancelled'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot update a ' . $task->status . ' task. Please contact admin if needed.'
                ], 200);
            }

            $oldStatus = $task->status;
            $oldDeadline = $task->deadline_date;
            $deadlineExtended = false;

            // Check if task is being marked as completed
            $isCompleting = $input['status'] == 'completed';

            // Check if deadline is overdue
            $isOverdue = Carbon::parse($task->deadline_date)->lt(Carbon::now());

            // Handle deadline extension ONLY if extend_deadline is true
            $extendDeadline = isset($input['extend_deadline']) && filter_var($input['extend_deadline'], FILTER_VALIDATE_BOOLEAN);

            if ($extendDeadline) {
                // // Check extension limit (max 3 extensions)
                // if ($task->extension_count >= 3) {
                //     return response()->json([
                //         'success' => false,
                //         'message' => 'Maximum deadline extensions reached (3). Task cannot be extended further.'
                //     ], 200);
                // }

                // Validate new_deadline is provided
                if (!isset($input['new_deadline']) || empty($input['new_deadline'])) {
                    return response()->json([
                        'success' => false,
                        'message' => 'New deadline is required when extending deadline.'
                    ], 200);
                }

                $newDeadline = $input['new_deadline'];

                // Save original deadline if not already set
                if (!$task->original_deadline_date) {
                    $task->original_deadline_date = $task->deadline_date;
                }

                // Update task deadline
                $task->deadline_date = $newDeadline;
                $task->extension_count = $task->extension_count + 1;
                $deadlineExtended = true;
            }

            // Create task update record
            $updateData = [
                'tenant_id' => $task->tenant_id ?? 1,
                'task_id' => $taskId,
                'updated_by' => $authUser->id,
                'status' => $input['status'],
                'remarks' => $input['remarks'],
                'deadline_extension' => $deadlineExtended ? 1 : 0,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            // Add deadline info if extended
            if ($deadlineExtended) {
                $updateData['old_deadline'] = $oldDeadline;
                $updateData['new_deadline'] = $task->deadline_date;
                // Append extension info to remarks
                $extensionNote = "\n\n[Deadline Extended] From: {$oldDeadline} To: {$task->deadline_date}. Reason: " . ($input['extension_reason'] ?? 'Task not completed on time');
                $updateData['remarks'] = $extensionNote;
            } else {
                $updateData['old_deadline'] = $oldDeadline;
                $updateData['new_deadline'] = $oldDeadline;
            }

            TaskUpdate::create($updateData);

            // Update main task
            $task->status = $input['status'];
            $task->save();

            DB::commit();
            try {
                $assigner = User::find($assign->assigned_by);
                if ($assigner) {
                    $this->notificationService->notifyTaskStatusUpdate(
                        $task,
                        $authUser,
                        $oldStatus,
                        $request->status,
                        $request->remarks
                    );
                }
            } catch (Exception $e) {
                Log::error('Failed to send task update notification: ' . $e->getMessage());
            }
            // Prepare response message
            $responseMessage = 'Task updated successfully';
            if ($deadlineExtended) {
                $responseMessage .= ' and deadline extended to ' . Carbon::parse($task->deadline_date)->format('Y-m-d');
            }
            if ($isCompleting) {
                $responseMessage .= '. Approval request has been sent to the task assigner.';
            }

            return response()->json([
                'success' => true,
                'message' => $responseMessage,

            ], 200);
        } catch (Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'An error occurred. Please try again later.' . $e->getMessage(),
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    public function taskApproval(Request $request)
    {
        try {
            $authUser = Auth::user();

            $validator = Validator::make($request->all(), [
                'id' => 'required|exists:tasks,id',
                'status' => 'required|string|in:approved,rejected',
                'remarks' => 'required|string|max:1000',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => $validator->errors()->first()
                ], 200);
            }

            DB::beginTransaction();

            $taskId = $request->id;
            $task = Task::findOrFail($taskId);

            // Check if user is the assigner
            $assign = TaskAssign::where('task_id', $taskId)
                ->where('assigned_by', $authUser->id)
                ->first();

            if (!$assign) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only the person who assigned this task can approve/reject it.'
                ], 200);
            }

            // Check if task is completed
            if ($task->status != 'completed') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only completed tasks can be approved or rejected.'
                ], 200);
            }

            $oldStatus = $task->status;

            // Create task approval
            TaskApproval::create([
                'task_id' => $taskId,
                'approved_by' => $authUser->id,
                'completed_by' => $assign->assigned_to,
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
                    'assigned_by' => $assign->assigned_by,
                    'assigned_to' => $assign->assigned_to,
                    'status' => 'assigned',
                ]);
            }

            DB::commit();

            // Send notification to assignee
            try {
                $assignee = User::find($assign->assigned_to);
                if ($assignee && $this->notificationService) {

                    // For approved tasks, send notification about approval
                    $this->notificationService->notifyTaskStatusUpdate(
                        $task,
                        $authUser,
                        $oldStatus,
                        $request->status,
                        $request->remarks
                    );
                }
            } catch (Exception $e) {
                Log::error('Failed to send task approval notification: ' . $e->getMessage());
            }


            $message = $request->status === 'approved'
                ? 'Task approved successfully'
                : 'Task rejected and new task created successfully. The employee has been reassigned the task.';

            return response()->json([
                'success' => true,
                'message' => $message,
            ], 200);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Task approval error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'An error occurred. Please try again later. ' . $e->getMessage()
            ], 500);
        }
    }


    public function getTaskDetails($taskId)
    {
        try {
            $authUser = Auth::user();

            // Check if user has access to this task (including admin/hr)
            $hasAccess = TaskAssign::where('task_id', $taskId)
                ->where(function ($query) use ($authUser) {
                    $query->where('assigned_to', $authUser->id)
                        ->orWhere('assigned_by', $authUser->id);
                })
                ->exists();

            if (!$hasAccess && !app(TaskPermissionService::class)->isElevated($authUser)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have access to this task.'
                ], 200);
            }

            // Get task with all relationships
            $task = Task::with([
                'project',
                'assignments.assignedBy',
                'assignments.assignedTo',
                'updates.updatedBy',
                'approvals.approvedBy',
                'approvals.completedBy',
            ])->find($taskId);

            if (!$task) {
                return response()->json([
                    'success' => false,
                    'message' => 'Task not found.'
                ], 200);
            }

            // Get all members with their individual details
            $members = DB::table('task_assigns')
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

            // Get assigner details (who created/assigned the task)
            $assignerDetails = DB::table('task_assigns')
                ->leftJoin('users', 'task_assigns.assigned_by', '=', 'users.id')
                ->leftJoin('user_basic_details', 'users.id', '=', 'user_basic_details.user_id')
                ->where('task_assigns.task_id', $task->id)
                ->select(
                    'users.id as assigned_by_id',
                    'users.name as assigned_by_name',
                    'users.email as assigned_by_email',
                    'users.employee_id as assigned_by_employee_id',
                    'user_basic_details.profile_image as assigned_by_image'
                )->first();

            // Calculate days remaining and overdue
            $deadlineDate = $task->deadline_date ? \Carbon\Carbon::parse($task->deadline_date) : null;
            $currentDate = \Carbon\Carbon::now();

            if ($deadlineDate) {
                $daysRemaining = $currentDate->diffInDays($deadlineDate, false);
                $isOverdue = $currentDate->gt($deadlineDate) && !in_array($task->status, ['completed', 'approved', 'rejected', 'cancelled']);
            } else {
                $daysRemaining = null;
                $isOverdue = false;
            }

            // Format dates for display
            $formattedTaskDate = $task->task_date ? \Carbon\Carbon::parse($task->task_date)->format('d M, Y') : null;
            $formattedDeadlineDate = $task->deadline_date ? \Carbon\Carbon::parse($task->deadline_date)->format('d M, Y') : null;

            // Format updates
            $updates = $task->updates->map(function ($update) {
                return [
                    'id' => $update->id,
                    'old_deadline' => $update->old_deadline,
                    'deadline_extension' => $update->deadline_extension,
                    'new_deadline' => $update->new_deadline,
                    'status' => $update->status,
                    'remarks' => $update->remarks,
                    'updated_by_employee_id' => $update->updatedBy->employee_id ?? null,
                    'updated_by_name' => $update->updatedBy->name ?? null,
                    'updated_by_email' => $update->updatedBy->email ?? null,
                    'updated_at' => $update->created_at->format('Y-m-d H:i:s'),
                ];
            });

            $firstMember = $members->first();
            $approval = $task->approvals->first();

            // Determine task type and set assigned_to accordingly
            $isGroupTask = $task->task_mode == 'group';
            $isIndividualTask = $task->task_mode == 'individual';

            // Set assigned_to ONLY for individual tasks
            $assignedToValue = null;
            $selfAssignedValue = null;

            if ($isIndividualTask) {
                // Check if it's self-assigned (assigned_by == assigned_to)
                $firstAssignment = $task->assignments->first();
                $isSelfTask = $firstAssignment && $firstAssignment->assigned_by == $firstAssignment->assigned_to;

                if ($isSelfTask) {
                    $assignedToValue = "Self Assigned";
                    $selfAssignedValue = 1;
                } else {
                    // Individual task assigned to someone else
                    $assignedToValue = $firstMember->name ?? null;
                    $selfAssignedValue = 0;
                }
            } elseif ($isGroupTask) {
                // For group tasks - NO single assignee
                $assignedToValue = null;
                $selfAssignedValue = 2;
            }

            $response = [
                // ========== EXISTING FIELDS (backward compatibility) ==========
                'id' => $task->id,
                'task_code' => $task->task_code,
                'title' => $task->title,
                'description' => $task->description,
                'priority' => $task->priority,
                'status' => $task->status,
                'task_date' => $task->task_date,
                'voice_file' => $task->voice_file ? asset($task->voice_file) : null,
                'file' => $task->file ? asset($task->file) : null,
                'deadline_date' => $task->deadline_date,
                'is_overdue' => now()->gt($task->deadline_date) && !in_array($task->status, ['completed', 'cancelled']),
                'assigned_by' => $assignerDetails->assigned_by_name ?? null,
                'assigned_to' => $assignedToValue,
                'project' => $task->project ? [
                    'name' => $task->project->name,
                    'code' => $task->project->project_code,
                    'description' => $task->project->description,
                ] : null,
                'approval' => $approval ? [
                    'id' => $approval->id,
                    'status' => $approval->status,
                    'remarks' => $approval->remarks,
                    'approved_by_employee_id' => $approval->approvedBy->employee_id ?? null,
                    'approved_by_name' => $approval->approvedBy->name ?? null,
                    'approved_by_email' => $approval->approvedBy->email ?? null,
                    'created_at' => $approval->created_at,
                ] : null,
                'updates' => $updates,

                // ========== NEW FIELDS ADDED ==========
                'task_mode' => $task->task_mode,
                'self_assigned' => $selfAssignedValue,
                'group_completion_rule' => $task->group_completion_rule,
                'completion_threshold' => $task->completion_threshold,
                'group_lead_id' => $task->group_lead_id,
                'original_deadline_date' => $task->original_deadline_date,
                'extension_count' => $task->extension_count,

                // Date calculations
                // 'days_remaining' => $daysRemaining,
                'is_overdue_detailed' => $isOverdue,
                'formatted_task_date' => $formattedTaskDate,
                'formatted_deadline_date' => $formattedDeadlineDate,

                // Assigner details (complete)
                'assigned_by_details' => [
                    'id' => $assignerDetails->assigned_by_id ?? null,
                    'name' => $assignerDetails->assigned_by_name ?? null,
                    'email' => $assignerDetails->assigned_by_email ?? null,
                    'employee_id' => $assignerDetails->assigned_by_employee_id ?? null,
                    'profile_image' => $assignerDetails->assigned_by_image ? asset($assignerDetails->assigned_by_image) : null,
                ],

                // Primary assignee details (only for individual tasks)
                'assigned_to_details' => ($isIndividualTask && $firstMember) ? [
                    'id' => $firstMember->user_id,
                    'name' => $firstMember->name,
                    'email' => $firstMember->email,
                    'employee_id' => $firstMember->employee_id,
                    'profile_image' => $firstMember->profile_image ? asset($firstMember->profile_image) : null,
                    'member_role' => $firstMember->member_role,
                    'individual_status' => $firstMember->individual_status,
                ] : null,

                // Complete members list (especially important for group tasks)
                'members' => $members->map(function ($member) {
                    return [
                        'user_id' => $member->user_id,
                        'name' => $member->name,
                        'email' => $member->email,
                        'employee_id' => $member->employee_id,
                        'profile_image' => $member->profile_image ? asset($member->profile_image) : null,
                        'member_role' => $member->member_role,
                        'individual_status' => $member->individual_status,
                        'individual_remarks' => $member->individual_remarks,
                        'started_at' => $member->started_at,
                        'completed_at' => $member->completed_at,
                    ];
                }),
                'member_count' => $members->count(),

                // Group lead details (only for group tasks)
                'group_lead_details' => ($isGroupTask && $task->group_lead_id) ?
                    $members->firstWhere('user_id', $task->group_lead_id) : null,

                // Enhanced approval details
                'approval_details' => $approval ? [
                    'id' => $approval->id,
                    'status' => $approval->status,
                    'remarks' => $approval->remarks,
                    'requested_by_name' => $approval->completedBy->name ?? null,
                    'requested_by_email' => $approval->completedBy->email ?? null,
                    'requested_by_employee_id' => $approval->completedBy->employee_id ?? null,
                    'approved_by_name' => $approval->approvedBy->name ?? null,
                    'approved_by_email' => $approval->approvedBy->email ?? null,
                    'approved_by_employee_id' => $approval->approvedBy->employee_id ?? null,
                    'approval_date' => $approval->created_at ? $approval->created_at->format('Y-m-d H:i:s') : null,
                ] : null,
            ];

            return response()->json([
                'success' => true,
                'message' => 'Task details fetched successfully',
                'data' => $response
            ], 200);
        } catch (Exception $e) {
            Log::error('Task detail API error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'An error occurred. Please try again later.'.$e->getMessage()
            ], 500);
        }
    }
}
