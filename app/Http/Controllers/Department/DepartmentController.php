<?php

namespace App\Http\Controllers\Department;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class DepartmentController extends Controller
{
    public function index(Request $request)
    {
        $userId = Auth::id();
        $data['users'] = User::where('status', 1)->get();
        $data['departments'] = Department::select(
            'departments.id',
            'departments.name',
            'departments.description',
            'departments.department_head',
            'departments.status',
            'departments.created_at',
            'departments.updated_at',
            DB::raw('COUNT(user_job_details.user_id) as employees_count')
        )
            ->leftJoin('user_job_details', 'user_job_details.department', '=', 'departments.id')
            ->groupBy(
                'departments.id',
                'departments.name',
                'departments.description',
                'departments.department_head',
                'departments.status',
                'departments.created_at',
                'departments.updated_at'
            )
            ->get();
        return view('client.department.department', $data);
    }
    public function detail(Request $request, $id)
    {

        $id = decrypt($id);
        $data['department'] = Department::where('id', $id)->first();
        $data['users'] = User::where('status', 1)
            ->whereHas('jobDetails', function ($query) use ($id) {
                $query->where('department', $id);
            })
            ->with(['basicDetails', 'jobDetails', 'jobDetails.Designation', 'jobDetails.Department'])
            ->get();
        return view('client.department.department-detail', $data);
    }
    public function store(Request $request)
    {
        $tenantId = Session('tenant_id');
        $request->validate([
            'name' => [
                'required',
                'max:255',
                'string',
                Rule::unique('departments', 'name')->where(function ($query) use ($tenantId) {
                    return $query->where('tenant_id', $tenantId);
                }),
            ],
            'department_head' => 'required|exists:users,id|unique:departments,department_head',
            'description' => 'nullable|string|max:1000',
            'status' => 'nullable|in:0,1',
        ]);
        $user = Auth::id();
        try {
            $department = new Department();
            $department->name = $request->name;
            $department->department_head = $request->department_head;
            $department->description = $request->description;

            $department->save();
            return response()->json(['success' => true, 'message' => 'Department Created Successfully!!!'], 200);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => 'Something went wrong. Please try again later.' . $e->getMessage()], 500);
        }
    }
    
    public function update(Request $request, $id)
    {
        $id = decrypt($id);
        $request->validate([
            'name' => [
                'required',
                'max:255',
                'string',
                Rule::unique('departments', 'name')
                    ->where(function ($query) {
                        return $query->where('tenant_id', session('tenant_id'));
                    })
                    ->ignore($id)
            ],
            'department_head' => 'required|exists:users,id',
            'description' => 'nullable|string|max:1000',
            'status' => 'nullable|in:0,1',
        ]);
        try {
            $department = Department::findOrFail($id);
            $department->name = $request->name;
            $department->description = $request->description;
            $department->status = $request->status;
            $department->department_head = $request->department_head;
            $department->save();
            return response()->json(['success' => true, 'message' => 'Department Updated Successfully!'], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Department not found.'], 404);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => 'Something went wrong. Please try again later.'], 500);
        }
    }
    public function status(Request $request)
    {
        $request->validate([
            'status' => 'required|in:0,1',
            'id' => 'required|exists:departments,id'
        ]);
        try {
            $department = Department::find($request->id);
            if (!$department) {
                return response()->json(['success' => false, 'message' => 'Designation not found.'], 404);
            }
            $department->status = $request->status;
            $department->save();
            return response()->json(['success' => true, 'message' => 'Status Updated Successfully'], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Something went wrong. Please try again later.'
            ], 500);
        }
    }
}
