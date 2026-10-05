<?php

namespace App\Services;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

/**
 * Today's birthdays and work anniversaries for the current tenant (User is
 * tenant-scoped via TenantTrait). Shared by the admin dashboard's "Today's
 * celebrations" card and the mobile API GET /api/user/celebrations/today, so
 * both always list the same people.
 *
 * - Active, non-admin employees only.
 * - Birthday = user_basic_details.dob month/day; someone born on 29 Feb is
 *   celebrated on 28 Feb in non-leap years. Age is not exposed.
 * - Work anniversary = user_job_details.joining_date month/day, at least one
 *   full year ago (no "0 years" on someone's first day).
 */
class CelebrationService
{
    public function today(?Carbon $date = null): array
    {
        $date = ($date ?? Carbon::today())->copy()->startOfDay();

        $birthdays = $this->base()
            ->whereNotNull('b.dob')
            ->where(fn ($q) => $this->onDay($q, 'b.dob', $date))
            ->get()
            ->map(fn ($u) => $this->present($u))
            ->values()
            ->all();

        $anniversaries = $this->base()
            ->whereNotNull('j.joining_date')
            ->whereYear('j.joining_date', '<', $date->year)
            ->where(fn ($q) => $this->onDay($q, 'j.joining_date', $date))
            ->get()
            ->map(fn ($u) => $this->present($u) + ['years' => $date->year - Carbon::parse($u->joined_on)->year])
            ->values()
            ->all();

        return [
            'date' => $date->toDateString(),
            'birthdays' => $birthdays,
            'anniversaries' => $anniversaries,
            'total' => count($birthdays) + count($anniversaries),
        ];
    }

    private function base(): Builder
    {
        return User::query()
            ->join('user_basic_details as b', 'b.user_id', '=', 'users.id')
            ->leftJoin('user_job_details as j', 'j.user_id', '=', 'users.id')
            ->leftJoin('designations as ds', 'ds.id', '=', 'j.designation')
            ->leftJoin('departments as dp', 'dp.id', '=', 'j.department')
            ->where('users.status', 1)
            ->where('users.role', '!=', 'admin')
            ->orderBy('users.name')
            ->select([
                'users.id', 'users.name', 'users.employee_id', 'b.profile_image',
                // aliased: reading `joining_date` on a User hits getJoiningDateAttribute() (lazy-loads jobDetails per row)
                'j.joining_date as joined_on',
                'ds.name as designation_name', 'dp.name as department_name',
            ]);
    }

    /** Same month/day as $date (and 29 Feb on 28 Feb of a non-leap year). */
    private function onDay($query, string $column, Carbon $date): void
    {
        $query->where(fn ($q) => $q->whereMonth($column, $date->month)->whereDay($column, $date->day));

        if ($date->month === 2 && $date->day === 28 && ! $date->isLeapYear()) {
            $query->orWhere(fn ($q) => $q->whereMonth($column, 2)->whereDay($column, 29));
        }
    }

    private function present($u): array
    {
        return [
            'id' => $u->id,
            'name' => $u->name,
            'employee_id' => $u->employee_id,
            'designation' => $u->designation_name,
            'department' => $u->department_name,
            'profile_image' => $u->profile_image ? file_url($u->profile_image, 'profile_photo') : null,
        ];
    }
}
