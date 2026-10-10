<?php

namespace App\Http\Controllers\Api\Attendance;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\AttendanceRegularization;
use App\Services\RbacService;
use App\Traits\AuthorizesByScope;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Mobile API attendance regularization: raise, my list, reportees' list, approve /
 * reject. Moved out of Api\Attendance\AttendanceController unchanged
 * (code-quality plan, Phase 4).
 */
class MobileRegularizationController extends Controller
{
    use AuthorizesByScope;

    public function regularizationStore(Request $request)
    {
        try {
            $authUser = Auth::user();

            $validator = Validator::make($request->all(), [
                'date' => 'required|date_format:Y-m-d|before_or_equal:today',
                'request_type' => 'required|in:in_time,out_time,both,full_day,wfh_not_marked,technical_issue',
                'in_time' => 'required_if:request_type,in_time,both|nullable|date_format:H:i',
                // Out before in is allowed for an overnight shift — checked below.
                'out_time' => 'required_if:request_type,out_time,both|nullable|date_format:H:i',
                'user_shift_id' => 'nullable|integer',
                'file' => 'nullable|file|mimes:jpg,jpeg,png,pdf,doc,docx|max:2048',
                'reason' => 'required|string|max:500',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => $validator->errors()->first(),
                ], 200);
            }

            // Company / employee request limits (Company Policies → Request limits, Employee 360 → Policies).
            if ($limit = app(\App\Services\RequestLimitService::class)->regularizationRefusal(
                (int) $authUser->tenant_id, (int) $authUser->id, $request->date
            )) {
                return response()->json(['success' => false, 'message' => $limit], 200);
            }

            $shiftCheck = app(\App\Services\Attendance\RegularizationShiftCheck::class)->check(
                (int) $authUser->id, (int) $authUser->tenant_id, $request->date,
                $request->input('user_shift_id'), $request->in_time, $request->out_time,
                $request->boolean('out_next_day'),
            );
            if ($shiftCheck['error']) {
                return response()->json([
                    'success' => false,
                    'message' => $shiftCheck['error'],
                ], 200);
            }

            if (AttendanceRegularization::where('user_id', $authUser->id)
                ->where('date', $request->date)
                ->where('request_type', $request->request_type)
                ->where('user_shift_id', $shiftCheck['user_shift_id'])
                ->exists()
            ) {
                return response()->json([
                    'success' => false,
                    'message' => 'Request already exists for this date.',
                ], 200);
            }

            $filePath = $request->hasFile('file')
                ? file_storage()->upload($request->file('file'), 'regularization', ['tenant' => $authUser->tenant_id])->path
                : null;

            $reg = AttendanceRegularization::create([
                'tenant_id' => $authUser->tenant_id,
                'user_id' => $authUser->id,
                'date' => $request->date,
                'user_shift_id' => $shiftCheck['user_shift_id'],
                'request_type' => $request->request_type,
                'in_time' => $request->in_time,
                'out_time' => $request->out_time,
                'out_next_day' => $request->boolean('out_next_day'),
                'file' => $filePath,
                'reason' => $request->reason,
                'status' => 'pending',
            ]);

            // Parity with the web submit path.
            try {
                app(\App\Services\AttendanceRegularizationNotificationService::class)
                    ->notifyRegularizationSubmitted($reg);
            } catch (\Throwable $e) {
                Log::error('Regularization submit notification failed: '.$e->getMessage());
            }

            return response()->json([
                'success' => true,
                'message' => 'Request submitted successfully',
            ], 200);
        } catch (Exception $e) {
            // The response stays generic; the reason goes to the log (was swallowed).
            Log::error('Mobile attendance '.__FUNCTION__.' failed', ['user_id' => Auth::id(), 'error' => $e->getMessage(), 'at' => $e->getFile().':'.$e->getLine()]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred. Please try again later.',
            ], 500);
        }
    }

    public function getMyRegularizations(Request $request)
    {
        try {
            $authUser = Auth::user();

            $query = DB::table('attendance_regularizations as ar')
                ->select([
                    'ar.id',
                    'ar.date',
                    'ar.request_type',
                    'ar.in_time',
                    'ar.out_time',
                    'ar.out_next_day',
                    'ar.reason',
                    'ar.file',
                    'ar.status',
                    'um.name as approved_by',
                    'ar.approved_date',
                    'u.id as user_id',
                    'u.employee_id as employee_id',
                    'u.name as user_name',
                    'u.email as user_email',
                    'ar.created_at',
                    'd.name as designation',
                    'bd.profile_image',
                ])
                ->where('ar.user_id', $authUser->id)
                ->leftJoin('users as u', 'ar.user_id', '=', 'u.id')
                ->leftJoin('users as um', 'ar.approved_by', '=', 'um.id')
                ->leftJoin('user_job_details as jd', 'u.id', '=', 'jd.user_id')
                ->leftJoin('user_basic_details as bd', 'u.id', '=', 'bd.user_id')
                ->leftJoin('designations as d', 'jd.designation', '=', 'd.id');

            if ($request->has('status') && $request->status != 'all') {
                $query->where('status', $request->status);
            }

            if ($request->has('request_type') && $request->request_type != 'all') {
                $query->where('request_type', $request->request_type);
            }

            if ($request->has('search') && ! empty($request->search)) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('reason', 'like', "%{$search}%")
                        ->orWhere('date', 'like', "%{$search}%")
                        ->orWhere('request_type', 'like', "%{$search}%");
                });
            }

            if ($request->has('start_date') && ! empty($request->start_date)) {
                $query->where('date', '>=', $request->start_date);
            }

            if ($request->has('end_date') && ! empty($request->end_date)) {
                $query->where('date', '<=', $request->end_date);
            }

            $perPage = $request->get('per_page', 15);
            $regularizations = $query->orderBy('created_at', 'desc')
                ->paginate($perPage);

            $formattedRequests = $regularizations->map(function ($request) {
                return [
                    'id' => $request->id,
                    'date' => $request->date,
                    'request_type' => $request->request_type,
                    'in_time' => $request->in_time,
                    'out_time' => $request->out_time,
                    // $request here is the regularization row, not the HTTP request.
                    'out_next_day' => (bool) $request->out_next_day,
                    'reason' => $request->reason,
                    'file' => file_url($request->file, 'regularization'),
                    'status' => $request->status,
                    'submit_date' => $request->created_at,
                    'user_id' => $request->user_id,
                    'employee_id' => $request->employee_id,
                    'user_name' => $request->user_name,
                    'user_email' => $request->user_email,
                    'profile_image' => file_url($request->profile_image, 'profile_photo'),
                    'designation' => $request->designation,
                    'approved_by' => $request->approved_by,
                    'approved_date' => $request->approved_date,
                ];
            });

            $summaryQuery = DB::table('attendance_regularizations')
                ->where('user_id', $authUser->id);

            $summary = [
                'total' => $summaryQuery->count(),
                'pending' => $summaryQuery->clone()->where('status', 'pending')->count(),
                'approved' => $summaryQuery->clone()->where('status', 'approved')->count(),
                'rejected' => $summaryQuery->clone()->where('status', 'rejected')->count(),
            ];

            return response()->json([
                'success' => true,
                'message' => 'Regularization requests fetched successfully',
                'data' => $formattedRequests,
                'summary' => $summary,
                'links' => [
                    'first' => $regularizations->url(1),
                    'last' => $regularizations->url($regularizations->lastPage()),
                    'prev' => $regularizations->previousPageUrl(),
                    'next' => $regularizations->nextPageUrl(),
                ],
            ], 200);
        } catch (Exception $e) {
            // The response stays generic; the reason goes to the log (was swallowed).
            Log::error('Mobile attendance '.__FUNCTION__.' failed', ['user_id' => Auth::id(), 'error' => $e->getMessage(), 'at' => $e->getFile().':'.$e->getLine()]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred. Please try again later.',
            ], 500);
        }
    }

    public function getReporteesRegularizations(Request $request)
    {
        try {
            $authUser = Auth::user();

            if (! app(RbacService::class)->can($authUser, 'attendance', 'approve')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access. Only managers, admins or supervisors can approve requests.',
                ], 200);
            }

            $reporteeIds = DB::table('user_reporting_heads')
                ->where('reporting_head_id', $authUser->id)
                ->pluck('user_id')
                ->toArray();

            if (empty($reporteeIds)) {
                return response()->json([
                    'success' => true,
                    'message' => 'No reportees found',
                    'data' => [],
                    'summary' => [
                        'total' => 0,
                        'pending' => 0,
                        'approved' => 0,
                        'rejected' => 0,
                    ],
                    'links' => [
                        'first' => null,
                        'last' => null,
                        'prev' => null,
                        'next' => null,
                    ],
                ], 200);
            }

            $query = DB::table('attendance_regularizations as ar')
                ->select([
                    'ar.id',
                    'ar.date',
                    'ar.request_type',
                    'ar.in_time',
                    'ar.out_time',
                    'ar.out_next_day',
                    'ar.reason',
                    'ar.file',
                    'ar.status',
                    'um.name as approved_by',
                    'ar.approved_date',
                    'ar.created_at',
                    'u.id as user_id',
                    'u.employee_id as employee_id',
                    'u.name as user_name',
                    'u.email as user_email',
                    'd.name as designation',
                    'bd.profile_image',
                ])
                ->leftJoin('users as u', 'ar.user_id', '=', 'u.id')
                ->leftJoin('users as um', 'ar.approved_by', '=', 'um.id')
                ->leftJoin('user_job_details as jd', 'u.id', '=', 'jd.user_id')
                ->leftJoin('user_basic_details as bd', 'u.id', '=', 'bd.user_id')
                ->leftJoin('designations as d', 'jd.designation', '=', 'd.id')
                ->whereIn('ar.user_id', $reporteeIds);

            if ($request->has('status') && $request->status != 'all') {
                $query->where('ar.status', $request->status);
            }

            if ($request->has('request_type') && $request->request_type != 'all') {
                $query->where('ar.request_type', $request->request_type);
            }

            if ($request->has('search') && ! empty($request->search)) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('ar.reason', 'like', "%{$search}%")
                        ->orWhere('ar.date', 'like', "%{$search}%")
                        ->orWhere('ar.request_type', 'like', "%{$search}%")
                        ->orWhere('u.name', 'like', "%{$search}%")
                        ->orWhere('u.employee_id', 'like', "%{$search}%");
                });
            }

            if ($request->has('start_date') && ! empty($request->start_date)) {
                $query->where('ar.date', '>=', $request->start_date);
            }

            if ($request->has('end_date') && ! empty($request->end_date)) {
                $query->where('ar.date', '<=', $request->end_date);
            }

            if ($request->has('user_id') && ! empty($request->user_id)) {
                $query->where('ar.user_id', $request->user_id);
            }

            $perPage = $request->get('per_page', 15);
            $regularizations = $query->orderBy('ar.created_at', 'desc')
                ->paginate($perPage);

            $formattedRequests = $regularizations->map(function ($request) {
                return [
                    'id' => $request->id,
                    'date' => $request->date,
                    'request_type' => $request->request_type,
                    'in_time' => $request->in_time,
                    'out_time' => $request->out_time,
                    // $request here is the regularization row, not the HTTP request.
                    'out_next_day' => (bool) $request->out_next_day,
                    'reason' => $request->reason,
                    'file' => file_url($request->file, 'regularization'),
                    'status' => $request->status,
                    'submit_date' => $request->created_at,
                    'user_id' => $request->user_id,
                    'employee_id' => $request->employee_id,
                    'user_name' => $request->user_name,
                    'user_email' => $request->user_email,
                    'profile_image' => file_url($request->profile_image, 'profile_photo'),
                    'designation' => $request->designation,
                    'approved_by' => $request->approved_by,
                    'approved_date' => $request->approved_date,
                ];
            });

            $reportees = DB::table('users as u')
                ->select('u.id', 'u.name', 'u.employee_id', 'jd.designation')
                ->leftJoin('user_job_details as jd', 'u.id', '=', 'jd.user_id')
                ->whereIn('u.id', function ($q) use ($authUser) {
                    $q->select('user_id')->from('user_reporting_heads')->where('reporting_head_id', $authUser->id);
                })
                ->orderBy('u.name')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Reportees regularization requests fetched successfully',
                'data' => $formattedRequests,
                'reportees' => $reportees,
                'links' => [
                    'first' => $regularizations->url(1),
                    'last' => $regularizations->url($regularizations->lastPage()),
                    'prev' => $regularizations->previousPageUrl(),
                    'next' => $regularizations->nextPageUrl(),
                ],
            ], 200);
        } catch (Exception $e) {
            // The response stays generic; the reason goes to the log (was swallowed).
            Log::error('Mobile attendance '.__FUNCTION__.' failed', ['user_id' => Auth::id(), 'error' => $e->getMessage(), 'at' => $e->getFile().':'.$e->getLine()]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred. Please try again later.',
            ], 500);
        }
    }

    public function regularizationApproval(Request $request)
    {
        try {
            $authUser = Auth::user();
            $tenantId = $authUser->tenant_id;

            if (! app(RbacService::class)->can($authUser, 'attendance', 'approve')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access. Only managers, admins can approve requests.',
                ], 200);
            }

            $validator = Validator::make($request->all(), [
                'id' => 'required|exists:attendance_regularizations,id',
                'status' => 'required|string|in:approved,rejected',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => $validator->errors()->first(),
                ], 200);
            }

            $regularizationId = $request->id;

            // Tenant-scoped lookup — never trust a bare id.
            $regularization = DB::table('attendance_regularizations as ar')
                ->select([
                    'ar.*',
                    'u.id as user_id',
                    'u.name as user_name',
                    'u.email as user_email',
                    'jd.reporting_head',
                    'rh.name as reporting_head_name',
                ])
                ->join('users as u', 'ar.user_id', '=', 'u.id')
                ->leftJoin('user_job_details as jd', 'u.id', '=', 'jd.user_id')
                ->leftJoin('users as rh', 'jd.reporting_head', '=', 'rh.id')
                ->where('ar.id', $regularizationId)
                ->where('ar.tenant_id', $tenantId)
                ->where('u.tenant_id', $tenantId)
                ->first();

            if (! $regularization) {
                return response()->json([
                    'success' => false,
                    'message' => 'Regularization request not found.',
                ], 200);
            }

            // Admin / HR may process any request in their tenant; a manager may
            // only process their own reportees'.
            if (! $this->scopeCoversOwner($authUser, 'attendance', 'approve', (int) $regularization->user_id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not authorized to approve/reject this request. Only the reporting head can process it.',
                ], 200);
            }

            if ($regularization->status != 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot update a '.$regularization->status.' Attendance Regularizations. Please contact admin if needed.',
                ], 200);
            }

            // Reject a future-dated approval BEFORE any write.
            $regularizationDate = Carbon::parse($regularization->date);
            if ($request->status == 'approved' && $regularizationDate->isFuture()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot create attendance for future date.',
                ], 200);
            }

            $model = AttendanceRegularization::withoutGlobalScopes()
                ->where('id', $regularizationId)->where('tenant_id', $tenantId)->first();

            // Tier 2 / T2-A — route through the approval workflow when the tenant
            // has one; returns null → legacy single-approver path below.
            try {
                $ar = app(\App\Services\Approvals\ApprovalService::class)
                    ->decide('regularization', $model, $authUser, $request->status, $request->input('remarks'));
                if ($ar !== null) {
                    return response()->json([
                        'success' => true,
                        'message' => $ar->status === 'pending'
                            ? 'Recorded. Awaiting the next approval level.'
                            : 'Attendance Regularizations status updated successfully.',
                    ], 200);
                }
            } catch (\RuntimeException $e) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 200);
            }

            DB::beginTransaction();
            try {
                $model->status = $request->status;
                $model->approved_by = $authUser->id;
                $model->approved_date = now();
                $model->save();

                if ($request->status == 'approved') {
                    // Complete + audited + refreshed via the write funnel.
                    app(\App\Services\Attendance\AttendanceEntryService::class)
                        ->applyRegularization($model, $authUser);
                }

                DB::commit();
            } catch (\Throwable $e) {
                DB::rollBack();
                throw $e;
            }

            // Parity with the web approval path.
            try {
                $notifier = app(\App\Services\AttendanceRegularizationNotificationService::class);
                if ($request->status == 'approved') {
                    $notifier->notifyRegularizationApproved($model, $request->input('remarks'));
                } else {
                    $notifier->notifyRegularizationRejected($model, $request->input('remarks'));
                }
            } catch (\Throwable $e) {
                Log::error('Regularization notification failed: '.$e->getMessage());
            }

            return response()->json([
                'success' => true,
                'message' => $request->status == 'rejected'
                    ? 'Regularization request rejected successfully.'
                    : 'Attendance Regularizations status updated successfully.',
            ], 200);
        } catch (Exception $e) {
            // The response stays generic; the reason goes to the log (was swallowed).
            Log::error('Mobile attendance '.__FUNCTION__.' failed', ['user_id' => Auth::id(), 'error' => $e->getMessage(), 'at' => $e->getFile().':'.$e->getLine()]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred. Please try again later.',
            ], 500);
        }
    }
}
