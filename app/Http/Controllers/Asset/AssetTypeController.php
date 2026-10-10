<?php

namespace App\Http\Controllers\Asset;

use App\Http\Controllers\Controller;
use App\Models\AssetCategory;
use App\Models\AssetType;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AssetTypeController extends Controller
{
    public function index(Request $request)
    {
        $types = AssetType::with('category')->withCount('assets')->orderBy('name')->get();
        $categories = AssetCategory::active()->orderBy('name')->get();

        return view('client.asset.types.index', compact('types', 'categories'));
    }

    public function store(Request $request)
    {
        $tenantId = session('tenant_id');
        $request->validate([
            'asset_category_id' => 'nullable|exists:asset_categories,id',
            'name' => [
                'required', 'max:150', 'string',
                Rule::unique('asset_types', 'name')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
            'code' => 'nullable|string|max:20',
            'description' => 'nullable|string|max:1000',
            'status' => 'nullable|in:0,1',
        ]);

        try {
            $type = AssetType::create([
                'asset_category_id' => $request->asset_category_id,
                'name' => $request->name,
                'code' => $request->code,
                'description' => $request->description,
                'status' => $request->status ?? 1,
            ]);

            return response()->json(['success' => true, 'message' => 'Type created successfully.', 'data' => $type]);
        } catch (\Exception $e) {
            report($e);
            return response()->json(['success' => false, 'message' => 'Something went wrong. Please try again later.'], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $id = decrypt($id);
        $request->validate([
            'asset_category_id' => 'nullable|exists:asset_categories,id',
            'name' => [
                'required', 'max:150', 'string',
                Rule::unique('asset_types', 'name')->where(fn ($q) => $q->where('tenant_id', session('tenant_id')))->ignore($id),
            ],
            'code' => 'nullable|string|max:20',
            'description' => 'nullable|string|max:1000',
            'status' => 'nullable|in:0,1',
        ]);

        try {
            $type = AssetType::findOrFail($id);
            $type->update([
                'asset_category_id' => $request->asset_category_id,
                'name' => $request->name,
                'code' => $request->code,
                'description' => $request->description,
                'status' => $request->status ?? $type->status,
            ]);

            return response()->json(['success' => true, 'message' => 'Type updated successfully.']);
        } catch (\Exception $e) {
            report($e);
            return response()->json(['success' => false, 'message' => 'Something went wrong. Please try again later.'], 500);
        }
    }

    public function destroy(Request $request, $id)
    {
        try {
            $id = decrypt($id);
            $type = AssetType::findOrFail($id);

            $assignedCount = $type->assets()->count();
            if ($assignedCount > 0) {
                return response()->json([
                    'success' => false,
                    'message' => "Re-type {$assignedCount} asset(s) before deleting this type.",
                ], 422);
            }

            $type->delete();

            return response()->json(['success' => true, 'message' => 'Type deleted successfully.']);
        } catch (\Exception $e) {
            report($e);
            return response()->json(['success' => false, 'message' => 'Something went wrong. Please try again later.'], 500);
        }
    }
}
