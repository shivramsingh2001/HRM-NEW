<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\AttendanceAnomaly;
use App\Services\Analytics\AttendanceAnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Tier 2 / T2-F — public analytics + anomaly feed (scope analytics:read).
 */
class AnalyticsV1Controller extends Controller
{
    public function presentNow(AttendanceAnalyticsService $svc)
    {
        return ApiResponse::ok($svc->presentNow((int) app('current_tenant')->id));
    }

    public function trends(Request $request, AttendanceAnalyticsService $svc)
    {
        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'department_id' => ['nullable', 'integer'],
        ]);

        $from = isset($data['from']) ? Carbon::parse($data['from'])->toDateString() : now()->subDays(30)->toDateString();
        $to = isset($data['to']) ? Carbon::parse($data['to'])->toDateString() : now()->toDateString();

        return ApiResponse::ok($svc->trends(
            (int) app('current_tenant')->id, $from, $to, $data['department_id'] ?? null
        ), ['from' => $from, 'to' => $to]);
    }

    public function overtimeCost(Request $request, AttendanceAnalyticsService $svc)
    {
        $data = $request->validate(['month' => ['nullable', 'date_format:Y-m']]);

        return ApiResponse::ok($svc->overtimeCost(
            (int) app('current_tenant')->id, $data['month'] ?? now()->format('Y-m')
        ));
    }

    public function anomalies(Request $request)
    {
        $data = $request->validate([
            'status' => ['nullable', 'in:open,ack,dismissed'],
            'type' => ['nullable', 'string'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $page = AttendanceAnomaly::query()
            ->when($data['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($data['type'] ?? null, fn ($q, $t) => $q->where('type', $t))
            ->orderByDesc('id')
            ->paginate($data['per_page'] ?? 50);

        return ApiResponse::paginated($page, fn ($a) => [
            'id' => $a->id,
            'user_id' => $a->user_id,
            'date' => optional($a->date)->toDateString(),
            'type' => $a->type,
            'severity' => $a->severity,
            'status' => $a->status,
            'detail' => $a->detail,
            'created_at' => optional($a->created_at)->toIso8601String(),
        ]);
    }
}
