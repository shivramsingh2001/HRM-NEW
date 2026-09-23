<?php

namespace App\Http\Controllers\Asset;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetType;
use App\Models\CompanyBranch;
use App\Models\User;
use App\Models\Vendor;
use App\Services\Asset\AssetLifecycleService;
use App\Services\RbacService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AssetController extends Controller
{
    public function __construct(
        protected AssetLifecycleService $lifecycle,
        protected RbacService $rbac,
    ) {
    }

    /**
     * Admin/HR/manager asset register — filterable, paginated, bulk-action
     * ready list. Scope resolution mirrors TeamController::getTeamMembers():
     * company = every tenant asset, team = assets currently held by the
     * manager's direct reports, own = handled separately by myAssets().
     */
    public function index(Request $request)
    {
        $authUser = Auth::user();
        $scope = $this->rbac->scopeFor($authUser, 'assets', 'view');

        $query = Asset::with([
            'category', 'type', 'vendor', 'branch', 'currentAssigneeUser',
            'currentAssignment', 'activeRepair', 'activeDamageReport',
        ]);

        if ($scope === 'team') {
            $query->whereHas('currentAssigneeUser', fn ($q) => $q->managedBy($authUser->id));
        } elseif ($scope !== 'company') {
            $query->whereRaw('1 = 0');
        }

        $search = $request->get('search');
        $categoryId = $request->get('category_id');
        $typeId = $request->get('type_id');
        $status = $request->get('status');
        $branchId = $request->get('branch_id');
        $vendorId = $request->get('vendor_id');
        $assignedTo = $request->get('assigned_to');

        $query
            ->when($search, fn ($q) => $q->where(function ($sub) use ($search) {
                $sub->where('name', 'like', "%{$search}%")
                    ->orWhere('asset_code', 'like', "%{$search}%")
                    ->orWhere('serial_number', 'like', "%{$search}%");
            }))
            ->when($categoryId, fn ($q) => $q->where('asset_category_id', $categoryId))
            ->when($typeId, fn ($q) => $q->where('asset_type_id', $typeId))
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->when($vendorId, fn ($q) => $q->where('vendor_id', $vendorId))
            ->when($assignedTo, fn ($q) => $q->where('current_assignee_id', $assignedTo));

        $assets = $query->orderByDesc('created_at')->paginate(20)->appends($request->query());

        $stats = [
            'total' => Asset::count(),
            'assigned' => Asset::where('status', 'assigned')->count(),
            'available' => Asset::where('status', 'available')->count(),
            'in_repair' => Asset::where('status', 'in_repair')->count(),
            'disposed' => Asset::where('status', 'disposed')->count(),
        ];

        $categories = AssetCategory::active()->orderBy('name')->get();
        $types = AssetType::active()->orderBy('name')->get();
        $branches = CompanyBranch::active()->orderBy('name')->get();
        $vendors = Vendor::active()->orderBy('name')->get();
        $users = User::where('status', 1)->orderBy('name')->get();

        return view('client.asset.index', compact(
            'assets', 'stats', 'categories', 'types', 'branches', 'vendors', 'users', 'scope'
        ));
    }

    public function show(Request $request, $id)
    {
        $id = decrypt($id);
        $asset = Asset::with([
            'category', 'type', 'vendor', 'branch', 'currentAssigneeUser', 'createdBy',
            'assignments.user', 'assignments.assignedBy',
            'repairs.vendor', 'damageReports', 'disposal', 'histories.actor', 'attachments.uploadedBy',
        ])->findOrFail($id);

        $users = User::where('status', 1)->orderBy('name')->get();
        $branches = CompanyBranch::active()->orderBy('name')->get();
        $vendors = Vendor::active()->orderBy('name')->get();

        return view('client.asset.show', compact('asset', 'users', 'branches', 'vendors'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:200',
            'asset_category_id' => 'nullable|exists:asset_categories,id',
            'asset_type_id' => 'nullable|exists:asset_types,id',
            'serial_number' => 'nullable|string|max:100',
            'model_number' => 'nullable|string|max:100',
            'brand' => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'barcode_value' => 'nullable|string|max:100',
            'vendor_id' => 'nullable|exists:vendors,id',
            'purchase_date' => 'nullable|date',
            'purchase_cost' => 'nullable|numeric|min:0',
            'purchase_order_number' => 'nullable|string|max:100',
            'invoice_number' => 'nullable|string|max:100',
            'warranty_start_date' => 'nullable|date',
            'warranty_end_date' => 'nullable|date|after_or_equal:warranty_start_date',
            'warranty_provider' => 'nullable|string|max:150',
            'branch_id' => 'nullable|exists:company_branches,id',
            'location_notes' => 'nullable|string|max:255',
            'condition' => 'nullable|in:new,good,fair,poor,damaged',
            'depreciation_method' => 'nullable|in:straight_line,declining_balance,none',
            'salvage_value' => 'nullable|numeric|min:0',
            'useful_life_months' => 'nullable|integer|min:0',
            'notes' => 'nullable|string',
        ]);

        try {
            $asset = $this->lifecycle->register($request->all(), Auth::user());

            if ($request->wantsJson()) {
                return response()->json(['success' => true, 'message' => 'Asset registered successfully.', 'data' => $asset]);
            }

            return redirect()->route('assets.show', encrypt($asset->id))->with('success', 'Asset registered successfully.');
        } catch (Exception $e) {
            Log::error('Asset registration error: ' . $e->getMessage());

            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'Failed to register asset.'], 500);
            }

            return back()->withInput()->with('error', 'Failed to register asset.');
        }
    }

    public function update(Request $request, $id)
    {
        $id = decrypt($id);
        $asset = Asset::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:200',
            'asset_category_id' => 'nullable|exists:asset_categories,id',
            'asset_type_id' => 'nullable|exists:asset_types,id',
            'serial_number' => 'nullable|string|max:100',
            'model_number' => 'nullable|string|max:100',
            'brand' => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'barcode_value' => 'nullable|string|max:100',
            'vendor_id' => 'nullable|exists:vendors,id',
            'purchase_date' => 'nullable|date',
            'purchase_cost' => 'nullable|numeric|min:0',
            'purchase_order_number' => 'nullable|string|max:100',
            'invoice_number' => 'nullable|string|max:100',
            'warranty_start_date' => 'nullable|date',
            'warranty_end_date' => 'nullable|date|after_or_equal:warranty_start_date',
            'warranty_provider' => 'nullable|string|max:150',
            'branch_id' => 'nullable|exists:company_branches,id',
            'location_notes' => 'nullable|string|max:255',
            'condition' => 'nullable|in:new,good,fair,poor,damaged',
            'depreciation_method' => 'nullable|in:straight_line,declining_balance,none',
            'salvage_value' => 'nullable|numeric|min:0',
            'useful_life_months' => 'nullable|integer|min:0',
            'notes' => 'nullable|string',
        ]);

        try {
            $old = $asset->toArray();
            $asset->update($request->except(['tenant_id', 'asset_code', 'status', 'current_assignee_id']));

            app(\App\Services\AuditLogger::class)->record(
                'tenant_user', Auth::id(), (int) Auth::user()->tenant_id,
                'asset.updated', 'Asset', $asset->id, $old, $asset->fresh()->toArray()
            );

            return response()->json(['success' => true, 'message' => 'Asset updated successfully.']);
        } catch (Exception $e) {
            Log::error('Asset update error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to update asset.'], 500);
        }
    }

    public function destroy(Request $request, $id)
    {
        try {
            $id = decrypt($id);
            $asset = Asset::findOrFail($id);

            if (in_array($asset->status, ['assigned', 'pending_acceptance'], true)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Return this asset before deleting its record.',
                ], 422);
            }

            $asset->delete();

            return response()->json(['success' => true, 'message' => 'Asset deleted successfully.']);
        } catch (Exception $e) {
            Log::error('Asset delete error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to delete asset.'], 500);
        }
    }

    /**
     * Bulk status / category change from the list page's bulk-action bar
     * (mirrors ShiftController::bulkUpdateUserShifts()'s validate-then-loop
     * shape).
     */
    public function bulkAction(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:assets,id',
            'field' => 'required|in:status,asset_category_id,branch_id',
            'value' => 'nullable',
        ]);

        // Only status values that don't require a dedicated lifecycle action
        // (return/accept/etc.) are safe as a raw bulk field update.
        if ($request->field === 'status' && !in_array($request->value, ['available', 'retired'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Only "Available" and "Retired" can be bulk-set directly; other statuses require the matching action (assign/return/repair/dispose).',
            ], 422);
        }

        try {
            $updated = Asset::whereIn('id', $request->ids)->update([$request->field => $request->value]);

            return response()->json(['success' => true, 'message' => "{$updated} asset(s) updated.", 'data' => ['updated' => $updated]]);
        } catch (Exception $e) {
            Log::error('Asset bulk action error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Bulk update failed.'], 500);
        }
    }

    /**
     * CSV export of the (filtered) asset register — same streaming shape as
     * ShiftController::exportUserShifts().
     */
    public function export(Request $request)
    {
        $query = Asset::with(['category', 'type', 'vendor', 'branch', 'currentAssigneeUser']);

        $query
            ->when($request->get('search'), fn ($q, $v) => $q->where(function ($sub) use ($v) {
                $sub->where('name', 'like', "%{$v}%")->orWhere('asset_code', 'like', "%{$v}%");
            }))
            ->when($request->get('category_id'), fn ($q, $v) => $q->where('asset_category_id', $v))
            ->when($request->get('status'), fn ($q, $v) => $q->where('status', $v))
            ->when($request->get('branch_id'), fn ($q, $v) => $q->where('branch_id', $v));

        $assets = $query->orderBy('asset_code')->get();

        $filename = 'asset-register_' . now()->format('Ymd_His') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        $callback = function () use ($assets) {
            $out = fopen('php://output', 'w');
            fputs($out, "\xEF\xBB\xBF");

            fputcsv($out, [
                'Asset Code', 'Name', 'Category', 'Type', 'Status', 'Serial Number',
                'Brand', 'Model', 'Vendor', 'Branch', 'Assigned To', 'Purchase Date',
                'Purchase Cost', 'Warranty End Date',
            ]);

            foreach ($assets as $a) {
                fputcsv($out, [
                    $a->asset_code,
                    $a->name,
                    $a->category->name ?? '-',
                    $a->type->name ?? '-',
                    ucfirst(str_replace('_', ' ', $a->status)),
                    $a->serial_number ?? '-',
                    $a->brand ?? '-',
                    $a->model_number ?? '-',
                    $a->vendor->name ?? '-',
                    $a->branch->name ?? '-',
                    $a->currentAssigneeUser->name ?? '-',
                    $a->purchase_date?->format('Y-m-d') ?? '-',
                    $a->purchase_cost ?? '-',
                    $a->warranty_end_date?->format('Y-m-d') ?? '-',
                ]);
            }

            fclose($out);
        };

        return response()->stream($callback, 200, $headers);
    }

    /* ============================================================
     |  EMPLOYEE SELF-SERVICE ("My Assets")
     ============================================================ */

    /**
     * Assets currently/previously assigned to the logged-in user — scoped
     * by ownership only, exactly like LoanController::index().
     */
    public function myAssets(Request $request)
    {
        $query = Asset::with(['category', 'type', 'branch'])
            ->whereHas('assignments', fn ($q) => $q->where('user_id', Auth::id()));

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $assets = $query->orderByDesc('updated_at')->paginate(15)->appends($request->query());

        $pendingAcceptanceCount = Asset::where('current_assignee_id', null)
            ->whereHas('assignments', fn ($q) => $q->where('user_id', Auth::id())->where('status', 'pending_acceptance'))
            ->count();

        return view('client.asset.my-assets.index', compact('assets', 'pendingAcceptanceCount'));
    }

    public function myAssetShow(Request $request, $id)
    {
        $id = decrypt($id);
        $asset = Asset::with(['category', 'type', 'branch', 'assignments' => function ($q) {
            $q->where('user_id', Auth::id())->orderByDesc('assigned_at');
        }, 'histories'])->findOrFail($id);

        $ownsAsset = $asset->assignments()->where('user_id', Auth::id())->exists();
        if (!$ownsAsset) {
            abort(403, 'This asset is not associated with your account.');
        }

        return view('client.asset.my-assets.show', compact('asset'));
    }
}
