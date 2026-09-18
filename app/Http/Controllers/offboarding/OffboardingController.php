<?php

namespace App\Http\Controllers\offboarding;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\OffboardingRequest;
use App\Models\ExitInterview;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class OffboardingController extends Controller
{
    /**
     * Main index method - routes to appropriate view based on role
     */
    public function index(Request $request)
    {
        $userRole = auth()->user()->role ?? 'employee';

        if (in_array($userRole, ['admin', 'hr'])) {
            return $this->adminIndex($request);
        } elseif ($userRole == 'manager') {
            return $this->managerIndex($request);
        } else {
            return $this->employeeIndex($request);
        }
    }

    /**
     * Admin/HR Index - See all requests
     */
    public function adminIndex(Request $request)
    {
        try {
            $query = OffboardingRequest::with([
                'employee',
                'createdBy',
                'approvedBy',
                'managerReviewBy',
                'hrReviewBy',
                'employee.jobDetails.Department',
                'employee.jobDetails.Designation'
            ]);

            if ($request->has('status') && $request->status) {
                $query->where('status', $request->status);
            }

            if ($request->has('stage') && $request->stage) {
                switch ($request->stage) {
                    case 'manager_review':
                        $query->where('manager_review_status', 'pending')->where('status', 'pending_approval');
                        break;
                    case 'hr_review':
                        $query->where('manager_review_status', 'approved')->where('hr_review_status', 'pending');
                        break;
                    case 'knowledge_transfer':
                        $query->where('hr_review_status', 'approved')->where('knowledge_transfer_status', '!=', 'completed');
                        break;
                    case 'asset_clearance':
                        $query->where('asset_return_status', '!=', 'completed');
                        break;
                    case 'exit_interview':
                        $query->where('exit_interview_status', 'scheduled');
                        break;
                    case 'final_settlement':
                        $query->where('final_settlement_status', 'processing');
                        break;
                }
            }

            if ($request->has('employee_id') && $request->employee_id) {
                $query->where('employee_id', $request->employee_id);
            }

            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->whereHas('employee', function ($q) use ($search) {
                    $q->where('name', 'LIKE', "%{$search}%")
                        ->orWhere('employee_id', 'LIKE', "%{$search}%")
                        ->orWhere('email', 'LIKE', "%{$search}%");
                });
            }

            $offboardings = $query->orderBy('created_at', 'desc')->paginate(15);

            $stats = [
                'total'           => OffboardingRequest::count(),
                'pending_manager' => OffboardingRequest::where('manager_review_status', 'pending')->where('status', 'pending_approval')->count(),
                'pending_hr'      => OffboardingRequest::where('manager_review_status', 'approved')->where('hr_review_status', 'pending')->count(),
                'approved'        => OffboardingRequest::where('status', 'approved')->count(),
                'completed'       => OffboardingRequest::where('status', 'completed')->count(),
                'rejected'        => OffboardingRequest::where('status', 'rejected')->count(),
            ];

            $employees    = User::where('status', '1')->orderBy('name')->get();
            $allEmployees = $employees;

            return view('client.offboarding.index_admin', compact('offboardings', 'stats', 'employees', 'allEmployees'));
        } catch (\Exception $e) {
            Log::error('Failed to fetch offboarding requests: ' . $e->getMessage());
            return back()->with('error', 'Failed to load offboarding requests.');
        }
    }

    /**
     * Manager Index - See team members' requests
     */
    public function managerIndex(Request $request)
    {
        try {
            $teamMemberIds = User::managedBy(auth()->id())->pluck('id');
            $teamMemberIds->push(auth()->id());

            $query = OffboardingRequest::with(['employee', 'createdBy'])
                ->whereIn('employee_id', $teamMemberIds);

            // Apply employee filter if selected
            if ($request->has('employee_id') && $request->employee_id) {
                $query->where('employee_id', $request->employee_id);
            }

            if ($request->has('status') && $request->status) {
                $query->where('status', $request->status);
            }

            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->whereHas('employee', function ($q) use ($search) {
                    $q->where('name', 'LIKE', "%{$search}%")
                        ->orWhere('employee_id', 'LIKE', "%{$search}%")
                        ->orWhere('email', 'LIKE', "%{$search}%");
                });
            }

            $offboardings = $query->orderBy('created_at', 'desc')->paginate(15);

            // Get all team members for the employee dropdown filter
            $employees = User::whereIn('id', $teamMemberIds)->get();

            $stats = [
                'total'           => OffboardingRequest::whereIn('employee_id', $teamMemberIds)->count(),
                'pending_manager' => OffboardingRequest::whereIn('employee_id', $teamMemberIds)->where('manager_review_status', 'pending')->count(),
                'pending_hr'      => OffboardingRequest::whereIn('employee_id', $teamMemberIds)->where('manager_review_status', 'approved')->where('hr_review_status', 'pending')->count(),
                'approved'        => OffboardingRequest::whereIn('employee_id', $teamMemberIds)->where('status', 'approved')->count(),
                'rejected'        => OffboardingRequest::whereIn('employee_id', $teamMemberIds)->where('status', 'rejected')->count(),
            ];

            $teamMembers = User::whereIn('id', $teamMemberIds)->get();

            return view('client.offboarding.index_manager', compact('offboardings', 'stats', 'teamMembers', 'employees'));
        } catch (\Exception $e) {
            Log::error('Failed to fetch team offboarding requests: ' . $e->getMessage());
            return back()->with('error', 'Failed to load team offboarding requests.');
        }
    }

    /**
     * Employee Index - See only own requests
     */
    public function employeeIndex(Request $request)
    {
        try {
            $activeRequest = OffboardingRequest::where('employee_id', auth()->id())
                ->whereIn('status', ['pending_approval', 'approved', 'completed'])
                ->first();

            $hasActiveRequest = $activeRequest ? true : false;

            return view('client.offboarding.index_employee', compact('activeRequest', 'hasActiveRequest'));
        } catch (\Exception $e) {
            Log::error('Failed to fetch employee offboarding: ' . $e->getMessage());
            return back()->with('error', 'Failed to load offboarding requests.');
        }
    }

    /**
     * Show form to create offboarding request.
     */
    public function create(Request $request)
    {
        try {
            $employeeId      = $request->get('employee_id');
            $selectedEmployee = null;

            if ($employeeId) {
                $selectedEmployee = User::find($employeeId);
            }

            $userRole = auth()->user()->role ?? 'employee';

            if (in_array($userRole, ['admin', 'hr'])) {
                $employees = User::where('status', '1')->where('role', 'employee')->orderBy('name')->get();
            } else {
                $employees        = User::where('id', auth()->id())->get();
                $selectedEmployee = auth()->user();
            }

            $reasons = [
                'resignation'      => 'Resignation',
                'retirement'       => 'Retirement',
                'termination'      => 'Termination',
                'contract_end'     => 'Contract End',
                'mutual_agreement' => 'Mutual Agreement',
                'other'            => 'Other',
            ];

            return view('client.offboarding.create', compact('employees', 'reasons', 'selectedEmployee'));
        } catch (\Exception $e) {
            Log::error('Failed to load create form: ' . $e->getMessage());
            return back()->with('error', 'Failed to load create form.');
        }
    }

    /**
     * Store offboarding request.
     */
    public function store(Request $request)
    {
        try {
            DB::beginTransaction();

            $validator = Validator::make($request->all(), [
                'employee_id'       => 'required|exists:users,id',
                'last_working_date' => 'required|date|after_or_equal:today',
                'resignation_date'  => 'nullable|date|before_or_equal:today',
                'reason'            => 'required|in:resignation,retirement,termination,contract_end,mutual_agreement,other',
                'reason_detail'     => 'nullable|string',
                'feedback'          => 'nullable|string',
                'eligible_for_rehire' => 'nullable|boolean',
                'hr_remarks'        => 'nullable|string',
                'finance_remarks'   => 'nullable|string',
                'it_remarks'        => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return redirect()->back()->withErrors($validator)->withInput();
            }

            $userRole = auth()->user()->role ?? 'employee';
            if (!in_array($userRole, ['admin', 'hr'])) {
                if ($request->employee_id != auth()->user()->id) {
                    return redirect()->back()->with('error', 'You can only create offboarding requests for yourself.')->withInput();
                }
            }

            $existingRequest = OffboardingRequest::where('employee_id', $request->employee_id)
                ->whereIn('status', ['pending_approval', 'approved'])
                ->first();

            if ($existingRequest) {
                return redirect()->back()->with('error', 'Employee already has an active offboarding request.')->withInput();
            }

            $offboarding = OffboardingRequest::create([
                'tenant_id'                  => auth()->user()->tenant_id ?? null,
                'employee_id'                => $request->employee_id,
                'request_date'               => now(),
                'last_working_date'          => $request->last_working_date,
                'resignation_date'           => $request->resignation_date ?? now(),
                'reason'                     => $request->reason,
                'reason_detail'              => $request->reason_detail,
                'feedback'                   => $request->feedback,
                'eligible_for_rehire'        => $request->eligible_for_rehire ?? 1,
                'hr_remarks'                 => $request->hr_remarks,
                'finance_remarks'            => $request->finance_remarks,
                'it_remarks'                 => $request->it_remarks,
                'status'                     => 'pending_approval',
                'manager_review_status'      => 'pending',
                'hr_review_status'           => 'pending',
                'knowledge_transfer_status'  => 'not_started',
                'exit_interview_status'      => 'not_scheduled',
                'final_settlement_status'    => 'pending',
                'created_by'                 => auth()->id(),
            ]);

            DB::commit();

            return redirect()->route('offboarding.show', $offboarding->id)
                ->with('success', 'Offboarding request created successfully!');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create offboarding request: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to create offboarding request.')->withInput();
        }
    }

    /**
     * Display offboarding request details.
     */
    public function show($id)
    {
        try {
            $offboarding = OffboardingRequest::with([
                'employee',
                'employee.jobDetails',
                'employee.jobDetails.Designation',
                'employee.jobDetails.Department',
                'createdBy',
                'approvedBy',
                'managerReviewBy',
                'hrReviewBy',
                'exitInterview',
                'exitInterview.interviewer',
                'finalSettlementProcessedBy',
            ])->findOrFail($id);

            $userRole    = auth()->user()->role ?? 'employee';
            $isRequester = $offboarding->employee_id == auth()->user()->id;

            if ($userRole == 'employee' && !$isRequester) {
                return redirect()->route('offboarding.index')->with('error', 'You can only view your own offboarding requests.');
            }

            if ($userRole == 'manager') {
                $isTeamMember = User::managedBy(auth()->user()->id)
                    ->where('id', $offboarding->employee_id)
                    ->exists();
                if (!$isTeamMember && !$isRequester) {
                    return redirect()->route('offboarding.manager')->with('error', 'You can only view your team members\' offboarding requests.');
                }
            }

            $hrUsers  = User::whereIn('role', ['hr', 'admin'])->orderBy('name')->get();
            $managers = User::whereIn('role', ['manager', 'admin'])->orderBy('name')->get();

            return view('client.offboarding.show', compact('offboarding', 'hrUsers', 'managers'));
        } catch (\Exception $e) {
            Log::error('Failed to fetch offboarding details: ' . $e->getMessage());
            return redirect()->route('offboarding.index')->with('error', 'Offboarding request not found.');
        }
    }

    /**
     * Manager Review - Approve or Reject
     */
    public function managerReview($id, Request $request)
    {
        try {
            DB::beginTransaction();

            $request->validate([
                'action'   => 'required|in:approve,reject',
                'comments' => 'required_if:action,reject|nullable|string',
            ]);

            $offboarding = OffboardingRequest::findOrFail($id);

            if ($offboarding->manager_review_status !== 'pending') {
                return redirect()->back()->with('error', 'Manager review already completed.');
            }

            if ($request->action === 'approve') {
                $offboarding->update([
                    'manager_review_status'   => 'approved',
                    'manager_review_by'       => Auth::id(),
                    'manager_review_at'       => now(),
                    'manager_review_comments' => $request->comments,
                ]);
                $message = 'Manager review approved. Waiting for HR review.';
            } else {
                // Rejecting at manager stage - also set status explicitly so trigger agrees
                $offboarding->update([
                    'manager_review_status'   => 'rejected',
                    'manager_review_by'       => Auth::id(),
                    'manager_review_at'       => now(),
                    'manager_review_comments' => $request->comments,
                    'status'                  => 'rejected',
                ]);
                $message = 'Offboarding request rejected by manager.';
            }

            DB::commit();

            return redirect()->route('offboarding.show', $id)->with('success', $message);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to process manager review: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to process manager review.');
        }
    }

    /**
     * HR Review
     */
    public function hrReview($id, Request $request)
    {
        try {
            DB::beginTransaction();

            $request->validate([
                'action'            => 'required|in:approve,reject',
                'comments'          => 'required_if:action,reject|nullable|string',
                'last_working_date' => 'required_if:action,approve|nullable|date',
            ]);

            $offboarding = OffboardingRequest::findOrFail($id);

            if ($offboarding->manager_review_status !== 'approved') {
                return redirect()->back()->with('error', 'Manager approval required before HR review.');
            }

            if ($offboarding->hr_review_status !== 'pending') {
                return redirect()->back()->with('error', 'HR review already completed.');
            }

            if ($request->action === 'approve') {
                $offboarding->update([
                    'hr_review_status'   => 'approved',
                    'hr_review_by'       => Auth::id(),
                    'hr_review_at'       => now(),
                    'hr_review_comments' => $request->comments,
                    'last_working_date'  => $request->last_working_date ?? $offboarding->last_working_date,
                    'status'             => 'approved',
                    'approved_by'        => Auth::id(),
                    'approved_at'        => now(),
                ]);

                if ($offboarding->employee->jobDetails) {
                    $offboarding->employee->jobDetails->update([
                        'leaving_date' => $offboarding->last_working_date,
                    ]);
                }

                $message = 'HR review approved. Offboarding process can begin.';
            } else {
                $offboarding->update([
                    'hr_review_status'   => 'rejected',
                    'hr_review_by'       => Auth::id(),
                    'hr_review_at'       => now(),
                    'hr_review_comments' => $request->comments,
                    'status'             => 'rejected',
                ]);
                $message = 'Offboarding request rejected by HR.';
            }

            DB::commit();

            return redirect()->route('offboarding.show', $id)->with('success', $message);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to process HR review: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to process HR review.');
        }
    }

    /**
     * Start Knowledge Transfer
     */
    public function startKnowledgeTransfer($id, Request $request)
    {
        try {
            DB::beginTransaction();

            $offboarding = OffboardingRequest::findOrFail($id);

            if ($offboarding->status !== 'approved') {
                return redirect()->back()->with('error', 'Offboarding must be approved before starting knowledge transfer.');
            }

            if ($offboarding->knowledge_transfer_status !== 'not_started') {
                return redirect()->back()->with('error', 'Knowledge transfer already started or completed.');
            }

            $offboarding->update([
                'knowledge_transfer_status' => 'in_progress',
                'knowledge_transfer_notes'  => $request->notes ?? null,
            ]);

            DB::commit();

            return redirect()->route('offboarding.show', $id)->with('success', 'Knowledge transfer process started.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to start knowledge transfer: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to start knowledge transfer.');
        }
    }

    /**
     * Complete Knowledge Transfer
     */
    public function completeKnowledgeTransfer($id, Request $request)
    {
        try {
            DB::beginTransaction();

            $request->validate(['notes' => 'nullable|string']);

            $offboarding = OffboardingRequest::findOrFail($id);

            if ($offboarding->knowledge_transfer_status !== 'in_progress') {
                return redirect()->back()->with('error', 'Knowledge transfer not in progress.');
            }

            $offboarding->update([
                'knowledge_transfer_status'      => 'completed',
                'knowledge_transfer_completed_at' => now(),
                'knowledge_transfer_notes'        => $request->notes ?? $offboarding->knowledge_transfer_notes,
            ]);

            DB::commit();

            return redirect()->route('offboarding.show', $id)->with('success', 'Knowledge transfer completed.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to complete knowledge transfer: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to complete knowledge transfer.');
        }
    }

    /**
     * Update Asset Clearance
     */
    public function updateAssetClearance($id, Request $request)
    {
        try {
            DB::beginTransaction();

            $request->validate([
                'asset_return_status'    => 'required|in:pending,partial,completed',
                'document_return_status' => 'required|in:pending,partial,completed',
                'clearance_status'       => 'required|in:pending,in_progress,completed',
                'clearance_remarks'      => 'nullable|string',
            ]);

            $offboarding = OffboardingRequest::findOrFail($id);

            $offboarding->update([
                'asset_return_status'    => $request->asset_return_status,
                'document_return_status' => $request->document_return_status,
                'clearance_status'       => $request->clearance_status,
                'feedback'               => $request->clearance_remarks ?? $offboarding->feedback,
            ]);

            DB::commit();

            return redirect()->route('offboarding.show', $id)->with('success', 'Clearance status updated successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update clearance: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to update clearance: ' . $e->getMessage());
        }
    }

    /**
     * Store Exit Interview
     */
    public function storeExitInterview(Request $request, $offboardingId)
    {
        try {
            DB::beginTransaction();

            $request->validate([
                'interviewer_id'           => 'required|exists:users,id',
                'interview_date'           => 'required|date',
                'work_environment_rating'  => 'nullable|integer|min:1|max:5',
                'management_rating'        => 'nullable|integer|min:1|max:5',
                'career_growth_rating'     => 'nullable|integer|min:1|max:5',
                'compensation_rating'      => 'nullable|integer|min:1|max:5',
                'work_life_balance_rating' => 'nullable|integer|min:1|max:5',
                'primary_reason'           => 'nullable|string',
                'what_would_improve'       => 'nullable|string',
                'would_recommend'          => 'nullable|boolean',
                'feedback_comments'        => 'nullable|string',
                'suggestions'              => 'nullable|string',
            ]);

            $offboarding = OffboardingRequest::findOrFail($offboardingId);

            if ($offboarding->exitInterview) {
                return redirect()->back()->with('error', 'Exit interview already recorded for this offboarding.');
            }

            ExitInterview::create([
                'tenant_id'                => $offboarding->tenant_id,
                'offboarding_request_id'   => $offboarding->id,
                'employee_id'              => $offboarding->employee_id,
                'interviewer_id'           => $request->interviewer_id,
                'interview_date'           => $request->interview_date,
                'work_environment_rating'  => $request->work_environment_rating,
                'management_rating'        => $request->management_rating,
                'career_growth_rating'     => $request->career_growth_rating,
                'compensation_rating'      => $request->compensation_rating,
                'work_life_balance_rating' => $request->work_life_balance_rating,
                'primary_reason'           => $request->primary_reason,
                'what_would_improve'       => $request->what_would_improve,
                'would_recommend'          => $request->would_recommend,
                'feedback_comments'        => $request->feedback_comments,
                'suggestions'              => $request->suggestions,
                'status'                   => 'completed',
                'created_by'               => Auth::id(),
            ]);

            $offboarding->update([
                'exit_interview_date'         => $request->interview_date,
                'exit_interview_conducted_by' => $request->interviewer_id,
                'exit_interview_notes'        => $request->feedback_comments,
                'exit_interview_status'       => 'scheduled',
            ]);

            DB::commit();

            return redirect()->route('offboarding.show', $offboardingId)->with('success', 'Exit interview saved successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to save exit interview: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to save exit interview.');
        }
    }

    /**
     * Update Exit Interview
     */
    public function updateExitInterview($id, $exitId, Request $request)
    {
        try {
            DB::beginTransaction();

            $exitInterview = ExitInterview::findOrFail($exitId);

            $exitInterview->update([
                'work_environment_rating'  => $request->work_environment_rating,
                'management_rating'        => $request->management_rating,
                'career_growth_rating'     => $request->career_growth_rating,
                'compensation_rating'      => $request->compensation_rating,
                'work_life_balance_rating' => $request->work_life_balance_rating,
                'primary_reason'           => $request->primary_reason,
                'what_would_improve'       => $request->what_would_improve,
                'would_recommend'          => $request->would_recommend,
                'feedback_comments'        => $request->feedback_comments,
                'suggestions'              => $request->suggestions,
                'status'                   => 'completed',
            ]);

            $offboarding = OffboardingRequest::findOrFail($id);
            $offboarding->update(['exit_interview_status' => 'completed']);

            DB::commit();

            return redirect()->route('offboarding.show', $id)->with('success', 'Exit interview completed successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update exit interview: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to update exit interview.');
        }
    }

    /**
     * Process Final Settlement
     */
    public function processFinalSettlement($id, Request $request)
    {
        try {
            DB::beginTransaction();

            $request->validate([
                'full_final_settlement' => 'required|numeric|min:0',
                'settlement_notes'      => 'nullable|string',
            ]);

            $offboarding = OffboardingRequest::findOrFail($id);

            if ($offboarding->asset_return_status !== 'completed') {
                return redirect()->back()->with('error', 'Asset clearance must be completed before final settlement.');
            }

            $offboarding->update([
                'final_settlement_status'       => 'processing',
                'full_final_settlement'         => $request->full_final_settlement,
                'final_settlement_notes'        => $request->settlement_notes,
                'final_settlement_processed_at' => now(),
                'final_settlement_processed_by' => Auth::id(),
            ]);

            DB::commit();

            return redirect()->route('offboarding.show', $id)->with('success', 'Final settlement processing started.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to process final settlement: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to process final settlement.');
        }
    }

    /**
     * Mark Settlement as Paid
     */
    public function markSettlementPaid($id, Request $request)
    {
        try {
            DB::beginTransaction();

            $request->validate([
                'settlement_paid_date' => 'required|date',
                'payment_reference'    => 'nullable|string',
            ]);

            $offboarding = OffboardingRequest::findOrFail($id);

            if ($offboarding->final_settlement_status !== 'processing') {
                return redirect()->back()->with('error', 'Final settlement not in processing state.');
            }

            $offboarding->update([
                'final_settlement_status' => 'paid',
                'settlement_paid_date'    => $request->settlement_paid_date,
                'final_settlement_notes'  => $request->payment_reference ?? $offboarding->final_settlement_notes,
            ]);

            DB::commit();

            return redirect()->route('offboarding.show', $id)->with('success', 'Final settlement marked as paid.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to mark settlement paid: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to mark settlement paid.');
        }
    }

    /**
     * Complete offboarding
     */
    public function complete($id, Request $request)
    {
        try {
            DB::beginTransaction();

            $offboarding = OffboardingRequest::findOrFail($id);

            if ($offboarding->knowledge_transfer_status !== 'completed') {
                return redirect()->back()->with('error', 'Knowledge transfer must be completed first.');
            }
            if ($offboarding->asset_return_status !== 'completed') {
                return redirect()->back()->with('error', 'Asset clearance must be completed first.');
            }
            if ($offboarding->final_settlement_status !== 'paid') {
                return redirect()->back()->with('error', 'Final settlement must be paid first.');
            }

            $offboarding->update([
                'status'                   => 'completed',
                'offboarding_completed_at' => now(),
                'completed_at'             => now(),
            ]);

            $offboarding->employee->update(['status' => '0']);

            DB::commit();

            return redirect()->route('offboarding.index')->with('success', 'Offboarding process completed successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to complete offboarding: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to complete offboarding.');
        }
    }

    /**
     * -----------------------------------------------------------------------
     * REJECT offboarding request
     *
     * This is the ADMIN/HR standalone reject button on the show page.
     * It handles rejection at whichever stage the request is currently at:
     *   - Stage 1  : pending_approval + manager_review pending  → reject manager review
     *   - Stage 2  : manager approved + hr_review pending       → reject HR review
     *   - Stage 3+ : already approved (post-HR approval)        → force-reject via DB UPDATE
     *
     * The DB trigger sets status='rejected' whenever a review status = 'rejected',
     * so we always update the relevant review column as well to keep everything
     * consistent.
     * -----------------------------------------------------------------------
     */
    public function reject($id, Request $request)
    {
        try {
            DB::beginTransaction();

            $request->validate([
                'rejection_reason' => 'required|string|min:3',
                'reject_stage'     => 'nullable|string', // optional hint from blade
            ]);

            $offboarding = OffboardingRequest::findOrFail($id);

            // Guard: can only reject non-terminal requests
            if (in_array($offboarding->status, ['completed', 'cancelled', 'rejected'])) {
                return redirect()->back()->with('error', 'This offboarding request cannot be rejected. Current status: ' . $offboarding->status);
            }

            $rejectionReason = $request->rejection_reason;
            $rejectedBy      = Auth::id();
            $now             = now();

            // ---- Determine current stage and update accordingly ----

            if ($offboarding->manager_review_status === 'pending') {
                // Stage 1: Still awaiting manager approval
                $offboarding->update([
                    'manager_review_status'   => 'rejected',
                    'manager_review_by'       => $rejectedBy,
                    'manager_review_at'       => $now,
                    'manager_review_comments' => $rejectionReason,
                    // status set by trigger, but set explicitly too for safety
                    'status'                  => 'rejected',
                ]);
            } elseif ($offboarding->manager_review_status === 'approved' && $offboarding->hr_review_status === 'pending') {
                // Stage 2: Manager approved but HR review pending
                $offboarding->update([
                    'hr_review_status'   => 'rejected',
                    'hr_review_by'       => $rejectedBy,
                    'hr_review_at'       => $now,
                    'hr_review_comments' => $rejectionReason,
                    // status set by trigger, but set explicitly too for safety
                    'status'             => 'rejected',
                ]);
            } else {
                // Stage 3+: Already fully approved - force rejection
                // We must also flip the HR review status so the trigger agrees
                $offboarding->update([
                    'hr_review_status'   => 'rejected',
                    'hr_review_by'       => $rejectedBy,
                    'hr_review_at'       => $now,
                    'hr_review_comments' => 'Force rejected after approval: ' . $rejectionReason,
                    'status'             => 'rejected',
                ]);
            }

            DB::commit();

            return redirect()->route('offboarding.show', $id)
                ->with('success', 'Offboarding request has been rejected successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to reject offboarding: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to reject offboarding request: ' . $e->getMessage());
        }
    }

    /**
     * Cancel offboarding request
     * NOTE: Cancellation is different from rejection.
     * Cancel = voluntarily withdrawn (by employee or admin).
     * Reject = denied by manager/HR.
     * The DB trigger does NOT handle 'cancelled', so we bypass it with a raw UPDATE.
     */
    public function cancel($id, Request $request)
    {
        try {
            DB::beginTransaction();

            $offboarding = OffboardingRequest::findOrFail($id);

            if (!in_array($offboarding->status, ['pending_approval', 'approved'])) {
                return redirect()->back()->with('error', 'Offboarding request cannot be cancelled at this stage.');
            }

            // Use a raw DB update to bypass the trigger for 'cancelled' status
            // (The trigger doesn't have a 'cancelled' branch, so setting status='cancelled'
            //  in Eloquent gets overwritten. Raw UPDATE skips the BEFORE UPDATE trigger logic
            //  only in MariaDB if we use DB::statement directly.)
            //
            // Better approach: update a non-trigger-watched column first, then status.
            // Actually the cleanest fix is to update the trigger (see SQL in comments above).
            // If you've updated the trigger, the Eloquent call below works fine.
            $offboarding->update([
                'status'   => 'cancelled',
                'feedback' => $request->cancellation_reason ?? 'Request cancelled',
            ]);

            if ($offboarding->employee) {
                $offboarding->employee->update(['status' => '1']);
            }

            DB::commit();

            return redirect()->route('offboarding.index')->with('success', 'Offboarding request cancelled.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to cancel offboarding: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to cancel offboarding request.');
        }
    }

    /**
     * Update remarks
     */
    public function updateRemarks($id, Request $request)
    {
        try {
            $offboarding = OffboardingRequest::findOrFail($id);

            $offboarding->update([
                'hr_remarks'      => $request->hr_remarks,
                'finance_remarks' => $request->finance_remarks,
                'it_remarks'      => $request->it_remarks,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Remarks updated successfully.',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update remarks: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update remarks.',
            ], 500);
        }
    }
}
