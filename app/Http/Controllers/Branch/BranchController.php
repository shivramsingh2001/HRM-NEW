<?php

namespace App\Http\Controllers\Branch;

use App\Http\Controllers\Controller;
use App\Models\CompanyBranch;
use App\Models\User;
use App\Models\UserJobDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Company Branch — pure organizational profile (name/address/contact/
 * manager). Separate from AttendanceLocation (attendance geofencing).
 * Mirrors DepartmentController/DesignationController's shape.
 */
class BranchController extends Controller
{
    public function index(Request $request)
    {
        $branches = CompanyBranch::with('branchHead')->orderBy('name')->get()->map(function ($branch) {
            $branch->employees_count = UserJobDetail::where('branch_id', $branch->id)->count();
            return $branch;
        });

        $users = User::where('status', 1)->get();

        return view('client.branch.branch', compact('branches', 'users'));
    }

    public function detail(Request $request, $id)
    {
        $id = decrypt($id);
        $branch = CompanyBranch::with('branchHead')->findOrFail($id);
        $users = User::where('status', 1)
            ->whereHas('jobDetails', function ($query) use ($id) {
                $query->where('branch_id', $id);
            })
            ->with(['basicDetails', 'jobDetails', 'jobDetails.Designation', 'jobDetails.Department'])
            ->get();

        return view('client.branch.branch-detail', compact('branch', 'users'));
    }

    public function store(Request $request)
    {
        $tenantId = session('tenant_id');
        $request->validate([
            'name' => [
                'required',
                'max:255',
                'string',
                Rule::unique('company_branches', 'name')->where(function ($query) use ($tenantId) {
                    return $query->where('tenant_id', $tenantId);
                }),
            ],
            'description' => 'nullable|string|max:1000',
            'address' => 'nullable|string|max:1000',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:150',
            'branch_head' => 'nullable|exists:users,id',
            'status' => 'nullable|in:0,1',
        ]);

        try {
            $branch = new CompanyBranch();
            $branch->name = $request->name;
            $branch->description = $request->description;
            $branch->address = $request->address;
            $branch->city = $request->city;
            $branch->state = $request->state;
            $branch->country = $request->country;
            $branch->postal_code = $request->postal_code;
            $branch->phone = $request->phone;
            $branch->email = $request->email;
            $branch->branch_head = $request->branch_head;
            $branch->status = $request->status ?? 1;
            $branch->save();

            return response()->json(['success' => true, 'message' => 'Branch Created Successfully!'], 200);
        } catch (\Exception $e) {
            report($e);
            return response()->json(['success' => false, 'message' => 'Something went wrong. Please try again later.'], 500);
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
                Rule::unique('company_branches', 'name')
                    ->where(function ($query) {
                        return $query->where('tenant_id', session('tenant_id'));
                    })
                    ->ignore($id),
            ],
            'description' => 'nullable|string|max:1000',
            'address' => 'nullable|string|max:1000',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:150',
            'branch_head' => 'nullable|exists:users,id',
            'status' => 'nullable|in:0,1',
        ]);

        try {
            $branch = CompanyBranch::findOrFail($id);
            $branch->name = $request->name;
            $branch->description = $request->description;
            $branch->address = $request->address;
            $branch->city = $request->city;
            $branch->state = $request->state;
            $branch->country = $request->country;
            $branch->postal_code = $request->postal_code;
            $branch->phone = $request->phone;
            $branch->email = $request->email;
            $branch->branch_head = $request->branch_head;
            $branch->status = $request->status ?? $branch->status;
            $branch->save();

            return response()->json(['success' => true, 'message' => 'Branch Updated Successfully!'], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Branch not found.'], 404);
        } catch (\Exception $e) {
            report($e);
            return response()->json(['success' => false, 'message' => 'Something went wrong. Please try again later.'], 500);
        }
    }

    public function destroy(Request $request, $id)
    {
        try {
            $id = decrypt($id);
            $branch = CompanyBranch::findOrFail($id);

            $assignedCount = UserJobDetail::where('branch_id', $id)->count();
            if ($assignedCount > 0) {
                return response()->json([
                    'success' => false,
                    'message' => "Reassign {$assignedCount} employee(s) to another branch before deleting.",
                ], 422);
            }

            $branch->delete();

            return response()->json(['success' => true, 'message' => 'Branch Deleted Successfully!'], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Branch not found.'], 404);
        } catch (\Exception $e) {
            report($e);
            return response()->json(['success' => false, 'message' => 'Something went wrong. Please try again later.'], 500);
        }
    }

    /**
     * Bulk-assign employees to this branch (writes user_job_details.branch_id
     * — the organizational Branch column, distinct from office_branch which
     * is for attendance geofencing). Mirrors Shift Roster's assign_type
     * (user/department/all) pattern.
     */
    public function assignEmployees(Request $request, $id)
    {
        $id = decrypt($id);
        $branch = CompanyBranch::findOrFail($id);

        $request->validate([
            'assign_type' => 'required|in:user,department,all',
            'user_ids' => 'required_if:assign_type,user|array',
            'user_ids.*' => 'exists:users,id',
            'department_id' => 'required_if:assign_type,department|exists:departments,id',
        ]);

        $userIds = $this->getUserIdsForAssignment($request);

        DB::beginTransaction();
        try {
            $assignedCount = UserJobDetail::whereIn('user_id', $userIds)->update(['branch_id' => $branch->id]);
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "{$assignedCount} employee(s) assigned to {$branch->name}.",
                'assigned_count' => $assignedCount,
            ], 200);
        } catch (\Exception $e) {
            report($e);
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Something went wrong. Please try again later.'], 500);
        }
    }

    private function getUserIdsForAssignment(Request $request): array
    {
        if ($request->assign_type === 'all') {
            return User::where('status', 1)->pluck('id')->toArray();
        }

        if ($request->assign_type === 'department') {
            return User::whereHas('jobDetails', fn ($q) => $q->where('department', $request->department_id))
                ->where('status', 1)
                ->pluck('id')
                ->toArray();
        }

        return $request->user_ids ?? [];
    }
}
