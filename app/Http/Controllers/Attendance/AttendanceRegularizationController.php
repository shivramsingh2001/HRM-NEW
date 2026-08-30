<?php

namespace App\Http\Controllers\Attendance;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRegularization;
use App\Models\Attendance;
use App\Models\User;
use App\Models\UserJobDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\AttendanceRegularizationNotificationService;
use Carbon\Carbon;
use Exception;

class AttendanceRegularizationController extends Controller
{
    
     /**
     * @var AttendanceRegularizationNotificationService
     */
    protected $notificationService;

    public function __construct(AttendanceRegularizationNotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }
   
    public function index(Request $request)
    {
        $tenant_id = Auth::user()->tenant_id;
        $user_id = Auth::id();

        // Base query
        $query = AttendanceRegularization::with(['approver'])
            ->where('user_id', $user_id)
            ->where('tenant_id', $tenant_id);

        // Apply filters
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('request_type')) {
            $query->where('request_type', $request->request_type);
        }

        if ($request->filled('from_date')) {
            $query->whereDate('date', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->whereDate('date', '<=', $request->to_date);
        }

        // Get regularizations with pagination
        $regularizations = $query->orderBy('created_at', 'desc')->paginate(15);

        // Calculate statistics
        $totalRegularizations = AttendanceRegularization::where('user_id', $user_id)
            ->where('tenant_id', $tenant_id)
            ->count();

        $pendingRegularizations = AttendanceRegularization::where('user_id', $user_id)
            ->where('tenant_id', $tenant_id)
            ->where('status', 'pending')
            ->count();

        $approvedRegularizations = AttendanceRegularization::where('user_id', $user_id)
            ->where('tenant_id', $tenant_id)
            ->where('status', 'approved')
            ->count();

        $rejectedRegularizations = AttendanceRegularization::where('user_id', $user_id)
            ->where('tenant_id', $tenant_id)
            ->where('status', 'rejected')
            ->count();

        return view('client.attendance.regularization', compact(
            'regularizations',
            'totalRegularizations',
            'pendingRegularizations',
            'approvedRegularizations',
            'rejectedRegularizations'
        ));
    }

    /**
     * Store a newly created attendance regularization.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'request_type' => 'required|in:missed_punch_in,missed_punch_out,wrong_punch_time,attendance',
            'date' => 'required|date|before_or_equal:today',
            'in_time' => 'required_if:request_type,missed_punch_in,wrong_punch_time|nullable|date_format:H:i',
            'out_time' => 'required_if:request_type,missed_punch_out,wrong_punch_time|nullable|date_format:H:i',
            'reason' => 'required|string|min:10|max:1000',
            'file' => 'nullable|file|mimes:jpg,jpeg,png,pdf,doc,docx|max:2048'
        ], [
            'in_time.required_if' => 'In time is required for this request type',
            'out_time.required_if' => 'Out time is required for this request type',
            'reason.min' => 'Reason must be at least 10 characters',
            'file.max' => 'File size must not exceed 2MB',
            'file.mimes' => 'File must be of type: jpg, jpeg, png, pdf, doc, docx'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Validate time logic based on request type
        if ($request->request_type == 'both') {
            $inTime = strtotime($request->in_time);
            $outTime = strtotime($request->out_time);
            
            if ($outTime <= $inTime) {
                return response()->json([
                    'success' => false,
                    'errors' => [
                        'out_time' => ['Out time must be after in time']
                    ]
                ], 422);
            }
        }
        
         DB::beginTransaction();
        try {
            $tenant_id = Auth::user()->tenant_id;
            $user_id = Auth::id();

            // Check if already exists for this date and user
            $existing = AttendanceRegularization::where('user_id', $user_id)
                ->where('tenant_id', $tenant_id)
                ->where('date', $request->date)
                ->whereIn('status', ['pending', 'approved'])
                ->first();

            if ($existing) {
                return response()->json([
                    'success' => false,
                    'message' => 'You already have a ' . $existing->status . ' request for this date'
                ], 422);
            }

            // Handle file upload
            $filePath = null;
             if ($request->hasFile('file')) {
                $file = $request->file('file');
                $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                $path = public_path('uploads/regularizations');

                $file->move($path, $filename);
                $filePath = 'uploads/regularizations/' . $filename;
            }

            // Create regularization request
            $regularization = AttendanceRegularization::create([
                'tenant_id' => $tenant_id,
                'user_id' => $user_id,
                'date' => $request->date,
                'request_type' => $request->request_type,
                'in_time' => $request->in_time,
                'out_time' => $request->out_time,
                'reason' => $request->reason,
                'file' => $filePath,
                'status' => 'pending'
            ]);
            DB::commit();
             try {
                $this->notificationService->notifyRegularizationSubmitted($regularization);
            } catch (Exception $e) {
                Log::error('Failed to send attendance Regularization notifications: ' . $e->getMessage());
            }
           
            return response()->json([
                'success' => true,
                'message' => 'Regularization request submitted successfully',
                'data' => $regularization
            ]);

        } catch (Exception $e) {
             DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to submit request: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified attendance regularization.
     */
    public function update(Request $request, $id)
    {
        $tenant_id = Auth::user()->tenant_id;
        $user_id = Auth::id();

        $regularization = AttendanceRegularization::where('id', $id)
            ->where('user_id', $user_id)
            ->where('tenant_id', $tenant_id)
            ->where('status', 'pending')
            ->first();

        if (!$regularization) {
            return response()->json([
                'success' => false,
                'message' => 'Regularization request not found or cannot be edited'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'request_type' => 'required|in:missed_punch_in,missed_punch_out,wrong_punch_time,attendance',
            'date' => 'required|date|before_or_equal:today',
            'in_time' => 'required_if:request_type,missed_punch_in,wrong_punch_time|nullable|date_format:H:i',
            'out_time' => 'required_if:request_type,missed_punch_out,wrong_punch_time|nullable|date_format:H:i',
            'reason' => 'required|string|min:10|max:1000',
            'file' => 'nullable|file|mimes:jpg,jpeg,png,pdf,doc,docx|max:2048'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Validate time logic based on request type
        if ($request->request_type == 'both') {
            $inTime = strtotime($request->in_time);
            $outTime = strtotime($request->out_time);
            
            if ($outTime <= $inTime) {
                return response()->json([
                    'success' => false,
                    'errors' => [
                        'out_time' => ['Out time must be after in time']
                    ]
                ], 422);
            }
        }
        
          DB::beginTransaction();

        try {
            // Check for duplicate (excluding current)
            $existing = AttendanceRegularization::where('user_id', $user_id)
                ->where('tenant_id', $tenant_id)
                ->where('date', $request->date)
                ->where('id', '!=', $id)
                ->whereIn('status', ['pending', 'approved'])
                ->first();

            if ($existing) {
                return response()->json([
                    'success' => false,
                    'message' => 'You already have a ' . $existing->status . ' request for this date'
                ], 422);
            }

            // Handle file upload
            if ($request->hasFile('file')) {
                // Delete old file
                if ($regularization->file) {
                    Storage::disk('public')->delete($regularization->file);
                }
                $file = $request->file('file');
                $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                $path = public_path('uploads/regularizations');
                $file->move($path, $filename);
                $filePath = 'uploads/regularizations/' . $filename;
            }

            // Update regularization
            $regularization->update([
                'date' => $request->date,
                'request_type' => $request->request_type,
                'in_time' => $request->in_time,
                'out_time' => $request->out_time,
                'reason' => $request->reason,
            ]);
            
            DB::commit();
            //  try {
            //     $this->notificationService->notifyRegularizationSubmitted($regularization);
            // } catch (Exception $e) {
            //     Log::error('Failed to send attendance Regularization notifications: ' . $e->getMessage());
            // }
            return response()->json([
                'success' => true,
                'message' => 'Regularization request updated successfully',
                'data' => $regularization
            ]);

        } catch (\Exception $e) {
             DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update request: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified attendance regularization.
     */
    public function destroy($id)
    {
        $tenant_id = Auth::user()->tenant_id;
        $user_id = Auth::id();

        $regularization = AttendanceRegularization::where('id', $id)
            ->where('user_id', $user_id)
            ->where('tenant_id', $tenant_id)
            ->where('status', 'pending')
            ->first();

        if (!$regularization) {
            return response()->json([
                'success' => false,
                'message' => 'Regularization request not found or cannot be deleted'
            ], 404);
        }

        try {
            // Delete associated file
            if ($regularization->file) {
                Storage::disk('public')->delete($regularization->file);
            }

            $regularization->delete();

            return response()->json([
                'success' => true,
                'message' => 'Regularization request deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete request: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get regularization details for a specific ID (for AJAX)
     */
    public function show($id)
    {
        $tenant_id = Auth::user()->tenant_id;
        $user_id = Auth::id();

        $regularization = AttendanceRegularization::with(['approver'])
            ->where('id', $id)
            ->where('user_id', $user_id)
            ->where('tenant_id', $tenant_id)
            ->first();

        if (!$regularization) {
            return response()->json([
                'success' => false,
                'message' => 'Regularization request not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $regularization
        ]);
    }

    /**
     * Cancel a pending regularization request
     */
    public function cancel($id)
    {
        $tenant_id = Auth::user()->tenant_id;
        $user_id = Auth::id();

        $regularization = AttendanceRegularization::where('id', $id)
            ->where('user_id', $user_id)
            ->where('tenant_id', $tenant_id)
            ->where('status', 'pending')
            ->first();

        if (!$regularization) {
            return response()->json([
                'success' => false,
                'message' => 'Regularization request not found or cannot be cancelled'
            ], 404);
        }

        try {
            $regularization->update([
                'status' => 'cancelled'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Regularization request cancelled successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel request: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get regularization statistics for the current user
     */
    public function getStatistics()
    {
        $tenant_id = Auth::user()->tenant_id;
        $user_id = Auth::id();

        $stats = [
            'total' => AttendanceRegularization::where('user_id', $user_id)
                ->where('tenant_id', $tenant_id)
                ->count(),
            
            'pending' => AttendanceRegularization::where('user_id', $user_id)
                ->where('tenant_id', $tenant_id)
                ->where('status', 'pending')
                ->count(),
            
            'approved' => AttendanceRegularization::where('user_id', $user_id)
                ->where('tenant_id', $tenant_id)
                ->where('status', 'approved')
                ->count(),
            
            'rejected' => AttendanceRegularization::where('user_id', $user_id)
                ->where('tenant_id', $tenant_id)
                ->where('status', 'rejected')
                ->count(),
            
            'this_month' => AttendanceRegularization::where('user_id', $user_id)
                ->where('tenant_id', $tenant_id)
                ->whereMonth('date', now()->month)
                ->whereYear('date', now()->year)
                ->count()
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    /**
     * Check if user has any pending/approved request for a specific date
     */
    public function checkAvailability(Request $request)
    {
        $request->validate([
            'date' => 'required|date'
        ]);

        $tenant_id = Auth::user()->tenant_id;
        $user_id = Auth::id();

        $existing = AttendanceRegularization::where('user_id', $user_id)
            ->where('tenant_id', $tenant_id)
            ->where('date', $request->date)
            ->whereIn('status', ['pending', 'approved'])
            ->first();

        return response()->json([
            'success' => true,
            'available' => !$existing,
            'existing_request' => $existing ? [
                'id' => $existing->id,
                'status' => $existing->status,
                'request_type' => $existing->request_type
            ] : null
        ]);
    }

    /**
     * Download attached file
     */
    public function downloadFile($id)
    {
        $tenant_id = Auth::user()->tenant_id;
        $user_id = Auth::id();

        $regularization = AttendanceRegularization::where('id', $id)
            ->where('user_id', $user_id)
            ->where('tenant_id', $tenant_id)
            ->first();

        if (!$regularization || !$regularization->file) {
            abort(404, 'File not found');
        }

        $filePath = storage_path('app/public/' . $regularization->file);

        if (!file_exists($filePath)) {
            abort(404, 'File not found');
        }

        return response()->download($filePath);
    }
    
    /**
     * Display regularization requests for managers/admins
     */
    public function manage(Request $request)
    {
        $authUser = Auth::user();
        
        // Check if user has permission
        if (!in_array($authUser->role, ['manager', 'admin', 'hr'])) {
            abort(403, 'Unauthorized access');
        }
        
        // Build query using Eloquent
        $query = AttendanceRegularization::with(['user', 'approver'])
            ->where('tenant_id', $authUser->tenant_id);
        
        // If manager, only show requests where user's reporting head is current user
        if ($authUser->role == 'manager') {
            $query->whereHas('user.jobDetails', function($q) use ($authUser) {
                $q->where('reporting_head', $authUser->id);
            });
        }
        
        // Apply filters
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        
        if ($request->filled('request_type')) {
            $query->where('request_type', $request->request_type);
        }
        
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }
        
        if ($request->filled('employee')) {
            $query->whereHas('user', function($q) use ($request) {
                $q->where('name', 'LIKE', '%' . $request->employee . '%');
            });
        }
        
        if ($request->filled('from_date')) {
            $query->whereDate('date', '>=', $request->from_date);
        }
        
        if ($request->filled('to_date')) {
            $query->whereDate('date', '<=', $request->to_date);
        }
        
        $regularizations = $query->orderBy('created_at', 'desc')->paginate(15);
        
        // Calculate statistics using Eloquent
        $statsQuery = AttendanceRegularization::where('tenant_id', $authUser->tenant_id);
        
        if ($authUser->role == 'manager') {
            $statsQuery->whereHas('user.jobDetails', function($q) use ($authUser) {
                $q->where('reporting_head', $authUser->id);
            });
        }
        
        $totalRequests = (clone $statsQuery)->count();
        $pendingRequests = (clone $statsQuery)->where('status', 'pending')->count();
        $approvedRequests = (clone $statsQuery)->where('status', 'approved')->count();
        $rejectedRequests = (clone $statsQuery)->where('status', 'rejected')->count();
        
        // Get all employees for filter dropdown
        $employees = User::where('tenant_id', $authUser->tenant_id)
            ->orderBy('name')
            ->get();
     
        return view('client.attendance.manage', compact(
            'regularizations',
            'totalRequests',
            'pendingRequests',
            'approvedRequests',
            'rejectedRequests',
            'employees'
        ));
    }
    
    /**
     * Process regularization approval/rejection using Eloquent
     */
    public function regularizationApproval(Request $request)
    {
        try {
            $authUser = Auth::user();

            // Check authorization
            if (!in_array($authUser->role, ['manager', 'admin', 'hr'], true)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access. Only managers, admins, and HR can process requests.'
                ], 403);
            }

            // Validate request
            $validator = Validator::make($request->all(), [
                'id' => 'required|exists:attendance_regularizations,id',
                'status' => 'required|string|in:approved,rejected',
                'remarks' => 'nullable|string|max:500'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => $validator->errors()->first()
                ], 422);
            }

            // Get regularization with relationships using Eloquent (tenant-scoped
            // via the model's global scope on the web guard).
            $regularization = AttendanceRegularization::with(['user', 'user.jobDetails'])
                ->where('id', $request->id)
                ->first();

            if (!$regularization) {
                return response()->json([
                    'success' => false,
                    'message' => 'Regularization request not found.'
                ], 404);
            }

            // Check if request is already processed
            if ($regularization->status != 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'This request has already been ' . $regularization->status . '.'
                ], 400);
            }

            // For managers, verify they are the reporting head
            if ($authUser->role == 'manager') {
                $reportingHead = $regularization->user->jobDetails->reporting_head ?? null;

                if (!$reportingHead) {
                    return response()->json([
                        'success' => false,
                        'message' => 'User does not have a reporting head assigned. Please contact admin.'
                    ], 400);
                }

                if ($reportingHead != $authUser->id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'You are not authorized to process this request. Only the reporting head can process it.'
                    ], 403);
                }
            }

            // Approval + attendance creation must be atomic: if
            // createAttendanceFromRegularization() throws (e.g. future date),
            // the status change is rolled back too.
            DB::beginTransaction();
            try {
                $regularization->status = $request->status;
                $regularization->approved_by = $authUser->id;
                $regularization->approved_date = now();
                $regularization->approval_remarks = $request->remarks;
                $regularization->save();

                if ($request->status == 'approved') {
                    $this->createAttendanceFromRegularization($regularization);
                }

                DB::commit();
            } catch (\Throwable $e) {
                DB::rollBack();
                throw $e;
            }

            // Refresh the policy-resolved status + monthly summary for the day.
            if ($request->status == 'approved') {
                try {
                    $ym = \Carbon\Carbon::parse($regularization->date)->format('Y-m');
                    app(\App\Services\Attendance\LatePolicyService::class)
                        ->recalculateMonth((int) $regularization->user_id, (int) $regularization->tenant_id, $ym);
                    app(\App\Services\AttendanceSummaryService::class)
                        ->updateMonthlySummary((int) $regularization->user_id, $ym, (int) $regularization->tenant_id);
                } catch (\Throwable $e) {
                    Log::error('Post-regularization recompute failed: ' . $e->getMessage());
                }
            }

            // Notifications after commit so a delivery failure cannot undo a valid approval.
            try {
                if ($request->status == 'approved') {
                    $this->notificationService->notifyRegularizationApproved($regularization, $request->remarks);
                } else {
                    $this->notificationService->notifyRegularizationRejected($regularization, $request->remarks);
                }
            } catch (\Throwable $e) {
                Log::error('Regularization notification failed: ' . $e->getMessage());
            }

            $action = $request->status == 'approved' ? 'approved' : 'rejected';

            return response()->json([
                'success' => true,
                'message' => "Regularization request {$action} successfully."
            ]);

        } catch (\Exception $e) {
            Log::error('Regularization approval error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while processing the request. Please try again later.'
            ], 500);
        }
    }

    /**
     * Create or update attendance record from approved regularization
     */
    private function createAttendanceFromRegularization($regularization)
    {
        $regularizationDate = Carbon::parse($regularization->date);

        // Check if date is in future
        if ($regularizationDate->isFuture()) {
            throw new \Exception('Cannot create attendance for future date.');
        }

        // Find or create attendance record (scoped to the regularization's tenant)
        $attendance = Attendance::firstOrNew([
            'tenant_id' => $regularization->tenant_id,
            'user_id' => $regularization->user_id,
            'date' => $regularization->date
        ]);

        $attendance->tenant_id = $regularization->tenant_id;
        $attendance->regularization_id = $regularization->id;
        $attendance->is_regularized = 1;
        $attendance->regularized_by = Auth::id();
        $attendance->regularized_at = now();
        $attendance->status = 1; // Mark as present/processed
        
        // Set clock in time if provided
        if ($regularization->in_time) {
            $attendance->clock_in = Carbon::parse($regularizationDate->format('Y-m-d') . ' ' . $regularization->in_time);
        }

        // Set clock out time if provided
        if ($regularization->out_time) {
            $attendance->clock_out = Carbon::parse($regularizationDate->format('Y-m-d') . ' ' . $regularization->out_time);
        }

        // Calculate total hours if both times are set
        if ($regularization->in_time && $regularization->out_time) {
            $calc = new \App\Services\Attendance\AttendanceCalculator();
            $seconds = $calc->workedSeconds(
                Carbon::parse($attendance->clock_in),
                Carbon::parse($attendance->clock_out)
            );
            $attendance->total_hours = $calc->formatDuration($seconds);
            $attendance->worked_hours = $calc->decimalHours($seconds);
        }

        $attendance->save();

        return $attendance;
    }

    /**
     * Get regularization details for modal using Eloquent
     */
    public function getDetails($id)
    {
        try {
            $authUser = Auth::user();
            
            $regularization = AttendanceRegularization::with(['user', 'approver'])
                ->where('id', $id)
                ->where('tenant_id', $authUser->tenant_id)
                ->first();

            if (!$regularization) {
                return response()->json([
                    'success' => false,
                    'message' => 'Request not found'
                ], 404);
            }

            // Prepare data for response
            $data = [
                'id' => $regularization->id,
                'user_id' => $regularization->user_id,
                'user_name' => $regularization->user->name ?? null,
                'user_email' => $regularization->user->email ?? null,
                'date' => $regularization->date,
                'formatted_date' => $regularization->date ? date('d M Y', strtotime($regularization->date)) : null,
                'request_type' => $regularization->request_type,
                'in_time' => $regularization->in_time ? date('h:i A', strtotime($regularization->in_time)) : null,
                'out_time' => $regularization->out_time ? date('h:i A', strtotime($regularization->out_time)) : null,
                'reason' => $regularization->reason,
                'file' => $regularization->file,
                'file_url' => $regularization->file ? asset('storage/' . $regularization->file) : null,
                'status' => $regularization->status,
                'approved_by' => $regularization->approved_by,
                'approver_name' => $regularization->approver->name ?? null,
                'approved_date' => $regularization->approved_date ? date('d M Y h:i A', strtotime($regularization->approved_date)) : null,
                'approval_remarks' => $regularization->approval_remarks,
                
                // Badge classes
                'status_badge' => match($regularization->status) {
                    'approved' => 'bg-success',
                    'rejected' => 'bg-danger',
                    'pending' => 'bg-warning',
                    default => 'bg-secondary'
                },
                
                'request_type_badge' => match($regularization->request_type) {
                    'in_time' => 'badge-type-in',
                    'out_time' => 'badge-type-out',
                    'both' => 'badge-type-both',
                    default => 'bg-secondary'
                },
                
                'request_type_text' => match($regularization->request_type) {
                    'in_time' => 'In Time Only',
                    'out_time' => 'Out Time Only',
                    'both' => 'Both In & Out',
                    default => ucfirst($regularization->request_type)
                }
            ];

            return response()->json([
                'success' => true,
                'data' => $data
            ]);

        } catch (\Exception $e) {
            Log::error('Error in getDetails: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }
}