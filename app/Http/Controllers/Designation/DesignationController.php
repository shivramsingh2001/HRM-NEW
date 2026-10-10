<?php

namespace App\Http\Controllers\Designation;

use App\Http\Controllers\Controller;
use App\Models\Designation;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;


class DesignationController extends Controller
{
    public function index(Request $request)
    {
        $data['designations'] = Designation::select(
            'designations.id',
            'designations.name',
            'designations.description',
            'designations.status',
            'designations.created_at',
            'designations.updated_at',
            DB::raw('COUNT(user_job_details.user_id) as employees_count')
        )
            ->leftJoin('user_job_details', 'user_job_details.designation', '=', 'designations.id')
            ->groupBy(
                'designations.id',
                'designations.name',
                'designations.description',
                'designations.status',
                'designations.created_at',
                'designations.updated_at'
            )
            ->get();
        return view('client.designation.designation', $data);
    }
    public function detail(Request $request, $id)
    {

        $id = decrypt(value: $id);
        $data['designation'] = Designation::where('id', $id)->first();
        $data['users'] = User::where('status', 1)
            ->whereHas('jobDetails', function ($query) use ($id) {
                $query->where('designation', $id);
            })
            ->with(['basicDetails', 'jobDetails', 'jobDetails.Designation', 'jobDetails.Department'])
            ->get();
        return view('client.designation.designation-detail', $data);
    }
    public function store(Request $request)
    {
        $tenantId = Session('tenant_id');
        $request->validate([
            'name' => [
                'required',
                'max:255',
                'string',
                Rule::unique('designations', 'name')->where(function ($query) use ($tenantId) {
                    return $query->where('tenant_id', $tenantId);
                }),
            ],
            'description' => 'nullable|string|max:1000',
        ]);
        $user = Auth::id();
        try {
            $designation = new Designation();
            $designation->name = $request->name;
            $designation->description = $request->description;
            $designation->save();
            return response()->json(['success' => true, 'message' => 'Designation Created Successfully!!!'], 200);
        } catch (Exception $e) {
            report($e);
            return response()->json(['success' => false, 'message' => 'Something went wrong. Please try again later.'], 500);
        }
    }
    public function update(Request $request)
    {
        $id = decrypt($request->id);
        $request->validate([
            'name' => [
                'required',
                'max:255',
                'string',
                Rule::unique('designations', 'name')
                    ->where(function ($query) {
                        return $query->where('tenant_id', session('tenant_id'));
                    })
                    ->ignore($id)
            ],
            'description' => 'nullable|string|max:1000',
            'status' => 'nullable|in:0,1',
        ]);
        try {
            $designation = Designation::findOrFail($id);
            $designation->name = $request->name;
            $designation->description = $request->description;
            $designation->status = $request->status;
            $designation->save();
            return response()->json(['success' => true, 'message' => 'Designation Updated Successfully!'], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Designation not found.'], 404);
        } catch (Exception $e) {
            report($e);
            return response()->json(['success' => false, 'message' => 'Something went wrong. Please try again later.'], 500);
        }
    }
    public function status(Request $request)
    {
        $request->validate([
            'status' => 'required|in:0,1',
            'id' => 'required|exists:designations,id'
        ]);
        try {
            $designation = Designation::find($request->id);
            if (!$designation) {
                return response()->json(['success' => false, 'message' => 'Designation not found.'], 404);
            }
            $designation->status = $request->status;
            $designation->save();
            return response()->json(['success' => true, 'message' => 'Status Updated Successfully'], 200);
        } catch (Exception $e) {
            report($e);
            return response()->json([
                'success' => false,
                'message' => 'Something went wrong. Please try again later.'
            ], 500);
        }
    }
}
