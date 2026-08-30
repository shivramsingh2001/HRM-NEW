<?php

namespace App\Http\Controllers\Attendance;

use App\Http\Controllers\Controller;
use App\Models\OvertimeSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class OvertimeSettingController extends Controller
{
    /**
     * Roles allowed to change tenant overtime policy.
     */
    private const MANAGER_ROLES = ['admin', 'hr'];

    /**
     * Reject the request (JSON 403) if the current user may not manage settings.
     * Route middleware (role:admin,hr) is the primary guard; this is defence in depth.
     */
    private function ensureCanManage(): ?\Illuminate\Http\JsonResponse
    {
        $user = auth()->user();

        if (!$user || !in_array($user->role, self::MANAGER_ROLES, true)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only admin and HR can manage overtime settings.',
            ], 403);
        }

        return null;
    }

    /**
     * Current tenant id, or null when it cannot be resolved.
     */
    private function currentTenantId(): ?int
    {
        return auth()->user()->tenant_id ?? null;
    }

    /**
     * Display overtime settings page
     */
    public function index()
    {
        $tenantId = $this->currentTenantId();

        // Prefer the tenant-specific row; fall back to the global default.
        $settings = OvertimeSetting::where('tenant_id', $tenantId)
            ->orWhereNull('tenant_id')
            ->orderByRaw('tenant_id IS NULL') // tenant-specific row first
            ->first();

        return view('client.overtime.settings', compact('settings'));
    }

    /**
     * Update overtime settings
     */
    public function update(Request $request)
    {
        if ($denied = $this->ensureCanManage()) {
            return $denied;
        }

        $tenantId = $this->currentTenantId();

        if (!$tenantId) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to resolve your company. Please sign in again.',
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'rate_multiplier' => 'required|numeric|min:1|max:5',
            'max_hours_per_day' => 'nullable|numeric|min:0|max:24',
            'max_hours_per_month' => 'nullable|numeric|min:0|max:200',
            'require_approval' => 'nullable|boolean',
            'auto_approve_limit' => 'nullable|numeric|min:0|max:24',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $settings = OvertimeSetting::updateOrCreate(
            ['tenant_id' => $tenantId],
            [
                'rate_multiplier' => $request->rate_multiplier,
                'max_hours_per_day' => $request->max_hours_per_day,
                'max_hours_per_month' => $request->max_hours_per_month,
                'require_approval' => $request->has('require_approval') ? true : false,
                'auto_approve_limit' => $request->auto_approve_limit,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Overtime settings updated successfully',
            'data' => $settings
        ]);
    }

    /**
     * Reset settings to default
     */
    public function reset(Request $request)
    {
        if ($denied = $this->ensureCanManage()) {
            return $denied;
        }

        $tenantId = $this->currentTenantId();

        if (!$tenantId) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to resolve your company. Please sign in again.',
            ], 422);
        }

        $defaultSettings = [
            'rate_multiplier' => 1.50,
            'max_hours_per_day' => 4.00,
            'max_hours_per_month' => 50.00,
            'require_approval' => true,
            'auto_approve_limit' => 2.00,
        ];

        $settings = OvertimeSetting::updateOrCreate(
            ['tenant_id' => $tenantId],
            $defaultSettings
        );

        return response()->json([
            'success' => true,
            'message' => 'Settings reset to default successfully',
            'data' => $settings
        ]);
    }
}
