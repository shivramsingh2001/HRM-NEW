<?php

namespace App\Http\Controllers\Api\Project;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class ProjectController extends Controller
{
    public function userProject()
    {
        try {
            $authUser = Auth::user();
            
            // If user is manager, show all projects
            if ($authUser->role === 'manager') {
                $projects = DB::table('projects as p')
                    ->select([
                        'p.id',
                        'p.project_code',
                        'p.name',
                        'p.start_date',
                        'p.deadline_date',
                        'p.description',
                        'p.status',
                        'u.name as project_head',
                        DB::raw('(SELECT COUNT(*) FROM project_assigns WHERE project_id = p.id) as member_count')
                    ])
                    ->leftJoin('users as u', 'p.project_head', '=', 'u.id')
                    ->orderBy('p.name')
                    ->get();
            } 
            // For non-manager users, show only projects they're assigned to
            else {
                $projects = DB::table('projects as p')
                    ->select([
                        'p.id',
                        'p.project_code',
                        'p.name',
                        'p.start_date',
                        'p.deadline_date',
                        'p.description',
                        'p.status',
                        'u.name as project_head',
                        DB::raw('(SELECT COUNT(*) FROM project_assigns WHERE project_id = p.id) as member_count')
                    ])
                    ->leftJoin('users as u', 'p.project_head', '=', 'u.id')
                    ->join('project_assigns as pa', 'p.id', '=', 'pa.project_id')
                    ->where('pa.user_id', $authUser->id)
                    ->orderBy('p.name')
                    ->get();
            }
            
            return response()->json([
                'success' => true,
                'message' => "Data fetched Successfully",
                'data' => $projects
            ]);
        } catch (Exception $e) {
            report($e);
            return response()->json([
                'success' => false,
                'message' => "An Error occured. Please try again later."
            ], 500);
        }
    }
    
    public function index()
    {
        try {
        $user = Auth::user();
           
         $projects = DB::table('projects as p')
            ->select([
                'p.id',
                'p.project_code',
                'p.name',
                'p.start_date',
                'p.deadline_date',
                'p.description',
                'p.status',
                'u.name as project_head',
                DB::raw('(SELECT COUNT(*) FROM project_assigns WHERE project_id = p.id AND status = 1) as member_count')
            ])
            ->leftJoin('users as u', 'p.project_head', '=', 'u.id')
            ->leftJoin('project_assigns as pa', function($join) use ($user) {
                $join->on('p.id', '=', 'pa.project_id')
                     ->where('pa.user_id', $user->id)
                     ->where('pa.status', 1);
            })
            ->where(function($query) use ($user) {
                $query->whereNotNull('pa.id') // User is assigned to project
                      ->orWhere('p.project_head', $user->id); // User is project head
            })
            ->orderBy('p.name')
            ->distinct()
            ->get();
            return response()->json([
                'success' => true,
                'message' => "Data fetched Successfully",
                'data' => $projects
            ]);
        } catch (Exception $e) {
            report($e);
            return response()->json([
                'success' => false,
                'message' => "An Error occured. Please try again later.".$e->getMessage()
            ], 500);
        }
    }
     public function show($id)
    {
        
        try {
            $authUser = Auth::user();

            $projects = DB::table('projects as p')
                ->where('p.id', $id)
                ->select([
                    'p.id',
                    'p.project_code',
                    'p.name',
                    'p.start_date',
                    'p.deadline_date',
                    'p.description',
                    'p.status',
                    'u.name as project_head',
                    DB::raw('(SELECT COUNT(*) FROM project_assigns WHERE project_id = p.id) as member_count')
                ])
                ->leftJoin('users as u', 'p.project_head', '=', 'u.id')
                ->leftJoin('project_assigns as a', 'p.id', '=', 'a.project_id')
                ->first();
                
            $assigns = DB::table('project_assigns as pa')
                ->select([
                    'u.id',
                    'u.employee_id',
                    'u.name',
                    'u.email',
                    'pa.is_head',
                    'bd.profile_image',
                    'd.name as designation',
                    'dep.name as department',
                    'pa.created_at as assigned_at',
                     DB::raw("CASE 
                        WHEN u.status = 1 THEN 'active' 
                        WHEN u.status = 0 THEN 'inactive' 
                        ELSE 'unknown' 
                    END as status"),
                ])
                ->leftJoin('users as u', 'pa.user_id', '=', 'u.id')
                ->leftJoin('user_basic_details as bd', 'u.id', '=', 'bd.user_id')
                ->leftJoin('user_job_details as jd', 'u.id', '=', 'jd.user_id')
                ->leftJoin('designations as d', 'jd.designation', '=', 'd.id')
                ->leftJoin('departments as dep', 'jd.department', '=', 'dep.id')
                ->where('pa.project_id', $id)
                ->orderBy('u.name')
                ->get();
            file_storage()->mapUrls($assigns, ['profile_image' => 'profile_photo']);
            return response()->json([
                'success' => true,
                'message' => "Data fetched Successfully",
                'data' => [
                    'project'=>$projects,
                    'members'=>$assigns
                    ]
            ]);
        } catch (Exception $e) {
            report($e);
            return response()->json([
                'success' => false,
                'message' => "An Error occured. Please try again later."
            ], 500);
        }
    }
}
