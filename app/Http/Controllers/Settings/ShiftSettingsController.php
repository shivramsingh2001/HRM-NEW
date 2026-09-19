<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateShiftSettingsRequest;
use App\Models\Shift;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserWeekoffs;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ShiftSettingsController extends Controller
{
    /**
     * Show the per-tenant shift settings page.
     */
    public function index()
    {
        $tenant = Tenant::findOrFail(Auth::user()->tenant_id);

        $defaultShift = $tenant->default_shift_id
            ? Shift::withoutGlobalScopes()->where('tenant_id', $tenant->id)->find($tenant->default_shift_id)
            : null;

        $activeShiftsCount = Shift::where('tenant_id', $tenant->id)->where('status', 1)->count();
        $employeeCount = User::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('status', 1)->count();
        $assignedThisMonth = DB::table('user_shifts')
            ->where('tenant_id', $tenant->id)
            ->whereMonth('date', now()->month)
            ->whereYear('date', now()->year)
            ->count();

        return view('client.shift.settings', [
            'tenant' => $tenant,
            'defaultShift' => $defaultShift,
            'weekdays' => UpdateShiftSettingsRequest::WEEKDAYS,
            'activeShiftsCount' => $activeShiftsCount,
            'employeeCount' => $employeeCount,
            'assignedThisMonth' => $assignedThisMonth,
        ]);
    }

    /**
     * Persist the custom-shifts toggle and, when it is off, the fixed company
     * shift + the company-wide weekly offs.
     */
    public function update(UpdateShiftSettingsRequest $request)
    {
        $tenant = Tenant::findOrFail(Auth::user()->tenant_id);
        $enabled = $request->boolean('custom_shifts_enabled');

        DB::transaction(function () use ($request, $tenant, $enabled) {
            if (!$enabled) {
                $shift = $this->upsertDefaultShift($request, $tenant);
                $tenant->default_shift_id = $shift->id;

                $weekoffDays = array_values($request->input('weekoff_days', []));
                $tenant->default_weekoff_days = $weekoffDays;
                $this->regenerateWeeklyOffs($tenant->id, $weekoffDays);
            }

            $tenant->custom_shifts_enabled = $enabled;
            $tenant->save();
        });

        return back()->with(
            'success',
            $enabled
                ? 'Custom shifts enabled. Manage shifts and assignments from the Shifts screen.'
                : 'Shift settings saved. The whole company now runs on the fixed shift.'
        );
    }

    /**
     * Update the tenant's single fixed shift row, creating it the first time.
     */
    private function upsertDefaultShift(UpdateShiftSettingsRequest $request, Tenant $tenant): Shift
    {
        $start = $request->input('start_time');
        $end = $request->input('end_time');
        $grace = (int) ($request->input('grace_minutes') ?? 0);
        $break = (int) ($request->input('break_time') ?? 0);

        $s = Carbon::parse($start);
        $e = Carbon::parse($end);
        if ($e->lessThanOrEqualTo($s)) {
            $e->addDay(); // overnight
        }
        $totalHours = round(max(0, $s->diffInMinutes($e) - $break) / 60, 2);

        $shift = $tenant->default_shift_id
            ? Shift::withoutGlobalScopes()->where('tenant_id', $tenant->id)->find($tenant->default_shift_id)
            : null;

        $attrs = [
            'name' => $shift->name ?? 'Company Hours',
            'start_time' => $start,
            'end_time' => $end,
            'total_hours' => $totalHours,
            'grace_minutes' => $grace,
            'break_time' => $break,
            'status' => 1,
        ];

        if ($shift) {
            $shift->update($attrs);

            return $shift;
        }

        return Shift::create($attrs + ['created_by' => Auth::id(), 'tenant_id' => $tenant->id]);
    }

    /**
     * Rebuild the company-wide day_based week-offs for every active employee.
     */
    private function regenerateWeeklyOffs(int $tenantId, array $weekoffDays): void
    {
        UserWeekoffs::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('off_type', 'day_based')
            ->delete();

        if (empty($weekoffDays)) {
            return;
        }

        $userIds = User::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('status', 1)
            ->pluck('id');

        $now = now();
        $rows = [];
        foreach ($userIds as $userId) {
            foreach ($weekoffDays as $day) {
                $rows[] = [
                    'tenant_id' => $tenantId,
                    'user_id' => $userId,
                    'off_type' => 'day_based',
                    'day_name' => $day,
                    'start_date' => null,
                    'end_date' => null,
                    'status' => 1,
                    'created_by' => Auth::id(),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('user_weekoffs')->insert($chunk);
        }
    }
}
