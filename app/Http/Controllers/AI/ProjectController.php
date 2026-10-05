<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Models\Designation;
use App\Models\Project;
use App\Models\ProjectAssign;
use App\Models\User;
use App\Models\UserJobDetail;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use App\Services\RbacService;

class ProjectController extends Controller
{
    public function view_ai_all()
    {
        try {
            $authUser = Auth::user();

            // Base query using Project model
            $query = Project::select([
                'projects.id',
                'projects.project_code',
                'projects.name',
                'projects.start_date',
                'projects.deadline_date',
                'projects.description',
                'projects.status',
                'projects.priority',
                'projects.progress_percentage',
                'users.name as project_head',
                'users.id as project_head_id'
            ])
                ->leftJoin('users', 'projects.project_head', '=', 'users.id');

            // Add subqueries for counts
            $query->selectRaw('(SELECT COUNT(*) FROM project_assigns WHERE project_id = projects.id) as total_members');
            $query->selectRaw('(SELECT COUNT(*) FROM project_assigns WHERE project_id = projects.id AND status = "1") as active_members');

            // Permission-based filtering (was a fixed role switch that
            // silently locked out any custom role holding a real
            // projects:view grant).
            $projectScope = app(RbacService::class)->scopeFor($authUser, 'projects', 'view');
            switch ($projectScope) {
                case 'company':
                case 'team':
                    // See all projects, with assignment flags for this user.
                    $query->selectRaw('EXISTS(SELECT 1 FROM project_assigns WHERE project_id = projects.id AND user_id = ?) as is_assigned', [$authUser->id]);
                    $query->selectRaw('EXISTS(SELECT 1 FROM project_assigns WHERE project_id = projects.id AND user_id = ? AND is_head = "1") as is_project_head', [$authUser->id]);
                    break;

                case 'own':
                    // See only projects they're assigned to
                    $query->join('project_assigns as pa', function ($join) use ($authUser) {
                        $join->on('projects.id', '=', 'pa.project_id')
                            ->where('pa.user_id', '=', $authUser->id);
                    });
                    $query->selectRaw('true as is_assigned');
                    $query->selectRaw('pa.is_head as is_project_head');
                    break;

                default:
                    return response()->json([
                        'success' => false,
                        'message' => 'Unauthorized access. Invalid role.'
                    ], 403);
            }

            // Add task counts if tasks table exists
            // progress_percentage itself comes from the real projects column
            // (base select above) — kept in sync by TaskProgressObserver, the
            // single source of truth. These are just supplementary counts for
            // the response payload, using the same completed/approved
            // definition as everywhere else.
            if (Schema::hasTable('tasks')) {
                $query->selectRaw('(SELECT COUNT(*) FROM tasks WHERE project_id = projects.id) as total_tasks');
                $query->selectRaw('(SELECT COUNT(*) FROM tasks WHERE project_id = projects.id AND status IN ("completed","approved")) as completed_tasks');
            }

            // Apply ordering
            $query->orderBy('projects.status')
                ->orderBy('projects.name');

            $projects = $query->get();

            // Members of every listed project, loaded in bulk (was 3 queries per member).
            $assignsByProject = ProjectAssign::whereIn('project_id', $projects->pluck('id'))
                ->where('status', '1')->get()->groupBy('project_id');
            $memberIds = $assignsByProject->flatten()->pluck('user_id')->unique()->values();
            $memberUsers = User::whereIn('id', $memberIds)->get(['id', 'employee_id', 'name', 'email', 'role'])->keyBy('id');
            $memberJobs = UserJobDetail::whereIn('user_id', $memberIds)->get(['user_id', 'designation'])->keyBy('user_id');
            $designationNames = Designation::whereIn('id', $memberJobs->pluck('designation')->filter()->unique())->pluck('name', 'id');

            // Enhance the response with additional details
            $enhancedProjects = $projects->map(function ($project) use ($authUser, $projectScope, $assignsByProject, $memberUsers, $memberJobs, $designationNames) {

                $members = $assignsByProject->get($project->id, collect())
                    ->map(function ($assign) use ($memberUsers, $memberJobs, $designationNames) {
                        $user = $memberUsers->get($assign->user_id);
                        $jobDetail = $memberJobs->get($assign->user_id);
                        $designation = $jobDetail && $jobDetail->designation ? ($designationNames[$jobDetail->designation] ?? null) : null;

                        return [
                            'id' => $user ? $user->id : null,
                            'employee_id' => $user ? $user->employee_id : null,
                            'name' => $user ? $user->name : null,
                            'email' => $user ? $user->email : null,
                            'role' => $user ? $user->role : null,
                            'designation' => $designation,
                            'is_head' => $assign->is_head,
                            'is_project_head' => $assign->is_head == '1' ? true : false
                        ];
                    })
                    ->filter()
                    ->values();

                // For own-scope viewers, only show minimal member info
                if ($projectScope === 'own') {
                    $members = $members->map(function ($member) {
                        return [
                            'id' => $member['id'],
                            'name' => $member['name'],
                            'designation' => $member['designation'],
                            'is_project_head' => $member['is_project_head']
                        ];
                    });
                }

                // Calculate project status color and label
                $statusInfo = $this->getProjectStatusInfo($project->status);

                return [
                    'project' => [
                        'id' => $project->id,
                        'code' => $project->project_code,
                        'name' => $project->name,
                        'description' => $project->description,
                        'start_date' => $project->start_date,
                        'deadline' => $project->deadline_date,
                        'status' => [
                            'code' => $project->status,
                            'label' => $statusInfo['label'],
                           
                        ],
                        'project_head' => [
                            'id' => $project->project_head_id,
                            'name' => $project->project_head
                        ]
                    ],
                    'team' => [
                        'total_members' => $project->total_members,
                        'active_members' => $project->active_members ?? $project->total_members,
                        'members' => $members
                    ],
                    'progress' => [
                        'total_tasks' => $project->total_tasks ?? 0,
                        'completed_tasks' => $project->completed_tasks ?? 0,
                        'percentage' => $project->progress_percentage ?? 0
                    ],
                    'user_assignment' => [
                        'is_assigned' => $project->is_assigned ?? false,
                        'is_project_head' => $project->is_project_head ?? false,
                        'role_in_project' => $this->getUserProjectRole($project, $authUser->id)
                    ]
                ];
            });

            // Summary statistics
            $summary = [
                'total_projects' => $projects->count(),
                'by_status' => $projects->groupBy('status')->map->count(),
                'assigned_to_me' => $projects->where('is_assigned', true)->count(),
                'where_i_am_head' => $projects->where('is_project_head', true)->count()
            ];

            return response()->json([
                'success' => true,
                'message' => 'Projects fetched successfully',
                'data' => $enhancedProjects,
                'summary' => $summary,
                'user_role' => $authUser->role,
                'viewing_as' => [
                    'role' => $authUser->role,
                    'can_view_all' => $projectScope !== 'own'
                ]
            ], 200);
        } catch (Exception $e) {
            Log::error('View AI All Projects Error: ' . $e->getMessage(), [
                'user_id' => Auth::id(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred. Please try again later.'
            ], 500);
        }
    }
    private function getProjectStatusInfo($status)
    {
        $statusMap = [
            'ongoing' => [
                'label' => 'Ongoing',
               
            ],
            'pending' => [
                'label' => 'Pending',
               
            ],
            'hold' => [
                'label' => 'On Hold',
               
            ],
            'completed' => [
                'label' => 'Completed',
               
            ],
            'cancelled' => [
                'label' => 'Cancelled',
                
            ]
        ];

        return $statusMap[$status] ?? [
            'label' => ucfirst($status),
            
        ];
    }

    private function getUserProjectRole($project, $userId)
    {
        if ($project->project_head_id == $userId) {
            return 'project_head';
        }

        if (isset($project->is_project_head) && $project->is_project_head) {
            return 'project_head';
        }

        if (isset($project->is_assigned) && $project->is_assigned) {
            return 'team_member';
        }

        return 'not_assigned';
    }
}
