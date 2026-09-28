<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\Request as DBRequest;
use App\Models\RequestType;
use App\Services\RbacService;

class RequestController extends Controller
{
    public function view_ai_all(Request $request)
    {
        try {
            $authUser = Auth::user();

            // Base query with joins
            $query = DBRequest::query()
                ->join('users', 'requests.user_id', '=', 'users.id')
                ->join('user_job_details', 'user_job_details.user_id', '=', 'users.id')
                ->join('user_basic_details', 'user_basic_details.user_id', '=', 'users.id')
                ->leftJoin('request_types', 'requests.request_type_id', '=', 'request_types.id')
                ->leftJoin('users as reporting_heads', 'user_job_details.reporting_head', '=', 'reporting_heads.id')
                ->select(
                    'requests.id',
                   
                    'users.employee_id',
                    'users.name as employee_name',
                    'users.id as user_id',
                    'users.role as user_role',
                    'user_basic_details.profile_image as employee_profile_image',
                    'request_types.type_name as request_type',
                    'request_types.id as request_type_id',
                    'requests.start_date',
                    'requests.end_date',
                    DB::raw("DATEDIFF(requests.end_date, requests.start_date) + 1 as duration_days"),
                    'requests.reason',
                    'requests.status',
                    'requests.comments',
                    'requests.applied_date',
                    'requests.created_at',
                    'reporting_heads.name as reporting_head_name',
                    'reporting_heads.employee_id as reporting_head_employee_id',
                //     DB::raw("
                //     CASE 
                //         WHEN requests.attachment_path IS NULL OR requests.attachment_path = '' 
                //         THEN NULL
                //         ELSE CONCAT('$baseUrl/', requests.attachment_path)
                //     END as attachment_url
                // ")
                );

            // Permission-based filtering (was a fixed role switch that
            // silently locked out any custom role holding a real
            // requests:view grant).
            $requestScope = app(RbacService::class)->scopeFor($authUser, 'requests', 'view');
            switch ($requestScope) {
                case 'company':
                    break;

                case 'team':
                    $query->where(function ($q) use ($authUser) {
                        $q->whereIn('users.id', function ($sub) use ($authUser) { // Team members (any reporting head)
                            $sub->select('user_id')->from('user_reporting_heads')->where('reporting_head_id', $authUser->id);
                        })
                            ->orWhere('requests.user_id', $authUser->id); // Own requests
                    });
                    break;

                case 'own':
                    $query->where('requests.user_id', $authUser->id);
                    break;

                default:
                    return response()->json([
                        'success' => false,
                        'message' => 'Unauthorized access. Invalid role.'
                    ], 403);
            }

            // Apply filters
            if ($request->filled('status')) {
                $query->where('requests.status', $request->status);
            }

            if ($request->filled('request_type')) {
                $query->where('requests.request_type_id', $request->request_type);
            }

            if ($request->filled('employee_id') && $requestScope === 'company') {
                $query->where('requests.user_id', $request->employee_id);
            }

            if ($request->filled('from_date')) {
                $query->whereDate('requests.start_date', '>=', $request->from_date);
            }

            if ($request->filled('to_date')) {
                $query->whereDate('requests.end_date', '<=', $request->to_date);
            }

            // Search filter
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('users.name', 'LIKE', "%{$search}%")
                        ->orWhere('users.employee_id', 'LIKE', "%{$search}%")
                        ->orWhere('request_types.type_name', 'LIKE', "%{$search}%")
                        ->orWhere('requests.reason', 'LIKE', "%{$search}%");
                    
                });
            }

            // Order by
            $sortBy = $request->get('sort_by', 'created_desc');
            switch ($sortBy) {
                case 'created_asc':
                    $query->orderBy('requests.created_at', 'asc');
                    break;
                case 'created_desc':
                    $query->orderBy('requests.created_at', 'desc');
                    break;
                case 'start_date_asc':
                    $query->orderBy('requests.start_date', 'asc');
                    break;
                case 'start_date_desc':
                    $query->orderBy('requests.start_date', 'desc');
                    break;
                case 'duration_asc':
                    $query->orderByRaw('DATEDIFF(requests.end_date, requests.start_date) + 1 asc');
                    break;
                case 'duration_desc':
                    $query->orderByRaw('DATEDIFF(requests.end_date, requests.start_date) + 1 desc');
                    break;
                default:
                    $query->orderBy('requests.created_at', 'desc');
                    break;
            }

            // Get paginated results or all
            if ($request->filled('per_page')) {
                $requests = file_storage()->mapUrls($query->paginate($request->per_page), ['employee_profile_image' => 'profile_photo']);
            } else {
                $requests = file_storage()->mapUrls($query->get(), ['employee_profile_image' => 'profile_photo']);
            }

            // Calculate summary statistics
            $allRequestsQuery = clone $query;
            if ($request->filled('per_page')) {
                // For paginated, get all records for summary
                $allRequests = $allRequestsQuery->get();
            } else {
                $allRequests = $requests;
            }

            $summary = [
                'total_requests' => $allRequests->count(),
                'total_days' => $allRequests->sum('duration_days'),
                'by_status' => [
                    'pending' => $allRequests->where('status', 'PENDING')->count(),
                    'approved' => $allRequests->where('status', 'APPROVED')->count(),
                    'rejected' => $allRequests->where('status', 'REJECTED')->count(),
                    'cancelled' => $allRequests->where('status', 'CANCELLED')->count(),
                ],
                'by_request_type' => [],
                'total_days_by_status' => [
                    'pending' => $allRequests->where('status', 'PENDING')->sum('duration_days'),
                    'approved' => $allRequests->where('status', 'APPROVED')->sum('duration_days'),
                    'rejected' => $allRequests->where('status', 'REJECTED')->sum('duration_days'),
                    'cancelled' => $allRequests->where('status', 'CANCELLED')->sum('duration_days'),
                ]
            ];

            // Calculate by request type
            $requestTypes = RequestType::where('is_active', true)->get();
            foreach ($requestTypes as $type) {
                $summary['by_request_type'][$type->type_name] = [
                    'total' => $allRequests->where('request_type_id', $type->id)->count(),
                    'days' => $allRequests->where('request_type_id', $type->id)->sum('duration_days')
                ];
            }

            // Group by employee if the caller can see more than just their own
            $groupedByEmployee = null;
            if ($requestScope !== 'own') {
                $groupedByEmployee = $allRequests->groupBy('user_id')->map(function ($userRequests, $userId) {
                    $first = $userRequests->first();
                    return [
                        'user_id' => $userId,
                        'employee_id' => $first->employee_id,
                        'employee_name' => $first->employee_name,
                        'employee_profile_image' => $first->employee_profile_image,
                        'total_requests' => $userRequests->count(),
                        'total_days' => $userRequests->sum('duration_days'),
                        'pending_requests' => $userRequests->where('status', 'PENDING')->count(),
                        'approved_requests' => $userRequests->where('status', 'APPROVED')->count(),
                        'rejected_requests' => $userRequests->where('status', 'REJECTED')->count(),
                        'cancelled_requests' => $userRequests->where('status', 'CANCELLED')->count(),
                        'requests' => $userRequests
                    ];
                })->values();
            }

            // Prepare response data
            $responseData = [
                'success' => true,
                'message' => 'Request data fetched successfully!',
                // 'summary' => $summary,
                // 'grouped_by_employee' => $groupedByEmployee,
            ];

            // Add pagination data if paginated
            if ($request->filled('per_page')) {
                $responseData['data'] = $requests->items();
                $responseData['pagination'] = [
                    'current_page' => $requests->currentPage(),
                    'per_page' => $requests->perPage(),
                    'total' => $requests->total(),
                    'last_page' => $requests->lastPage(),
                    'from' => $requests->firstItem(),
                    'to' => $requests->lastItem(),
                ];
            } else {
                $responseData['data'] = $requests;
            }

            return response()->json($responseData, 200);
        } catch (Exception $e) {

            return response()->json([
                'success' => false,
                'message' => 'An error occurred. Please try again later.'.$e->getMessage(),

            ], 500);
        }
    }
}
