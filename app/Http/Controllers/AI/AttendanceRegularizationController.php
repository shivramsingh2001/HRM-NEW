<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRegularization;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AttendanceRegularizationController extends Controller
{
    public function view_ai_all(Request $request)
{
    try {
        $authUser = Auth::user();
        
        // Base query using AttendanceRegularization model
         $query = AttendanceRegularization::withoutGlobalScopes() 
              ->from('attendance_regularizations as ar')
            ->select([
                'ar.id',
                'ar.date',
                'ar.request_type',
                'ar.in_time',
                'ar.out_time',
                'ar.reason',
                'ar.file',
                'ar.status',
                'ar.user_id',
                'ar.approved_by',
                'ar.approved_date',
                'ar.created_at',
                'u.employee_id',
                'u.name as user_name',
                'u.email as user_email',
                'u.role as user_role',
                'd.name as designation',
                'bd.profile_image',
                'approver.name as approved_by_name',
                'approver.employee_id as approved_by_employee_id'
            ])
            ->leftJoin('users as u', 'ar.user_id', '=', 'u.id')
            ->leftJoin('users as approver', 'ar.approved_by', '=', 'approver.id')
            ->leftJoin('user_job_details as jd', 'u.id', '=', 'jd.user_id')
            ->leftJoin('user_basic_details as bd', 'u.id', '=', 'bd.user_id')
            ->leftJoin('designations as d', 'jd.designation', '=', 'd.id');

        // Role-based filtering
        switch ($authUser->role) {
            case 'admin':
            case 'hr':
                // Admin/HR: See all regularization requests
                if ($request->has('user_id') && !empty($request->user_id)) {
                    $query->where('ar.user_id', $request->user_id);
                }
                if ($request->has('employee_id') && !empty($request->employee_id)) {
                    $query->where('u.employee_id', $request->employee_id);
                }
                break;
                
            case 'manager':
                // Manager: See team members' requests + their own requests
                $query->where(function($q) use ($authUser) {
                    $q->whereIn('ar.user_id', function($subQ) use ($authUser) {
                        $subQ->select('user_id')
                            ->from('user_reporting_heads')
                            ->where('reporting_head_id', $authUser->id);
                    })
                    ->orWhere('ar.user_id', $authUser->id);
                });
                break;
                
            case 'employee':
                // Employee: See only their own requests
                $query->where('ar.user_id', $authUser->id);
                break;
                
            default:
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access. Invalid role.'
                ], 200);
        }

        // IMPORTANT: Add tenant filter using the table alias
        $query->where('ar.tenant_id', $authUser->tenant_id); // or Session::get('tenant_id')

        // Apply filters
        if ($request->has('status') && $request->status != 'all') {
            $query->where('ar.status', $request->status);
        }

        if ($request->has('request_type') && $request->request_type != 'all') {
            $query->where('ar.request_type', $request->request_type);
        }

        if ($request->has('start_date') && !empty($request->start_date)) {
            $query->where('ar.date', '>=', $request->start_date);
        }

        if ($request->has('end_date') && !empty($request->end_date)) {
            $query->where('ar.date', '<=', $request->end_date);
        }

        $regularizations = $query->orderBy('ar.created_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'message' => 'Regularization requests fetched successfully',
            'data' => $regularizations
        ], 200);
        
    } catch (Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'An error occurred: ' . $e->getMessage()
        ], 500);
    }
}
}
