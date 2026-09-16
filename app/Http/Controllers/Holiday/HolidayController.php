<?php

namespace App\Http\Controllers\Holiday;

use App\Http\Controllers\Controller;
use App\Models\Holiday;
use Exception;
use Illuminate\Http\Request;

class HolidayController extends Controller
{
    public function index(Request $request)
    {
        $data['holidays'] = Holiday::orderByDesc('start_date')->get();

        return view('client.holiday.holiday', $data);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'description' => 'nullable|string|max:1000',
        ]);

        try {
            Holiday::create([
                'name' => $validated['name'],
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
                'description' => $validated['description'] ?? null,
                'status' => true,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Holiday created successfully!',
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Something went wrong. Please try again later.',
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:holidays,name,' . $id,
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'description' => 'nullable|string|max:1000',
            'status' => 'required|in:0,1',
        ]);

        try {
            $holiday = Holiday::findOrFail($id);

            $holiday->update([
                'name' => $validated['name'],
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
                'description' => $validated['description'] ?? null,
                'status' => $validated['status'],
            ]);

            return response()->json(['success' => true, 'message' => 'Holiday updated successfully!'], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Holiday not found.'], 404);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => 'Something went wrong. Please try again later.'], 500);
        }
    }

    public function status(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:0,1',
        ]);

        try {
            $holiday = Holiday::find($id);

            if (! $holiday) {
                return response()->json(['success' => false, 'message' => 'Holiday not found.'], 404);
            }

            $holiday->status = $request->status;
            $holiday->save();

            return response()->json([
                'success' => true,
                'status' => $holiday->status,
                'message' => 'Status updated successfully.',
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Something went wrong. Please try again later.',
            ], 500);
        }
    }
}
