<?php

namespace App\Http\Controllers\AI\Concerns;

use App\Models\User;
use App\Services\RbacService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Shared helpers for the /api/ai/* read endpoints: who the caller may see
 * (own / team / company, from RbacService) and the module's JSON shapes.
 */
trait AiScope
{
    /** The caller's view scope for $module: own | team | company | null (no grant). */
    protected function scopeOf(User $user, string $module): ?string
    {
        return app(RbacService::class)->scopeFor($user, $module, 'view');
    }

    /**
     * Users visible under $scope.
     * null  = company scope (everyone in the tenant)
     * array = user ids (team = caller + people reporting to them; own = caller)
     */
    protected function visibleUserIds(User $user, string $scope): ?array
    {
        return match ($scope) {
            'company' => null,
            'team' => array_values(array_unique(array_merge(
                [(int) $user->id],
                DB::table('user_reporting_heads')
                    ->where('reporting_head_id', $user->id)
                    ->pluck('user_id')->map(fn ($id) => (int) $id)->all()
            ))),
            default => [(int) $user->id],
        };
    }

    /** Restrict $ids further to one requested user_id (null = no request). */
    protected function narrowToUser(array|null $ids, $requested): array|null
    {
        if ($requested === null || $requested === '' || ! ctype_digit((string) $requested)) {
            return $ids;
        }
        $requested = (int) $requested;

        return $ids === null || in_array($requested, $ids, true) ? [$requested] : [];
    }

    protected function forbidden(): JsonResponse
    {
        return response()->json(['success' => false, 'message' => 'Unauthorized access. Invalid role.'], 403);
    }

    protected function failed(string $what, \Throwable $e): JsonResponse
    {
        Log::error("AI {$what} failed", ['user_id' => Auth::id(), 'error' => $e->getMessage()]);

        return response()->json(['success' => false, 'message' => 'An error occurred. Please try again later.'], 500);
    }

    /** Valid Y-m-d string or null. */
    protected function ymd($value): ?string
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }
        [$y, $m, $d] = array_map('intval', explode('-', $value));

        return checkdate($m, $d, $y) ? $value : null;
    }

    /** id => {id, name, employee_id} for the given user ids (one query). */
    protected function people(int $tenantId, iterable $ids): array
    {
        $ids = collect($ids)->filter()->unique()->values();
        if ($ids->isEmpty()) {
            return [];
        }

        return DB::table('users')->where('tenant_id', $tenantId)->whereIn('id', $ids)
            ->get(['id', 'name', 'employee_id'])
            ->mapWithKeys(fn ($u) => [$u->id => ['id' => $u->id, 'name' => $u->name, 'employee_id' => $u->employee_id]])
            ->all();
    }
}
