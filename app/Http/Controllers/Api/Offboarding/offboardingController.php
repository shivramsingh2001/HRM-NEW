<?php

namespace App\Http\Controllers\Api\Offboarding;

use App\Http\Controllers\Controller;
use App\Models\OffboardingRequest;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class OffboardingController extends Controller
{
    /**
     * Get notice period information for the authenticated employee
     */
    public function noticePeriode(Request $request)
    {
        try {
            $tenant = Tenant::where('id', session('tenant_id'))->first();
            $employee_id = Auth::id();

            $existingRequest = OffboardingRequest::where('employee_id', $employee_id)
                ->whereIn('status', ['pending_approval', 'approved', 'completed'])
                ->exists();

            return response()->json([
                'success' => true,
                'message' => "Notice period information retrieved successfully",
                'data' => [
                    'notice_period_days' => $tenant->notice_period ?? 30,
                    'is_applied' => $existingRequest,
                    'min_last_working_date' => now()->addDays($tenant->notice_period ?? 30)->format('Y-m-d')
                ]
            ], 200);
        } catch (\Exception $e) {
            Log::error('Failed to get notice period: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => "Failed to retrieve notice period information."
            ], 500);
        }
    }

    /**
     * Create a new offboarding request (Resignation only for employees)
     */
    public function store(Request $request)
    {
        try {
            DB::beginTransaction();

            $validator = Validator::make($request->all(), [
                'last_working_date' => 'required|date|after_or_equal:today',
                'resignation_date' => 'nullable|date|before_or_equal:today',
                'reason_detail' => 'required|string|min:10|max:500',
                'feedback' => 'nullable|string|max:1000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => $validator->errors()->first(),

                ], 200);
            }

            $employee_id = Auth::id();

            // Check if employee exists and is active
            $employee = User::where('id', $employee_id)->where('status', '1')->first();
            if (!$employee) {
                return response()->json([
                    'success' => false,
                    'message' => "Employee not found or inactive."
                ], 200);
            }

            // Check if employee already has pending offboarding
            $existingRequest = OffboardingRequest::where('employee_id', $employee_id)
                ->whereIn('status', ['pending_approval', 'approved'])
                ->first();

            if ($existingRequest) {
                return response()->json([
                    'success' => false,
                    'message' => "You already have an active offboarding request. Request Code: " . $existingRequest->request_code,
                    'data' => [
                        'request_code' => $existingRequest->request_code,
                        'status' => $existingRequest->status,
                        'last_working_date' => $existingRequest->last_working_date
                    ]
                ], 200);
            }

            // Get tenant notice period for validation
            $tenant = Tenant::where('id', session('tenant_id'))->first();
            $minNoticeDays = $tenant->notice_period ?? 15;
            $minDate = now()->addDays($minNoticeDays)->format('Y-m-d');

            if ($request->last_working_date < $minDate) {
                return response()->json([
                    'success' => false,
                    'message' => "Last working date must be at least {$minNoticeDays} days from today."
                ], 200);
            }

            $offboarding = OffboardingRequest::create([
                'tenant_id' => session('tenant_id'),
                'employee_id' => $employee_id,
                'request_date' => now()->format('Y-m-d'),
                'last_working_date' => $request->last_working_date,
                'resignation_date' => $request->resignation_date ?? now()->format('Y-m-d'),
                'reason' => 'resignation',
                'reason_detail' => $request->reason_detail,
                'feedback' => $request->feedback,
                'eligible_for_rehire' => 1,
                'status' => 'pending_approval',
                'manager_review_status' => 'pending',
                'hr_review_status' => 'pending',
                'knowledge_transfer_status' => 'not_started',
                'exit_interview_status' => 'not_scheduled',
                'final_settlement_status' => 'pending',
                'created_by' => $employee_id
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Offboarding request submitted successfully!",
                // 'data' => [
                //     'id' => $offboarding->id,
                //     'request_code' => $offboarding->request_code,
                //     'status' => $offboarding->status,
                //     'last_working_date' => $offboarding->last_working_date,
                //     'created_at' => $offboarding->created_at
                // ]
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create offboarding request: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => "Failed to create offboarding request: " . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get employee's own offboarding requests
     */
    public function myRequests(Request $request)
    {
        try {
            $employee_id = Auth::id();

            $requests = OffboardingRequest::with(['createdBy', 'approvedBy', 'managerReviewBy', 'hrReviewBy'])
                ->where('employee_id', $employee_id)
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($request) {
                    return [
                        'id' => $request->id,
                        'request_code' => $request->request_code,
                        'status' => $request->status,
                        'status_label' => $request->status_label,
                        'reason' => $request->reason,
                        'reason_detail' => $request->reason_detail,
                        'request_date' => $request->request_date,
                        'last_working_date' => $request->last_working_date,
                        'manager_review_status' => $request->manager_review_status,
                        'hr_review_status' => $request->hr_review_status,
                        'knowledge_transfer_status' => $request->knowledge_transfer_status,
                        'asset_return_status' => $request->asset_return_status,
                        'exit_interview_status' => $request->exit_interview_status,
                        'final_settlement_status' => $request->final_settlement_status,
                        'created_at' => $request->created_at,
                        'current_stage' => $this->getCurrentStage($request)
                    ];
                });

            $activeRequest = OffboardingRequest::where('employee_id', $employee_id)
                ->whereIn('status', ['pending_approval', 'approved'])
                ->first();

            return response()->json([
                'success' => true,
                'message' => "Offboarding requests retrieved successfully",
                'data' => [
                    'requests' => $requests,
                    'has_active_request' => $activeRequest ? true : false,
                    'active_request' => $activeRequest ? [
                        'id' => $activeRequest->id,
                        'request_code' => $activeRequest->request_code,
                        'status' => $activeRequest->status,
                        'last_working_date' => $activeRequest->last_working_date
                    ] : null
                ]
            ], 200);
        } catch (\Exception $e) {
            Log::error('Failed to get offboarding requests: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => "Failed to retrieve offboarding requests."
            ], 500);
        }
    }

    /**
     * Get single offboarding request details
     */
    public function show()
    {
        try {
            $employee_id = Auth::id();

            $offboarding = OffboardingRequest::with([
                'employee',
                'createdBy',
                'approvedBy',
                'managerReviewBy',
                'hrReviewBy',
                'exitInterview',
                'exitInterview.interviewer'
            ])
                ->where('employee_id', $employee_id)
                ->orderBy('id', 'DESC')
                ->first();

            if (!$offboarding) {
                return response()->json([
                    'success' => false,
                    'message' => "Offboarding request not found."
                ], 200);
            }

            // Get tenant notice period
            $tenant = Tenant::where('id', session('tenant_id'))->first();
            $noticePeriodDays = $tenant->notice_period ?? 30;

            // Determine resignation status
            $resignationStatus = $this->getResignationStatus($offboarding);

            // Build timeline based on current status
            $timeline = $this->buildTimeline($offboarding);

            return response()->json([
                'success' => true,
                'message'=> "data fetched Successfully.",
                'data' => [
                    'resignation_status' => $resignationStatus,
                    'notice_period_days' => $noticePeriodDays,
                    'resignation_date' => $offboarding->resignation_date ?? $offboarding->request_date,
                    'last_working_date' => $offboarding->last_working_date,
                    'reason' => ucfirst(str_replace('_', ' ', $offboarding->reason)),
                    'reason_detail' => $offboarding->reason_detail,
                    'timeline' => $timeline
                ]
            ], 200);
        } catch (\Exception $e) {
            Log::error('Failed to get offboarding details: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => "Failed to retrieve offboarding details."
            ], 500);
        }
    }

    /**
     * Get resignation status based on current stage
     */
    private function getResignationStatus($offboarding)
    {
        // Completed
        if ($offboarding->status == 'completed') {
            return 'completed';
        }

        // Rejected
        if ($offboarding->status == 'rejected') {
            return 'rejected';
        }

        // Cancelled
        if ($offboarding->status == 'cancelled') {
            return 'cancelled';
        }

        // Final Settlement
        if ($offboarding->final_settlement_status == 'paid') {
            return 'settlement_completed';
        }
        if ($offboarding->final_settlement_status == 'processing') {
            return 'settlement_processing';
        }

        // Asset Clearance
        if ($offboarding->asset_return_status == 'completed') {
            return 'clearance_completed';
        }

        // Knowledge Transfer
        if ($offboarding->knowledge_transfer_status == 'completed') {
            return 'handover_completed';
        }
        if ($offboarding->knowledge_transfer_status == 'in_progress') {
            return 'handover_in_progress';
        }

        // HR Review
        if ($offboarding->hr_review_status == 'approved') {
            return 'hr_approved';
        }
        if ($offboarding->hr_review_status == 'rejected') {
            return 'hr_rejected';
        }

        // Manager Review
        if ($offboarding->manager_review_status == 'approved') {
            return 'manager_approved';
        }
        if ($offboarding->manager_review_status == 'rejected') {
            return 'manager_rejected';
        }
        if ($offboarding->manager_review_status == 'pending') {
            return 'manager_review';
        }

        return 'initiated';
    }

    /**
     * Build timeline array based on offboarding status
     */
    private function buildTimeline($offboarding)
    {
        // Define all timeline stages
        $timelineStages = [
            'notice_submission' => [
                'title' => 'Notice Submission',
                'subtitle' => 'Request Submitted',
                'check_condition' => function ($offboarding) {
                    return !empty($offboarding->request_date);
                }
            ],
            'manager_review' => [
                'title' => 'Manager Review',
                'subtitle' => $this->getManagerSubtitle($offboarding),
                'check_condition' => function ($offboarding) {
                    return $offboarding->manager_review_status == 'approved';
                }
            ],
            'hr_review' => [
                'title' => 'HR Review',
                'subtitle' => $this->getHRSubtitle($offboarding),
                'check_condition' => function ($offboarding) {
                    return $offboarding->hr_review_status == 'approved';
                }
            ],
            'approval' => [
                'title' => 'Approval & Date Lock',
                'subtitle' => $this->getApprovalSubtitle($offboarding),
                'check_condition' => function ($offboarding) {
                    return $offboarding->status == 'approved' || $offboarding->status == 'completed';
                }
            ],
            'handover' => [
                'title' => 'Knowledge Transfer',
                'subtitle' => $this->getHandoverSubtitle($offboarding),
                'check_condition' => function ($offboarding) {
                    return $offboarding->knowledge_transfer_status == 'completed';
                }
            ],
            'clearance' => [
                'title' => 'Asset Clearance',
                'subtitle' => $this->getClearanceSubtitle($offboarding),
                'check_condition' => function ($offboarding) {
                    return $offboarding->asset_return_status == 'completed';
                }
            ],
            'exit_interview' => [
                'title' => 'Exit Interview',
                'subtitle' => $this->getExitInterviewSubtitle($offboarding),
                'check_condition' => function ($offboarding) {
                    return $offboarding->exit_interview_status == 'completed';
                }
            ],
            'settlement' => [
                'title' => 'Final Settlement',
                'subtitle' => $this->getSettlementSubtitle($offboarding),
                'check_condition' => function ($offboarding) {
                    return $offboarding->final_settlement_status == 'paid';
                }
            ]
        ];

        $timeline = [];
        $previousCompleted = true;

        foreach ($timelineStages as $status => $stage) {
            $isCompleted = $stage['check_condition']($offboarding);

            // A stage is only completed if all previous stages are completed
            $isCompleted = $previousCompleted && $isCompleted;

            $timeline[] = [
                'status' => $status,
                'title' => $stage['title'],
                'subtitle' => $stage['subtitle'],
                'completed' => $isCompleted
            ];

            $previousCompleted = $isCompleted;
        }

        return $timeline;
    }
    private function getManagerSubtitle($offboarding)
    {
        if ($offboarding->manager_review_status == 'approved') {
            return 'Approved by Manager';
        }
        if ($offboarding->manager_review_status == 'rejected') {
            return 'Rejected by Manager';
        }
        return 'Pending Approval';
    }


    private function getHRSubtitle($offboarding)
    {
        if ($offboarding->hr_review_status == 'approved') {
            return 'Approved by HR';
        }
        if ($offboarding->hr_review_status == 'rejected') {
            return 'Rejected by HR';
        }
        if ($offboarding->manager_review_status == 'approved') {
            return 'Awaiting HR Approval';
        }
        return 'Pending Manager Approval First';
    }

    private function getApprovalSubtitle($offboarding)
    {
        if ($offboarding->status == 'completed') {
            return 'Process Completed';
        }
        if ($offboarding->status == 'approved') {
            return 'Last Working Date Confirmed: ' . date('d M, Y', strtotime($offboarding->last_working_date));
        }
        if ($offboarding->manager_review_status == 'approved' && $offboarding->hr_review_status == 'approved') {
            return 'Waiting for Process Initiation';
        }
        if ($offboarding->manager_review_status == 'approved') {
            return 'Manager Approved - Pending HR';
        }
        return 'Waiting for Approvals';
    }

    private function getHandoverSubtitle($offboarding)
    {
        if ($offboarding->knowledge_transfer_status == 'completed') {
            return 'Handover Completed';
        }
        if ($offboarding->knowledge_transfer_status == 'in_progress') {
            return 'In Progress';
        }
        return 'Not Started';
    }

    private function getClearanceSubtitle($offboarding)
    {
        if ($offboarding->asset_return_status == 'completed') {
            return 'All Assets Cleared';
        }
        if ($offboarding->asset_return_status == 'partial') {
            return 'Partially Completed';
        }
        return 'Pending';
    }

    private function getExitInterviewSubtitle($offboarding)
    {
        if ($offboarding->exit_interview_status == 'completed') {
            return 'Interview Completed';
        }
        if ($offboarding->exit_interview_status == 'scheduled') {
            return 'Scheduled';
        }
        return 'Not Scheduled';
    }

    private function getSettlementSubtitle($offboarding)
    {
        if ($offboarding->final_settlement_status == 'paid') {
            return 'Settlement Paid';
        }
        if ($offboarding->final_settlement_status == 'processing') {
            return 'Processing';
        }
        return 'Locked';
    }

    public function cancel($id, Request $request)
    {
        try {
            DB::beginTransaction();

            $validator = Validator::make($request->all(), [
                'cancellation_reason' => 'nullable|string|max:500'
            ]);

            $offboarding = OffboardingRequest::where('employee_id', Auth::id())
                ->where('id', $id)
                ->whereIn('status', ['pending_approval', 'approved'])
                ->first();

            if (!$offboarding) {
                return response()->json([
                    'success' => false,
                    'message' => "Offboarding request not found or cannot be cancelled."
                ], 404);
            }

            $offboarding->update([
                'status' => 'cancelled',
                'feedback' => $request->cancellation_reason ?? 'Request cancelled by employee'
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Offboarding request cancelled successfully."
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to cancel offboarding: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => "Failed to cancel offboarding request."
            ], 500);
        }
    }

    /**
     * Get current stage of offboarding
     */
    private function getCurrentStage($offboarding)
    {
        if ($offboarding->status == 'completed') {
            return 'Completed';
        }
        if ($offboarding->status == 'rejected') {
            return 'Rejected';
        }
        if ($offboarding->status == 'cancelled') {
            return 'Cancelled';
        }
        if ($offboarding->final_settlement_status == 'paid') {
            return 'Final Settlement Completed';
        }
        if ($offboarding->final_settlement_status == 'processing') {
            return 'Final Settlement Processing';
        }
        if ($offboarding->asset_return_status == 'completed') {
            return 'Asset Clearance Completed';
        }
        if ($offboarding->knowledge_transfer_status == 'completed') {
            return 'Knowledge Transfer Completed';
        }
        if ($offboarding->hr_review_status == 'approved') {
            return 'HR Approved - Pending KT';
        }
        if ($offboarding->manager_review_status == 'approved') {
            return 'Manager Approved - Pending HR';
        }
        if ($offboarding->manager_review_status == 'pending') {
            return 'Pending Manager Approval';
        }
        return 'Initiated';
    }

    /**
     * Calculate progress percentage
     */
    private function calculateProgress($offboarding)
    {
        $stages = [
            'request_submitted' => !empty($offboarding->request_date),
            'manager_approved' => $offboarding->manager_review_status == 'approved',
            'hr_approved' => $offboarding->hr_review_status == 'approved',
            'knowledge_transfer' => $offboarding->knowledge_transfer_status == 'completed',
            'asset_clearance' => $offboarding->asset_return_status == 'completed',
            'exit_interview' => $offboarding->exit_interview_status == 'completed',
            'final_settlement' => $offboarding->final_settlement_status == 'paid'
        ];

        $completed = count(array_filter($stages));
        $total = count($stages);

        return round(($completed / $total) * 100);
    }
}
