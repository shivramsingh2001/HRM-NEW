<?php

namespace App\Http\Controllers\Team;

use App\Http\Controllers\Controller;
use App\Http\Requests\Team\BulkMarkAttendanceRequest;
use App\Services\Attendance\BulkAttendanceMarker;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Throwable;

/**
 * Team Members → tick several employees → mark the same status for all of them.
 * One-employee marking stays in TeamController::markAttendance.
 */
class BulkAttendanceController extends Controller
{
    public function store(BulkMarkAttendanceRequest $request, BulkAttendanceMarker $marker): JsonResponse
    {
        try {
            $result = $marker->mark($request->userIds(), [
                'date' => $request->input('date'),
                'end_date' => $request->input('end_date'),
                'status' => $request->attendanceStatus(),
                'use_shift_times' => $request->usesShiftTimes(),
                'clock_in' => $request->input('clock_in'),
                'clock_out' => $request->input('clock_out'),
                'clock_out_next_day' => $request->boolean('clock_out_next_day'),
                'leave_type_id' => $request->input('leave_type_id'),
                'remarks' => $request->input('remarks'),
            ], Auth::user());
        } catch (Throwable $e) {
            report($e);

            return response()->json(['success' => false, 'message' => 'Failed to mark attendance. Please try again.'], 500);
        }

        $ok = count($result['marked']);
        $bad = count($result['failed']);
        $message = $ok.' employee'.($ok === 1 ? '' : 's').' marked'
            .($bad ? ', '.$bad.' not marked' : '').'.';

        return response()->json([
            'success' => $ok > 0,
            'message' => $message,
            'marked' => $result['marked'],
            'failed' => $result['failed'],
        ], 200);
    }
}
