<?php

namespace App\Http\Controllers\Holiday;

use App\Http\Controllers\Controller;
use App\Models\Holiday;
use Carbon\CarbonPeriod;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class HolidayController extends Controller
{
    public function index(Request $request)
    {
        $data['holidays'] = Holiday::all();
        return view('client.holiday.holiday', $data);
    }
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'description' => 'nullable|string|max:1000',
        ]);

        $user = Auth::id();

        try {

            $period = CarbonPeriod::create($request->start_date, $request->end_date);

            foreach ($period as $date) {

                $exists = Holiday::where('name', $request->name)
                    ->where('start_date', $date->format('Y-m-d'))
                    ->exists();

                if (!$exists) {
                    Holiday::create([
                        'name' => $request->name,
                        'start_date' => $date->format('Y-m-d'),
                        'end_date' => $date->format('Y-m-d'),
                        'description' => $request->description,
                    ]);
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Holiday created successfully!'
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Something went wrong. Please try again later.' . $e->getMessage()
            ], 500);
        }
    }
    public function update(Request $request)
    {
        $id = $request->id;
        $request->validate([
            'name' => 'required|max:255|string|unique:holidays,name,' . $id,
            'start_date' => 'required|date',
            'description' => 'nullable|string|max:1000',
            'status' => 'required|in:0,1'
        ]);
       
        try {
            $exists = Holiday::where('name', $request->name)
                ->where('start_date', $request->start_date)
                ->first();
           
            if ($exists) {
                $holiday = Holiday::findOrFail($id);
                $holiday->name = $request->name;
                $holiday->start_date = $request->start_date;
                $holiday->status = $request->status;
                $holiday->description = $request->description;
                $holiday->save();
            }

            return response()->json(['success' => true, 'message' => 'Holiday Updated Successfully!'], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Holiday not found.'], 404);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => 'Something went wrong. Please try again later.'], 500);
        }
    }
    public function status(Request $request)
    {
        $request->validate([
            'status' => 'required|in:0,1',
            'id' => 'required|exists:holidays,id'
        ]);
        try {
            $holiday = Holiday::find($request->id);
            if (!$holiday) {
                return response()->json(['success' => false, 'message' => 'Holiday not found.'], 404);
            }
            $holiday->status = $request->status;
            $holiday->save();
            return response()->json(['success' => true, 'message' => 'Status Updated Successfully'], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Something went wrong. Please try again later.'
            ], 500);
        }
    }
}
