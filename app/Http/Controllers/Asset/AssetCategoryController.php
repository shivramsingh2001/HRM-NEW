<?php

namespace App\Http\Controllers\Asset;

use App\Http\Controllers\Controller;
use App\Models\AssetCategory;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Asset category reference data (e.g. IT Equipment, Furniture, Vehicles).
 * Mirrors BranchController's small-CRUD shape.
 */
class AssetCategoryController extends Controller
{
    public function index(Request $request)
    {
        $categories = AssetCategory::withCount('assets')->orderBy('name')->get();

        return view('client.asset.categories.index', compact('categories'));
    }

    public function store(Request $request)
    {
        $tenantId = session('tenant_id');
        $request->validate([
            'name' => [
                'required', 'max:150', 'string',
                Rule::unique('asset_categories', 'name')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
            'code' => 'nullable|string|max:20',
            'description' => 'nullable|string|max:1000',
            'status' => 'nullable|in:0,1',
        ]);

        try {
            $category = AssetCategory::create([
                'name' => $request->name,
                'code' => $request->code,
                'description' => $request->description,
                'status' => $request->status ?? 1,
            ]);

            return response()->json(['success' => true, 'message' => 'Category created successfully.', 'data' => $category]);
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
                'required', 'max:150', 'string',
                Rule::unique('asset_categories', 'name')->where(fn ($q) => $q->where('tenant_id', session('tenant_id')))->ignore($id),
            ],
            'code' => 'nullable|string|max:20',
            'description' => 'nullable|string|max:1000',
            'status' => 'nullable|in:0,1',
        ]);

        try {
            $category = AssetCategory::findOrFail($id);
            $category->update([
                'name' => $request->name,
                'code' => $request->code,
                'description' => $request->description,
                'status' => $request->status ?? $category->status,
            ]);

            return response()->json(['success' => true, 'message' => 'Category updated successfully.']);
        } catch (\Exception $e) {
            report($e);
            return response()->json(['success' => false, 'message' => 'Something went wrong. Please try again later.'], 500);
        }
    }

    public function destroy(Request $request, $id)
    {
        try {
            $id = decrypt($id);
            $category = AssetCategory::findOrFail($id);

            $assignedCount = $category->assets()->count();
            if ($assignedCount > 0) {
                return response()->json([
                    'success' => false,
                    'message' => "Re-categorize {$assignedCount} asset(s) before deleting this category.",
                ], 422);
            }

            $category->delete();

            return response()->json(['success' => true, 'message' => 'Category deleted successfully.']);
        } catch (\Exception $e) {
            report($e);
            return response()->json(['success' => false, 'message' => 'Something went wrong. Please try again later.'], 500);
        }
    }
}
