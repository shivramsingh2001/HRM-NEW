<?php

namespace App\Http\Controllers\Expense;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\ExpenseBudget;
use App\Models\ExpenseType;
use App\Models\Project;
use App\Services\AuditLogger;
use App\Services\Expense\ExpenseBudgetService;
use App\Support\Money;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Expense budgets (a cap on approved spend per fiscal year, optionally per department / project /
 * category) — see ExpenseBudgetService for how usage is counted and enforced on approval.
 * Routes are behind permission:expenses,manage.
 */
class ExpenseBudgetController extends Controller
{
    public function __construct(private ExpenseBudgetService $budgets)
    {
    }

    public function index(Request $request)
    {
        $fiscalYear = $request->input('fiscal_year');
        if (! is_string($fiscalYear) || ! preg_match('/^\d{4}(-\d{2})?$/', $fiscalYear)) {
            $fiscalYear = $this->budgets->currentFiscalYear();
        }

        $rows = ExpenseBudget::with(['department:id,name', 'project:id,name', 'expenseType:id,name'])
            ->where('fiscal_year', $fiscalYear)->orderBy('id')->get()
            ->map(function (ExpenseBudget $b) {
                $allocated = Money::toCents($b->allocated_amount);
                $used = $this->budgets->usedCents($b);

                return [
                    'id' => $b->id,
                    'label' => $this->budgets->describe($b),
                    'department_id' => $b->department_id,
                    'project_id' => $b->project_id,
                    'expense_type_id' => $b->expense_type_id,
                    'allocated' => Money::fromCents($allocated),
                    'used' => Money::fromCents($used),
                    'remaining' => Money::fromCents($allocated - $used),
                    'percent' => $allocated > 0 ? round($used / $allocated * 100, 1) : ($used > 0 ? 100.0 : 0.0),
                    'enforcement' => $b->enforcement,
                ];
            });

        // The years worth listing: current, next, previous, and any that already have budgets.
        $known = ExpenseBudget::query()->distinct()->pluck('fiscal_year')->all();
        $now = now();
        $years = collect([
            $this->budgets->fiscalYearFor($now->copy()->subYear()),
            $this->budgets->fiscalYearFor($now),
            $this->budgets->fiscalYearFor($now->copy()->addYear()),
        ])->merge($known)->unique()->sort()->values();

        return view('client.expense.expense.budgets', [
            'fiscalYear' => $fiscalYear,
            'years' => $years,
            'budgets' => $rows,
            'departments' => Department::where('status', 1)->orderBy('name')->get(['id', 'name']),
            'projects' => Project::where('status', '!=', 'cancelled')->orderBy('name')->get(['id', 'name']),
            'expenseTypes' => ExpenseType::where('status', 1)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request)
    {
        $v = $this->validator($request, creating: true);
        if ($v->fails()) {
            return response()->json(['success' => false, 'message' => $v->errors()->first(), 'errors' => $v->errors()], 422);
        }

        $dims = [
            'department_id' => $request->filled('department_id') ? (int) $request->department_id : null,
            'project_id' => $request->filled('project_id') ? (int) $request->project_id : null,
            'expense_type_id' => $request->filled('expense_type_id') ? (int) $request->expense_type_id : null,
        ];

        // The DB unique key cannot see duplicates when a dimension is NULL, so check it here.
        $exists = ExpenseBudget::where('fiscal_year', $request->fiscal_year)
            ->where(fn ($q) => $dims['department_id'] === null ? $q->whereNull('department_id') : $q->where('department_id', $dims['department_id']))
            ->where(fn ($q) => $dims['project_id'] === null ? $q->whereNull('project_id') : $q->where('project_id', $dims['project_id']))
            ->where(fn ($q) => $dims['expense_type_id'] === null ? $q->whereNull('expense_type_id') : $q->where('expense_type_id', $dims['expense_type_id']))
            ->exists();

        if ($exists) {
            return response()->json(['success' => false, 'message' => 'A budget for exactly this scope and fiscal year already exists — edit it instead.'], 422);
        }

        try {
            $budget = ExpenseBudget::create($dims + [
                'fiscal_year' => $request->fiscal_year,
                'allocated_amount' => $request->allocated_amount,
                'used_amount' => 0,
                'remaining_amount' => $request->allocated_amount,
                'enforcement' => $request->enforcement,
            ]);
            $this->audit('expenses.budget_created', $budget);

            return response()->json(['success' => true, 'message' => 'Budget created.']);
        } catch (Exception $e) {
            return $this->failure($e, 'store');
        }
    }

    /** Only the cap and the enforcement mode change; scope and fiscal year are fixed (delete + recreate to move a budget). */
    public function update(Request $request, $id)
    {
        $v = $this->validator($request, creating: false);
        if ($v->fails()) {
            return response()->json(['success' => false, 'message' => $v->errors()->first(), 'errors' => $v->errors()], 422);
        }

        try {
            $budget = ExpenseBudget::findOrFail($id);
            $budget->update(['allocated_amount' => $request->allocated_amount, 'enforcement' => $request->enforcement]);
            $this->refreshUsage($budget);
            $this->audit('expenses.budget_updated', $budget);

            return response()->json(['success' => true, 'message' => 'Budget updated.']);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return response()->json(['success' => false, 'message' => 'Budget not found.'], 404);
        } catch (Exception $e) {
            return $this->failure($e, 'update');
        }
    }

    public function destroy($id)
    {
        try {
            $budget = ExpenseBudget::findOrFail($id);
            $this->audit('expenses.budget_deleted', $budget);
            $budget->delete();

            return response()->json(['success' => true, 'message' => 'Budget deleted.']);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return response()->json(['success' => false, 'message' => 'Budget not found.'], 404);
        } catch (Exception $e) {
            return $this->failure($e, 'destroy');
        }
    }

    // ------------------------------------------------------------------

    private function validator(Request $request, bool $creating)
    {
        $rules = [
            'allocated_amount' => 'required|numeric|min:0|max:99999999999.99',
            'enforcement' => 'required|in:warn,block',
        ];

        if ($creating) {
            // Department / Project / ExpenseType are tenant-scoped models, so exists-checks through them
            // reject another company's ids.
            $rules += [
                'fiscal_year' => ['required', 'regex:/^\d{4}(-\d{2})?$/'],
                'department_id' => ['nullable', 'integer', fn ($a, $v, $fail) => Department::whereKey($v)->exists() ?: $fail('The selected department is invalid.')],
                'project_id' => ['nullable', 'integer', fn ($a, $v, $fail) => Project::whereKey($v)->exists() ?: $fail('The selected project is invalid.')],
                'expense_type_id' => ['nullable', 'integer', fn ($a, $v, $fail) => ExpenseType::whereKey($v)->exists() ?: $fail('The selected category is invalid.')],
            ];
        }

        return Validator::make($request->all(), $rules, ['fiscal_year.regex' => 'The fiscal year must look like 2026-27.']);
    }

    /** Recompute the convenience columns for one budget from live approved spend. */
    private function refreshUsage(ExpenseBudget $budget): void
    {
        $used = $this->budgets->usedCents($budget);
        $budget->forceFill([
            'used_amount' => Money::fromCents($used),
            'remaining_amount' => Money::fromCents(Money::toCents($budget->allocated_amount) - $used),
        ])->save();
    }

    private function audit(string $action, ExpenseBudget $budget): void
    {
        app(AuditLogger::class)->record('tenant_user', Auth::id(), (int) $budget->tenant_id, $action, 'ExpenseBudget', (int) $budget->id, [], [
            'fiscal_year' => $budget->fiscal_year, 'scope' => $this->budgets->describe($budget->loadMissing(['department:id,name', 'project:id,name', 'expenseType:id,name'])),
            'allocated' => (string) $budget->allocated_amount, 'enforcement' => $budget->enforcement,
        ]);
    }

    private function failure(Exception $e, string $context)
    {
        Log::error("Expense budget {$context} failed: " . $e->getMessage(), ['user_id' => Auth::id()]);

        return response()->json(['success' => false, 'message' => 'Something went wrong. Please try again or contact support.'], 500);
    }
}
