<?php

namespace App\Http\Controllers\Api\Attendance;

use App\Http\Controllers\Controller;
use App\Models\RequestAttachment;
use App\Models\RequestHistory;
use App\Models\Request as RequestStore;
use App\Models\RequestType;
use App\Models\User;
use Exception;
use Google\Cloud\Storage\Connection\Rest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Services\RbacService;
use App\Services\AuditLogger;
use App\Services\RequestNotificationService;
use App\Traits\AuthorizesByScope;

class RequestController extends Controller
{
    use AuthorizesByScope;

    protected $auditLogger;
    protected $notificationService;

    public function __construct(AuditLogger $auditLogger, RequestNotificationService $notificationService)
    {
        $this->auditLogger = $auditLogger;
        $this->notificationService = $notificationService;
    }

    public function type(Request $request)
    {
        try {
            $requestTypes = RequestType::where('is_active', true)->get(['id','type_name','description']);
            return response()->json([
                'success' => true,
                'message' => 'data fetched successfully.',
                'data' => $requestTypes
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occured. Please try again later.'
            ], 500);
        }
    }
    
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'request_type_id' => 'required|exists:request_types,id',
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'required|string|max:1000',
            'attachment' => 'sometimes|nullable|file|max:5120|mimes:jpg,jpeg,png,pdf,doc,docx'
        ]);
    
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ], 200);
        }
    
        $validated = $validator->validated();
        $user = Auth::user();
        
        try {
            DB::beginTransaction();
            
            // Check for overlapping requests
            $overlapping = RequestStore::overlapping($user->id, $validated['start_date'], $validated['end_date'])->exists();
    
            if ($overlapping) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'You already have a pending or approved request for this date range.'
                ], 200);
            }
    
            // Create request
            $newRequest = RequestStore::create([
                'request_type_id' => $validated['request_type_id'],
                'user_id' => $user->id,
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
                'reason' => $validated['reason'],
                'status' => 'PENDING',
                'applied_date' => now()
            ]);
    
            // Handle file upload safely
            if ($request->hasFile('attachment') && $request->file('attachment')->isValid()) {
                try {
                    $file = $request->file('attachment');
                    
                    // Verify file exists and is readable
                    if (!$file->isValid()) {
                        throw new \Exception('Uploaded file is not valid');
                    }
                    
                    $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                    $path = public_path('uploads/requests/attachments');

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
    
            RequestHistory::create([
                'request_id' => $newRequest->id,
                'action_by' => $user->id,
                'action' => 'CREATED',
                'new_values' => json_encode($newRequest->toArray())
            ]);

            $this->auditLogger->record('tenant_user', $user->id, (int) $user->tenant_id, 'requests.created', 'Request', $newRequest->id, [], $newRequest->toArray());

            DB::commit();

            try {
                $this->notificationService->notifyRequestSubmitted($newRequest);
            } catch (\Exception $e) {
                \Log::error('Failed to send request notification: ' . $e->getMessage());
            }

            return response()->json([
                'success' => true,
                'message' => 'Request created successfully.',
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();


            return response()->json([
                'success' => false,
                'message' => 'Failed to create request. Please try again.',

            ], 500);
        }
    }

    /**
     * Update a pending request (mobile — was missing entirely; web has the
     * equivalent in Attendance\RequestController::update()).
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'request_type_id' => 'required|exists:request_types,id',
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'required|string|max:1000',
            'attachment' => 'sometimes|nullable|file|max:5120|mimes:jpg,jpeg,png,pdf,doc,docx'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ], 200);
        }

        $validated = $validator->validated();
        $user = Auth::user();

        try {
            $existingRequest = RequestStore::findOrFail($id);

            if ($existingRequest->user_id != $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 200);
            }

            if (!$existingRequest->canBeEdited()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only pending requests can be updated.'
                ], 200);
            }

            DB::beginTransaction();

            $overlapping = RequestStore::overlapping($user->id, $validated['start_date'], $validated['end_date'], $id)->exists();

            if ($overlapping) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'You already have another pending or approved request for this date range.'
                ], 200);
            }

            $oldValues = $existingRequest->toArray();

            $existingRequest->update([
                'request_type_id' => $validated['request_type_id'],
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
                'reason' => $validated['reason']
            ]);

            if ($request->hasFile('attachment') && $request->file('attachment')->isValid()) {
                $file = $request->file('attachment');
                $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                $path = public_path('uploads/requests/attachments');

                $file->move($path, $filename);
                $filePath = 'uploads/requests/attachments/' . $filename;

                RequestAttachment::create([
                    'request_id' => $existingRequest->id,
                    'file_name' => $file->getClientOriginalName(),
                    'file_path' => $filePath,
                    'file_type' => $file->getMimeType(),
                    'file_size' => $file->getSize(),
                    'uploaded_by' => $user->id
                ]);
            }

            RequestHistory::create([
                'request_id' => $existingRequest->id,
                'action_by' => $user->id,
                'action' => 'UPDATED',
                'old_values' => json_encode($oldValues),
                'new_values' => json_encode($existingRequest->toArray())
            ]);

            $this->auditLogger->record('tenant_user', $user->id, (int) $user->tenant_id, 'requests.updated', 'Request', $existingRequest->id, $oldValues, $existingRequest->toArray());

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Request updated successfully.'
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update request. Please try again.'
            ], 500);
        }
    }

    /**
     * Cancel (soft-cancel, not delete) a PENDING or APPROVED request — was
     * missing entirely on mobile; mirrors the web controller's destroy().
     */
    public function cancel($id)
    {
        $user = Auth::user();

        try {
            $existingRequest = RequestStore::findOrFail($id);

            if ($existingRequest->user_id != $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 200);
            }

            if (!$existingRequest->canBeCancelled()) {
                return response()->json([
                    'success' => false,
                    'message' => 'This request cannot be cancelled.'
                ], 200);
            }

            DB::beginTransaction();

            $oldStatus = $existingRequest->status;
            $wasApproved = $oldStatus === 'APPROVED';

            $existingRequest->update(['status' => 'CANCELLED']);

            RequestHistory::create([
                'request_id' => $existingRequest->id,
                'action_by' => $user->id,
                'action' => 'CANCELLED',
                'old_values' => json_encode(['status' => $oldStatus]),
                'new_values' => json_encode(['status' => 'CANCELLED'])
            ]);

            $this->auditLogger->record('tenant_user', $user->id, (int) $user->tenant_id, 'requests.cancelled', 'Request', $existingRequest->id, ['status' => $oldStatus], ['status' => 'CANCELLED']);

            DB::commit();

            try {
                $this->notificationService->notifyRequestCancelled($existingRequest, $wasApproved);
            } catch (\Exception $e) {
                \Log::error('Failed to send request cancellation notification: ' . $e->getMessage());
            }

            return response()->json([
                'success' => true,
                'message' => 'Request cancelled successfully.'
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel request. Please try again.'
            ], 500);
        }
    }

    public function view(Request $request)
    {
        try {
            $user = Auth::user();
            
            $requests = RequestStore::with(['requestType' => function($query) {
                $query->select('id', 'type_name'); // Only select needed fields from requestType
            }])
            ->where('user_id', $user->id)
            ->select([
                'id',
                'request_type_id',
                'start_date',
                'end_date',
                'reason',
                'status',
                'comments',
                'applied_date',
                'created_at'
            ])
            ->orderBy('created_at', 'desc')
            ->paginate(15);
    
            // Transform the data to format dates and show only selected fields
            $transformedRequests = collect($requests->items())->map(function ($request) {
                return [
                    'id' => $request->id,
                    'request_type' => $request->requestType->type_name,
                    'request_type_id' => $request->requestType->id,
                    'start_date' => $request->start_date ? $request->start_date->format('Y-m-d') : null,
                    'end_date' => $request->end_date ? $request->end_date->format('Y-m-d') : null,
                    'reason' => $request->reason,
                    'status' => $request->status,
                    'comments' => $request->comments,
                    'applied_date' => $request->applied_date ? $request->applied_date->format('Y-m-d') : null,
                    // 'created_at' => $request->created_at ? $request->created_at->format('Y-m-d H:i:s') : null
                ];
            });
    
            return response()->json([
                'success' => true,
                'message' => 'Data fetched successfully.',
                'data' => [
                    'requests' => $transformedRequests,
                    'pagination' => [
                        'current_page' => $requests->currentPage(),
                        'last_page' => $requests->lastPage(),
                        'per_page' => $requests->perPage(),
                        'total' => $requests->total(),
                        'next_page_url' => $requests->nextPageUrl(),
                        'prev_page_url' => $requests->previousPageUrl()
                    ]
                ]
            ], 200);
            
        } catch (Exception $e) {
           
            return response()->json([
                'success' => false,
                'message' => 'An error occurred. Please try again later.'
            ], 500);
        }
    }
    
    public function view_all(Request $request)
    {
        try {
            $user = Auth::user();
    
            // Check if user has permission (was a fixed role allowlist that
            // silently locked out any custom role holding a real
            // requests:view grant)
            $viewScope = app(RbacService::class)->scopeFor($user, 'requests', 'view');
            if ($viewScope === null || $viewScope === 'own') {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have permission to view these requests.'
                ], 403); // Use 403 for permission denied
            }
    
            // Build the query with proper selections
            $query = RequestStore::with([
                'requestType' => function($q) {
                    $q->select('id', 'type_name'); // Select only needed fields
                },
                'user' => function($q) {
                    $q->select('id', 'name', 'email', 'employee_id'); // Select only needed user fields
                }
            ]);
    
            // Apply scope-based filtering
            if ($viewScope === 'team') {
                $teamUserIds = User::managedBy($user->id)
                    ->pluck('id')
                    ->toArray();
                
                // If no team members, return empty result
                if (empty($teamUserIds)) {
                    return response()->json([
                        'success' => true,
                        'message' => 'No team members found.',
                        'data' => [
                            'requests' => [],
                            'pagination' => [
                                'current_page' => 1,
                                'last_page' => 1,
                                'per_page' => 15,
                                'total' => 0,
                                'next_page_url' => null,
                                'prev_page_url' => null
                            ]
                        ]
                    ], 200);
                }
                
                $query->whereIn('user_id', $teamUserIds);
            }
    
            // Add search/filter functionality
            if ($request->has('search') && !empty($request->search)) {
                $searchTerm = $request->search;
                $query->where(function($q) use ($searchTerm) {
                    $q->whereHas('user', function($userQuery) use ($searchTerm) {
                        $userQuery->where('name', 'LIKE', "%{$searchTerm}%")
                            ->orWhere('email', 'LIKE', "%{$searchTerm}%")
                            ->orWhere('employee_id', 'LIKE', "%{$searchTerm}%");
                    });
                });
            }
    
            // Filter by status
            if ($request->has('status') && !empty($request->status)) {
                $query->where('status', $request->status);
            }
    
            // Filter by request type
            if ($request->has('request_type_id') && !empty($request->request_type_id)) {
                $query->where('request_type_id', $request->request_type_id);
            }
    
            // Filter by date range
            if ($request->has('from_date') && $request->has('to_date')) {
                $query->whereBetween('applied_date', [$request->from_date, $request->to_date]);
            }
    
            // Select only needed columns from requests table
            $query->select([
                'id',
                'request_type_id',
                'user_id',
                'start_date',
                'end_date',
                'reason',
                'status',
                'comments',
                'applied_date',
                'created_at'
            ]);
    
            // Order and paginate
            $requests = $query->orderBy('created_at', 'desc')->paginate(15);
    
            // Transform the data
            $transformedRequests = collect($requests->items())->map(function ($request) {
                return [
                    'id' => $request->id,
                    'request_type_id' => $request->requestType->id ?? null,
                    'request_type' => $request->requestType->type_name ?? null,
                    'name' => $request->user->name ?? null,
                    'employee_id' => $request->user->employee_id ?? null,
                    'email' => $request->user->email ?? null,
                    'start_date' => $request->start_date ? $request->start_date->format('Y-m-d') : null,
                    'end_date' => $request->end_date ? $request->end_date->format('Y-m-d') : null,
                    'duration_days' => $request->duration_in_days, // Using model accessor
                    'reason' => $request->reason,
                    'status' => $request->status,
                    'comments' => $request->comments,
                    'applied_date' => $request->applied_date ? $request->applied_date->format('Y-m-d') : null,
                    // 'created_at' => $request->created_at ? $request->created_at->format('Y-m-d H:i:s') : null
                ];
            });
    
            return response()->json([
                'success' => true,
                'message' => 'Requests fetched successfully.',
                'data' => [
                    'requests' => $transformedRequests,
                    'pagination' => [
                        'current_page' => $requests->currentPage(),
                        'last_page' => $requests->lastPage(),
                        'per_page' => $requests->perPage(),
                        'total' => $requests->total(),
                        'next_page_url' => $requests->nextPageUrl(),
                        'prev_page_url' => $requests->previousPageUrl()
                    ],
                    // 'filters' => [ // Return applied filters for UI
                    //     'search' => $request->search ?? null,
                    //     'status' => $request->status ?? null,
                    //     'request_type_id' => $request->request_type_id ?? null,
                    //     'from_date' => $request->from_date ?? null,
                    //     'to_date' => $request->to_date ?? null
                    // ]
                ]
            ], 200);
            
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred. Please try again later.'
            ], 500);
        }
    }
    
    public function updateStatus(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:APPROVED,REJECTED',
            'id' => 'required|exists:requests,id',
            'remarks' => 'nullable|string|max:250',
        ]);
        $id = $request->id;
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ], 200);
        }
        $user = Auth::user();
        try {
            $existingRequest = RequestStore::findOrFail($id);

            // Was a coarse admin/hr/manager gate with no further per-record
            // check — any manager could approve/reject ANY tenant's request,
            // not just their own team's. scopeCoversOwner() closes that gap.
            if (!$this->scopeCoversOwner($user, 'requests', 'approve', (int) $existingRequest->user_id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have permission to view these requests.'
                ], 200);
            }

            if ($existingRequest->status != 'PENDING') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only pending requests can be approved or reject.'
                ], 200);
            }

            $commentsValidator = Validator::make($request->all(), [
                'comments' => $request->status === 'REJECTED' ? 'required|string|max:500' : 'nullable|string|max:500'
            ]);
            if ($commentsValidator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => $commentsValidator->errors()->first()
                ], 200);
            }

            DB::beginTransaction();

            $oldStatus = $existingRequest->status;
            $remarks = $request->remarks ?? $request->comments ?? null;
            $existingRequest->update([
                'status' => $request->status,
                'comments' => $remarks
            ]);

            RequestHistory::create([
                'request_id' => $existingRequest->id,
                'action_by' => $user->id,
                'action' => $request->status,
                'comments' => $remarks,
                'old_values' => json_encode(['status' => $oldStatus]),
                'new_values' => json_encode(['status' => $request->status])
            ]);

            $this->auditLogger->record('tenant_user', $user->id, (int) $user->tenant_id, 'requests.' . strtolower($request->status), 'Request', $existingRequest->id, ['status' => $oldStatus], ['status' => $request->status]);

            DB::commit();

            try {
                if ($request->status === 'APPROVED') {
                    $this->notificationService->notifyRequestApproved($existingRequest, $remarks);
                } else {
                    $this->notificationService->notifyRequestRejected($existingRequest, $remarks);
                }
            } catch (\Exception $e) {
                \Log::error('Failed to send request status notification: ' . $e->getMessage());
            }

            return response()->json([
                'success' => true,
                'message' => 'Request ' . $request->status . ' successfully.'
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'An error occured. Please try again later.'
            ], 500);
        }
    }
    
    /**
 * Show complete details of a specific request by ID
 * 
 * @param Request $request
 * @param int $id
 * @return \Illuminate\Http\JsonResponse
 */
public function show(Request $request, $id)
{
    try {
        $user = Auth::user();
        
        // Find the request with all relationships loaded
        $requestDetail = RequestStore::with([
            'requestType',
            'user' => function($query) {
                $query->select('id', 'name', 'email', 'employee_id', 'contact');
            },
            'user.basicDetails' => function($query) {
                $query->select('user_id', 'dob', 'gender');
            },
            'user.jobDetails' => function($query) {
                $query->select('user_id', 'department', 'designation', 'reporting_head', 'joining_date');
            },
            'user.jobDetails.department' => function($query) {
                $query->select('id', 'name');
            },
            'user.jobDetails.designation' => function($query) {
                $query->select('id', 'name');
            },
            'reportingHead' => function($query) {
                $query->select('id', 'name', 'email', 'employee_id');
            },
            'attachments' => function($query) {
                $query->select('id', 'request_id', 'file_name', 'file_path', 'file_type', 'file_size', 'uploaded_by', 'created_at');
            },
            'attachments.uploadedBy' => function($query) {
                $query->select('id', 'name');
            },
            'history' => function($query) {
                $query->with(['actionBy' => function($q) {
                    $q->select('id', 'name', 'employee_id');
                }])->orderBy('created_at', 'desc');
            }
        ])->find($id);

        // Check if request exists
        if (!$requestDetail) {
            return response()->json([
                'success' => false,
                'message' => 'Request not found.'
            ], 200);
        }

        // Check authorization: own request, or a scope that covers this
        // owner (was "any manager", inconsistent with view_all()'s
        // team-only listing — now consistently team-scoped).
        if (!$this->scopeCoversOwner($user, 'requests', 'view', (int) $requestDetail->user_id)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to view this request.'
            ], 200);
        }

        // Transform the data
        $detailedRequest = [
            
            'employee' => [
                'id' => $requestDetail->user->id ?? null,
                'name' => $requestDetail->user->name ?? null,
                'email' => $requestDetail->user->email ?? null,
                'employee_id' => $requestDetail->user->employee_id ?? null,
                'contact' => $requestDetail->user->contact ?? null,
                'profile_image' => $requestDetail->user->basicDetails->profile_image ?? null,
            ],
            'request_details' => [
                'id' => $requestDetail->id,
                'request_type_id' => $requestDetail->requestType->id ?? null,
                'request_type' => $requestDetail->requestType->type_name ?? null,
                'start_date' => $requestDetail->start_date ? $requestDetail->start_date->format('Y-m-d') : null,
                'end_date' => $requestDetail->end_date ? $requestDetail->end_date->format('Y-m-d') : null,
                'duration_days' => $requestDetail->duration_in_days,
                'reason' => $requestDetail->reason,
                'status' => $requestDetail->status,
                'comments' => $requestDetail->comments,
                'applied_date' => $requestDetail->applied_date ? $requestDetail->applied_date->format('Y-m-d') : null,
                'created_at' => $requestDetail->created_at ? $requestDetail->created_at->format('Y-m-d H:i:s') : null,
                'updated_at' => $requestDetail->updated_at ? $requestDetail->updated_at->format('Y-m-d H:i:s') : null
            ],
            'attachments' => $requestDetail->attachments->map(function($attachment) {
                return [
                    // 'id' => $attachment->id,
                    // 'file_name' => $attachment->file_name,
                    // 'file_path' => $attachment->file_path,
                    'file_url' => $attachment->file_url,
                    'file_type' => $attachment->file_type,
                    // 'file_size' => $attachment->file_size,
                    // 'formatted_file_size' => $attachment->formatted_file_size, 
                    // 'uploaded_by' => $attachment->uploadedBy ? [
                    //     'id' => $attachment->uploadedBy->id,
                    //     'name' => $attachment->uploadedBy->name
                    // ] : null,
                    // 'uploaded_at' => $attachment->created_at ? $attachment->created_at->format('Y-m-d H:i:s') : null
                ];
            }),
            'history' => $requestDetail->history->map(function($history) {
                return [
                    'id' => $history->id,
                    'action' => $history->action,
                    'comments' => $history->comments,
                    'action_by' => $history->actionBy ? [
                        'id' => $history->actionBy->id,
                        'name' => $history->actionBy->name,
                        'employee_id' => $history->actionBy->employee_id
                    ] : null,
                    'old_values' => json_decode($history->old_values, true),
                    'new_values' => json_decode($history->new_values, true),
                    'created_at' => $history->created_at ? $history->created_at->format('Y-m-d H:i:s') : null
                ];
            })
        ];

        return response()->json([
            'success' => true,
            'message' => 'Request details fetched successfully.',
            'data' => $detailedRequest
        ], 200);

    } catch (Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'An error occurred while fetching request details. Please try again later.'.$e->getMessage()
        ], 500);
    }
}
}
