<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The company's leave year (Company Policies → Leave carry forward):
 * tenants.leave_year_start_month / _day, falling back to
 * config('leave.fiscal_year_start_*') (1 April). Used by the yearly credit,
 * the new-joiner pro-rata credit and `leaves:carry-forward`.
 */
class LeaveYearService
{
    /** @var array<int,array{0:int,1:int}> */
    private array $memo = [];

    /** [month, day] the tenant's leave year starts on. */
    public function anchor(int $tenantId): array
    {
        if (! isset($this->memo[$tenantId])) {
            $row = DB::table('tenants')->where('id', $tenantId)->first(['leave_year_start_month', 'leave_year_start_day']);
            $month = (int) ($row->leave_year_start_month ?? 0) ?: (int) config('leave.fiscal_year_start_month', 4);
            $day = (int) ($row->leave_year_start_day ?? 0) ?: (int) config('leave.fiscal_year_start_day', 1);
            $this->memo[$tenantId] = [max(1, min(12, $month)), max(1, min(28, $day))];
        }

        return $this->memo[$tenantId];
    }

    /** Start of the leave year that $date falls in. */
    public function startFor(int $tenantId, ?Carbon $date = null): Carbon
    {
        $date = ($date ?? Carbon::today())->copy()->startOfDay();
        [$month, $day] = $this->anchor($tenantId);
        $start = Carbon::create($date->year, $month, $day)->startOfDay();

        return $start->gt($date) ? $start->subYear() : $start;
    }

    /** The leave-year start in calendar year $year (e.g. the next one after a joining date). */
    public function startInYear(int $tenantId, int $year): Carbon
    {
        [$month, $day] = $this->anchor($tenantId);

        return Carbon::create($year, $month, $day)->startOfDay();
    }

    /** "2026-27" style label for the leave year starting on $start ("2026" for a January start). */
    public function label(Carbon $start): string
    {
        return $start->month === 1 && $start->day === 1
            ? (string) $start->year
            : $start->year . '-' . substr((string) ($start->year + 1), -2);
    }

    public function forget(): void
    {
        $this->memo = [];
    }
}
