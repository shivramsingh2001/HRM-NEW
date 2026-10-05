<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Services\CelebrationService;
use Exception;
use Illuminate\Support\Facades\Log;

/**
 * GET /api/user/celebrations/today — today's birthdays and work anniversaries
 * in the employee's company (any signed-in user). Same list as the admin
 * dashboard's "Today's celebrations" card (App\Services\CelebrationService).
 */
class CelebrationController extends Controller
{
    public function today(CelebrationService $celebrations)
    {
        try {
            $data = $celebrations->today();

            return response()->json([
                'success' => true,
                'message' => $data['total'] > 0 ? 'Today\'s celebrations fetched successfully.' : 'No birthdays or work anniversaries today.',
                'data' => $data,
            ], 200);
        } catch (Exception $e) {
            Log::error('Celebrations today API error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Unable to fetch today\'s celebrations. Please try again later.',
            ], 500);
        }
    }
}
