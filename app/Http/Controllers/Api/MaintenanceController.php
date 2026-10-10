<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MaintenanceModeService;
use Illuminate\Http\JsonResponse;

/**
 * GET /api/maintenance — public, no auth. The mobile app calls it on start-up
 * to decide whether to show the maintenance screen. Response shape is frozen
 * (older app versions depend on it).
 */
class MaintenanceController extends Controller
{
    public function show(MaintenanceModeService $maintenance): JsonResponse
    {
        return response()->json($maintenance->publicPayload());
    }
}
