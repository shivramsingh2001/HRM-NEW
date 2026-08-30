<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Models\Holiday;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class HolidayController extends Controller
{
    public function view_ai_all(Request $request)
    {
        try {
            $user = Auth::user();
            $currentYear = now()->year;

            $holidays = Holiday::whereYear('start_date', $currentYear)
                ->where('status','1')
                ->orderBy('start_date', 'asc')
                ->get(['id','start_date','name','description']);
            return response()->json([
                'success' => true,
                'message' => "Data fetched successfully!!!",
                'data' => $holidays
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "An error occured. Please try again later.",
            ], 500);
        }
    }
}
