<?php

namespace App\Http\Controllers\Leave;

use App\Http\Controllers\Controller;
use App\Models\ExpenseType;
use App\Models\LeaveType;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class LeaveTypeController extends Controller
{
    public function index(Request $request)
    {
        // Using Eloquent instead of DB::table()
        $data['leaveTypes'] = LeaveType::orderBy('name')->get();
        return view('client.leave.leave-type', $data);
    }

    public function detail(Request $request, $id)
    {
        try {
            $id = decrypt($id);
            // Using Eloquent with findOrFail for better error handling
            $data['leaveType'] = LeaveType::findOrFail($id);
            return view('client.leave.leave-type-detail', $data);
        } catch (Exception $e) {
            return redirect()
                ->route('leave.types')
                ->with('error', 'Leave type not found.');
        }
    }

    public function store(Request $request)
    {
        $tenantId = Session('tenant_id');
        $request->validate([
            'name' => [
                'required',
                'max:255',
                'string',
                Rule::unique('leave_types', 'name')->where(function ($query) use ($tenantId) {
                    return $query->where('tenant_id', $tenantId);
                }),
            ],
            'credit_type' => 'required|in:weekly,monthly,yearly,no',
            'credit_value' => [
                'required',
                'numeric',
                function ($attribute, $value, $fail) use ($request) {
                    if ($request->credit_type == 'no' && $value != 0) {
                        $fail('Credit value must be 0 when credit type is "no".');
                    }

                    if ($request->credit_type != 'no' && $value <= 0) {
                        $fail('Credit value must be greater than 0.');
                    }
                },
            ],
            'description' => 'nullable|string|max:1000',
        ]);

        try {
            // Using Eloquent create method
            $type = LeaveType::create([
                'name' => $request->name,
                'description' => $request->description,
                'credit_type' => $request->credit_type,
                'credit_value' => $request->credit_value,
                'status' => 1, // Default status

            ]);

            return response()->json([
                'success' => true,
                'message' => 'Leave Type Created Successfully!!!'
            ], 200);
        } catch (Exception $e) {

            return response()->json([
                'success' => false,
                'message' => 'Something went wrong. Please try again later.'
            ], 500);
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
                Rule::unique('leave_types', 'name')
                    ->where(function ($query) {
                        return $query->where('tenant_id', session('tenant_id'));
                    })
                    ->ignore($id)
            ],
            'credit_type' => 'required|in:weekly,monthly,yearly,no',
            'credit_value' => [
                'required',
                'numeric',
                function ($attribute, $value, $fail) use ($request) {
                    if ($request->credit_type === 'no' && $value != 0) {
                        $fail('Credit value must be 0 when credit type is "no".');
                    }

                    if ($request->credit_type !== 'no' && $value <= 0) {
                        $fail('Credit value must be greater than 0.');
                    }
                },
            ],
            'description' => 'nullable|string|max:1000',
            'status' => 'nullable|in:0,1',
        ]);

        try {
            // Using Eloquent findOrFail
            $type = LeaveType::findOrFail($id);

            // Using Eloquent update method
            $type->update([
                'name' => $request->name,
                'description' => $request->description,
                'credit_type' => $request->credit_type,
                'credit_value' => $request->credit_value,
                'status' => $request->status ?? $type->status,

            ]);

            return response()->json([
                'success' => true,
                'message' => 'Leave Type Updated Successfully!'
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Leave Type not found.'
            ], 404);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Something went wrong. Please try again later.'
            ], 500);
        }
    }

    public function status(Request $request)
    {
        $request->validate([
            'status' => 'required|in:0,1',
            'id' => 'required|exists:leave_types,id'
        ]);

        try {
            // Using Eloquent find
            $type = LeaveType::find($request->id);

            if (!$type) {
                return response()->json([
                    'success' => false,
                    'message' => 'Leave Type not found.'
                ], 404);
            }

            // Using Eloquent update
            $type->update([
                'status' => $request->status,
                'updated_by' => Auth::id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Status Updated Successfully'
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Something went wrong. Please try again later.'
            ], 500);
        }
    }

    /**
     * Optional: Add a destroy method if needed
     */
    public function destroy($id)
    {
        try {
            $id = decrypt($id);
            $type = LeaveType::findOrFail($id);

            // Check if leave type is being used in leaves table
            $leavesCount = $type->leaves()->count(); // Assuming you have relationship

            if ($leavesCount > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete leave type as it is being used in leave applications.'
                ], 400);
            }

            $type->delete();

            return response()->json([
                'success' => true,
                'message' => 'Leave Type Deleted Successfully!'
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Leave Type not found.'
            ], 404);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Something went wrong. Please try again later.'
            ], 500);
        }
    }
}
