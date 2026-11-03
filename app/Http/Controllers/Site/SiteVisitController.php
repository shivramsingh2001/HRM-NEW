<?php

namespace App\Http\Controllers\Site;

use App\Exceptions\FileStorageException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Site\StoreSiteVisitRequest;
use App\Http\Requests\Site\UpdateSiteVisitRequest;
use App\Models\SiteVisit;
use App\Services\Site\SiteException;
use App\Services\Site\SiteVisitReportService;
use App\Services\Site\SiteVisitService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * My Site Visits (web): list my visits, record a visit, edit today's visit.
 * Same rules as the mobile API — everything goes through SiteVisitService.
 */
class SiteVisitController extends Controller
{
    public function __construct(private SiteVisitService $visits, private SiteVisitReportService $reports) {}

    /** My Visits tab, plus a Team Visits tab (?tab=team) for a team / company site_visits:view scope. */
    public function index(Request $request)
    {
        $user = Auth::user();
        $canTeam = $this->reports->canSeeTeam($user);
        $tab = $canTeam && $request->input('tab') === 'team' ? 'team' : 'mine';

        $filters = SiteVisitService::cleanFilters($request->only(['date', 'month', 'date_from', 'date_to', 'search', 'site_id', 'user_id']));
        if ($tab === 'mine') {
            unset($filters['user_id']);
        }
        // No date chosen → this month so far.
        if (empty($filters['date']) && empty($filters['month']) && empty($filters['date_from']) && empty($filters['date_to'])) {
            $filters['date_from'] = now()->startOfMonth()->toDateString();
            $filters['date_to'] = now()->toDateString();
        }

        $data = ['tab' => $tab, 'canTeam' => $canTeam, 'filters' => $filters];
        if ($tab === 'team') {
            $data += [
                'visits' => $this->reports->teamVisits($user, $filters),
                'summary' => $this->reports->teamSummary($user, $filters),
                'visitedSites' => $this->reports->teamSiteOptions($user),
                'employees' => $this->reports->employeeOptions($user)->where('id', '!=', $user->id),
            ];
        } else {
            $data += [
                'visits' => $this->visits->ownVisits($user, $filters),
                'summary' => $this->visits->ownSummary($user, $filters),
                'visitedSites' => $this->visits->ownVisitedSites($user),
            ];
        }

        return view('client.site.visits', $data);
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
