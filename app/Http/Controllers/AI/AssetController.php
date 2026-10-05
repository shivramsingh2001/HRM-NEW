<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\AI\Concerns\AiScope;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * GET /api/ai/asset — company assets with category, assignee, warranty and open repair.
 * own scope = assets currently assigned to the caller; team = assigned to the caller or a
 * reportee; company = every asset (purchase cost / vendor only at company scope).
 */
class AssetController extends Controller
{
    use AiScope;

    public function view_ai_all(Request $request)
    {
        try {
            $authUser = Auth::user();
            $tenantId = (int) $authUser->tenant_id;

            $scope = $this->scopeOf($authUser, 'assets');
            if ($scope === null) {
                return $this->forbidden();
            }
            $company = $scope === 'company';
            $ids = $this->narrowToUser($this->visibleUserIds($authUser, $scope), $request->user_id);

            $query = DB::table('assets as a')
                ->leftJoin('asset_categories as c', 'c.id', '=', 'a.asset_category_id')
                ->leftJoin('asset_types as t', 't.id', '=', 'a.asset_type_id')
                ->leftJoin('company_branches as b', 'b.id', '=', 'a.branch_id')
                ->leftJoin('vendors as v', 'v.id', '=', 'a.vendor_id')
                ->where('a.tenant_id', $tenantId)
                ->whereNull('a.deleted_at')
                ->select([
                    'a.id', 'a.asset_code', 'a.name', 'a.status', 'a.condition', 'a.brand', 'a.model_number', 'a.serial_number',
                    'a.purchase_date', 'a.purchase_cost', 'a.warranty_end_date', 'a.warranty_provider', 'a.location_notes',
                    'a.current_assignee_id', 'a.image', 'c.id as category_id', 'c.name as category', 't.name as type',
                    'b.name as branch', 'v.name as vendor',
                ]);

            if ($ids !== null) {
                $query->whereIn('a.current_assignee_id', $ids ?: [0]);
            }
            if ($request->filled('status')) {
                $query->where('a.status', $request->status);
            }
            if ($request->filled('category_id')) {
                $query->where('a.asset_category_id', $request->category_id);
            }
            if ($request->filled('search')) {
                $s = '%' . $request->search . '%';
                $query->where(fn ($q) => $q->where('a.name', 'like', $s)->orWhere('a.asset_code', 'like', $s)
                    ->orWhere('a.serial_number', 'like', $s)->orWhere('a.brand', 'like', $s));
            }

            $assets = $query->orderBy('a.name')->get();
            $assetIds = $assets->pluck('id')->all();

            // Current assignment (pending acceptance / accepted) and open repair, one query each.
            $assignments = DB::table('asset_assignments')->where('tenant_id', $tenantId)->whereIn('asset_id', $assetIds ?: [0])
                ->whereIn('status', ['pending_acceptance', 'accepted'])->orderByDesc('assigned_at')
                ->get(['asset_id', 'user_id', 'status', 'assigned_at', 'accepted_at', 'expected_return_date'])->unique('asset_id')->keyBy('asset_id');
            $repairs = DB::table('asset_repairs')->where('tenant_id', $tenantId)->whereIn('asset_id', $assetIds ?: [0])
                ->whereIn('status', ['reported', 'in_progress'])->orderByDesc('reported_at')
                ->get(['asset_id', 'status', 'issue_description', 'reported_at', 'expected_return_date'])->unique('asset_id')->keyBy('asset_id');
            $people = $this->people($tenantId, $assets->pluck('current_assignee_id'));

            $today = Carbon::today();
            $data = $assets->map(function ($a) use ($assignments, $repairs, $people, $company, $today) {
                $warranty = null;
                if ($a->warranty_end_date) {
                    $end = Carbon::parse($a->warranty_end_date);
                    $warranty = $end->lt($today) ? 'expired' : ($end->lte($today->copy()->addDays(30)) ? 'expiring_soon' : 'active');
                }
                $assign = $assignments->get($a->id);
                $repair = $repairs->get($a->id);

                return [
                    'id' => $a->id,
                    'asset_code' => $a->asset_code,
                    'name' => $a->name,
                    'category' => $a->category,
                    'type' => $a->type,
                    'brand' => $a->brand,
                    'model_number' => $a->model_number,
                    'serial_number' => $a->serial_number,
                    'status' => $a->status,
                    'condition' => $a->condition,
                    'branch' => $a->branch,
                    'location_notes' => $a->location_notes,
                    'image_url' => $a->image ? file_url($a->image, 'asset_attachment') : null,
                    'assigned_to' => $people[$a->current_assignee_id] ?? null,
                    'assignment' => $assign ? [
                        'status' => $assign->status,
                        'assigned_at' => $assign->assigned_at,
                        'accepted_at' => $assign->accepted_at,
                        'expected_return_date' => $assign->expected_return_date,
                    ] : null,
                    'warranty' => [
                        'end_date' => $a->warranty_end_date,
                        'provider' => $a->warranty_provider,
                        'status' => $warranty,
                    ],
                    'open_repair' => $repair ? [
                        'status' => $repair->status,
                        'issue' => $repair->issue_description,
                        'reported_at' => $repair->reported_at,
                        'expected_return_date' => $repair->expected_return_date,
                    ] : null,
                ] + ($company ? [
                    'purchase_date' => $a->purchase_date,
                    'purchase_cost' => $a->purchase_cost !== null ? (float) $a->purchase_cost : null,
                    'vendor' => $a->vendor,
                ] : []);
            })->values();

            $summary = [
                'total_assets' => $data->count(),
                'by_status' => $data->countBy('status'),
                'by_category' => $data->countBy(fn ($a) => $a['category'] ?? 'Uncategorised'),
                'assigned' => $data->whereNotNull('assigned_to')->count(),
                'pending_acceptance' => $data->filter(fn ($a) => ($a['assignment']['status'] ?? null) === 'pending_acceptance')->count(),
                'in_repair' => $data->whereNotNull('open_repair')->count(),
                'warranty_expiring_30_days' => $data->filter(fn ($a) => $a['warranty']['status'] === 'expiring_soon')->count(),
                'warranty_expired' => $data->filter(fn ($a) => $a['warranty']['status'] === 'expired')->count(),
                'assigned_to_me' => $data->filter(fn ($a) => ($a['assigned_to']['id'] ?? null) == $authUser->id)->count(),
            ];
            if ($company) {
                $summary['total_purchase_cost'] = round($data->sum('purchase_cost'), 2);
            }

            return response()->json([
                'success' => true,
                'message' => 'Asset data fetched successfully',
                'data' => $data,
                'summary' => $summary,
                'scope' => $scope,
            ], 200);
        } catch (\Throwable $e) {
            return $this->failed('assets', $e);
        }
    }
}
