<?php

namespace App\Http\Controllers\Analytics;

use App\Http\Controllers\Controller;
use App\Models\AttendanceAnomaly;
use App\Services\Analytics\AttendanceAnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * Tier 2 / T2-F — the tenant-facing attendance analytics dashboard + anomaly
 * review.
 */
class AttendanceAnalyticsController extends Controller
{
    public function index(Request $request, AttendanceAnalyticsService $svc)
    {
        $tenantId = (int) Auth::user()->tenant_id;

        $from = $request->filled('from') ? Carbon::parse($request->from)->toDateString() : now()->subDays(30)->toDateString();
        $to = $request->filled('to') ? Carbon::parse($request->to)->toDateString() : now()->toDateString();
        $month = $request->input('month', now()->format('Y-m'));

        return view('client.analytics.attendance', [
            'presentNow' => $svc->presentNow($tenantId),
            'trends' => $svc->trends($tenantId, $from, $to),
            'otCost' => $svc->overtimeCost($tenantId, $month),
            'anomalies' => AttendanceAnomaly::where('tenant_id', $tenantId)
                ->where('status', 'open')->orderByDesc('id')->limit(50)->get(),
            'from' => $from, 'to' => $to, 'month' => $month,
        ]);
    }

    public function reviewAnomaly(Request $request, $id)
    {
        $tenantId = (int) Auth::user()->tenant_id;
        $data = $request->validate([
            'status' => ['required', 'in:ack,dismissed'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        AttendanceAnomaly::where('tenant_id', $tenantId)->findOrFail($id)->update([
            'status' => $data['status'],
            'review_note' => $data['note'] ?? null,
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
        ]);

        return back()->with('success', 'Anomaly ' . $data['status'] . '.');
    }
}
