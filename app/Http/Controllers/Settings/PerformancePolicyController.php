<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdatePerformancePolicyRequest;
use App\Models\PerformancePolicy;
use App\Services\Performance\PerformancePolicyResolver;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class PerformancePolicyController extends Controller
{
    /**
     * Show the performance-scoring policy settings page for the current tenant.
     */
    public function index()
    {
        $tenantId = (int) Auth::user()->tenant_id;

        // The weights/thresholds currently in force (tenant row, else global default).
        $policy = app(PerformancePolicyResolver::class)->forTenantDate($tenantId, now()->format('Y-m-d'));

        $history = PerformancePolicy::where('tenant_id', $tenantId)
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->limit(12)
            ->get();

        return view('client.settings.performance-policy', compact('policy', 'history'));
    }

    /**
     * Persist a NEW versioned policy row (effective from the chosen date,
     * default today) — never mutates a past version in place, matching
     * AttendancePolicyController's pattern.
     */
    public function update(UpdatePerformancePolicyRequest $request)
    {
        $tenantId = (int) Auth::user()->tenant_id;
        $data = $request->validated();

        $effectiveFrom = $request->filled('effective_from')
            ? Carbon::parse($request->input('effective_from'))->format('Y-m-d')
            : now()->format('Y-m-d');

        PerformancePolicy::updateOrCreate(
            ['tenant_id' => $tenantId, 'effective_from' => $effectiveFrom],
            array_merge($data, ['created_by' => Auth::id()])
        );

        app(PerformancePolicyResolver::class)->forget();

        return back()->with('success', "Performance policy saved (effective {$effectiveFrom}). It applies from the next daily calculation.");
    }
}
