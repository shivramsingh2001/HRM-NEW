<?php

namespace App\Services\Site;

use App\Models\Site;
use App\Models\SiteVisit;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * The only writer of `site_visits`, shared by the web page and the mobile API.
 * Rules:
 *   - the visit date is always today (server clock) — never taken from the client;
 *   - only an active site of the visitor's own company can be chosen;
 *   - only the person who recorded a visit can edit it, and only on the same
 *     day; the site and date stay as they were;
 *   - remarks are required; contact name / mobile, one file and the phone's
 *     location are optional.
 */
class SiteVisitService
{
    public const FILE_MODULE = 'site_visit';

    /** Fields the visitor may change on edit. */
    private const EDITABLE = ['contact_person_name', 'contact_mobile', 'remarks'];

    public function __construct(private AuditLogger $auditLogger)
    {
    }

    /** @param array<string,mixed> $data validated input */
    public function create(User $user, array $data, ?UploadedFile $file = null): SiteVisit
    {
        $tenantId = (int) $user->tenant_id;
        $site = Site::where('tenant_id', $tenantId)->find($data['site_id'] ?? 0);
        if (! $site || $site->status !== 'active') {
            throw new SiteException('Choose an active site.');
        }

        $path = $file ? file_storage()->upload($file, self::FILE_MODULE, ['tenant' => $tenantId, 'user' => $user->id])->path : null;

        try {
            $visit = DB::transaction(fn () => SiteVisit::create([
                'tenant_id' => $tenantId,
                'user_id' => $user->id,
                'site_id' => $site->id,
                'visit_date' => now()->toDateString(),
                'visited_at' => now(),
                'contact_person_name' => $data['contact_person_name'] ?? null,
                'contact_mobile' => $data['contact_mobile'] ?? null,
                'remarks' => $data['remarks'],
                'file_path' => $path,
                'latitude' => $data['latitude'] ?? null,
                'longitude' => $data['longitude'] ?? null,
            ]));
        } catch (\Throwable $e) {
            file_storage()->delete($path, self::FILE_MODULE);
            throw $e;
        }

        $this->auditLogger->record('tenant_user', $user->id, $tenantId, 'site_visit.created', 'SiteVisit', $visit->id, [], $visit->toArray());

        return $visit;
    }

    /** @param array<string,mixed> $data validated input; remove_file=true drops the current file */
    public function update(SiteVisit $visit, User $user, array $data, ?UploadedFile $file = null): SiteVisit
    {
        if ((int) $visit->user_id !== (int) $user->id) {
            throw new SiteException('You can only edit your own visits.');
        }
        if (! $this->isEditable($visit)) {
            throw new SiteException('A visit can only be edited on the day it was recorded.');
        }

        $old = $visit->toArray();

        DB::transaction(function () use ($visit, $data, $file) {
            $visit->fill(array_intersect_key($data, array_flip(self::EDITABLE)));
            if ($file) {
                $visit->file_path = file_storage()->replace($visit->file_path, $file, self::FILE_MODULE,
                    ['tenant' => $visit->tenant_id, 'user' => $visit->user_id])->path;
            } elseif (! empty($data['remove_file']) && $visit->file_path) {
                file_storage()->deleteAfterCommit($visit->file_path, self::FILE_MODULE);
                $visit->file_path = null;
            }
            $visit->edited_at = now();
            $visit->save();
        });

        $this->auditLogger->record('tenant_user', $user->id, (int) $visit->tenant_id, 'site_visit.updated', 'SiteVisit', $visit->id, $old, $visit->toArray());

        return $visit;
    }

    public function isEditable(SiteVisit $visit): bool
    {
        return $visit->visit_date->toDateString() === now()->toDateString();
    }

    /**
     * Active sites to pick from, searched by code / name / district.
     */
    public function pickableSites(int $tenantId, ?string $search = null, int $perPage = 50): LengthAwarePaginator
    {
        return Site::where('tenant_id', $tenantId)->active()
            ->when($search, fn ($q, $term) => $q->where(fn ($w) => $w->where('site_code', 'like', "%{$term}%")
                ->orWhere('site_name', 'like', "%{$term}%")
                ->orWhere('district', 'like', "%{$term}%")))
            ->orderBy('site_name')
            ->paginate($perPage, ['id', 'site_code', 'site_name', 'state_code', 'district', 'address', 'location', 'latitude', 'longitude']);
    }

    /**
     * A user's own visits, newest first. Filters: date (Y-m-d) or month (Y-m), site_id.
     *
     * @param array<string,mixed> $filters
     */
    public function ownVisits(User $user, array $filters, int $perPage = 20): LengthAwarePaginator
    {
        return $this->filtered(SiteVisit::where('tenant_id', $user->tenant_id)->where('user_id', $user->id), $filters)
            ->with('site:id,site_code,site_name,district,address,status')
            ->orderByDesc('visited_at')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Keeps only well-formed list filters (a bad value is dropped, not an error).
     *
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public static function cleanFilters(array $input): array
    {
        $ok = fn (string $key, string $pattern) => is_string($input[$key] ?? null) && preg_match($pattern, $input[$key]) ? $input[$key] : null;

        return array_filter([
            'date' => $ok('date', '/^\d{4}-(0[1-9]|1[0-2])-(0[1-9]|[12]\d|3[01])$/'),
            'month' => $ok('month', '/^\d{4}-(0[1-9]|1[0-2])$/'),
            'site_id' => is_numeric($input['site_id'] ?? null) ? (int) $input['site_id'] : null,
            'user_id' => is_numeric($input['user_id'] ?? null) ? (int) $input['user_id'] : null,
        ]);
    }

    /**
     * @param Builder<SiteVisit> $query
     * @param array<string,mixed> $filters
     * @return Builder<SiteVisit>
     */
    public function filtered(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['date'] ?? null, fn ($q, $d) => $q->whereDate('visit_date', $d))
            ->when(empty($filters['date']) ? ($filters['month'] ?? null) : null, function ($q, $m) {
                [$y, $mo] = array_map('intval', explode('-', $m));
                $q->whereYear('visit_date', $y)->whereMonth('visit_date', $mo);
            })
            ->when($filters['site_id'] ?? null, fn ($q, $id) => $q->where('site_id', $id))
            ->when($filters['user_id'] ?? null, fn ($q, $id) => $q->where('user_id', $id));
    }

    /** @return array<string,mixed> one visit as the API / web page show it */
    public function toApi(SiteVisit $visit): array
    {
        $site = $visit->site;

        return [
            'id' => $visit->id,
            'visit_date' => $visit->visit_date->toDateString(),
            'visited_at' => $visit->visited_at->format('Y-m-d H:i:s'),
            'site' => $site ? [
                'id' => $site->id, 'site_code' => $site->site_code, 'site_name' => $site->site_name,
                'district' => $site->district, 'address' => $site->address,
            ] : null,
            'contact_person_name' => $visit->contact_person_name,
            'contact_mobile' => $visit->contact_mobile,
            'remarks' => $visit->remarks,
            'file_url' => file_url($visit->file_path, self::FILE_MODULE),
            'file_name' => $visit->file_path ? basename($visit->file_path) : null,
            'latitude' => $visit->latitude,
            'longitude' => $visit->longitude,
            'edited_at' => $visit->edited_at?->format('Y-m-d H:i:s'),
            'can_edit' => (int) $visit->user_id === (int) auth()->id() && $this->isEditable($visit),
        ];
    }
}
