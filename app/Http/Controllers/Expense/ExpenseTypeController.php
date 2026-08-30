<?php

namespace App\Http\Controllers\Expense;

use App\Http\Controllers\Controller;
use App\Models\ExpenseType;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;


class ExpenseTypeController extends Controller
{
    public function index(Request $request)
    {
        $data['expenseTypes'] = ExpenseType::orderBy('name')->get();
        return view('client.expense.type.type', $data);
    }
    public function store(Request $request)
    {
        $request->validate([
             'name' => [
                'required',
                'max:255',
                'string',
                Rule::unique('expense_types', 'name')->where(function ($query) use ($tenantId) {
                    return $query->where('tenant_id', session('tenant_id'));
                }),
            ],
            'description' => 'nullable|string|max:1000',
        ]);
        $user = Auth::id();
        try {
            $type = new ExpenseType();
            $type->name = $request->name;
            $type->description = $request->description;
            $type->save();
            return response()->json(['success' => true, 'message' => 'Expense Type Created Successfully!!!'], 200);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => 'Something went wrong. Please try again later.'], 500);
        }
    }
    public function update(Request $request)
    {
        $id = $request->id;
        $request->validate([
            'name' => [
                'required',
                'max:255',
                'string',
                Rule::unique('expense_types', 'name')
                    ->where(function ($query) {
                        return $query->where('tenant_id', session('tenant_id'));
                    })
                    ->ignore($id)
            ],
            'description' => 'nullable|string|max:1000',
            'status' => 'required|in:0,1'
        ]);
        try {
            $type = ExpenseType::findOrFail($id);
            $type->name = $request->name;
            $type->status = $request->status;
            $type->description = $request->description;
            $type->save();
            return response()->json(['success' => true, 'message' => 'Expense Type Updated Successfully!'], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Expense Type not found.'], 404);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => 'Something went wrong. Please try again later.'], 500);
        }
    }
    public function status(Request $request)
    {
        $request->validate([
            'status' => 'required|in:0,1',
            'id' => 'required|exists:expense_types,id'
        ]);
        try {
            $type = ExpenseType::find($request->id);
            if (!$type) {
                return response()->json(['success' => false, 'message' => 'Expense Type not found.'], 404);
            }
            $type->status = $request->status;
            $type->save();
            return response()->json(['success' => true, 'message' => 'Status Updated Successfully'], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Something went wrong. Please try again later.'
            ], 500);
        }
    }
}
