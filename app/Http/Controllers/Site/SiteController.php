<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Http\Requests\Site\SiteRequest;
use App\Models\Site;
use App\Services\Site\SiteException;
use App\Services\Site\SiteService;
use App\Services\Site\SiteVisitReportService;
use App\Services\Site\SiteVisitService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Sites (admin / HR): list, add, edit, detail, activate / deactivate,
 * delete and CSV export. All rules live in SiteService.
 */
class SiteController extends Controller
{
    private const FILTERS = ['search', 'state_code', 'district', 'status'];

    public function __construct(private SiteService $sites, private SiteVisitReportService $reports) {}

    public function index(Request $request)
    {
        $tenantId = (int) Auth::user()->tenant_id;

        return view('client.site.index', [
            'sites' => $this->sites->paginate($tenantId, $request->only(self::FILTERS)),
            'states' => $this->sites->stateOptions(),
            'districts' => $this->sites->districtOptions($tenantId),
        ]);
    }

    public function show(Request $request, $id)
    {
        $site = Site::with(['createdBy:id,name', 'updatedBy:id,name'])->withCount('visits')->findOrFail($id);
        $month = SiteVisitService::cleanFilters($request->only('month'))['month'] ?? null;

        return view('client.site.show', [
            'site' => $site,
            'states' => $this->sites->stateOptions(),
            'visitMonth' => $month,
            'visits' => $this->reports->siteHistory((int) $site->tenant_id, $site->id, $month),
        ]);
    }

    public function store(SiteRequest $request)
    {
        $site = $this->sites->create((int) Auth::user()->tenant_id, $request->validated(), Auth::user());

        return response()->json(['status' => true, 'message' => "Site {$site->site_code} added."]);
    }

    public function update(SiteRequest $request, $id)
    {
        $site = $this->sites->update(Site::findOrFail($id), $request->validated(), Auth::user());

        return response()->json(['status' => true, 'message' => "Site {$site->site_code} updated."]);
    }

    public function status(Request $request, $id)
    {
        $data = $request->validate(['status' => 'required|in:active,inactive']);
        $site = $this->sites->setStatus(Site::findOrFail($id), $data['status'], Auth::user());

        return response()->json(['status' => true, 'message' => "Site {$site->site_code} is now {$site->status}."]);
    }

    public function destroy($id)
    {
        $site = Site::findOrFail($id);
        try {
            $this->sites->delete($site, Auth::user());
        } catch (SiteException $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['status' => true, 'message' => "Site {$site->site_code} deleted."]);
    }

    public function export(Request $request)
    {
        $tenantId = (int) Auth::user()->tenant_id;
        $states = $this->sites->stateOptions();
        $rows = $this->sites->exportRows($tenantId, $request->only(self::FILTERS));

        return response()->streamDownload(function () use ($rows, $states) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Site Code', 'Site Name', 'State', 'District', 'Address', 'Location', 'Latitude', 'Longitude', 'Onboarding Date', 'Status']);
            foreach ($rows as $site) {
                fputcsv($out, [
                    $site->site_code, $site->site_name, $states[$site->state_code] ?? '', $site->district,
                    $site->address, $site->location, $site->latitude, $site->longitude,
                    $site->onboarding_date?->format('d-m-Y'), ucfirst($site->status),
                ]);
            }
            fclose($out);
        }, 'sites-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
    }
}
