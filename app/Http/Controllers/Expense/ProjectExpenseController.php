<?php

namespace App\Http\Controllers\Expense;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\ProjectExpese;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ProjectExpenseController extends Controller
{
    public function index(Request $request)
    {
        $id = Auth::id();
        $baseUrl = config('app.url');
        $authUser = Auth::user();
        $data['projects'] = DB::table('projects as p')
            ->select([
                'p.id',
                'p.project_code',
                'p.name',
            ])
            ->get();

        $data['expenseTypes'] = DB::table('expense_types')->where('status', 1)->get();
        $data['expenses'] = DB::table('project_expeses')
            ->leftJoin('expense_types', 'project_expeses.expense_type', '=', 'expense_types.id')
            ->leftJoin('projects', 'project_expeses.project_id', '=', 'projects.id')
            ->select([
                'project_expeses.id',
                'project_expeses.amount',
                'project_expeses.description',
                'expense_types.name as expense_type',
                'project_expeses.expense_type as expense_typeid',
                'projects.name as project_name',
                'projects.id as project_id',
                DB::raw("
                        CASE 
                            WHEN expenses.file IS NULL OR expenses.file = '' 
                            THEN NULL
                            ELSE CONCAT('$baseUrl', expenses.file)
                        END as file
                    "),
                'project_expeses.status',
            ])
            ->get();
        return view('client.expense.expense.project-expense', $data);
    }
    public function store(Request $request)
    {
        $request->validate([
            'expense_type' => 'required|exists:expense_types,id',
            'project_id' => 'required|exists:projects,id',
            'description' => 'nullable|string|max:1000',
            'file' => 'nullable|file|max:2048',
            'amount' => 'required|numeric',
        ]);
        $user = Auth::id();
        try {
            $expense = new ProjectExpese();
            $expense->user_id = $user;
            $expense->expense_type = $request->expense_type;
            $expense->amount = $request->amount;
            $expense->created_by = $user;
            $expense->project_id = $request->project_id;
            $expense->description = $request->description;
            if ($request->hasFile('file')) {
                $file = $request->file('file');
                $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                $directory = public_path('uploads/expense');
                if (!file_exists($directory)) {
                    mkdir($directory, 0755, true);
                }
                $file->move($directory, $filename);
                $final = 'uploads/expense/' . $filename;
                $expense->file = $final;
            }
            $expense->save();
            return response()->json(['success' => true, 'message' => 'Project Expense Created Successfully!!!'], 200);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => 'Something went wrong. Please try again later.' . $e->getMessage()], 500);
        }
    }
    public function update(Request $request)
    {
        $id = $request->id;
        $request->validate([
            'expense_type' => 'required|exists:expense_types,id',
            'project_id' => 'required|exists:projects,id',
            'description' => 'nullable|string|max:1000',
            'file' => 'nullable|file|max:2048',
            'amount' => 'required|numeric',
        ]);
        $user = Auth::id();
        try {
            $expense = ProjectExpese::findOrFail($id);
            $expense->expense_type = $request->expense_type;
            $expense->amount = $request->amount;
            $expense->created_by = $user;
            $expense->project_id = $request->project_id;
            $expense->description = $request->description;
            if ($request->hasFile('file')) {
                $file = $request->file('file');
                $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                $directory = public_path('uploads/expense');
                if (!file_exists($directory)) {
                    mkdir($directory, 0755, true);
                }
                $file->move($directory, $filename);
                $final = 'uploads/expense/' . $filename;
                $expense->file = $final;
            }
            $expense->save();
            return response()->json(['success' => true, 'message' => 'Project Expense Updated Successfully!'], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Project Expense not found.'], 404);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => 'Something went wrong. Please try again later.' . $e->getMessage()], 500);
        }
    }
}
