<?php

namespace App\Services\Site;

use App\Models\Site;
use App\Models\SiteVisit;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;

/**
 * The only writer of `sites`. Hands out the per-company running `site_no`
 * and the `site_code` built from it (SITE-0001, SITE-0002 …) — both are set
 * once on create and never changed afterwards. Every change is audit-logged.
 */
class SiteService
{
    public const CODE_PREFIX = 'SITE';

    /** Columns an admin may set; site_no / site_code / tenant_id are system-owned. */
    private const EDITABLE = [
        'site_name', 'state_code', 'district', 'address', 'location',
        'latitude', 'longitude', 'onboarding_date', 'status',
    ];

    public function __construct(private AuditLogger $auditLogger)
    {
    }

    public static function formatCode(int $siteNo): string
    {
        return sprintf('%s-%04d', self::CODE_PREFIX, $siteNo);
    }

    /** @param array<string,mixed> $data validated input */
    public function create(int $tenantId, array $data, User $actor): Site
    {
        return DB::transaction(function () use ($tenantId, $data, $actor) {
            $siteNo = $this->nextSiteNo($tenantId);

            $site = Site::create(array_merge($this->editable($data), [
                'tenant_id' => $tenantId,
                'site_no' => $siteNo,
                'site_code' => self::formatCode($siteNo),
                'status' => $data['status'] ?? 'active',
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]));

            $this->auditLogger->record('tenant_user', $actor->id, $tenantId, 'site.created', 'Site', $site->id, [], $site->toArray());

            return $site;
        });
    }

    /** @param array<string,mixed> $data validated input */
    public function update(Site $site, array $data, User $actor): Site
    {
        $old = $site->toArray();
        $site->fill($this->editable($data));
        $site->updated_by = $actor->id;
        $site->save();

        $this->auditLogger->record('tenant_user', $actor->id, (int) $site->tenant_id, 'site.updated', 'Site', $site->id, $old, $site->toArray());

        return $site;
    }

    public function setStatus(Site $site, string $status, User $actor): Site
    {
        if (! in_array($status, Site::STATUSES, true)) {
            throw new SiteException('Unknown site status.');
        }

        return $this->update($site, ['status' => $status], $actor);
    }

    /** Soft delete; a site that already has visits is kept — deactivate it instead. */
    public function delete(Site $site, User $actor): void
    {
        $hasVisits = SiteVisit::where('tenant_id', $site->tenant_id)->where('site_id', $site->id)->exists();
        if ($hasVisits) {
            throw new SiteException('This site already has visits, so it can\'t be deleted. Mark it inactive instead.');
        }

        $site->delete();
        $this->auditLogger->record('tenant_user', $actor->id, (int) $site->tenant_id, 'site.deleted', 'Site', $site->id, $site->toArray(), []);
    }

    /** @return Collection<int|string,string> state_code => display name (the geo master only holds Indian states). */
    public function stateOptions(): Collection
    {
        return DB::table('states')->where('status', 1)->orderBy('name')->pluck('name', 'state_code')
            ->map(fn ($name) => ucwords(strtolower((string) $name)));
    }

    /** @return Collection<int,string> districts already used on the company's sites, for the filter. */
    public function districtOptions(int $tenantId): Collection
    {
        return Site::where('tenant_id', $tenantId)->whereNotNull('district')->where('district', '!=', '')
            ->distinct()->orderBy('district')->pluck('district');
    }

    /** @param array<string,mixed> $filters search, state_code, district, status */
    public function paginate(int $tenantId, array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->filtered($tenantId, $filters)
            ->withCount('visits')
            ->orderByDesc('site_no')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @param array<string,mixed> $filters
     * @return LazyCollection<int,Site>
     */
    public function exportRows(int $tenantId, array $filters): LazyCollection
    {
        return $this->filtered($tenantId, $filters)->orderBy('site_no')->lazy(500);
    }

    /**
     * @param array<string,mixed> $filters
     * @return Builder<Site>
     */
    private function filtered(int $tenantId, array $filters): Builder
    {
        return Site::where('tenant_id', $tenantId)
            ->when($filters['search'] ?? null, function ($q, $term) {
                $q->where(fn ($w) => $w->where('site_code', 'like', "%{$term}%")
                    ->orWhere('site_name', 'like', "%{$term}%")
                    ->orWhere('address', 'like', "%{$term}%"));
            })
            ->when($filters['state_code'] ?? null, fn ($q, $v) => $q->where('state_code', $v))
            ->when($filters['district'] ?? null, fn ($q, $v) => $q->where('district', $v))
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v));
    }

    /**
     * Next running number for the company. Locks the company's rows so two
     * saves (or an import) can't take the same number; deleted sites keep
     * theirs, so a code is never handed out twice. The unique
     * (tenant_id, site_no) index is the last line of defence.
     */
    private function nextSiteNo(int $tenantId): int
    {
        $max = Site::withoutGlobalScope('tenant')->withTrashed()
            ->where('tenant_id', $tenantId)
            ->lockForUpdate()
            ->max('site_no');

        return (int) $max + 1;
    }

    /**
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    private function editable(array $data): array
    {
        return array_intersect_key($data, array_flip(self::EDITABLE));
    }
}
