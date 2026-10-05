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
    /**
     * Carry forward (applied by `leaves:carry-forward` when Company Policies → Leave carry forward is on):
     * the days that move into the next credit period — required for credited paid types, up to the
     * credit value. Expiry applies to yearly types only.
     */
    private function carryForwardRules(Request $request): array
    {
        $applies = ! $request->boolean('is_unpaid') && $request->credit_type !== 'no';

        return [
            'max_carry_forward' => [
                $applies ? 'required' : 'nullable',
                'numeric',
                'min:0',
                function ($attribute, $value, $fail) use ($request, $applies) {
                    if ($applies && is_numeric($request->credit_value) && (float) $value > (float) $request->credit_value) {
                        $fail('Carry forward cannot be more than the credit value (' . (float) $request->credit_value . ').');
                    }
                },
            ],
            'carry_forward_expiry_months' => 'nullable|integer|min:1|max:12',
        ];
    }

    private const CARRY_FORWARD_MESSAGES = [
        'max_carry_forward.required' => 'Enter how many days carry forward (0 = none).',
        'max_carry_forward.min' => 'Carry forward cannot be negative.',
        'carry_forward_expiry_months.min' => 'Expiry must be 1 to 12 months (leave blank for never).',
        'carry_forward_expiry_months.max' => 'Expiry must be 1 to 12 months (leave blank for never).',
    ];

    /**
     * Blank expiry = never. Unpaid types and types that are never credited keep
     * no balance, so both are cleared; expiry is kept for yearly types only.
     */
    private function carryForwardValues(Request $request): array
    {
        if ($request->boolean('is_unpaid') || $request->credit_type === 'no') {
            return ['max_carry_forward' => null, 'carry_forward_expiry_months' => null];
        }

        return [
            'max_carry_forward' => $request->filled('max_carry_forward') ? round((float) $request->max_carry_forward, 2) : null,
            'carry_forward_expiry_months' => $request->credit_type === 'yearly' && $request->filled('carry_forward_expiry_months')
                ? (int) $request->carry_forward_expiry_months : null,
        ];
    }

    public function index(Request $request)
    {
        // Using Eloquent instead of DB::table()
        $data['leaveTypes'] = LeaveType::orderBy('name')->get();
        $data['carryForwardEnabled'] = (bool) DB::table('tenants')->where('id', Auth::user()->tenant_id)->value('leave_carry_forward_enabled');
        return view('client.leave.leave-type', $data);
    }

    public function detail(Request $request, $id)
    {
        try {
            $id = decrypt($id);
            // Using Eloquent with findOrFail for better error handling
            $data['leaveType'] = LeaveType::findOrFail($id);
            $data['carryForwardEnabled'] = (bool) DB::table('tenants')->where('id', Auth::user()->tenant_id)->value('leave_carry_forward_enabled');
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
            'is_unpaid' => 'nullable|boolean',
        ] + $this->carryForwardRules($request), self::CARRY_FORWARD_MESSAGES);

        try {
            // Using Eloquent create method
            $type = LeaveType::create($this->carryForwardValues($request) + [
                'name' => $request->name,
                'description' => $request->description,
                'credit_type' => $request->credit_type,
                'credit_value' => $request->credit_value,
                'status' => 1, // Default status
                // is_unpaid is the single authoritative paid/unpaid signal
                // (LeaveService/Payroll/Performance all read it) — a tenant
                // can flag any custom type as unpaid, not just the one
                // system LWP row.
                'is_unpaid' => $request->boolean('is_unpaid'),
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
            'is_unpaid' => 'nullable|boolean',
        ] + $this->carryForwardRules($request), self::CARRY_FORWARD_MESSAGES);

        try {
            // Using Eloquent findOrFail
            $type = LeaveType::findOrFail($id);

            if ($type->isSystemType()) {
                return response()->json([
                    'success' => false,
                    'message' => 'This is a system-managed leave type and cannot be edited.'
                ], 403);
            }

            // Using Eloquent update method
            $type->update($this->carryForwardValues($request) + [
                'name' => $request->name,
                'description' => $request->description,
                'credit_type' => $request->credit_type,
                'credit_value' => $request->credit_value,
                'status' => $request->status ?? $type->status,
                // Preserve the existing flag when the field isn't sent at
                // all (e.g. a form that doesn't yet expose this control),
                // rather than silently resetting it to false.
                'is_unpaid' => $request->has('is_unpaid') ? $request->boolean('is_unpaid') : $type->is_unpaid,

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

            if ($type->isSystemType()) {
                return response()->json([
                    'success' => false,
                    'message' => 'This is a system-managed leave type and cannot be deactivated.'
                ], 403);
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

            if ($type->isSystemType()) {
                return response()->json([
                    'success' => false,
                    'message' => 'This is a system-managed leave type and cannot be deleted.'
                ], 403);
            }

            // Check if leave type is being used in leaves table
            $leavesCount = $type->leaves()->count();

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
