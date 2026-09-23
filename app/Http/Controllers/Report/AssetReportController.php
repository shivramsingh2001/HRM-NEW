<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\CompanyBranch;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Asset Report family — same shape as TaskReportController/
 * ProjectReportController: authorizeReportAccess() gate, filterable list +
 * CSV export per report, all under role:admin,hr,manager.
 */
class AssetReportController extends Controller
{
    private function authorizeReportAccess()
    {
        $authUser = Auth::user();

        if (!in_array($authUser->role, ['manager', 'admin', 'hr'])) {
            abort(403, 'Unauthorized access to asset reports.');
        }

        return $authUser;
    }

    public function register(Request $request)
    {
        try {
            $this->authorizeReportAccess();

            $query = Asset::with(['category', 'type', 'vendor', 'branch', 'currentAssigneeUser']);

            $query
                ->when($request->get('category_id'), fn ($q, $v) => $q->where('asset_category_id', $v))
                ->when($request->get('status'), fn ($q, $v) => $q->where('status', $v))
                ->when($request->get('branch_id'), fn ($q, $v) => $q->where('branch_id', $v))
                ->when($request->get('search'), fn ($q, $v) => $q->where(function ($qq) use ($v) {
                    $qq->where('asset_code', 'like', "%{$v}%")
                        ->orWhere('name', 'like', "%{$v}%")
                        ->orWhere('serial_number', 'like', "%{$v}%")
                        ->orWhereHas('currentAssigneeUser', fn ($u) => $u->where('name', 'like', "%{$v}%")->orWhere('employee_id', 'like', "%{$v}%"));
                }));

            $assets = $query->orderBy('asset_code')->paginate(25)->appends($request->query());

            $categories = AssetCategory::active()->orderBy('name')->get();
            $branches = CompanyBranch::active()->orderBy('name')->get();

            return view('client.report.asset.register', compact('assets', 'categories', 'branches'));
        } catch (Exception $e) {
            Log::error('Asset register report error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'An error occurred while generating the report.');
        }
    }

    public function exportRegister(Request $request)
    {
        $this->authorizeReportAccess();

        $query = Asset::with(['category', 'type', 'vendor', 'branch', 'currentAssigneeUser']);
        $query
            ->when($request->get('category_id'), fn ($q, $v) => $q->where('asset_category_id', $v))
            ->when($request->get('status'), fn ($q, $v) => $q->where('status', $v))
            ->when($request->get('branch_id'), fn ($q, $v) => $q->where('branch_id', $v))
            ->when($request->get('search'), fn ($q, $v) => $q->where(function ($qq) use ($v) {
                $qq->where('asset_code', 'like', "%{$v}%")
                    ->orWhere('name', 'like', "%{$v}%")
                    ->orWhere('serial_number', 'like', "%{$v}%")
                    ->orWhereHas('currentAssigneeUser', fn ($u) => $u->where('name', 'like', "%{$v}%")->orWhere('employee_id', 'like', "%{$v}%"));
            }));

        $assets = $query->orderBy('asset_code')->get();

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="asset-register_' . now()->format('Ymd_His') . '.csv"',
        ];

        $callback = function () use ($assets) {
            $out = fopen('php://output', 'w');
            fputs($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Asset Code', 'Name', 'Category', 'Status', 'Branch', 'Assigned To', 'Purchase Date', 'Warranty End']);
            foreach ($assets as $a) {
                fputcsv($out, [
                    $a->asset_code, $a->name, $a->category->name ?? '-', ucfirst(str_replace('_', ' ', $a->status)),
                    $a->branch->name ?? '-', $a->currentAssigneeUser->name ?? '-',
                    $a->purchase_date?->format('Y-m-d') ?? '-', $a->warranty_end_date?->format('Y-m-d') ?? '-',
                ]);
            }
            fclose($out);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function employeeWise(Request $request)
    {
        try {
            $this->authorizeReportAccess();

            $users = User::where('status', 1)
                ->whereHas('assetAssignments')
                ->with(['assetAssignments' => function ($q) {
                    $q->with('asset.category')->orderByDesc('assigned_at');
                }, 'jobDetails.branch'])
                ->when($request->get('branch_id'), fn ($q, $v) => $q->whereHas('jobDetails', fn ($j) => $j->where('branch_id', $v)))
                ->when($request->get('search'), fn ($q, $v) => $q->where(function ($qq) use ($v) {
                    $qq->where('name', 'like', "%{$v}%")->orWhere('employee_id', 'like', "%{$v}%");
                }))
                ->orderBy('name')
                ->paginate(20)
                ->appends($request->query());

            $branches = CompanyBranch::active()->orderBy('name')->get();

            return view('client.report.asset.employee-wise', compact('users', 'branches'));
        } catch (Exception $e) {
            Log::error('Asset employee-wise report error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'An error occurred while generating the report.');
        }
    }

    public function summary(Request $request)
    {
        try {
            $this->authorizeReportAccess();

            $branchId = $request->get('branch_id');

            $byStatus = Asset::when($branchId, fn ($q, $v) => $q->where('branch_id', $v))
                ->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
            $byCategory = Asset::with('category')
                ->when($branchId, fn ($q, $v) => $q->where('branch_id', $v))
                ->selectRaw('asset_category_id, count(*) as total')
                ->groupBy('asset_category_id')
                ->get()
                ->map(fn ($row) => ['name' => $row->category->name ?? 'Uncategorized', 'total' => $row->total]);
            $byBranch = Asset::with('branch')
                ->selectRaw('branch_id, count(*) as total')
                ->groupBy('branch_id')
                ->get()
                ->map(fn ($row) => ['name' => $row->branch->name ?? 'Unassigned', 'total' => $row->total]);

            $branches = CompanyBranch::active()->orderBy('name')->get();

            return view('client.report.asset.summary', compact('byStatus', 'byCategory', 'byBranch', 'branches', 'branchId'));
        } catch (Exception $e) {
            Log::error('Asset summary report error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'An error occurred while generating the report.');
        }
    }

    public function warrantyExpiry(Request $request)
    {
        try {
            $this->authorizeReportAccess();

            $days = (int) $request->get('days', 90);

            $assets = Asset::with(['category', 'branch', 'currentAssigneeUser'])
                ->whereNotNull('warranty_end_date')
                ->where('warranty_end_date', '<=', now()->addDays($days))
                ->when($request->get('branch_id'), fn ($q, $v) => $q->where('branch_id', $v))
                ->when($request->get('search'), fn ($q, $v) => $q->where(function ($qq) use ($v) {
                    $qq->where('asset_code', 'like', "%{$v}%")
                        ->orWhere('name', 'like', "%{$v}%")
                        ->orWhereHas('currentAssigneeUser', fn ($u) => $u->where('name', 'like', "%{$v}%")->orWhere('employee_id', 'like', "%{$v}%"));
                }))
                ->orderBy('warranty_end_date')
                ->paginate(25)
                ->appends($request->query());

            $branches = CompanyBranch::active()->orderBy('name')->get();

            return view('client.report.asset.warranty-expiry', compact('assets', 'days', 'branches'));
        } catch (Exception $e) {
            Log::error('Asset warranty-expiry report error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'An error occurred while generating the report.');
        }
    }
}
