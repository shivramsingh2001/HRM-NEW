<?php

namespace App\Http\Controllers\Project;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\User;
use App\Models\ProjectAssign;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ProjectController extends Controller
{
    public function index(Request $request)
    {
        $data['users'] = User::whereIn('role', ['manager', 'employee'])
            ->where('status', '1')
            ->get();

        $user = auth()->user();

        // Base query for projects
        $projectsQuery = Project::with(['headUser', 'assigns']);

        // Apply role-based filtering
        if ($user->role == 'admin' || $user->role == 'hr') {
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
        $data['active_projects'] = $allProjects->where('status', 'active')->count(); // Adjust status value as needed
        $data['ongoing_projects'] = $allProjects->where('status', 'ongoing')->count(); // Adjust status value as needed
        $data['completed_projects'] = $allProjects->where('status', 'completed')->count(); // Adjust status value as needed

        // Calculate assignments and heads
        $data['total_assignments'] = $allProjects->sum(function ($project) {
            return $project->assigns->count();
        });
        $data['project_heads'] = $allProjects->where('project_head', '!=', null)->count();

        // Apply any additional filters from request
        if ($request->has('status') && $request->status != '') {
            $projectsQuery = $projectsQuery->where('status', $request->status);
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
            // Validate request
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'start_date' => 'required|date|before:deadline_date',
                'deadline_date' => 'required|date|after_or_equal:start_date',
                'project_head' => 'required|exists:users,id',
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
                'status' => '1',
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
            $members = array_unique($members);
            foreach ($members as $userId) {
                $isHead = ($userId == $request->project_head) ? '1' : '0';

                ProjectAssign::create([
                    'project_id' => $project->id,
                    'user_id' => $userId,
                    'is_head' => $isHead,
                ]);
            }
            DB::commit();

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
        $data['project'] = Project::with(['headUser', 'assigns', 'assigns.users', 'assigns.users.basicDetails', 'assigns.users.jobDetails', 'assigns.users.jobDetails.Designation', 'assigns.users.jobDetails.Department'])->where('id', $id)->first();
        return view('client.project.project-detail', $data);
    }

    public function update(Request $request, $id)
    {
        try {
            $projectId = decrypt($id);
            $project = Project::findOrFail($projectId);

            // Validate request
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'start_date' => 'required|date|before:deadline_date',
                'deadline_date' => 'required|date|after_or_equal:start_date',
                'project_head' => 'required|exists:users,id',
                'status' => 'required|in:ongoing,pending,hold,completed,cancelled',
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

            // 1. Update project basic info
            $oldProjectHead = $project->project_head;
            $newProjectHead = $request->project_head;

            $project->name = $request->name ?: $project->name;
            $project->description = $request->description ?: $project->description;
            $project->start_date = $request->start_date ?: $project->start_date;
            $project->deadline_date = $request->deadline_date ?: $project->deadline_date;
            $project->project_head = $newProjectHead ?: $project->project_head;
            $project->status = $request->status ?: $project->status;
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
}
