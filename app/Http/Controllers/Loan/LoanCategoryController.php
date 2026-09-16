<?php

namespace App\Http\Controllers\Loan;

use App\Http\Controllers\Controller;
use App\Models\LoanCategory;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class LoanCategoryController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = LoanCategory::query();

            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            $data['categories'] = $query->orderBy('sort_order', 'asc')->orderBy('created_at', 'asc')->get();
            return view('client.loan.category.index', $data);
        } catch (Exception $e) {
            return back()->withErrors('An error occurred. Please try again later.');
        }
    }

    /**
     * Store a newly created loan category
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
            'code' => 'nullable|string|max:20|unique:loan_categories,code',
            'max_amount' => 'nullable|numeric|min:0',
            'default_interest_rate' => 'nullable|numeric|min:0|max:100',
            'max_tenure_months' => 'nullable|integer|min:1|max:60',
            'requires_approval' => 'boolean',
            'status' => 'boolean',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // validated(), not all() — LoanCategory uses $guarded = [], so
            // the raw request let any extra client-supplied field through
            // untouched (e.g. tenant_id, id), bypassing tenant auto-stamping.
            $category = LoanCategory::create($validator->validated());

            return response()->json([
                'success' => true,
                'message' => 'Loan category created successfully',
                'data' => $category
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred. Please try again later.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified loan category
     */
    public function show($id)
    {
        try {
            $category = LoanCategory::findOrFail($id);
            return response()->json([
                'success' => true,
                'data' => $category
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Category not found'
            ], 404);
        }
    }

    /**
     * Update the specified loan category
     */
    public function update(Request $request, $id)
    {
        $category = LoanCategory::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:100',
            'code' => [
                'sometimes',
                'string',
                'max:20',
                Rule::unique('loan_categories', 'code')->ignore($category->id)
            ],
            'max_amount' => 'nullable|numeric|min:0',
            'default_interest_rate' => 'nullable|numeric|min:0|max:100',
            'max_tenure_months' => 'nullable|integer|min:1|max:60',
            'requires_approval' => 'boolean',
            'status' => 'boolean',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $category->update($validator->validated());

            return response()->json([
                'success' => true,
                'message' => 'Loan category updated successfully',
                'data' => $category
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred. Please try again later.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified loan category
     */
    public function destroy($id)
    {
        try {
            $category = LoanCategory::findOrFail($id);

            // Check if category has any loans
            if ($category->loans()->count() > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete category as it has associated loans'
                ], 400);
            }

            $category->delete();

            return response()->json([
                'success' => true,
                'message' => 'Loan category deleted successfully'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred. Please try again later.'
            ], 500);
        }
    }

    /**
     * Get active categories for dropdown
     */
    public function getActiveCategories(Request $request)
    {
        try {
            $query = LoanCategory::where('status', true);

            if ($request->has('tenant_id')) {
                $query->where('tenant_id', $request->tenant_id);
            } else {
                $query->whereNull('tenant_id');
            }

            $categories = $query->orderBy('created_at', 'asc')->get();

            return response()->json([
                'success' => true,
                'data' => $categories
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred'
            ], 500);
        }
    }

    /**
     * Calculate EMI for a category
     */
    public function calculateEmi(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:1',
            'tenure_months' => 'required|integer|min:1'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $category = LoanCategory::findOrFail($id);

            // Validate amount against category limit
            if (!$category->validateAmount($request->amount)) {
                return response()->json([
                    'success' => false,
                    'message' => "Amount exceeds maximum limit of {$category->max_amount}"
                ], 400);
            }

            // Validate tenure against category limit
            if (!$category->validateTenure($request->tenure_months)) {
                return response()->json([
                    'success' => false,
                    'message' => "Tenure exceeds maximum limit of {$category->max_tenure_months} months"
                ], 400);
            }

            $emi = $category->calculateEmi($request->amount, $request->tenure_months);
            $totalPayable = $emi * $request->tenure_months;
            $totalInterest = $totalPayable - $request->amount;

            return response()->json([
                'success' => true,
                'data' => [
                    'emi_amount' => number_format($emi, 2),
                    'total_payable' => number_format($totalPayable, 2),
                    'total_interest' => number_format($totalInterest, 2),
                    'monthly_interest_rate' => number_format($category->default_interest_rate / 12, 2)
                ]
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred'
            ], 500);
        }
    }
}