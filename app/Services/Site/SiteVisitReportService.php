<?php

namespace App\Services\Site;

use App\Models\SiteVisit;
use App\Models\User;
use App\Services\RbacService;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Site visit reports — day-wise visit list, monthly per-employee summary,
 * one employee's month day by day, and a site's visit history. Shared by
 * the web Reports pages and the mobile API. Who is covered follows the
 * viewer's `site_visits:view` scope: company (admin / HR), team = self +
 * reportees (manager), own (employee).
 */
class SiteVisitReportService
{
    public function __construct(private RbacService $rbac, private SiteVisitService $visits)
    {
    }

    /** @return int[]|null user ids the viewer may report on; null = the whole company */
    public function scopedUserIds(User $viewer): ?array
    {
        return match ($this->rbac->scopeFor($viewer, 'site_visits', 'view')) {
            'company' => null,
            'team' => User::managedBy($viewer->id)->where('tenant_id', $viewer->tenant_id)->pluck('id')
                ->push($viewer->id)->map(fn ($id) => (int) $id)->unique()->values()->all(),
            'own' => [(int) $viewer->id],
            default => [],
        };
    }

    /** @return Collection<int,User> employees for the report filter */
    public function employeeOptions(User $viewer): Collection
    {
        $ids = $this->scopedUserIds($viewer);

        return User::where('tenant_id', $viewer->tenant_id)->where('status', 1)
            ->when($ids !== null, fn ($q) => $q->whereIn('id', $ids))
            ->orderBy('name')->get(['id', 'name', 'employee_id']);
    }

    /**
     * Every visit on one date (filters: date, user_id, site_id), oldest first.
     *
     * @param array<string,mixed> $filters
     * @return Builder<SiteVisit>
     */
    public function dayWiseQuery(User $viewer, array $filters): Builder
    {
        $filters['date'] = $filters['date'] ?? now()->toDateString();
        unset($filters['month']);

        return $this->scoped($viewer, $filters)
            ->with(['user:id,name,employee_id', 'site:id,site_code,site_name,district'])
            ->orderBy('visited_at');
    }

    /** @param array<string,mixed> $filters */
    public function dayWise(User $viewer, array $filters, int $perPage = 25): LengthAwarePaginator
    {
        return $this->dayWiseQuery($viewer, $filters)->paginate($perPage)->withQueryString();
    }

    /**
     * One row per employee with visits in the month: visits, different sites,
     * days with a visit, last visit. Busiest first.
     *
     * @return Collection<int,array{user_id:int,name:string,employee_id:?string,visits:int,sites:int,days:int,last_visit:?string}>
     */
    public function monthlySummary(User $viewer, string $month, ?int $userId = null): Collection
    {
        $rows = $this->scoped($viewer, ['month' => $month, 'user_id' => $userId])
            ->toBase()
            ->selectRaw('user_id, COUNT(*) as visits, COUNT(DISTINCT site_id) as sites, COUNT(DISTINCT visit_date) as days, MAX(visited_at) as last_visit')
            ->groupBy('user_id')
            ->get();

        $users = User::where('tenant_id', $viewer->tenant_id)->whereIn('id', $rows->pluck('user_id'))
            ->get(['id', 'name', 'employee_id'])->keyBy('id');

        return $rows->map(fn ($r) => [
            'user_id' => (int) $r->user_id,
            'name' => (string) ($users[$r->user_id]->name ?? '—'),
            'employee_id' => $users[$r->user_id]->employee_id ?? null,
            'visits' => (int) $r->visits,
            'sites' => (int) $r->sites,
            'days' => (int) $r->days,
            'last_visit' => $r->last_visit ? Carbon::parse($r->last_visit)->format('Y-m-d H:i:s') : null,
        ])->sortByDesc('visits')->values();
    }

    /**
     * One employee's month, day by day (up to today): visits that day and the sites visited.
     *
     * @return array{user:?User,month:string,totals:array{visits:int,sites:int,days:int},
     *   days:array<int,array{date:string,visits:int,sites:array<int,string>}>,
     *   visits:Collection<int,SiteVisit>}
     */
    public function employeeMonth(User $viewer, int $userId, string $month): array
    {
        $ids = $this->scopedUserIds($viewer);
        $user = User::where('tenant_id', $viewer->tenant_id)->find($userId);
        if (! $user || ($ids !== null && ! in_array($userId, $ids, true))) {
            throw new SiteException('You cannot see this employee\'s visits.');
        }

        $visits = $this->scoped($viewer, ['month' => $month, 'user_id' => $userId])
            ->with('site:id,site_code,site_name,district')
            ->orderBy('visited_at')->get();
        $byDay = $visits->groupBy(fn (SiteVisit $v) => $v->visit_date->toDateString());

        $start = Carbon::createFromFormat('Y-m-d', $month.'-01')->startOfDay();
        $end = $start->copy()->endOfMonth()->min(now()->endOfDay());
        $days = [];
        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            $dayVisits = $byDay->get($d->toDateString(), collect());
            $days[] = [
                'date' => $d->toDateString(),
                'visits' => $dayVisits->count(),
                'sites' => $dayVisits->map(fn (SiteVisit $v) => $v->site->site_name ?? '—')->unique()->values()->all(),
            ];
        }

        return [
            'user' => $user,
            'month' => $month,
            'totals' => [
                'visits' => $visits->count(),
                'sites' => $visits->pluck('site_id')->unique()->count(),
                'days' => $byDay->count(),
            ],
            'days' => $days,
            'visits' => $visits,
        ];
    }

    /** Visits at one site, newest first (filter: month). */
    public function siteHistory(int $tenantId, int $siteId, ?string $month, int $perPage = 20): LengthAwarePaginator
    {
        return $this->visits->filtered(SiteVisit::where('tenant_id', $tenantId)->where('site_id', $siteId), ['month' => $month])
            ->with('user:id,name,employee_id')
            ->orderByDesc('visited_at')
            ->paginate($perPage, ['*'], 'visits_page')
            ->withQueryString();
    }

    /** A month (Y-m) from input, defaulting to this month; never in the future. */
    public static function month(mixed $value): string
    {
        $month = is_string($value) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $value) ? $value : now()->format('Y-m');

        return min($month, now()->format('Y-m'));
    }

    /**
     * Company visits limited to what the viewer may see, plus list filters.
     *
     * @param array<string,mixed> $filters date | month, user_id, site_id
     * @return Builder<SiteVisit>
     */
    private function scoped(User $viewer, array $filters): Builder
    {
        $ids = $this->scopedUserIds($viewer);

        return $this->visits->filtered(
            SiteVisit::where('tenant_id', $viewer->tenant_id)->when($ids !== null, fn ($q) => $q->whereIn('user_id', $ids)),
            array_filter($filters)
        );
    }
}
