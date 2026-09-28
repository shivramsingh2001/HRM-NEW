<?php

namespace App\Services\Broadcast;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\LazyCollection;

/**
 * Resolves a Tenant Admin's audience filters into the matching set of
 * `users` rows for a broadcast.
 *
 * Combination semantics (product-confirmed, see the module's implementation
 * plan):
 *  - `all=true` is mutually exclusive with every other filter (enforced in
 *    BroadcastComposerService::validate(), not here).
 *  - Across dimensions (role/department/designation/branch): AND — each
 *    non-empty dimension narrows the result.
 *  - Within one dimension (e.g. role=[admin,hr]): OR — any of the values.
 *  - `user_ids`: ADDITIVE — hand-picked employees are unioned on top of
 *    whatever the dimension filters resolve to, never intersected with them.
 *
 * Tenant isolation is structural, not conventional: this always queries
 * `App\Models\User`, which carries `TenantTrait`'s global scope, so every
 * query here is automatically bounded to `app('current_tenant')->id`
 * regardless of what a request payload claims. Department/designation/branch
 * ids are matched through `UserJobDetail`'s `whereHas`, which itself joins
 * `TenantTrait`-scoped models (`Department`/`Designation`/`CompanyBranch`),
 * so a forged cross-tenant id simply matches zero rows rather than leaking.
 */
class BroadcastAudienceResolver
{
    public function queryFor(array $filters, ?int $excludeUserId = null): Builder
    {
        $all = (bool) ($filters['all'] ?? false);
        $roles = $this->cleanValues($filters['role'] ?? []);
        $departmentIds = $this->cleanIds($filters['department_ids'] ?? []);
        $designationIds = $this->cleanIds($filters['designation_ids'] ?? []);
        $branchIds = $this->cleanIds($filters['branch_ids'] ?? []);
        $userIds = $this->cleanIds($filters['user_ids'] ?? []);

        $query = User::query()->where('status', 1);

        if (! $all) {
            $hasDimensionFilter = $roles || $departmentIds || $designationIds || $branchIds;

            $query->where(function (Builder $outer) use ($hasDimensionFilter, $roles, $departmentIds, $designationIds, $branchIds, $userIds) {
                if ($hasDimensionFilter) {
                    $outer->where(function (Builder $dims) use ($roles, $departmentIds, $designationIds, $branchIds) {
                        if ($roles) {
                            $dims->whereIn('role', $roles);
                        }
                        if ($departmentIds) {
                            $dims->whereHas('jobDetails', fn (Builder $jd) => $jd->whereIn('department', $departmentIds));
                        }
                        if ($designationIds) {
                            $dims->whereHas('jobDetails', fn (Builder $jd) => $jd->whereIn('designation', $designationIds));
                        }
                        if ($branchIds) {
                            $dims->whereHas('jobDetails', fn (Builder $jd) => $jd->whereIn('branch_id', $branchIds));
                        }
                    });
                }

                if ($userIds) {
                    $hasDimensionFilter ? $outer->orWhereIn('id', $userIds) : $outer->whereIn('id', $userIds);
                }

                if (! $hasDimensionFilter && ! $userIds) {
                    // Nothing selected and not "all" — BroadcastComposerService::validate()
                    // should already reject this payload; stay defensively empty here too.
                    $outer->whereRaw('1 = 0');
                }
            });
        }

        if ($excludeUserId) {
            $query->where('id', '!=', $excludeUserId);
        }

        return $query;
    }

    public function countFor(array $filters, ?int $excludeUserId = null): int
    {
        return $this->queryFor($filters, $excludeUserId)->count();
    }

    public function resolveFor(array $filters, ?int $excludeUserId = null): LazyCollection
    {
        return $this->queryFor($filters, $excludeUserId)->select(['id', 'tenant_id'])->cursor();
    }

    private function cleanIds(array $values): array
    {
        return array_values(array_unique(array_filter(array_map('intval', $values))));
    }

    private function cleanValues(array $values): array
    {
        return array_values(array_unique(array_filter(array_map('strval', $values))));
    }
}
