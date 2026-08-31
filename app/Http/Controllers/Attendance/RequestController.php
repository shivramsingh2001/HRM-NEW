<?php

namespace App\Http\Controllers\Attendance;

use App\Http\Controllers\Controller;
use App\Models\Request;
use App\Models\RequestType;
use App\Models\RequestAttachment;
use App\Models\RequestHistory;
use App\Models\User;
use Illuminate\Http\Request as HttpRequest;
use App\Services\RequestNotificationService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class RequestController extends Controller
{
    use \App\Http\Controllers\Concerns\SanitizesCsv;

     protected $notificationService;

    public function __construct(RequestNotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }
    /**
     * Display a listing of the user's requests.
     */
    public function index(HttpRequest $request)
    {
        try {
            $user = Auth::user();

            // Build query for user's requests only
            $query = Request::with(['requestType', 'reportingHead'])
                ->where('user_id', $user->id);

            // Apply filters
            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            if ($request->filled('request_type')) {
                $query->where('request_type_id', $request->request_type);
            }

            if ($request->filled('from_date')) {
                $query->where('start_date', '>=', $request->from_date);
            }

            if ($request->filled('to_date')) {
                $query->where('end_date', '<=', $request->to_date);
            }

            // Get paginated results
            $requests = $query->orderBy('created_at', 'desc')->paginate(15);

            // Calculate statistics
            $totalRequests = Request::where('user_id', $user->id)->count();
            $totalDays = Request::where('user_id', $user->id)
                ->get()
                ->sum(function ($req) {
                    return $req->start_date->diffInDays($req->end_date) + 1;
                });

            $pendingRequests = Request::where('user_id', $user->id)
                ->where('status', 'PENDING')
                ->count();
            $pendingDays = Request::where('user_id', $user->id)
                ->where('status', 'PENDING')
                ->get()
                ->sum(function ($req) {
                    return $req->start_date->diffInDays($req->end_date) + 1;
                });

            $approvedRequests = Request::where('user_id', $user->id)
                ->where('status', 'APPROVED')
                ->count();
            $approvedDays = Request::where('user_id', $user->id)
                ->where('status', 'APPROVED')
                ->get()
                ->sum(function ($req) {
                    return $req->start_date->diffInDays($req->end_date) + 1;
                });

            $cancelledRequests = Request::where('user_id', $user->id)
                ->whereIn('status', ['REJECTED'])
                ->count();
            $cancelledDays = Request::where('user_id', $user->id)
                ->whereIn('status', ['REJECTED'])
                ->get()
                ->sum(function ($req) {
                    return $req->start_date->diffInDays($req->end_date) + 1;
                });

            // Get request types for filter
            $requestTypes = RequestType::where('is_active', true)->get();

            return view('client.request.request', compact(
                'requests',
                'requestTypes',
                'totalRequests',
                'totalDays',
                'pendingRequests',
                'pendingDays',
                'approvedRequests',
                'approvedDays',
                'cancelledRequests',
                'cancelledDays'
            ));
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to load requests: ' . $e->getMessage());
        }
    }

    /**
     * Store a newly created request.
     */
    public function store(HttpRequest $request)
    {
        try {
            $validated = $request->validate([
                'request_type_id' => 'required|exists:request_types,id',
                'start_date' => 'required|date|after_or_equal:today',
                'end_date' => 'required|date|after_or_equal:start_date',
                'reason' => 'required|string|max:1000',
                 'attachments' => 'nullable|file|max:5120|mimes:jpg,jpeg,png,pdf,doc,docx'
            ]);

            $user = Auth::user();

            DB::beginTransaction();

            // Check for overlapping requests
            $overlapping = Request::where('user_id', $user->id)
                ->whereIn('status', ['PENDING', 'APPROVED'])
                ->where(function ($query) use ($validated) {
                    $query->whereBetween('start_date', [$validated['start_date'], $validated['end_date']])
                        ->orWhereBetween('end_date', [$validated['start_date'], $validated['end_date']])
                        ->orWhere(function ($q) use ($validated) {
                            $q->where('start_date', '<=', $validated['start_date'])
                                ->where('end_date', '>=', $validated['end_date']);
                        });
                })
                ->exists();

            if ($overlapping) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'You already have a pending or approved request for this date range.'
                ], 422);
            }

            // Create request
            $newRequest = Request::create([
                'request_type_id' => $validated['request_type_id'],
                'user_id' => $user->id,
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
                'reason' => $validated['reason'],
                'status' => 'PENDING',
                'applied_date' => now()
            ]);

            // Handle attachments
            if ($request->hasFile('attachments') && $request->file('attachments')->isValid()) {
                try {
                    $file = $request->file('attachments');
                    
                    // Verify file exists and is readable
                    if (!$file->isValid()) {
                        throw new \Exception('Uploaded file is not valid');
                    }
                    
                    $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                    $path = public_path('uploads/request/attachments');
    
                    $file->move($path, $filename);
                    $filePath = 'uploads/requests/attachments/' . $filename;
                    
                    if (!$path) {
                        throw new \Exception('Failed to store file');
                    }
                    
                    RequestAttachment::create([
                        'request_id' => $newRequest->id,
                        'file_name' => $file->getClientOriginalName(),
                        'file_path' => $filePath,
                        'file_type' => $file->getMimeType(),
                        'file_size' => $file->getSize(),
                        'uploaded_by' => $user->id
                    ]);
                    
                } catch (\Exception $fileException) {
                    \Log::error('File upload error: ' . $fileException->getMessage());
                }
            }

            // Create history
            RequestHistory::create([
                'request_id' => $newRequest->id,
                'action_by' => $user->id,
                'action' => 'CREATED',
                'new_values' => json_encode($newRequest->toArray())
            ]);

            DB::commit();
             // Send notifications
            try {
                $this->notificationService->notifyRequestSubmitted($newRequest);
            } catch (\Exception $e) {
                \Log::error('Failed to send request notification: ' . $e->getMessage());
            }

            return response()->json([
                'success' => true,
                'message' => 'Request created successfully.',
                'data' => $newRequest
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create request: '.$e->getMessage()
            ], 500);
        }
    }

    public function update(HttpRequest $request, $id)
    {
        try {
            $existingRequest = Request::findOrFail($id);

            // Check if user owns this request
            if ($existingRequest->user_id != Auth::id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            // Check if request can be edited
            if ($existingRequest->status != 'PENDING') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only pending requests can be updated.'
                ], 400);
            }

            $validated = $request->validate([
                'request_type_id' => 'required|exists:request_types,id',
                'start_date' => 'required|date|after_or_equal:today',
                'end_date' => 'required|date|after_or_equal:start_date',
                'reason' => 'required|string|max:1000',
                'attachments.*' => 'nullable|file|max:5120|mimes:jpg,jpeg,png,pdf,doc,docx'
            ]);

            DB::beginTransaction();

            // Check for overlapping requests (excluding current)
            $overlapping = Request::where('user_id', Auth::id())
                ->where('id', '!=', $id)
                ->whereIn('status', ['PENDING', 'APPROVED'])
                ->where(function ($query) use ($validated) {
                    $query->whereBetween('start_date', [$validated['start_date'], $validated['end_date']])
                        ->orWhereBetween('end_date', [$validated['start_date'], $validated['end_date']])
                        ->orWhere(function ($q) use ($validated) {
                            $q->where('start_date', '<=', $validated['start_date'])
                                ->where('end_date', '>=', $validated['end_date']);
                        });
                })
                ->exists();

            if ($overlapping) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'You already have another pending or approved request for this date range.'
                ], 422);
            }

            $oldValues = $existingRequest->toArray();

            $existingRequest->update([
                'request_type_id' => $validated['request_type_id'],
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
                'reason' => $validated['reason']
            ]);

            // Handle new attachments
            if ($request->hasFile('attachments') && $request->file('attachments')->isValid()) {
                 $file = $request->file('attachments');
                    
                // Verify file exists and is readable
                if (!$file->isValid()) {
                    throw new \Exception('Uploaded file is not valid');
                }
                    
                $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                $path = public_path('uploads/request-attachments');

                $file->move($path, $filename);
                $filePath = 'uploads/request-attachments/' . $filename;
                RequestAttachment::create([
                    'request_id' => $existingRequest->id,
                    'file_name' => $file->getClientOriginalName(),
                    'file_path' => $filePath,
                    'file_type' => $file->getMimeType(),
                    'file_size' => $file->getSize(),
                    'uploaded_by' => Auth::id()
                ]);
                
            }

            // Create history
            RequestHistory::create([
                'request_id' => $existingRequest->id,
                'action_by' => Auth::id(),
                'action' => 'UPDATED',
                'old_values' => json_encode($oldValues),
                'new_values' => json_encode($existingRequest->toArray())
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Request updated successfully.'
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update request: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cancel the specified request.
     */
    public function destroy($id)
    {
        try {
            $existingRequest = Request::findOrFail($id);

            // Check if user owns this request
            if ($existingRequest->user_id != Auth::id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

         
            if (!in_array($existingRequest->status, ['PENDING'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'This request cannot be Delete.'
                ], 400);
            }

            DB::beginTransaction();

            $requestData = $existingRequest->toArray();
            $attachmentCount = $existingRequest->attachments->count();
    
            foreach ($existingRequest->attachments as $attachment) {
                $filePath = public_path($attachment->file_path);
                if (file_exists($filePath)) {
                    unlink($filePath); // Delete the file
                }
            }
            $existingRequest->delete();
            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Request Deleted successfully.'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to Delete request: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete attachment.
     */
    public function deleteAttachment($attachmentId)
    {
        try {
            $attachment = RequestAttachment::with('request')->findOrFail($attachmentId);

            // Check if user owns the request
            if ($attachment->request->employee_id != Auth::id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            // Check if request is pending
            if ($attachment->request->status != 'PENDING') {
                return response()->json([
                    'success' => false,
                    'message' => 'Attachments can only be deleted from pending requests.'
                ], 400);
            }

            Storage::disk('public')->delete($attachment->file_path);
            $attachment->delete();

            return response()->json([
                'success' => true,
                'message' => 'Attachment deleted successfully.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete attachment: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get request statistics for dashboard.
     */
    public function getStats()
    {
        try {
            $user = Auth::user();

            $stats = [
                'total' => Request::where('employee_id', $user->id)->count(),
                'pending' => Request::where('employee_id', $user->id)->where('status', 'PENDING')->count(),
                'approved' => Request::where('employee_id', $user->id)->where('status', 'APPROVED')->count(),
                'rejected' => Request::where('employee_id', $user->id)->where('status', 'REJECTED')->count(),
                'cancelled' => Request::where('employee_id', $user->id)->where('status', 'CANCELLED')->count(),
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export requests to CSV.
     */
    public function export(HttpRequest $request)
    {
        try {
            $user = Auth::id();

            $query = Request::with(['requestType', 'reportingHead'])
                ->where('user_id', $user);

            // Apply same filters as index
            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            if ($request->filled('request_type')) {
                $query->where('request_type_id', $request->request_type);
            }

            if ($request->filled('from_date')) {
                $query->where('start_date', '>=', $request->from_date);
            }

            if ($request->filled('to_date')) {
                $query->where('end_date', '<=', $request->to_date);
            }

            $requests = $query->orderBy('created_at', 'desc')->get();

            $filename = 'my_requests_' . date('Y-m-d_His') . '.csv';
            $handle = fopen('php://temp', 'w+');

            // Add headers
            $this->writeCsvRow($handle, [
                'ID',
                'Request Type',
                'Start Date',
                'End Date',
                'Duration (Days)',
                'Reason',
                'Applied Date',
                'Reporting Head',
                'Status'
            ]);

            // Add data
            foreach ($requests as $req) {
                $this->writeCsvRow($handle, [
                    $req->id,
                    $req->requestType->type_name,
                    $req->start_date->format('d-m-Y'),
                    $req->end_date->format('d-m-Y'),
                    $req->start_date->diffInDays($req->end_date) + 1,
                    $req->reason,
                    $req->applied_date->format('d-m-Y'),
                    $req->reportingHead->name ?? 'N/A',
                    $req->status
                ]);
            }

            rewind($handle);
            $content = stream_get_contents($handle);
            fclose($handle);

            return response($content)
                ->header('Content-Type', 'text/csv')
                ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to export data: ' . $e->getMessage());
        }
    }




    public function managerIndex(HttpRequest $request)
    {
        try {
            $user = Auth::user();

            // Build query based on user role
            $query = Request::with(['requestType', 'user', 'reportingHead']);

            // Role-based filtering
            if ($user->role == 'admin' || $user->role == 'hr') {
                // Admin and HR can see all requests
                // No additional filter
            } else {
                // Manager - see only their team members' requests
                // Get all users where this manager is reporting head
               $teamUserIds = User::join('user_job_details', 'users.id', '=', 'user_job_details.user_id')
                ->where('user_job_details.reporting_head', $user->id)
                ->pluck('users.id')
                ->toArray();
                // Include manager's own requests if needed
                $teamUserIds[] = $user->id;
                $query->whereIn('user_id', $teamUserIds);
            }

            // Apply filters
            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            if ($request->filled('request_type')) {
                $query->where('request_type_id', $request->request_type);
            }

            if ($request->filled('employee') && ($user->role == 'admin' || $user->role == 'hr')) {
                $query->where('user_id', $request->employee);
            }

            if ($request->filled('from_date')) {
                $query->whereDate('start_date', '>=', $request->from_date);
            }

            if ($request->filled('to_date')) {
                $query->whereDate('end_date', '<=', $request->to_date);
            }

            // Get paginated results
            $requests = $query->orderBy('created_at', 'desc')->paginate(15);

            // Calculate statistics
            $statsQuery = clone $query;
            $allRequests = $statsQuery->get();

            $totalRequests = $allRequests->count();
            $totalDays = $allRequests->sum(function ($req) {
                return $req->start_date->diffInDays($req->end_date) + 1;
            });

            $pendingRequests = $allRequests->where('status', 'PENDING')->count();
            $pendingDays = $allRequests->where('status', 'PENDING')->sum(function ($req) {
                return $req->start_date->diffInDays($req->end_date) + 1;
            });

            $approvedRequests = $allRequests->where('status', 'APPROVED')->count();
            $approvedDays = $allRequests->where('status', 'APPROVED')->sum(function ($req) {
                return $req->start_date->diffInDays($req->end_date) + 1;
            });

            $rejectedRequests = $allRequests->where('status', 'REJECTED')->count();
            $rejectedDays = $allRequests->where('status', 'REJECTED')->sum(function ($req) {
                return $req->start_date->diffInDays($req->end_date) + 1;
            });

            $cancelledRequests = $allRequests->where('status', 'CANCELLED')->count();
            $cancelledDays = $allRequests->where('status', 'CANCELLED')->sum(function ($req) {
                return $req->start_date->diffInDays($req->end_date) + 1;
            });

            // Get request types for filter
            $requestTypes = RequestType::where('is_active', true)->get();

            // Get all employees for filter (only for admin/hr)
            $employees = [];
            if ($user->role == 'admin' || $user->role == 'hr') {
                $employees = User::where('status', 1)
                    ->where('role', '!=', 'admin')
                    ->orderBy('name')
                    ->get(['id', 'name', 'employee_id']);
            }

            return view('client.request.view-all-request', compact(
                'requests',
                'requestTypes',
                'employees',
                'totalRequests',
                'totalDays',
                'pendingRequests',
                'pendingDays',
                'approvedRequests',
                'approvedDays',
                'rejectedRequests',
                'rejectedDays',
                'cancelledRequests',
                'cancelledDays'
            ));
        } catch (\Exception $e) {
         
            return redirect()->back()->with('error', 'Failed to load requests: ' . $e->getMessage());
        }
    }

    /**
     * Display single request details for manager.
     */
    public function managerShow($id)
    {
        try {
            $user = Auth::user();

            $request = Request::with([
                'requestType',
                'user',
                'reportingHead',
                'attachments',
                'history' => function ($q) {
                    $q->with('actionBy')->latest();
                }
            ])->findOrFail($id);

            // Check if user has permission to view this request
            if ($user->role == 'admin' || $user->role == 'hr') {
                // Admin/HR can view all requests
            } else {
                // Manager can only view their team's requests
                $teamUserIds = User::whereHas('jobDetails', fn ($q) => $q->where('reporting_head', $user->id))->pluck('id')->toArray();
                $teamUserIds[] = $user->id;

                if (!in_array($request->user_id, $teamUserIds)) {
                    abort(403, 'Unauthorized access.');
                }
            }

            return view('client.request.manager-request-detail', compact('request'));
        } catch (\Exception $e) {
            return redirect()->route('manager.requests')
                ->with('error', 'Request not found or access denied.');
        }
    }

    /**
     * Approve a request.
     */
    public function approve(HttpRequest $request, $id)
    {
        try {
            $existingRequest = Request::findOrFail($id);
            $user = Auth::user();

            // Check if user has permission to approve
            if ($user->role == 'admin' || $user->role == 'hr') {
                // Admin/HR can approve any request
            } else {
                // Manager can only approve requests from their own reportees
                if (optional($existingRequest->user->jobDetails)->reporting_head != $user->id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'You are not authorized to approve this request.'
                    ], 403);
                }
            }

            // Check if request can be approved
            if ($existingRequest->status != 'PENDING') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only pending requests can be approved.'
                ], 400);
            }

            $validated = $request->validate([
                'comments' => 'nullable|string|max:500'
            ]);

            DB::beginTransaction();

            $oldStatus = $existingRequest->status;
            $existingRequest->update([
                'status' => 'APPROVED',
                'comments' => $validated['comments'] ?? null
            ]);

            // Create history
            RequestHistory::create([
                'request_id' => $existingRequest->id,
                'action_by' => $user->id,
                'action' => 'APPROVED',
                'comments' => $validated['comments'] ?? null,
                'old_values' => json_encode(['status' => $oldStatus]),
                'new_values' => json_encode(['status' => 'APPROVED'])
            ]);

            DB::commit();

            // This action always approves — notify the employee accordingly.
            try {
                $this->notificationService->notifyRequestApproved($existingRequest, $validated['comments'] ?? null);
            } catch (\Exception $e) {
                \Log::error('Failed to send request approved notification: ' . $e->getMessage());
            }

            return response()->json([
                'success' => true,
                'message' => 'Request approved successfully.'
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to approve request: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reject a request.
     */
    public function reject(HttpRequest $request, $id)
    {
        try {
            $existingRequest = Request::findOrFail($id);
            $user = Auth::user();

            // Check if user has permission to reject
            if ($user->role == 'admin' || $user->role == 'hr') {
                // Admin/HR can reject any request
            } else {
                // Manager can only reject requests from their own reportees
                if (optional($existingRequest->user->jobDetails)->reporting_head != $user->id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'You are not authorized to reject this request.'
                    ], 403);
                }
            }

            // Check if request can be rejected
            if ($existingRequest->status != 'PENDING') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only pending requests can be rejected.'
                ], 400);
            }

            $validated = $request->validate([
                'comments' => 'required|string|max:500'
            ]);

            DB::beginTransaction();

            $oldStatus = $existingRequest->status;
            $existingRequest->update([
                'status' => 'REJECTED',
                'comments' => $validated['comments']
            ]);

            // Create history
            RequestHistory::create([
                'request_id' => $existingRequest->id,
                'action_by' => $user->id,
                'action' => 'REJECTED',
                'comments' => $validated['comments'],
                'old_values' => json_encode(['status' => $oldStatus]),
                'new_values' => json_encode(['status' => 'REJECTED'])
            ]);

            DB::commit();

            // TODO: Send notification to employee
            // $this->sendNotification($existingRequest->user, 'request_rejected', $existingRequest);

            return response()->json([
                'success' => true,
                'message' => 'Request rejected successfully.'
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to reject request: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk approve multiple requests.
     */
    public function bulkApprove(HttpRequest $request)
    {
        try {
            $validated = $request->validate([
                'request_ids' => 'required|array',
                'request_ids.*' => 'exists:requests,id',
                'comments' => 'nullable|string|max:500'
            ]);

            $user = Auth::user();
            $successCount = 0;
            $failCount = 0;

            DB::beginTransaction();

            foreach ($validated['request_ids'] as $requestId) {
                $existingRequest = Request::find($requestId);

                // Check permissions
                if ($user->role != 'admin' && $user->role != 'hr') {
                    if (optional($existingRequest->user->jobDetails)->reporting_head != $user->id) {
                        $failCount++;
                        continue;
                    }
                }

                // Check if pending
                if ($existingRequest->status != 'PENDING') {
                    $failCount++;
                    continue;
                }

                $oldStatus = $existingRequest->status;
                $existingRequest->update([
                    'status' => 'APPROVED',
                    'comments' => $validated['comments'] ?? null
                ]);

                // Create history
                RequestHistory::create([
                    'request_id' => $existingRequest->id,
                    'action_by' => $user->id,
                    'action' => 'APPROVED',
                    'comments' => $validated['comments'] ?? null,
                    'old_values' => json_encode(['status' => $oldStatus]),
                    'new_values' => json_encode(['status' => 'APPROVED'])
                ]);

                $successCount++;
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "{$successCount} requests approved successfully. {$failCount} failed.",
                'data' => [
                    'success_count' => $successCount,
                    'fail_count' => $failCount
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to bulk approve: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export manager requests to CSV.
     */
    public function managerExport(HttpRequest $request)
    {
        try {
            $user = Auth::user();

            // Build query based on user role
            $query = Request::with(['requestType', 'user', 'reportingHead']);

            if ($user->role == 'admin' || $user->role == 'hr') {
                // Admin and HR can see all requests
            } else {
                // Manager - see only their team members
                $teamUserIds = User::whereHas('jobDetails', fn ($q) => $q->where('reporting_head', $user->id))->pluck('id')->toArray();
                $teamUserIds[] = $user->id;
                $query->whereIn('user_id', $teamUserIds);
            }

            // Apply filters
            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            if ($request->filled('request_type')) {
                $query->where('request_type_id', $request->request_type);
            }

            if ($request->filled('employee') && ($user->role == 'admin' || $user->role == 'hr')) {
                $query->where('user_id', $request->employee);
            }

            if ($request->filled('from_date')) {
                $query->whereDate('start_date', '>=', $request->from_date);
            }

            if ($request->filled('to_date')) {
                $query->whereDate('end_date', '<=', $request->to_date);
            }

            $requests = $query->orderBy('created_at', 'desc')->get();

            $filename = 'requests_' . date('Y-m-d_His') . '.csv';
            $handle = fopen('php://temp', 'w+');

            // Add UTF-8 BOM for Excel
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));

            // Add headers
            $this->writeCsvRow($handle, [
                'Request ID',
                'Employee ID',
                'Employee Name',
                'Request Type',
                'Start Date',
                'End Date',
                'Duration (Days)',
                'Reason',
                'Applied Date',
                'Status',
                'Comments'
            ]);

            // Add data
            foreach ($requests as $req) {
                $this->writeCsvRow($handle, [
                    $req->id,
                    $req->user->employee_id ?? 'N/A',
                    $req->user->name ?? 'N/A',
                    $req->requestType->type_name ?? 'N/A',
                    $req->start_date->format('d-m-Y'),
                    $req->end_date->format('d-m-Y'),
                    $req->start_date->diffInDays($req->end_date) + 1,
                    $req->reason,
                    $req->applied_date->format('d-m-Y'),
                    $req->status,
                    $req->comments ?? ''
                ]);
            }

            rewind($handle);
            $content = stream_get_contents($handle);
            fclose($handle);

            return response($content)
                ->header('Content-Type', 'text/csv; charset=utf-8')
                ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to export data: ' . $e->getMessage());
        }
    }

    /**
     * Get dashboard statistics for manager.
     */
    public function managerStats()
    {
        try {
            $user = Auth::user();

            // Build query based on user role
            $query = Request::query();

            if ($user->role == 'admin' || $user->role == 'hr') {
                // Admin and HR - all requests
            } else {
                // Manager - only team requests
                $teamUserIds = User::whereHas('jobDetails', fn ($q) => $q->where('reporting_head', $user->id))->pluck('id')->toArray();
                $teamUserIds[] = $user->id;
                $query->whereIn('user_id', $teamUserIds);
            }

            $stats = [
                'total' => (clone $query)->count(),
                'pending' => (clone $query)->where('status', 'PENDING')->count(),
                'approved' => (clone $query)->where('status', 'APPROVED')->count(),
                'rejected' => (clone $query)->where('status', 'REJECTED')->count(),
                'cancelled' => (clone $query)->where('status', 'CANCELLED')->count(),
                'total_days' => (clone $query)->get()->sum(function ($req) {
                    return $req->start_date->diffInDays($req->end_date) + 1;
                }),
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
