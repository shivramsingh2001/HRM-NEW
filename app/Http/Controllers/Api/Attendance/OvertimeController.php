<?php

namespace App\Http\Controllers\Api\Attendance;

use App\Http\Controllers\Controller;
use App\Models\OvertimeRequest;
use App\Models\OvertimeSetting;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Services\OvertimeNotificationService;
use Illuminate\Support\Facades\Log;

class OvertimeController extends Controller
{
    protected $notificationService;

    public function __construct(OvertimeNotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }
    public function index(Request $request)
    {
        try {
            $tenantId = auth()->user()->tenant_id ?? null;
            $userId = auth()->user()->id;

            // Build query
            $query = OvertimeRequest::where('tenant_id', $tenantId)
                ->where('user_id', $userId);

            // Apply filters
            if ($request->has('status') && $request->status) {
                $query->where('status', $request->status);
            }

            if ($request->has('from_date') && $request->from_date) {
                $query->whereDate('date', '>=', $request->from_date);
            }

            if ($request->has('to_date') && $request->to_date) {
                $query->whereDate('date', '<=', $request->to_date);
            }

            // Get paginated results
            $requests = $query->orderBy('date', 'desc')->paginate(15);

            // Calculate statistics
            $totalRequests = OvertimeRequest::where('tenant_id', $tenantId)
                ->where('user_id', $userId)
                ->count();

            $totalHours = OvertimeRequest::where('tenant_id', $tenantId)
                ->where('user_id', $userId)
                ->sum('overtime_hours');

            $pendingRequests = OvertimeRequest::where('tenant_id', $tenantId)
                ->where('user_id', $userId)
                ->where('status', 'pending')
                ->count();

            $pendingHours = OvertimeRequest::where('tenant_id', $tenantId)
                ->where('user_id', $userId)
                ->where('status', 'pending')
                ->sum('overtime_hours');

            $approvedRequests = OvertimeRequest::where('tenant_id', $tenantId)
                ->where('user_id', $userId)
                ->where('status', 'approved')
                ->count();

            $approvedHours = OvertimeRequest::where('tenant_id', $tenantId)
                ->where('user_id', $userId)
                ->where('status', 'approved')
                ->sum(DB::raw('COALESCE(approved_hours, overtime_hours)'));

            $rejectedRequests = OvertimeRequest::where('tenant_id', $tenantId)
                ->where('user_id', $userId)
                ->where('status', 'rejected')
                ->count();

            $rejectedHours = OvertimeRequest::where('tenant_id', $tenantId)
                ->where('user_id', $userId)
                ->where('status', 'rejected')
                ->sum('overtime_hours');

            // Transform the paginated data
            $transformedRequests = $requests->through(function ($request) {
                return [
                    'id' => $request->id,
                    'date' => $request->date,
                    'date_formatted' => Carbon::parse($request->date)->format('d M Y'),
                    'overtime_hours' => number_format($request->overtime_hours, 1),
                    'approved_hours' => $request->approved_hours ? number_format($request->approved_hours, 1) : null,
                    'reason' => $request->reason,
                    'status' => $request->status,
                    'status_label' => ucfirst($request->status),
                    'rejection_reason' => $request->rejection_reason,
                    'created_at' => $request->created_at,
                    'created_at_formatted' => Carbon::parse($request->created_at)->format('d M Y h:i A'),
                    // 'updated_at' => $request->updated_at,
                ];
            });

            // Prepare response data
            $responseData = [
                'success' => true,
                'message' => 'Overtime requests retrieved successfully',
                'data' => [
                    'requests' => $transformedRequests,
                    'pagination' => [
                        'current_page' => $requests->currentPage(),
                        'per_page' => $requests->perPage(),
                        'total' => $requests->total(),
                        'last_page' => $requests->lastPage(),
                        'next_page_url' => $requests->nextPageUrl(),
                        'prev_page_url' => $requests->previousPageUrl(),
                    ],
                    // 'statistics' => [
                    //     'total' => [
                    //         'requests' => $totalRequests,
                    //         'hours' => number_format($totalHours, 1),
                    //     ],
                    //     'pending' => [
                    //         'requests' => $pendingRequests,
                    //         'hours' => number_format($pendingHours, 1),
                    //     ],
                    //     'approved' => [
                    //         'requests' => $approvedRequests,
                    //         'hours' => number_format($approvedHours, 1),
                    //     ],
                    //     'rejected' => [
                    //         'requests' => $rejectedRequests,
                    //         'hours' => number_format($rejectedHours, 1),
                    //     ],
                    // ],
                ],
            ];

            return response()->json($responseData, 200);
        } catch (Exception $e) {

            return response()->json([
                'success' => false,
                'message' => 'An error occured.Please try again later.'
            ], 500);
        }
    }


    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'date' => 'required|date',
            'overtime_hours' => 'required|numeric|min:0.5|max:24',
            'reason' => 'required|string|min:3|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ], 200);
        }

        $tenantId = auth()->user()->tenant_id ?? null;
        $userId = auth()->user()->id;
        try {
            // Check if request already exists for this date
            $existingRequest = OvertimeRequest::where('user_id', $userId)
                ->where('date', $request->date)
                ->first();

            if ($existingRequest) {
                return response()->json([
                    'success' => false,
                    'message' => 'You have already submitted an overtime request for this date'
                ], 200);
            }

            // Get settings
            $settings = OvertimeSetting::where('tenant_id', $tenantId)
                ->orWhereNull('tenant_id')
                ->first();

            // Validate against max hours per day
            if ($settings && $settings->max_hours_per_day) {
                if ($request->overtime_hours > $settings->max_hours_per_day) {
                    return response()->json([
                        'success' => false,
                        'message' => "Overtime hours cannot exceed {$settings->max_hours_per_day} hours per day"
                    ], 200);
                }
            }

            // Determine status
            $status = 'pending';
            if ($settings && !$settings->require_approval) {
                $status = 'approved';
            } elseif ($settings && $settings->auto_approve_limit && $request->overtime_hours <= $settings->auto_approve_limit) {
                $status = 'approved';
            }

            // Create request
            $overtimeRequest = OvertimeRequest::create([
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'date' => $request->date,
                'overtime_hours' => $request->overtime_hours,
                'reason' => $request->reason,
                'status' => $status,
                'approved_at' => $status == 'approved' ? now() : null,
            ]);
            try {
                $this->notificationService->notifyOvertimeSubmitted($overtimeRequest);
            } catch (Exception $e) {
                Log::error('Failed to send leave notifications: ' . $e->getMessage());
            }


            $message = $status == 'approved'
                ? 'Overtime request auto-approved successfully'
                : 'Overtime request submitted successfully';

            return response()->json([
                'success' => true,
                'message' => $message,
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => true,
                'message' => "An error occured.Please try again later.",
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'date' => 'required|date',
            'overtime_hours' => 'required|numeric|min:0.5|max:24',
            'reason' => 'required|string|min:3|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ], 200);
        }

        $tenantId = auth()->user()->tenant_id ?? null;
        $userId = auth()->user()->id;
        try {
            // Find the request
            $overtimeRequest = OvertimeRequest::where('id', $id)
                ->where('user_id', $userId)
                ->where('tenant_id', $tenantId)
                ->first();
            $oldHours = $overtimeRequest->overtime_hours;

            if (!$overtimeRequest) {
                return response()->json([
                    'success' => false,
                    'message' => 'Overtime request not found'
                ], 200);
            }

            // Only allow update if status is pending
            if ($overtimeRequest->status != 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only pending requests can be updated'
                ], 200);
            }

            // Check date uniqueness (excluding current request)
            $existingRequest = OvertimeRequest::where('user_id', $userId)
                ->where('date', $request->date)
                ->where('id', '!=', $id)
                ->first();

            if ($existingRequest) {
                return response()->json([
                    'success' => false,
                    'message' => 'You already have an overtime request for this date'
                ], 200);
            }

            // Get settings for validation
            $settings = OvertimeSetting::where('tenant_id', $tenantId)
                ->orWhereNull('tenant_id')
                ->first();


            if ($settings && $settings->max_hours_per_day) {
                if ($request->overtime_hours > $settings->max_hours_per_day) {
                    return response()->json([
                        'success' => false,
                        'message' => "Overtime hours cannot exceed {$settings->max_hours_per_day} hours per day"
                    ], 200);
                }
            }

            // Update the request
            $overtimeRequest->update([
                'date' => $request->date,
                'overtime_hours' => $request->overtime_hours,
                'reason' => $request->reason,
            ]);

            try {
                if ($oldHours != $overtimeRequest->overtime_hours) {
                    $this->notificationService->notifyOvertimeSubmitted($overtimeRequest);
                }
            } catch (Exception $e) {
                Log::error('Failed to send leave notifications: ' . $e->getMessage());
            }

            return response()->json([
                'success' => true,
                'message' => 'Overtime request updated successfully',
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => true,
                'message' => "An error occured.Please try again later.",
            ], 500);
        }
    }

    public function destroy($id)
    {
        $tenantId = auth()->user()->tenant_id ?? null;
        $userId = auth()->user()->id;
        try {
            $overtimeRequest = OvertimeRequest::where('id', $id)
                ->where('user_id', $userId)
                ->where('tenant_id', $tenantId)
                ->first();

            if (!$overtimeRequest) {
                return response()->json([
                    'success' => false,
                    'message' => 'Overtime request not found'
                ], 200);
            }

            // Only allow deletion if status is pending
            if ($overtimeRequest->status != 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only pending requests can be cancelled'
                ], 200);
            }
            try {
                $this->notificationService->notifyOvertimeCancelled($overtimeRequest);
            } catch (Exception $e) {
                Log::error('Failed to send leave notifications: ' . $e->getMessage());
            }
            // Delete the request
            $overtimeRequest->delete();

            return response()->json([
                'success' => true,
                'message' => 'Overtime request cancelled successfully'
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => true,
                'message' => "An error occured.Please try again later.",
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $authUser = Auth::user();
            $tenantId = session('tenant_id') ?? $authUser->tenant_id;

            $request = OvertimeRequest::with(['user', 'approver'])
                ->join('users', 'overtime_requests.user_id', '=', 'users.id')
                ->leftJoin('user_job_details', 'overtime_requests.user_id', '=', 'user_job_details.user_id')
                ->select(
                    'overtime_requests.*',
                    'users.name as user_name',
                    'users.email as user_email',
                    'users.employee_id',
                    'user_job_details.department',
                    'user_job_details.designation'
                )
                ->where('overtime_requests.id', $id)
                ->where('overtime_requests.tenant_id', $tenantId)
                ->first();

            if (!$request) {
                return response()->json([
                    'success' => false,
                    'message' => 'Request not found'
                ], 200);
            }

            // Check authorization
            if (!in_array($authUser->role, ['admin', 'manager']) && $request->user_id != $authUser->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access to this request'
                ], 200);
            }

            // Add approver name if exists
            if ($request->approver_id) {
                $approver = User::find($request->approver_id);
                $request->approver_name = $approver ? $approver->name : null;
            }

            // Format the response data
            $formattedData = [
                'id' => $request->id,
                'user_id' => $request->user_id,
                'user_name' => $request->user_name,
                'user_email' => $request->user_email,
                'employee_id' => $request->employee_id,
                'department' => $request->department,
                'designation' => $request->designation,
                'date' => $request->date,
                'date_formatted' => Carbon::parse($request->date)->format('d M Y'),
                'overtime_hours' => number_format($request->overtime_hours, 1),
                'approved_hours' => $request->approved_hours ? number_format($request->approved_hours, 1) : null,
                'reason' => $request->reason,
                'status' => $request->status,
                'status_label' => ucfirst($request->status),
                'rejection_reason' => $request->rejection_reason,
                'approver_name' => $request->approver_name ?? null,
                'created_at' => $request->created_at,
                'created_at_formatted' => Carbon::parse($request->created_at)->format('d M Y h:i A'),
                'approved_at' => $request->approved_at,
                'approved_at_formatted' => $request->approved_at ? Carbon::parse($request->approved_at)->format('d M Y h:i A') : null,
            ];

            return response()->json([
                'success' => true,
                'message' => 'Overtime request retrieved successfully',
                'data' => $formattedData
            ], 200);
        } catch (Exception $e) {

            return response()->json([
                'success' => false,
                'message' =>  'An error occured.Please try again later.'
            ], 500);
        }
    }

    public function pendingApprovals(Request $request)
    {
        try {
            // Check if user is admin or manager
            if (!in_array(auth()->user()->role, ['admin', 'manager'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access. Only admin and managers can view pending approvals.'
                ], 403);
            }

            $tenantId = auth()->user()->tenant_id ?? null;

            $query = OvertimeRequest::with(['user', 'approver'])
                ->where('tenant_id', $tenantId)
                ->where('status', 'pending');

            // Apply filters
            if ($request->has('from_date') && $request->from_date) {
                $query->whereDate('date', '>=', $request->from_date);
            }

            if ($request->has('to_date') && $request->to_date) {
                $query->whereDate('date', '<=', $request->to_date);
            }

            if ($request->has('user_id') && $request->user_id) {
                $query->where('user_id', $request->user_id);
            }

            $pendingRequests = $query->orderBy('date', 'asc')->paginate($request->get('per_page', 20));

            // Transform the data
            $transformedRequests = $pendingRequests->through(function ($request) {
                return [
                    'id' => $request->id,
                    'user' => [
                        'id' => $request->user->id,
                        'name' => $request->user->name,
                        'email' => $request->user->email,
                        'employee_id' => $request->user->employee_id,
                    ],
                    'date' => $request->date,
                    'date_formatted' => Carbon::parse($request->date)->format('d M Y'),
                    'overtime_hours' => number_format($request->overtime_hours, 1),
                    'reason' => $request->reason,
                    'status' => $request->status,
                    'created_at' => $request->created_at,
                    'created_at_formatted' => Carbon::parse($request->created_at)->format('d M Y h:i A'),
                ];
            });

            // Calculate summary statistics
            $summary = [
                'total_pending' => OvertimeRequest::where('tenant_id', $tenantId)
                    ->where('status', 'pending')
                    ->count(),
                'total_hours_pending' => number_format(
                    OvertimeRequest::where('tenant_id', $tenantId)
                        ->where('status', 'pending')
                        ->sum('overtime_hours'),
                    1
                ),
                'total_employees' => OvertimeRequest::where('tenant_id', $tenantId)
                    ->where('status', 'pending')
                    ->distinct('user_id')
                    ->count('user_id'),
            ];

            return response()->json([
                'success' => true,
                'message' => 'Pending approvals retrieved successfully',
                'data' => [
                    'requests' => $transformedRequests,
                    // 'summary' => $summary,
                    'pagination' => [
                        'current_page' => $pendingRequests->currentPage(),
                        'per_page' => $pendingRequests->perPage(),
                        'total' => $pendingRequests->total(),
                        'last_page' => $pendingRequests->lastPage(),
                        'next_page_url' => $pendingRequests->nextPageUrl(),
                        'prev_page_url' => $pendingRequests->previousPageUrl(),
                    ]
                ]
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve pending approvals: ' . $e->getMessage()
            ], 500);
        }
    }

    public function approve(Request $request, $id)
    {
        try {
            // Check if user is admin or manager
            if (!in_array(auth()->user()->role, ['admin', 'manager'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to approve requests'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'approved_hours' => 'nullable|numeric|min:0|max:24',
                'comments' => 'nullable|string|max:500',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' =>  $validator->errors()->first(),
                ], 200);
            }

            $tenantId = auth()->user()->tenant_id ?? null;

            $overtimeRequest = OvertimeRequest::where('id', $id)
                ->where('tenant_id', $tenantId)
                ->first();

            if (!$overtimeRequest) {
                return response()->json([
                    'success' => false,
                    'message' => 'Request not found'
                ], 200);
            }

            if ($overtimeRequest->status != 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => "Request is already {$overtimeRequest->status}"
                ], 200);
            }

            $approvedHours = $request->approved_hours ?? $overtimeRequest->overtime_hours;

            // Validate against settings
            $settings = OvertimeSetting::where('tenant_id', $tenantId)
                ->orWhereNull('tenant_id')
                ->first();

            if ($settings && $settings->max_hours_per_day && $approvedHours > $settings->max_hours_per_day) {
                return response()->json(200);
            }

            $overtimeRequest->update([
                'status' => 'approved',
                'approved_by' => auth()->user()->id,
                'approved_hours' => $approvedHours,
                'approved_at' => now(),
                'rejection_reason' => null,
            ]);

            // Load the approver relationship
            $overtimeRequest->load('approver');
            try {
                $this->notificationService->notifyOvertimeApproved($overtimeRequest, $request->comments);
            } catch (Exception $e) {
                Log::error('Failed to send leave notifications: ' . $e->getMessage());
            }
            return response()->json([
                'success' => true,
                'message' => 'Overtime request approved successfully',
                // 'data' => [
                //     'id' => $overtimeRequest->id,
                //     'status' => $overtimeRequest->status,
                //     'approved_hours' => number_format($overtimeRequest->approved_hours, 1),
                //     'approved_by' => $overtimeRequest->approver->name ?? null,
                //     'approved_at' => $overtimeRequest->approved_at,
                //     'approved_at_formatted' => Carbon::parse($overtimeRequest->approved_at)->format('d M Y h:i A'),
                // ]
            ], 200);
        } catch (Exception $e) {

            return response()->json([
                'success' => false,
                'message' =>  'An error occured.Please try again later.'
            ], 500);
        }
    }
    public function reject(Request $request, $id)
    {
        try {
            // Check if user is admin or manager
            if (!in_array(auth()->user()->role, ['admin', 'manager'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to reject requests'
                ], 200);
            }

            $validator = Validator::make($request->all(), [
                'rejection_reason' => 'required|string|min:3|max:500',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => $validator->errors()->first(),
                ], 200);
            }

            $tenantId = auth()->user()->tenant_id ?? null;

            $overtimeRequest = OvertimeRequest::where('id', $id)
                ->where('tenant_id', $tenantId)
                ->first();

            if (!$overtimeRequest) {
                return response()->json([
                    'success' => false,
                    'message' => 'Request not found'
                ], 200);
            }

            if ($overtimeRequest->status != 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => "Request is already {$overtimeRequest->status}"
                ], 200);
            }

            $overtimeRequest->update([
                'status' => 'rejected',
                'approved_by' => auth()->user()->id,
                'rejection_reason' => $request->rejection_reason,
                'approved_at' => now(),
            ]);

            // Load the approver relationship
            $overtimeRequest->load('approver');
            try {
                $this->notificationService->notifyOvertimeRejected($overtimeRequest, $request->rejection_reason);
            } catch (Exception $e) {
                Log::error('Failed to send leave notifications: ' . $e->getMessage());
            }
            return response()->json([
                'success' => true,
                'message' => 'Overtime request rejected',
                // 'data' => [
                //     'id' => $overtimeRequest->id,
                //     'status' => $overtimeRequest->status,
                //     'rejection_reason' => $overtimeRequest->rejection_reason,
                //     'rejected_by' => $overtimeRequest->approver->name ?? null,
                //     'rejected_at' => $overtimeRequest->approved_at,
                //     'rejected_at_formatted' => Carbon::parse($overtimeRequest->approved_at)->format('d M Y h:i A'),
                // ]
            ], 200);
        } catch (Exception $e) {

            return response()->json([
                'success' => false,
                'message' =>  'An error occured.Please try again later.'
            ], 500);
        }
    }
}
