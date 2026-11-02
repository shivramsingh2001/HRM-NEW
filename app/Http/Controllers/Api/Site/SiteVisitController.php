<?php

namespace App\Http\Controllers\Api\Site;

use App\Exceptions\FileStorageException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreSiteVisitRequest;
use App\Http\Requests\Api\UpdateSiteVisitRequest;
use App\Models\SiteVisit;
use App\Services\Site\SiteException;
use App\Services\Site\SiteService;
use App\Services\Site\SiteVisitReportService;
use App\Services\Site\SiteVisitService;
use App\Traits\AuthorizesByScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Mobile API (legacy /api, JWT) — site visits. Same rules as the web page
 * (Site Visits): everything goes through SiteVisitService. Envelope
 * { success, message, data }; a refused request is 422 with the reason in
 * `message`. The visit date is always today and is never read from the request.
 */
class SiteVisitController extends Controller
{
    use AuthorizesByScope;

    public function __construct(private SiteVisitService $visits) {}

    /** GET /api/sites — active sites to pick from (?search=, ?page=, ?per_page= up to 100). */
    public function sites(Request $request): JsonResponse
    {
        $page = $this->visits->pickableSites((int) Auth::user()->tenant_id, $request->input('search'),
            min(100, max(1, (int) $request->input('per_page', 50))));
        $states = app(SiteService::class)->stateOptions();

        return $this->ok('Sites', [
            'sites' => collect($page->items())->map(fn ($s) => [
                'id' => $s->id, 'site_code' => $s->site_code, 'site_name' => $s->site_name,
                'state' => $states[$s->state_code] ?? null, 'district' => $s->district, 'address' => $s->address,
                'location' => $s->location, 'latitude' => $s->latitude, 'longitude' => $s->longitude,
            ])->all(),
            'pagination' => $this->pagination($page),
        ]);
    }

    /** GET /api/site-visits — my visits (?date=Y-m-d or ?month=Y-m, ?site_id=). */
    public function index(Request $request): JsonResponse
    {
        $filters = SiteVisitService::cleanFilters($request->only(['date', 'month', 'site_id']));
        $page = $this->visits->ownVisits(Auth::user(), $filters, min(50, max(1, (int) $request->input('per_page', 20))));

        return $this->ok('Site visits', [
            'today' => now()->toDateString(),
            'visits' => array_map(fn ($v) => $this->row($v), $page->items()),
            'pagination' => $this->pagination($page),
        ]);
    }

    /** GET /api/site-visits/{id} — my visit, or one in my view scope (manager: team, HR/admin: company). */
    public function show($id): JsonResponse
    {
        $visit = SiteVisit::with('site')->findOrFail($id);
        $user = Auth::user();
        if ((int) $visit->user_id !== (int) $user->id && ! $this->scopeCoversOwner($user, 'site_visits', 'view', (int) $visit->user_id)) {
            return response()->json(['success' => false, 'message' => 'You cannot view this visit.'], 403);
        }

        return $this->ok('Site visit', $this->visits->toApi($visit));
    }

    /** POST /api/site-visits — multipart: site_id, remarks, contact_person_name?, contact_mobile?, file?, latitude?, longitude? */
    public function store(StoreSiteVisitRequest $request): JsonResponse
    {
        try {
            $visit = $this->visits->create(Auth::user(), $request->validated(), $request->file('file'));
        } catch (SiteException|FileStorageException $e) {
            return $this->refused($e->getMessage());
        }

        return $this->ok('Visit recorded.', $this->visits->toApi($visit->load('site')));
    }

    /** POST|PUT /api/site-visits/{id} — remarks, contact_person_name?, contact_mobile?, file?, remove_file? (same day, own visit only). */
    public function update(UpdateSiteVisitRequest $request, $id): JsonResponse
    {
        $visit = SiteVisit::with('site')->findOrFail($id);
        try {
            $visit = $this->visits->update($visit, Auth::user(), $request->validated(), $request->file('file'));
        } catch (SiteException|FileStorageException $e) {
            return $this->refused($e->getMessage());
        }

        return $this->ok('Visit updated.', $this->visits->toApi($visit));
    }

    /** GET /api/manager/site-visits/day-wise — visits on one day by everyone in my view scope (?date=, ?user_id=, ?site_id=). */
    public function dayReport(Request $request, SiteVisitReportService $reports): JsonResponse
    {
        $filters = SiteVisitService::cleanFilters($request->only(['date', 'user_id', 'site_id']));
        $filters['date'] = min($filters['date'] ?? now()->toDateString(), now()->toDateString());
        $page = $reports->dayWise(Auth::user(), $filters, min(50, max(1, (int) $request->input('per_page', 25))));

        return $this->ok('Day-wise site visits', [
            'date' => $filters['date'],
            'visits' => array_map(fn ($v) => $this->row($v) + ['employee' => $this->employee($v)], $page->items()),
            'pagination' => $this->pagination($page),
        ]);
    }

    /** GET /api/manager/site-visits/monthly — one row per employee in my view scope (?month=Y-m, ?user_id=). */
    public function monthlyReport(Request $request, SiteVisitReportService $reports): JsonResponse
    {
        $month = SiteVisitReportService::month($request->input('month'));

        return $this->ok('Monthly site visits', [
            'month' => $month,
            'employees' => $reports->monthlySummary(Auth::user(), $month, $request->integer('user_id') ?: null)->all(),
        ]);
    }

    /** GET /api/manager/site-visits/monthly/{userId} — one employee's month, day by day (?month=Y-m). */
    public function employeeReport(Request $request, SiteVisitReportService $reports, $userId): JsonResponse
    {
        try {
            $data = $reports->employeeMonth(Auth::user(), (int) $userId, SiteVisitReportService::month($request->input('month')));
        } catch (SiteException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 403);
        }

        return $this->ok('Employee site visits', [
            'employee' => ['id' => $data['user']->id, 'name' => $data['user']->name, 'employee_id' => $data['user']->employee_id],
            'month' => $data['month'],
            'totals' => $data['totals'],
            'days' => $data['days'],
            'visits' => $data['visits']->map(fn ($v) => $this->row($v))->all(),
        ]);
    }

    /** @return array<string,mixed>|null */
    private function employee(mixed $visit): ?array
    {
        /** @var SiteVisit $visit */
        return $visit->user ? ['id' => $visit->user->id, 'name' => $visit->user->name, 'employee_id' => $visit->user->employee_id] : null;
    }

    /** @return array<string,mixed> */
    private function row(mixed $visit): array
    {
        /** @var SiteVisit $visit */
        return $this->visits->toApi($visit);
    }

    /** @return array<string,int> */
    private function pagination(\Illuminate\Contracts\Pagination\LengthAwarePaginator $page): array
    {
        return ['total' => $page->total(), 'current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'per_page' => $page->perPage()];
    }

    private function ok(string $message, array $data): JsonResponse
    {
        return response()->json(['success' => true, 'message' => $message, 'data' => $data]);
    }

    private function refused(string $reason): JsonResponse
    {
        return response()->json(['success' => false, 'message' => $reason, 'errors' => [$reason]], 422);
    }
}
