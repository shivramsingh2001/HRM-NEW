<?php

namespace App\Http\Controllers\Site;

use App\Exceptions\FileStorageException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Site\StoreSiteVisitRequest;
use App\Http\Requests\Site\UpdateSiteVisitRequest;
use App\Models\SiteVisit;
use App\Services\Site\SiteException;
use App\Services\Site\SiteVisitService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * My Site Visits (web): list my visits, record a visit, edit today's visit.
 * Same rules as the mobile API — everything goes through SiteVisitService.
 */
class SiteVisitController extends Controller
{
    public function __construct(private SiteVisitService $visits) {}

    public function index(Request $request)
    {
        $filters = SiteVisitService::cleanFilters($request->only(['date', 'month']));
        if (! $filters) {
            $filters['month'] = now()->format('Y-m');
        }

        return view('client.site.visits', [
            'visits' => $this->visits->ownVisits(Auth::user(), $filters),
            'filters' => $filters,
        ]);
    }

    /** Active sites for the site picker (select2 AJAX: ?search=&page=). */
    public function sites(Request $request)
    {
        $page = $this->visits->pickableSites((int) Auth::user()->tenant_id, $request->input('search'), 30);

        return response()->json([
            'results' => collect($page->items())->map(fn ($s) => [
                'id' => $s->id,
                'text' => "{$s->site_code} · {$s->site_name}".($s->district ? " ({$s->district})" : ''),
            ])->all(),
            'pagination' => ['more' => $page->hasMorePages()],
        ]);
    }

    public function store(StoreSiteVisitRequest $request)
    {
        try {
            $this->visits->create(Auth::user(), $request->validated(), $request->file('file'));
        } catch (SiteException|FileStorageException $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['status' => true, 'message' => 'Visit recorded.']);
    }

    public function update(UpdateSiteVisitRequest $request, $id)
    {
        try {
            $this->visits->update(SiteVisit::findOrFail($id), Auth::user(), $request->validated(), $request->file('file'));
        } catch (SiteException|FileStorageException $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['status' => true, 'message' => 'Visit updated.']);
    }
}
