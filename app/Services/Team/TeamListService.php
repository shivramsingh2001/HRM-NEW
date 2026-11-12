<?php

namespace App\Services\Team;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The Team page list for one day: the members the viewer may see (RBAC team:view
 * scope), each with that day's status, the status filter, and the status counts.
 * Shared by the web Team page (TeamController::team) and the mobile admin API.
 */
class TeamListService
{
    use \App\Http\Controllers\Concerns\TeamAttendanceStatus;

    public const STATUSES = ['present', 'absent', 'on_leave', 'holiday', 'weekoff', 'halfday', 'checked_in_only'];

    /**
     * @param  array{date?: ?string, status?: ?string, search?: ?string, branch_id?: mixed, department_id?: mixed, designation_id?: mixed}  $filters
     * @return array{members: Collection<int, object>, counts: array<string, int>}
     */
    public function list(User $viewer, string $date, array $filters = []): array
    {
        $members = $this->getTeamMembers($viewer, $date, null, $filters['search'] ?? null,
            $filters['branch_id'] ?? null, $filters['department_id'] ?? null, $filters['designation_id'] ?? null);

        $status = $filters['status'] ?? null;
        if ($status && in_array($status, self::STATUSES, true)) {
            $members = $members->filter(fn ($member) => $this->matches(strtolower($member->status ?? 'absent'), $status))->values();
        }

        return ['members' => $members, 'counts' => $this->counts($members)];
    }

    /** Dropdown options for the Team filters and the mark-attendance form. */
    public function filterOptions(int $tenantId): array
    {
        $options = fn (string $table) => DB::table($table)->where('tenant_id', $tenantId)->orderBy('name')->get(['id', 'name']);

        return [
            'branches' => $options('company_branches'),
            'departments' => $options('departments'),
            'designations' => $options('designations'),
            'leave_types' => \App\Models\LeaveType::where('tenant_id', $tenantId)->where('status', 1)->orderBy('name')->get(['id', 'name']),
        ];
    }

    private function matches(string $memberStatus, string $filter): bool
    {
        return match ($filter) {
            'present' => in_array($memberStatus, ['present', 'halfday', 'checked in only'], true),
            'absent' => $memberStatus === 'absent',
            'on_leave' => str_contains($memberStatus, 'leave'),
            'holiday' => $memberStatus === 'holiday',
            'weekoff' => $memberStatus === 'week off',
            'halfday' => $memberStatus === 'halfday',
            'checked_in_only' => $memberStatus === 'checked in only',
            default => true,
        };
    }

    /** @return array<string, int> half days and checked-in-only also count as present */
    private function counts(Collection $members): array
    {
        $counts = ['present' => 0, 'absent' => 0, 'on_leave' => 0, 'holiday' => 0, 'weekoff' => 0,
            'checked_in_only' => 0, 'halfday' => 0, 'total' => $members->count()];

        foreach ($members as $member) {
            $status = strtolower($member->status ?? 'absent');
            if ($status === 'present') {
                $counts['present']++;
            } elseif ($status === 'halfday') {
                $counts['halfday']++;
                $counts['present']++;
            } elseif ($status === 'checked in only') {
                $counts['checked_in_only']++;
                $counts['present']++;
            } elseif (str_contains($status, 'leave')) {
                $counts['on_leave']++;
            } elseif ($status === 'holiday') {
                $counts['holiday']++;
            } elseif ($status === 'week off') {
                $counts['weekoff']++;
            } elseif ($status === 'absent') {
                $counts['absent']++;
            }
        }

        return $counts;
    }
}
