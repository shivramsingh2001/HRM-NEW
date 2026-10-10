<?php

namespace App\Http\Controllers\Shift;

use App\Http\Controllers\Controller;
use App\Models\Shift;
use App\Models\UserShift;
use App\Support\ShiftWindow;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Log;

class ShiftController extends Controller
{

    /**
     * Display a listing of shifts
     */
    public function index(Request $request)
    {
        try {
            $query = Shift::query();

            // Filter by status
            if ($request->filled('status')) {
                $status = (int) $request->status;
                $query->where('status', $status);
                Log::info('Filtering by status: ' . $status);
            }

            // Search by name
            if ($request->filled('search')) {
                $query->where('name', 'LIKE', '%' . $request->search . '%');
            }

            // Filter by date range
            if ($request->filled('from_date') && $request->filled('to_date')) {
                $query->whereBetween('created_at', [$request->from_date, $request->to_date]);
            }

            // Per-shift "assigned this month" count for the list column
            $currentMonth = Carbon::now();
            $shifts = $query->withCount(['userShifts as assigned_this_month' => function ($q) use ($currentMonth) {
                $q->whereMonth('date', $currentMonth->month)->whereYear('date', $currentMonth->year);
            }])->orderBy('id', 'desc')->paginate(15)->withQueryString();

            // Get statistics
            $totalShifts = Shift::count();
            $activeShifts = Shift::where('status', 1)->count();
            $inactiveShifts = Shift::where('status', 0)->count();

            $assignedShiftsCount = UserShift::whereMonth('date', $currentMonth->month)
                ->whereYear('date', $currentMonth->year)
                ->count();

            return view('client.shift.index', compact(
                'shifts',
                'totalShifts',
                'activeShifts',
                'inactiveShifts',
                'assignedShiftsCount'
            ));
        } catch (Exception $e) {
            Log::error('Shift index error: ' . $e->getMessage());

            return back()->with('error', 'Failed to load shifts. Please try again.');
        }
    }

    /**
     * Store a newly created shift
     */
    public function store(\App\Http\Requests\Shift\StoreShiftRequest $request)
    {
        try {
            DB::beginTransaction();

            $shift = Shift::create([
                'name' => $request->name,
                'start_time' => $request->start_time,
                'end_time' => $request->end_time,
                'is_overnight' => $request->boolean('is_overnight'),
                'total_hours' => ShiftWindow::workingHours($request->start_time, $request->end_time, $request->break_time, $request->boolean('is_overnight')),
                'description' => $request->description,
                'grace_minutes' => $request->grace_minutes ?? 0,
                'color_code' => $request->color_code ?? '#3b82f6',
                'break_time' => $request->break_time ?? 0,
                ...$this->allowanceColumns($request),
                'status' => 1,
                'created_by' => Auth::id()
            ]);

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Shift created successfully',
                'data' => $shift
            ], 201);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Shift creation error: ' . $e->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'Shift creation failed. Please try again.'
            ], 500);
        }
    }

    /**
     * Update the specified shift
     */
    public function update(\App\Http\Requests\Shift\UpdateShiftRequest $request, $id)
    {
        try {
            $shift = Shift::find($id);

            DB::beginTransaction();

            $shift->update([
                'name' => $request->name,
                'start_time' => $request->start_time,
                'end_time' => $request->end_time,
                'is_overnight' => $request->boolean('is_overnight'),
                'total_hours' => ShiftWindow::workingHours($request->start_time, $request->end_time, $request->break_time, $request->boolean('is_overnight')),
                'description' => $request->description,
                'grace_minutes' => $request->grace_minutes ?? 0,
                'color_code' => $request->color_code ?? '#3b82f6',
                'break_time' => $request->break_time ?? 0,
                ...$this->allowanceColumns($request),
                'status' => $request->status ?? $shift->status
            ]);

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Shift updated successfully',
                'data' => $shift
            ], 200);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Shift update error: ' . $e->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'Shift update failed. Please try again.'
            ], 500);
        }
    }

    /**
     * Shift allowance (paid in payroll by ShiftAllowanceCalculator): none, or
     * an amount per day / per hour worked, optionally only once the shift's
     * worked hours reach allowance_min_hours.
     */
    private function allowanceColumns(Request $request): array
    {
        $type = $request->input('allowance_type', 'none') ?: 'none';
        $amount = (float) ($request->allowance_amount ?? 0);
        if ($type === 'none' || $amount <= 0) {
            return ['allowance_type' => 'none', 'allowance_amount' => 0, 'allowance_min_hours' => null];
        }

        return [
            'allowance_type' => $type,
            'allowance_amount' => round($amount, 2),
            'allowance_min_hours' => $request->filled('allowance_min_hours') ? (float) $request->allowance_min_hours : null,
        ];
    }

    /**
     * Change shift status
     */
    public function changeStatus($id)
    {
        try {
            $shift = Shift::find($id);

            if (!$shift) {
                return response()->json([
                    'status' => false,
                    'message' => 'Shift not found'
                ], 404);
            }

            DB::beginTransaction();

            $shift->status = $shift->status == 1 ? 0 : 1;
            $shift->save();

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Status updated successfully',
                'new_status' => $shift->status
            ], 200);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Shift status change error: ' . $e->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'Failed to update status'
            ], 500);
        }
    }

    /**
     * Delete shift
     */
    public function destroy($id)
    {
        try {
            $shift = Shift::find($id);

            if (!$shift) {
                return response()->json([
                    'status' => false,
                    'message' => 'Shift not found'
                ], 404);
            }

            // Check if shift is being used
            $assignedCount = UserShift::where('shift_id', $id)->count();

            if ($assignedCount > 0) {
                return response()->json([
                    'status' => false,
                    'message' => 'Cannot delete shift as it is assigned to ' . $assignedCount . ' users'
                ], 400);
            }

            // The tenant's fixed company shift (used when custom shifts are off).
            $isDefaultShift = \App\Models\Tenant::where('id', $shift->tenant_id)
                ->where('default_shift_id', $id)
                ->exists();

            if ($isDefaultShift) {
                return response()->json([
                    'status' => false,
                    'message' => 'Cannot delete the company default shift. Change it in Shift Settings instead.'
                ], 400);
            }

            DB::beginTransaction();

            $shiftName = $shift->name;
            $shift->delete();

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Shift "' . $shiftName . '" deleted successfully'
            ], 200);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Shift delete error: ' . $e->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'Delete failed. Please try again.'
            ], 500);
        }
    }

    /**
     * Assign week offs to a user
     */

}
