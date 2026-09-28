<?php

namespace App\Http\Controllers\Project;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectAttachment;
use App\Models\ProjectComment;
use App\Models\ProjectMilestone;
use App\Models\ProjectRisk;
use App\Models\ProjectUpdate;
use App\Models\User;
use App\Models\ProjectAssign;
use App\Observers\TaskProgressObserver;
use App\Services\AuditLogger;
use App\Services\ProjectNotificationService;
use App\Services\RbacService;
use App\Services\TaskPermissionService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ProjectController extends Controller
{
    protected $permissions;
    protected $rbac;
    protected $auditLogger;
    protected $notifications;

    public function __construct(
        TaskPermissionService $permissions,
        RbacService $rbac,
        AuditLogger $auditLogger,
        ProjectNotificationService $notifications
    ) {
        $this->permissions = $permissions;
        $this->rbac = $rbac;
        $this->auditLogger = $auditLogger;
        $this->notifications = $notifications;
    }

    public function index(Request $request)
    {
        $data['users'] = User::whereIn('role', ['manager', 'employee'])
            ->where('status', '1')
            ->get();

        $user = auth()->user();

        // Base query for projects
        $projectsQuery = Project::with(['head', 'assigns']);

        // Apply role-based filtering
        if ($this->permissions->isElevated($user)) {
            // Admin and HR can see all projects
            $projectsQuery = $projectsQuery->latest();
        } else {
            // For other roles (employee, manager), show only projects they're assigned to
            $projectsQuery = $projectsQuery->where(function ($query) use ($user) {
                $query->whereHas('assigns', function ($q) use ($user) {
                    $q->where('user_id', $user->id)
                        ->where('status', 1);
                })
                    ->orWhere('project_head', $user->id); // Also show projects where they are project head
            })->latest();
        }

        // Get all projects for statistics and filtering
        $allProjects = $projectsQuery->get();

        // Calculate statistics
        $data['total_projects'] = $allProjects->count();
        $data['active_projects'] = $allProjects->whereIn('status', ['ongoing', 'pending', 'hold'])->count();
        $data['ongoing_projects'] = $allProjects->where('status', 'ongoing')->count();
        $data['completed_projects'] = $allProjects->where('status', 'completed')->count();
        $data['overdue_projects'] = $allProjects->filter(fn ($p) => $p->deadline_date && \Carbon\Carbon::today()->gt($p->deadline_date) && in_array($p->status, ['ongoing', 'pending', 'hold']))->count();

        // Calculate assignments and heads
        $data['total_assignments'] = $allProjects->sum(function ($project) {
            return $project->assigns->count();
        });
        $data['project_heads'] = $allProjects->where('project_head', '!=', null)->count();

        // Apply any additional filters from request
        if ($request->has('status') && $request->status != '') {
            $projectsQuery = $projectsQuery->where('status', $request->status);
        }

        if ($request->has('priority') && $request->priority != '') {
            $projectsQuery = $projectsQuery->where('priority', $request->priority);
        }

        if ($request->has('search') && $request->search != '') {
            $projectsQuery = $projectsQuery->where('name', 'like', '%' . $request->search . '%');
        }

        // Get final filtered projects for display
        $data['projects'] = $projectsQuery->get();

        return view('client.project.view-project', $data);
    }

    public function store(Request $request)
    {
        try {
            if (!$this->rbac->can(auth()->user(), 'projects', 'create')) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have permission to create projects.',
                ], 403);
            }

            // Validate request
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'start_date' => 'required|date|before:deadline_date',
                'deadline_date' => 'required|date|after_or_equal:start_date',
                'project_head' => 'required|exists:users,id',
                'priority' => 'nullable|in:low,medium,high,critical',
                'budget' => 'nullable|numeric|min:0',
                'member' => 'required|array|min:1',
                'member.*' => 'exists:users,id',
            ], [
                'project_head.required' => 'Please select a project head.',
                'member.required' => 'Please select at least one team member.',
                'deadline_date.after_or_equal' => 'Deadline date must be today or a future date.',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            // Get unique project ID
            $projectId = $this->generateUniqueProjectId();

            // 1. Create project first
            $project = Project::create([
                'name' => $request->name,
                'project_code' => "PR-" . $projectId,
                'description' => $request->description,
                'start_date' => $request->start_date,
                'deadline_date' => $request->deadline_date,
                'project_head' => $request->project_head,
                // Was '1' — projects.status is a string enum
                // (ongoing/pending/hold/completed/cancelled), and MySQL silently
                // accepted '1' as that enum's 1-based positional index (=
                // 'ongoing' today, purely by coincidence of column order).
                'status' => 'ongoing',
                'priority' => $request->priority ?? 'medium',
                'budget' => $request->budget,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // 2. Prepare members data - start with project head
            $members = $request->member;

            // Ensure project head is in the members list
            if (!in_array($request->project_head, $members)) {
                $members[] = $request->project_head;
            }

            // Remove duplicates
            $members = array_unique($members);

            // 3. Insert project members in batch with project_head flag
            foreach ($members as $userId) {
                $isHead = ($userId == $request->project_head) ? '1' : '0';

                ProjectAssign::create([
                    'project_id' => $project->id,
                    'user_id' => $userId,
                    'is_head' => $isHead,
                ]);
            }
            DB::commit();

            $this->notifications->notifyProjectCreated($project->fresh());

            return response()->json([
                'success' => true,
                'message' => 'Project created successfully!',
                'data' => [
                    'project' => $project,
                    'members_count' => count($members)
                ]
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create project. Please try again.',

            ], 500);
        }
    }

    private function generateUniqueProjectId()
    {
        return DB::transaction(function () {
            $lastProject = DB::table('projects')
                ->select('id')
                ->lockForUpdate()
                ->orderByRaw('CAST(id AS UNSIGNED) DESC')
                ->first();

            if (!$lastProject || !is_numeric($lastProject->id)) {
                $nextNumber = 1;
            } else {
                $nextNumber = (int) $lastProject->id + 1;

                if ($nextNumber > 99999) {
                    $nextNumber = 1;
                }
            }

            return str_pad($nextNumber, 5, '0', STR_PAD_LEFT);
        });
    }

    public function show(Request $request, $id)
    {
        $id = decrypt($id);
        $data['users'] = DB::table('users')->where('status', 1)->get();

        $project = Project::with([
            'head', 'assigns', 'assigns.users', 'assigns.users.basicDetails', 'assigns.users.jobDetails',
            'assigns.users.jobDetails.Designation', 'assigns.users.jobDetails.Department',
            'milestones', 'risks', 'comments.author', 'attachments.uploadedBy',
        ])->findOrFail($id);

        $project->load(['updates' => function ($q) {
            $q->with('author')->limit(10);
        }]);

        $data['project'] = $project;
        $data['taskStats'] = $project->task_stats;

        $spent = (float) $project->approvedExpenses()->sum('amount');
        $data['budget'] = [
            'budget' => $project->budget,
            'spent' => $spent,
            'remaining' => $project->budget !== null ? (float) $project->budget - $spent : null,
        ];

        return view('client.project.project-detail', $data);
    }

    /**
     * JSON list of a project's active team members — e.g. for populating an
     * "assign from project team" dropdown elsewhere in the app.
     */
    public function getMembers($id)
    {
        $projectId = $this->resolveId($id);

        $project = Project::find($projectId);
        if (!$project) {
            return response()->json(['success' => false, 'message' => 'Project not found.'], 404);
        }

        $members = ProjectAssign::with('user')
            ->where('project_id', $projectId)
            ->where('status', 1)
            ->get()
            ->map(function ($assign) {
                return [
                    'id' => $assign->user->id ?? null,
                    'name' => $assign->user->name ?? null,
                    'email' => $assign->user->email ?? null,
                    'employee_id' => $assign->user->employee_id ?? null,
                    'is_head' => (bool) $assign->is_head,
                ];
            })
            ->filter(fn ($m) => $m['id'] !== null)
            ->values();

        return response()->json(['success' => true, 'data' => $members]);
    }

    /**
     * Only elevated roles or the project head may delete a project, and only
     * while it isn't completed — mirrors Task::delete()'s editable-window
     * rule. Tasks under the project are NOT cascade-deleted (project_id is
     * simply orphaned to null) — a project's task history should survive
     * the project record itself being removed.
     */
    public function destroy($id)
    {
        $projectId = $this->resolveId($id);

        $project = Project::find($projectId);
        if (!$project) {
            return response()->json(['success' => false, 'message' => 'Project not found.'], 404);
        }

        if ($error = $this->authorizeProjectAccess($project, 'delete')) {
            return response()->json(['success' => false, 'message' => $error], 403);
        }

        if ($project->status === 'completed') {
            return response()->json([
                'success' => false,
                'message' => 'A completed project cannot be deleted.',
            ], 422);
        }

        try {
            DB::beginTransaction();
            \App\Models\Task::where('project_id', $projectId)->update(['project_id' => null]);
            $project->delete(); // project_updates/comments/attachments/milestones/risks/assigns cascade via FK
            DB::commit();

            return response()->json(['success' => true, 'message' => 'Project deleted successfully.']);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Project delete error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to delete project.'], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $projectId = decrypt($id);
        $project = Project::findOrFail($projectId);

        if ($error = $this->authorizeProjectAccess($project, 'edit')) {
            return response()->json(['success' => false, 'message' => $error], 403);
        }

        try {
            // Validate request
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'start_date' => 'required|date|before:deadline_date',
                'deadline_date' => 'required|date|after_or_equal:start_date',
                'project_head' => 'required|exists:users,id',
                'status' => 'required|in:ongoing,pending,hold,completed,cancelled',
                'priority' => 'nullable|in:low,medium,high,critical',
                'budget' => 'nullable|numeric|min:0',
                'member' => 'required|array|min:1',
                'member.*' => 'exists:users,id',
            ], [
                'project_head.required' => 'Please select a project head.',
                'member.required' => 'Please select at least one team member.',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            $old = $project->only(['status', 'priority', 'project_head', 'start_date', 'deadline_date', 'budget']);
            $oldStatus = $project->status;

            // 1. Update project basic info
            $oldProjectHead = $project->project_head;
            $newProjectHead = $request->project_head;

            $project->name = $request->name ?: $project->name;
            $project->description = $request->description ?: $project->description;
            $project->start_date = $request->start_date ?: $project->start_date;
            $project->deadline_date = $request->deadline_date ?: $project->deadline_date;
            $project->project_head = $newProjectHead ?: $project->project_head;
            $project->status = $request->status ?: $project->status;
            $project->priority = $request->priority ?: $project->priority;
            $project->budget = $request->filled('budget') ? $request->budget : $project->budget;
            $project->updated_at = now();
            $project->save();

            // 2. Prepare members data
            $newMemberIds = $request->member;

            // Ensure new project head is in members list
            if (!in_array($newProjectHead, $newMemberIds)) {
                $newMemberIds[] = $newProjectHead;
            }

            $newMemberIds = array_unique($newMemberIds);

            // 3. Get existing active assignments
            $existingAssigns = ProjectAssign::where('project_id', $project->id)
                ->where('status', '1')
                ->get();

            $existingActiveMemberIds = $existingAssigns->pluck('user_id')->toArray();

            // 4. Identify changes
            $membersToAdd = array_diff($newMemberIds, $existingActiveMemberIds);
            $membersToRemove = array_diff($existingActiveMemberIds, $newMemberIds);

            // 5. Handle project head changes
            if ($oldProjectHead != $newProjectHead) {
                // Remove head flag from old project head if they're still in the team
                if (in_array($oldProjectHead, $newMemberIds)) {
                    ProjectAssign::where('project_id', $project->id)
                        ->where('user_id', $oldProjectHead)
                        ->update([
                            'is_head' => '0',
                            'updated_at' => now(),
                        ]);
                }

                // Add head flag to new project head
                ProjectAssign::where('project_id', $project->id)
                    ->where('user_id', $newProjectHead)
                    ->update([
                        'is_head' => '1',
                        'updated_at' => now(),
                    ]);
            }

            // 6. Add new members
            if (!empty($membersToAdd)) {
                foreach ($membersToAdd as $userId) {
                    $existingAssign = ProjectAssign::where('project_id', $project->id)
                        ->where('user_id', $userId)
                        ->first();

                    $isHead = ($userId == $newProjectHead) ? '1' : '0';

                    if ($existingAssign) {
                        $existingAssign->update([
                            'status' => '1',
                            'is_head' => $isHead,
                        ]);
                    } else {
                        ProjectAssign::create([
                            'project_id' => $project->id,
                            'user_id' => $userId,
                            'is_head' => $isHead,
                            'status' => '1',
                        ]);
                    }
                }
            }

            // 7. Remove members (set status = 0)
            if (!empty($membersToRemove)) {
                ProjectAssign::where('project_id', $project->id)
                    ->whereIn('user_id', $membersToRemove)
                    ->update([
                        'status' => '0',
                        'updated_at' => now(),
                    ]);
            }

            DB::commit();

            $user = auth()->user();
            $new = $project->fresh()->only(['status', 'priority', 'project_head', 'start_date', 'deadline_date', 'budget']);
            $this->auditLogger->record('tenant_user', $user->id, $project->tenant_id, 'project.updated', 'Project', $project->id, $old, $new);

            if ($oldStatus !== $project->status) {
                $this->notifications->notifyStatusChanged($project->fresh(), $oldStatus, $project->status, $user);
            }

            return response()->json([
                'success' => true,
                'message' => 'Project updated successfully!',
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update project. Please try again.',
            ], 500);
        }
    }

    // ==================== Project Updates (progress/completed/pending/issues/next actions) ====================

    public function storeUpdate(Request $request, $id)
    {
        $projectId = $this->resolveId($id);
        $project = Project::findOrFail($projectId);

        if ($error = $this->authorizeProjectAccess($project, 'edit')) {
            return response()->json(['success' => false, 'message' => $error], 403);
        }

        try {
            $validated = $request->validate([
                'reported_progress_percentage' => 'nullable|integer|min:0|max:100',
                'completed_work' => 'nullable|string',
                'pending_work' => 'nullable|string',
                'issues' => 'nullable|string',
                'next_actions' => 'nullable|string',
                'notes' => 'nullable|string',
            ]);

            $user = auth()->user();

            DB::beginTransaction();
            $update = ProjectUpdate::create(array_merge($validated, [
                'project_id' => $project->id,
                'user_id' => $user->id,
            ]));

            if ($request->filled('reported_progress_percentage')) {
                $project->progress_percentage = $validated['reported_progress_percentage'];
                $project->progress_manual_override = true;
                $project->save();
            }
            DB::commit();

            $this->notifications->notifyProjectUpdated($project->fresh(), $update, $user);

            return response()->json(['success' => true, 'message' => 'Update posted successfully.', 'data' => $update]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Validation error', 'errors' => $e->errors()], 422);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Failed to post update.'], 500);
        }
    }

    /** Clears the manual-override flag and immediately re-syncs progress from tasks. */
    public function resetProgress($id)
    {
        $projectId = $this->resolveId($id);
        $project = Project::findOrFail($projectId);

        if ($error = $this->authorizeProjectAccess($project, 'edit')) {
            return response()->json(['success' => false, 'message' => $error], 403);
        }

        try {
            $project->progress_manual_override = false;
            $project->save();

            app(TaskProgressObserver::class)->applyTaskDerivedProgress($project);

            return response()->json([
                'success' => true,
                'message' => 'Progress reset to task-derived calculation.',
                'progress_percentage' => $project->fresh()->progress_percentage,
            ]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to reset progress.'], 500);
        }
    }

    // ==================== Comments ====================

    public function storeComment(Request $request, $id)
    {
        $projectId = $this->resolveId($id);
        $project = Project::findOrFail($projectId);

        if ($error = $this->authorizeProjectAccess($project, 'edit')) {
            return response()->json(['success' => false, 'message' => $error], 403);
        }

        try {
            $validated = $request->validate(['comment' => 'required|string|max:2000']);

            $comment = ProjectComment::create([
                'project_id' => $project->id,
                'user_id' => auth()->id(),
                'comment' => $validated['comment'],
            ]);

            return response()->json(['success' => true, 'message' => 'Comment added.', 'data' => $comment->load('author')]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Validation error', 'errors' => $e->errors()], 422);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to add comment.'], 500);
        }
    }

    public function destroyComment($commentId)
    {
        try {
            $comment = ProjectComment::findOrFail($commentId);
            if (!$this->permissions->canManageOwnedResource(auth()->user(), (int) $comment->user_id)) {
                return response()->json(['success' => false, 'message' => 'You can only delete your own comments.'], 403);
            }
            $comment->delete();
            return response()->json(['success' => true, 'message' => 'Comment deleted.']);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to delete comment.'], 500);
        }
    }

    // ==================== Attachments ====================

    public function storeAttachment(Request $request, $id)
    {
        $projectId = $this->resolveId($id);
        $project = Project::findOrFail($projectId);

        if ($error = $this->authorizeProjectAccess($project, 'edit')) {
            return response()->json(['success' => false, 'message' => $error], 403);
        }

        try {
            $request->validate(['file' => 'required|file|max:10240']);

            $stored = file_storage()->upload($request->file('file'), 'project_attachment', ['id' => $project->id]);

            $attachment = ProjectAttachment::create([
                'project_id' => $project->id,
                'user_id' => auth()->id(),
                'file_path' => $stored->path,
                'original_filename' => $stored->originalName,
                'mime_type' => $stored->mimeType,
                'file_size' => $stored->size,
            ]);

            return response()->json(['success' => true, 'message' => 'File uploaded.', 'data' => $attachment]);
        } catch (\App\Exceptions\FileStorageException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], $e->httpStatus());
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Validation error', 'errors' => $e->errors()], 422);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to upload file.'], 500);
        }
    }

    public function destroyAttachment($attachmentId)
    {
        try {
            $attachment = ProjectAttachment::findOrFail($attachmentId);
            if (!$this->permissions->canManageOwnedResource(auth()->user(), (int) $attachment->user_id)) {
                return response()->json(['success' => false, 'message' => 'You can only delete your own attachments.'], 403);
            }

            file_storage()->delete($attachment->file_path, 'project_attachment');
            $attachment->delete();

            return response()->json(['success' => true, 'message' => 'Attachment deleted.']);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to delete attachment.'], 500);
        }
    }

    // ==================== Milestones ====================

    public function storeMilestone(Request $request, $id)
    {
        $projectId = $this->resolveId($id);
        $project = Project::findOrFail($projectId);

        if ($error = $this->authorizeProjectAccess($project, 'edit')) {
            return response()->json(['success' => false, 'message' => $error], 403);
        }

        try {
            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'description' => 'nullable|string',
                'due_date' => 'nullable|date',
            ]);

            $milestone = ProjectMilestone::create(array_merge($validated, [
                'project_id' => $project->id,
                'sort_order' => $project->milestones()->count(),
            ]));

            return response()->json(['success' => true, 'message' => 'Milestone added.', 'data' => $milestone]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Validation error', 'errors' => $e->errors()], 422);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to add milestone.'], 500);
        }
    }

    public function updateMilestone(Request $request, $milestoneId)
    {
        $milestone = ProjectMilestone::findOrFail($milestoneId);

        if ($error = $this->authorizeProjectAccess($milestone->project, 'edit')) {
            return response()->json(['success' => false, 'message' => $error], 403);
        }

        try {
            $validated = $request->validate([
                'title' => 'sometimes|required|string|max:255',
                'description' => 'nullable|string',
                'due_date' => 'nullable|date',
                'status' => 'sometimes|in:pending,completed',
            ]);

            $wasCompleted = $milestone->status === 'completed';
            $milestone->fill($validated);

            if (($validated['status'] ?? null) === 'completed' && !$wasCompleted) {
                $milestone->completed_at = now();
            } elseif (($validated['status'] ?? null) === 'pending') {
                $milestone->completed_at = null;
            }
            $milestone->save();

            if (!$wasCompleted && $milestone->status === 'completed') {
                $this->notifications->notifyMilestoneCompleted($milestone->project, $milestone);
            }

            return response()->json(['success' => true, 'message' => 'Milestone updated.']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Validation error', 'errors' => $e->errors()], 422);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to update milestone.'], 500);
        }
    }

    public function destroyMilestone($milestoneId)
    {
        $milestone = ProjectMilestone::findOrFail($milestoneId);

        if ($error = $this->authorizeProjectAccess($milestone->project, 'edit')) {
            return response()->json(['success' => false, 'message' => $error], 403);
        }

        try {
            $milestone->delete();
            return response()->json(['success' => true, 'message' => 'Milestone deleted.']);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to delete milestone.'], 500);
        }
    }

    // ==================== Risks & Blockers ====================

    public function storeRisk(Request $request, $id)
    {
        $projectId = $this->resolveId($id);
        $project = Project::findOrFail($projectId);

        if ($error = $this->authorizeProjectAccess($project, 'edit')) {
            return response()->json(['success' => false, 'message' => $error], 403);
        }

        try {
            $validated = $request->validate([
                'type' => 'required|in:risk,blocker',
                'title' => 'required|string|max:255',
                'description' => 'nullable|string',
                'severity' => 'required|in:low,medium,high,critical',
            ]);

            $risk = ProjectRisk::create(array_merge($validated, [
                'project_id' => $project->id,
                'raised_by' => auth()->id(),
                'raised_at' => now(),
            ]));

            $this->notifications->notifyRiskRaised($project, $risk);

            return response()->json(['success' => true, 'message' => ucfirst($risk->type) . ' logged.', 'data' => $risk]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Validation error', 'errors' => $e->errors()], 422);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to log risk.'], 500);
        }
    }

    public function updateRisk(Request $request, $riskId)
    {
        $risk = ProjectRisk::findOrFail($riskId);

        if ($error = $this->authorizeProjectAccess($risk->project, 'edit')) {
            return response()->json(['success' => false, 'message' => $error], 403);
        }

        try {
            $validated = $request->validate(['status' => 'required|in:open,mitigated,resolved,closed']);

            $risk->status = $validated['status'];
            if (in_array($validated['status'], ['resolved', 'closed']) && !$risk->resolved_at) {
                $risk->resolved_at = now();
            }
            $risk->save();

            return response()->json(['success' => true, 'message' => 'Risk status updated.']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Validation error', 'errors' => $e->errors()], 422);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to update risk.'], 500);
        }
    }

    public function destroyRisk($riskId)
    {
        $risk = ProjectRisk::findOrFail($riskId);

        if ($error = $this->authorizeProjectAccess($risk->project, 'edit')) {
            return response()->json(['success' => false, 'message' => $error], 403);
        }

        try {
            $risk->delete();
            return response()->json(['success' => true, 'message' => 'Risk deleted.']);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to delete risk.'], 500);
        }
    }

    // ==================== Shared helpers ====================

    private function resolveId($id)
    {
        try {
            return decrypt($id);
        } catch (\Exception $e) {
            return $id;
        }
    }

    /**
     * RBAC-backed, record-level check: 'company' scope (admin/hr) passes
     * unconditionally; 'own'/'team' scope requires the user to be the
     * project head or an active team member — mirrors AI\ProjectController's
     * existing RbacService::scopeFor() pattern, the one place in the
     * codebase that already enforced this correctly.
     *
     * Returns null when authorized, or a human-readable error message when
     * not (callers turn that into a 403 JSON response) — a plain return
     * value rather than abort()/throw so it composes cleanly with each
     * caller's own try/catch around its business logic.
     */
    private function authorizeProjectAccess(Project $project, string $action): ?string
    {
        $user = auth()->user();
        $scope = $this->rbac->scopeFor($user, 'projects', $action);

        if ($scope === null) {
            return 'You do not have permission to perform this action.';
        }

        if ($scope === 'company') {
            return null;
        }

        $isMember = $project->project_head == $user->id
            || $project->assigns()->where('user_id', $user->id)->where('status', 1)->exists();

        return $isMember ? null : 'You do not have permission to perform this action on this project.';
    }
}
