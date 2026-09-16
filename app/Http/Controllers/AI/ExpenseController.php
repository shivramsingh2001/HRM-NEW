<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Services\RbacService;

class ExpenseController extends Controller
{
    public function view_ai_all(Request $request)
    {
        try {
            $authUser = Auth::user();
            $baseUrl = config('app.url');

            // Base query
            $query = Expense::join('users', 'expenses.user_id', '=', 'users.id')
                ->join('user_job_details', 'user_job_details.user_id', '=', 'users.id')
                ->join('user_basic_details', 'user_basic_details.user_id', '=', 'users.id')
                ->leftJoin('expense_types', 'expenses.expense_type', '=', 'expense_types.id')
                ->leftJoin('projects', 'expenses.project_id', '=', 'projects.id')
                ->select(
                    'expenses.id',
                    'expenses.expense_number',
                    'users.employee_id',
                    'users.name as employee_name',
                    'users.id as user_id',
                    'users.role as user_role',
                    DB::raw("
                    CASE 
                        WHEN user_basic_details.profile_image IS NULL OR user_basic_details.profile_image = '' 
                        THEN NULL
                        ELSE CONCAT('$baseUrl', user_basic_details.profile_image)
                    END as employee_profile_image
                "),
                    'expense_types.name as expense_type',
                    'expense_types.id as expense_type_id',
                    'expenses.date as expense_date',
                    'projects.name as project_name',
                    'projects.project_code as project_code',
                    'projects.id as project_id',
                    'expenses.requirement_type',
                    'expenses.amount',
                    'expenses.description',
                    'expenses.status',
                    'expenses.is_billable',
                    'expenses.created_at',
                    DB::raw("
                    CASE 
                        WHEN expenses.file IS NULL OR expenses.file = '' 
                        THEN NULL
                        ELSE CONCAT('$baseUrl/', expenses.file)
                    END as file_url
                ")
                );

            // Permission-based filtering (was a fixed role switch that
            // silently locked out any custom role holding a real
            // expenses:view grant).
            $expenseScope = app(RbacService::class)->scopeFor($authUser, 'expenses', 'view');
            switch ($expenseScope) {
                case 'company':
                    // See all expenses (no filter)
                    break;

                case 'team':
                    $query->where(function ($q) use ($authUser) {
                        $q->where('user_job_details.reporting_head', $authUser->id) // Team members
                            ->orWhere('expenses.user_id', $authUser->id); // Own expenses
                    });
                    break;

                case 'own':
                    $query->where('expenses.user_id', $authUser->id);
                    break;

                default:
                    return response()->json([
                        'success' => false,
                        'message' => 'Unauthorized access. Invalid role.'
                    ], 200);
            }
            // Order by
            $query->orderBy('expenses.created_at', 'desc');

            $expenses = $query->get();

            // Calculate summary statistics
            $summary = [
                'total_expenses' => $expenses->count(),
                'total_amount' => $expenses->sum('amount'),
                'by_status' => [
                    'pending' => $expenses->where('status', 'pending')->count(),
                    'approved' => $expenses->where('status', 'approved')->count(),
                    'complete' => $expenses->where('status', 'complete')->count(),
                    'cancelled' => $expenses->where('status', 'cancelled')->count(),
                ],
                'by_requirement_type' => [
                    'advance' => $expenses->where('requirement_type', 'advance')->count(),
                    'settlement' => $expenses->where('requirement_type', 'settlement')->count(),
                ],
                'total_amount_by_status' => [
                    'pending' => $expenses->where('status', 'pending')->sum('amount'),
                    'approved' => $expenses->where('status', 'approved')->sum('amount'),
                    'complete' => $expenses->where('status', 'complete')->sum('amount'),
                    'cancelled' => $expenses->where('status', 'cancelled')->sum('amount'),
                ]
            ];

            // Group by employee if the caller can see more than just their own
            $groupedByEmployee = null;
            if ($expenseScope !== 'own') {
                $groupedByEmployee = $expenses->groupBy('user_id')->map(function ($userExpenses, $userId) {
                    $first = $userExpenses->first();
                    return [
                        'user_id' => $userId,
                        'employee_id' => $first->employee_id,
                        'employee_name' => $first->employee_name,
                        'total_expenses' => $userExpenses->count(),
                        'total_amount' => $userExpenses->sum('amount'),
                        'expenses' => $userExpenses
                    ];
                })->values();
            }

            return response()->json([
                'success' => true,
                'message' => 'Expense data fetched successfully!',
                'data' => $expenses,
                'summary' => $summary,
                'grouped_by_employee' => $groupedByEmployee,
                
            ], 200);
        } catch (Exception $e) {
           
            return response()->json([
                'success' => false,
                'message' => 'An error occurred. Please try again later.',
             
            ], 500);
        }
    }
}
