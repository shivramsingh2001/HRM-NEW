<?php

namespace App\Http\Controllers\Expense;

use App\Http\Controllers\Controller;
use App\Models\ExpenseType;
use App\Services\AuditLogger;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * Expense categories + the company's optional claim POLICY for each
 * (max_amount, receipt_required_above, max_backdate_days — see ExpensePolicyService).
 * Routes are behind permission:expenses,manage.
 */
class ExpenseTypeController extends Controller
{
    /** Optional policy fields; empty input means "no rule" (stored as NULL). */
    private const POLICY_RULES = [
        'max_amount' => 'nullable|numeric|gt:0|max:9999999.99',
        'receipt_required_above' => 'nullable|numeric|min:0|max:9999999.99',
        'max_backdate_days' => 'nullable|integer|min:0|max:3650',
    ];

    public function index(Request $request)
    {
        $data['expenseTypes'] = ExpenseType::orderBy('name')->get();
        return view('client.expense.type.type', $data);
    }

    public function store(Request $request)
    {
        $tenantId = Auth::user()->tenant_id;

        $request->validate([
            'name' => [
                'required',
                'max:255',
                'string',
                // Was `use ($tenantId)` with $tenantId never defined -> an ErrorException, so creating an
                // expense type returned HTTP 500 for everyone.
                Rule::unique('expense_types', 'name')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'description' => 'nullable|string|max:1000',
        ] + self::POLICY_RULES);

        try {
            $type = new ExpenseType();
            $type->name = $request->name;
            $type->description = $request->description;
            $this->fillPolicy($type, $request);
            $type->save();

            $this->audit('expenses.type_created', $type);

            return response()->json(['success' => true, 'message' => 'Expense Type Created Successfully!!!'], 200);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => 'Something went wrong. Please try again later.'], 500);
        }
    }

    public function update(Request $request)
    {
        $id = $request->id;
        $tenantId = Auth::user()->tenant_id;

        $request->validate([
            'name' => [
                'required',
                'max:255',
                'string',
                Rule::unique('expense_types', 'name')->where(fn ($query) => $query->where('tenant_id', $tenantId))->ignore($id),
            ],
            'description' => 'nullable|string|max:1000',
            'status' => 'required|in:0,1',
        ] + self::POLICY_RULES);

        try {
            $type = ExpenseType::findOrFail($id);
            $type->name = $request->name;
            $type->status = $request->status;
            $type->description = $request->description;
            $this->fillPolicy($type, $request);
            $type->save();

            $this->audit('expenses.type_updated', $type);

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

    private function fillPolicy(ExpenseType $type, Request $request): void
    {
        foreach (array_keys(self::POLICY_RULES) as $field) {
            $type->{$field} = $request->filled($field) ? $request->input($field) : null;
        }
    }

    private function audit(string $action, ExpenseType $type): void
    {
        app(AuditLogger::class)->record('tenant_user', Auth::id(), (int) $type->tenant_id, $action, 'ExpenseType', (int) $type->id, [], [
            'name' => $type->name,
            'max_amount' => $type->max_amount,
            'receipt_required_above' => $type->receipt_required_above,
            'max_backdate_days' => $type->max_backdate_days,
        ]);
    }
}
