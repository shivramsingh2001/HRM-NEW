<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\Dashboard\AdminDashboardPayload;
use Exception;
use Illuminate\Http\JsonResponse;

/**
 * Mobile admin API — the admin dashboard (Admin / HR). Same data as the web
 * /dashboard for admin and HR; the period filter is the same query string:
 * range = today | last_7_days | this_month | previous_month | custom (+ from, to).
 */
class DashboardController extends Controller
{
    public function index(AdminDashboardPayload $payload): JsonResponse
    {
        try {
            return response()->json(['success' => true, 'data' => $payload->build()]);
        } catch (Exception $e) {
            report($e);

            return response()->json(['success' => false, 'message' => 'Could not load the dashboard. Please try again.'], 500);
        }
    }
}
