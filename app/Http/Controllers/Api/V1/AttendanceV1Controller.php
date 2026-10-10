<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AttendanceStatus;
use App\Exceptions\ApiException;
use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\User;
use App\Services\Attendance\AttendanceEntryService;
use App\Services\AttendanceSummaryService;
use App\Services\RbacService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Tier 2 / T2-B — public read + write over a tenant's attendance.
 * Tenant scope comes from the API key (current_tenant is bound by ResolveApiClient).
 */
class AttendanceV1Controller extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'user_id' => ['nullable', 'integer'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $from = isset($data['from']) ? Carbon::parse($data['from'])->format('Y-m-d') : now()->subDays(30)->format('Y-m-d');
        $to = isset($data['to']) ? Carbon::parse($data['to'])->format('Y-m-d') : now()->format('Y-m-d');

        $page = Attendance::query()
            ->when($data['user_id'] ?? null, fn ($q, $u) => $q->where('user_id', $u))
            ->whereBetween('date', [$from, $to])
            ->orderBy('date')
            ->orderBy('id')
            ->paginate($data['per_page'] ?? 50);

        return ApiResponse::paginated($page, fn ($r) => [
            'id' => $r->id,
            'user_id' => $r->user_id,
            'date' => (string) $r->date,
            'clock_in' => $r->clock_in,
            'clock_out' => $r->clock_out,
            'clock_in_utc' => $r->clock_in_utc,
            'clock_out_utc' => $r->clock_out_utc,
            'timezone' => $r->tz,
            'worked_hours' => $r->worked_hours,
            'attendance_status' => $r->attendance_status,
            'effective_status' => $r->effective_status,
            'is_regularized' => (bool) $r->is_regularized,
        ]);
    }

    public function summary(Request $request)
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer'],
            'month' => ['nullable', 'date_format:Y-m'],
        ]);

        $tenantId = (int) app('current_tenant')->id;
        $month = $data['month'] ?? now()->format('Y-m');

        if (! User::where('id', $data['user_id'])->where('tenant_id', $tenantId)->exists()) {
            throw new ApiException('user.not_in_tenant', 'That user is not in your tenant.', 404);
        }

        $summary = app(AttendanceSummaryService::class)->getMonthly($data['user_id'], $month, $tenantId);

        return ApiResponse::ok($summary);
    }

    public function mark(Request $request, AttendanceEntryService $entry)
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer'],
            'actor_user_id' => ['required', 'integer'],
            'date' => ['required', 'date', 'before_or_equal:today'],
            'end_date' => ['nullable', 'date', 'after_or_equal:date', 'before_or_equal:today'],
            'status' => ['required', 'string'],
            'clock_in' => ['nullable', 'date_format:H:i'],
            'clock_out' => ['nullable', 'date_format:H:i'],
            'clock_out_next_day' => ['nullable', 'boolean'],
            'leave_type_id' => ['nullable', 'integer'],
            'remarks' => ['nullable', 'string', 'max:500'],
        ]);

        $tenantId = (int) app('current_tenant')->id;

        if (! in_array($data['status'], AttendanceStatus::markable(), true)) {
            throw new ApiException('attendance.invalid_status', 'Unsupported status: ' . $data['status'], 422, [
                'allowed' => AttendanceStatus::markable(),
            ]);
        }

        $actor = User::where('id', $data['actor_user_id'])->where('tenant_id', $tenantId)->first();
        if (! $actor || ! app(RbacService::class)->can($actor, 'attendance', 'edit')) {
            throw new ApiException('actor.not_authorized', 'actor_user_id must be an admin/hr/manager in your tenant.', 403);
        }
        if (! User::where('id', $data['user_id'])->where('tenant_id', $tenantId)->exists()) {
            throw new ApiException('user.not_in_tenant', 'That user is not in your tenant.', 404);
        }

        $result = $entry->markStatus([
            'user_id' => $data['user_id'],
            'tenant_id' => $tenantId,
            'date' => $data['date'],
            'end_date' => $data['end_date'] ?? null,
            'status' => AttendanceStatus::from($data['status']),
            'clock_in' => $data['clock_in'] ?? null,
            'clock_out' => $data['clock_out'] ?? null,
            'clock_out_next_day' => (bool) ($data['clock_out_next_day'] ?? false),
            'leave_type_id' => $data['leave_type_id'] ?? null,
            'remarks' => $data['remarks'] ?? null,
        ], $actor);

        return ApiResponse::ok([
            'marked_days' => $result['marked'],
            'is_update' => $result['is_update'],
            'attendance' => [
                'id' => $result['attendance']->id ?? null,
                'date' => (string) ($result['attendance']->date ?? $data['date']),
                'attendance_status' => $result['attendance']->attendance_status ?? $data['status'],
                'effective_status' => $result['attendance']->effective_status ?? null,
            ],
            'leave_created' => $result['leave']->leave_id ?? null,
        ], [], 201);
    }
}
