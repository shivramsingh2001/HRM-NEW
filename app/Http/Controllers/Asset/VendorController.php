<?php

namespace App\Http\Controllers\Asset;

use App\Http\Controllers\Controller;
use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class VendorController extends Controller
{
    public function index(Request $request)
    {
        $vendors = Vendor::withCount('assets')->orderBy('name')->get();

        return view('client.asset.vendors.index', compact('vendors'));
    }

    public function store(Request $request)
    {
        $tenantId = session('tenant_id');
        $request->validate([
            'name' => [
                'required', 'max:150', 'string',
                Rule::unique('vendors', 'name')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
            'contact_person' => 'nullable|string|max:150',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:150',
            'address' => 'nullable|string|max:1000',
            'tax_number' => 'nullable|string|max:50',
            'status' => 'nullable|in:0,1',
        ]);

        try {
            $vendor = Vendor::create($request->only([
                'name', 'contact_person', 'phone', 'email', 'address', 'tax_number',
            ]) + ['status' => $request->status ?? 1]);

            return response()->json(['success' => true, 'message' => 'Vendor created successfully.', 'data' => $vendor]);
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
                Rule::unique('vendors', 'name')->where(fn ($q) => $q->where('tenant_id', session('tenant_id')))->ignore($id),
            ],
            'contact_person' => 'nullable|string|max:150',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:150',
            'address' => 'nullable|string|max:1000',
            'tax_number' => 'nullable|string|max:50',
            'status' => 'nullable|in:0,1',
        ]);

        try {
            $vendor = Vendor::findOrFail($id);
            $vendor->update($request->only([
                'name', 'contact_person', 'phone', 'email', 'address', 'tax_number',
            ]) + ['status' => $request->status ?? $vendor->status]);

            return response()->json(['success' => true, 'message' => 'Vendor updated successfully.']);
        } catch (\Exception $e) {
            report($e);
            return response()->json(['success' => false, 'message' => 'Something went wrong. Please try again later.'], 500);
        }
    }

    public function destroy(Request $request, $id)
    {
        try {
            $id = decrypt($id);
            $vendor = Vendor::findOrFail($id);

            $assignedCount = $vendor->assets()->count();
            if ($assignedCount > 0) {
                return response()->json([
                    'success' => false,
                    'message' => "Re-assign {$assignedCount} asset(s) to another vendor before deleting.",
                ], 422);
            }

            $vendor->delete();

            return response()->json(['success' => true, 'message' => 'Vendor deleted successfully.']);
        } catch (\Exception $e) {
            report($e);
            return response()->json(['success' => false, 'message' => 'Something went wrong. Please try again later.'], 500);
        }
    }
}
