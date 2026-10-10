<?php

namespace App\Http\Controllers\AttendanceLocation;

use App\Http\Controllers\Controller;
use App\Models\AttendanceLocation;
use App\Models\UserJobDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Renamed from BranchController (2026_09_20) — this is purely the
 * attendance-geofencing entity (lat/long/radius), not organizational
 * structure. See Branch\BranchController for the separate Company Branch
 * module.
 */
class AttendanceLocationController extends Controller
{
    public function index()
    {
        $locations = AttendanceLocation::orderBy('created_at', 'desc')->paginate(15);
        return view('client.attendance-location.attendance-location', ['locations' => $locations]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            // Unique within the company only.
            'name' => ['required', 'string', 'max:255',
                \Illuminate\Validation\Rule::unique('attendance_locations', 'name')->where('tenant_id', auth()->user()->tenant_id)],
            'description' => 'nullable|string',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'radius' => 'nullable|integer|min:10|max:5000',
            'geofence_enabled' => 'nullable|boolean',
            'status' => 'required|in:0,1',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            // validated(), not all() — AttendanceLocation is mass-assignable
            // via an explicit $fillable, but this keeps the same safe
            // pattern the original BranchController used.
            $data = $validator->validated();
            $data['geofence_enabled'] = $request->boolean('geofence_enabled', true);
            $location = AttendanceLocation::create($data);

            return response()->json([
                'success' => true,
                'message' => 'Attendance location created successfully',
                'data' => $location,
            ]);
        } catch (\Exception $e) {
            report($e);
            return response()->json([
                'success' => false,
                'message' => 'Failed to create attendance location',
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $location = AttendanceLocation::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255',
                \Illuminate\Validation\Rule::unique('attendance_locations', 'name')->where('tenant_id', $location->tenant_id)->ignore($location->id)],
            'description' => 'nullable|string',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'radius' => 'nullable|integer|min:10|max:5000',
            'geofence_enabled' => 'nullable|boolean',
            'status' => 'required|in:0,1',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $data = $validator->validated();
            $data['geofence_enabled'] = $request->boolean('geofence_enabled', true);
            $location->update($data);

            return response()->json([
                'success' => true,
                'message' => 'Attendance location updated successfully',
            ]);
        } catch (\Exception $e) {
            report($e);
            return response()->json([
                'success' => false,
                'message' => 'Failed to update attendance location',
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $location = AttendanceLocation::findOrFail($id);

            $assignedCount = UserJobDetail::where('office_branch', $id)->count();
            if ($assignedCount > 0) {
                return response()->json([
                    'success' => false,
                    'message' => "Reassign {$assignedCount} employee(s) to another attendance location before deleting.",
                ], 422);
            }

            $location->delete();

            return response()->json([
                'success' => true,
                'message' => 'Attendance location deleted successfully',
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Attendance location not found.',
            ], 404);
        } catch (\Exception $e) {
            report($e);
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete attendance location',
            ], 500);
        }
    }
}
