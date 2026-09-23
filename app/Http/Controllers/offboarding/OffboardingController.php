<?php

namespace App\Http\Controllers\offboarding;

use App\Http\Controllers\Controller;
use App\Http\Requests\Offboarding\AddSettlementLineRequest;
use App\Http\Requests\Offboarding\CancelOffboardingRequest;
use App\Http\Requests\Offboarding\DecideNoticeOverrideRequest;
use App\Http\Requests\Offboarding\DecideOffboardingRequest;
use App\Http\Requests\Offboarding\MarkSettlementPaidRequest;
use App\Http\Requests\Offboarding\OverrideSettlementLineRequest;
use App\Http\Requests\Offboarding\ReopenOffboardingStageRequest;
use App\Http\Requests\Offboarding\RequestNoticeOverrideRequest;
use App\Http\Requests\Offboarding\SkipExitInterviewRequest;
use App\Http\Requests\Offboarding\StoreExitInterviewRequest;
use App\Http\Requests\Offboarding\StoreOffboardingRequest;
use App\Http\Requests\Offboarding\UpdateClearanceTaskRequest;
use App\Models\OffboardingClearanceTask;
use App\Models\OffboardingNoticeOverride;
use App\Models\OffboardingRequest;
use App\Models\OffboardingSettlementItem;
use App\Models\User;
use App\Services\Offboarding\OffboardingService;
use App\Services\Offboarding\OffboardingSettlementService;
use App\Services\RbacService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class OffboardingController extends Controller
{
    public function __construct(
        protected OffboardingService $offboarding,
        protected OffboardingSettlementService $settlement,
        protected RbacService $rbac,
    ) {
    }

    /* ============================================================
     |  LISTS
     ============================================================ */

    public function adminIndex(Request $request)
    {
        try {
            $query = OffboardingRequest::with([
                'employee', 'createdBy', 'approvedBy', 'managerReviewBy', 'hrReviewBy',
                'employee.jobDetails.Department', 'employee.jobDetails.Designation',
            ]);

            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }
            if ($request->filled('stage')) {
                $query->where('current_stage', $request->stage);
            }
            if ($request->filled('employee_id')) {
                $query->where('employee_id', $request->employee_id);
            }
            if ($request->filled('search')) {
                $search = $request->search;
                $query->whereHas('employee', function ($q) use ($search) {
                    $q->where('name', 'LIKE', "%{$search}%")
                        ->orWhere('employee_id', 'LIKE', "%{$search}%")
                        ->orWhere('email', 'LIKE', "%{$search}%");
                });
            }

            $offboardings = $query->orderByDesc('created_at')->paginate(15)->withQueryString();

            $stats = [
                'total' => OffboardingRequest::count(),
                'pending_approval' => OffboardingRequest::where('status', 'pending_approval')->count(),
                'in_progress' => OffboardingRequest::where('status', 'approved')->count(),
                'completed' => OffboardingRequest::where('status', 'completed')->count(),
                'rejected' => OffboardingRequest::where('status', 'rejected')->count(),
                'cancelled' => OffboardingRequest::where('status', 'cancelled')->count(),
            ];

            $employees = User::where('status', '1')->orderBy('name')->get();

            // For the "New Offboarding Request" side drawer on this page —
            // adminIndex() is always admin/hr (route-gated), same reason-rule
            // filtering as create().
            $role = Auth::user()->role ?? 'employee';
            $allowedReasons = collect(config('offboarding.reason_rules'))
                ->filter(fn ($rules) => in_array($role, $rules['creatable_by'], true))
                ->keys();
            $reasons = collect(OffboardingRequest::$reasons)->only($allowedReasons);
            $noticeDays = $this->offboarding->requiredNoticeDays((int) Auth::user()->tenant_id);
            $reasonRules = config('offboarding.reason_rules');

            return view('client.offboarding.index_admin', compact('offboardings', 'stats', 'employees', 'reasons', 'noticeDays', 'reasonRules'));
        } catch (\Exception $e) {
            Log::error('Failed to fetch offboarding requests: ' . $e->getMessage());
            return back()->with('error', 'Failed to load offboarding requests.');
        }
    }

    public function managerIndex(Request $request)
    {
        try {
            $teamMemberIds = User::managedBy(Auth::id())->pluck('id');

            $query = OffboardingRequest::with(['employee', 'createdBy'])
                ->whereIn('employee_id', $teamMemberIds);

            if ($request->filled('employee_id')) {
                $query->where('employee_id', $request->employee_id);
            }
            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }
            if ($request->filled('search')) {
                $search = $request->search;
                $query->whereHas('employee', function ($q) use ($search) {
                    $q->where('name', 'LIKE', "%{$search}%")
                        ->orWhere('employee_id', 'LIKE', "%{$search}%");
                });
            }

            $offboardings = $query->orderByDesc('created_at')->paginate(15)->withQueryString();

            $stats = [
                'total' => OffboardingRequest::whereIn('employee_id', $teamMemberIds)->count(),
                'pending_approval' => OffboardingRequest::whereIn('employee_id', $teamMemberIds)->where('status', 'pending_approval')->count(),
                'in_progress' => OffboardingRequest::whereIn('employee_id', $teamMemberIds)->where('status', 'approved')->count(),
                'rejected' => OffboardingRequest::whereIn('employee_id', $teamMemberIds)->where('status', 'rejected')->count(),
            ];

            $employees = User::whereIn('id', $teamMemberIds)->get();

            return view('client.offboarding.index_manager', compact('offboardings', 'stats', 'employees'));
        } catch (\Exception $e) {
            Log::error('Failed to fetch team offboarding requests: ' . $e->getMessage());
            return back()->with('error', 'Failed to load team offboarding requests.');
        }
    }

    public function employeeIndex(Request $request)
    {
        try {
            $activeRequest = OffboardingRequest::where('employee_id', Auth::id())
                ->whereIn('status', ['pending_approval', 'approved', 'completed'])
                ->latest('created_at')
                ->first();

            return view('client.offboarding.index_employee', ['activeRequest' => $activeRequest]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch employee offboarding: ' . $e->getMessage());
            return back()->with('error', 'Failed to load your offboarding request.');
        }
    }

    /* ============================================================
     |  CREATE / STORE
     ============================================================ */

    public function create(Request $request)
    {
        $role = Auth::user()->role ?? 'employee';
        $selectedEmployee = null;

        if (in_array($role, ['admin', 'hr'], true)) {
            $employees = User::where('status', '1')->orderBy('name')->get();
            if ($request->filled('employee_id')) {
                $selectedEmployee = User::find($request->employee_id);
            }
        } else {
            $employees = User::where('id', Auth::id())->get();
            $selectedEmployee = Auth::user();
        }

        $allowedReasons = collect(config('offboarding.reason_rules'))
            ->filter(fn ($rules) => in_array($role, $rules['creatable_by'], true))
            ->keys();
        $reasons = collect(OffboardingRequest::$reasons)->only($allowedReasons);

        $noticeDays = $this->offboarding->requiredNoticeDays((int) Auth::user()->tenant_id);
        $reasonRules = config('offboarding.reason_rules');

        return view('client.offboarding.create', compact('employees', 'reasons', 'selectedEmployee', 'noticeDays', 'reasonRules'));
    }

    public function store(StoreOffboardingRequest $request)
    {
        try {
            $offboarding = $this->offboarding->submit($request->validated(), Auth::user());

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Offboarding request submitted successfully.',
                    'redirect' => route('offboarding.show', $offboarding->id),
                ]);
            }

            return redirect()->route('offboarding.show', $offboarding->id)
                ->with('success', 'Offboarding request submitted successfully.');
        } catch (\RuntimeException $e) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
            }

            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    /* ============================================================
     |  SHOW
     ============================================================ */

    public function show($id)
    {
        $offboarding = OffboardingRequest::with([
            'employee.jobDetails.Department', 'employee.jobDetails.Designation',
            'createdBy', 'approvedBy', 'rejectedBy', 'cancelledBy', 'exitInterviewConductedBy',
            'exitInterview', 'clearanceTasks.completedBy', 'clearanceTasks.assetAssignment.asset',
            'settlementItems', 'noticeOverrides.requestedBy', 'noticeOverrides.approvedBy',
            'approvalRequest.workflow.steps', 'approvalRequest.actions.actor',
        ])->findOrFail($id);

        $scope = $this->rbac->scopeFor(Auth::user(), 'offboarding', 'view');
        if ($scope === 'own' && $offboarding->employee_id !== Auth::id()) {
            abort(403);
        }
        if ($scope === 'team' && ! User::managedBy(Auth::id())->pluck('id')->push(Auth::id())->contains($offboarding->employee_id)) {
            abort(403);
        }

        $canEdit = $this->rbac->can(Auth::user(), 'offboarding', 'edit');
        $canApprove = $this->rbac->can(Auth::user(), 'offboarding', 'approve');

        $isCurrentApprover = false;
        if ($canApprove && $offboarding->status === OffboardingRequest::STATUS_PENDING_APPROVAL && $offboarding->approvalRequest) {
            $isCurrentApprover = app(\App\Services\Approvals\ApprovalService::class)
                ->currentApprovers($offboarding->approvalRequest)
                ->contains(Auth::id());
        }

        return view('client.offboarding.show', compact('offboarding', 'canEdit', 'canApprove', 'isCurrentApprover'));
    }

    /* ============================================================
     |  APPROVAL DECISION
     ============================================================ */

    public function decide($id, DecideOffboardingRequest $request)
    {
        $offboarding = OffboardingRequest::findOrFail($id);

        try {
            $this->offboarding->decide($offboarding, Auth::user(), $request->action, $request->remarks);
            $message = $request->action === 'approved' ? 'Decision recorded — request approved at this level.' : 'Request rejected.';

            return back()->with('success', $message);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function cancel($id, CancelOffboardingRequest $request)
    {
        $offboarding = OffboardingRequest::findOrFail($id);

        try {
            $this->offboarding->cancel($offboarding, Auth::user(), $request->reason);

            return back()->with('success', 'Offboarding request cancelled.');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /* ============================================================
     |  KNOWLEDGE TRANSFER
     ============================================================ */

    public function startKnowledgeTransfer($id)
    {
        $offboarding = OffboardingRequest::findOrFail($id);

        try {
            $this->offboarding->startKnowledgeTransfer($offboarding, Auth::user());

            return back()->with('success', 'Knowledge transfer started.');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function completeKnowledgeTransfer($id, Request $request)
    {
        $offboarding = OffboardingRequest::findOrFail($id);

        try {
            $this->offboarding->completeKnowledgeTransfer($offboarding, Auth::user(), $request->input('notes'));

            return back()->with('success', 'Knowledge transfer completed — clearance checklist generated.');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /* ============================================================
     |  CLEARANCE
     ============================================================ */

    public function updateClearanceTask($id, $taskId, UpdateClearanceTaskRequest $request)
    {
        $task = OffboardingClearanceTask::where('offboarding_request_id', $id)->findOrFail($taskId);

        $this->offboarding->completeClearanceTask($task, Auth::user(), $request->status, $request->remarks);

        return back()->with('success', 'Clearance item updated.');
    }

    public function reopenStage($id, ReopenOffboardingStageRequest $request)
    {
        $offboarding = OffboardingRequest::findOrFail($id);

        try {
            $this->offboarding->reopenStage($offboarding, Auth::user(), $request->stage, $request->reason);

            return back()->with('success', 'Stage reopened.');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /* ============================================================
     |  EXIT INTERVIEW
     ============================================================ */

    public function recordExitInterview($id, StoreExitInterviewRequest $request)
    {
        $offboarding = OffboardingRequest::findOrFail($id);

        try {
            $this->offboarding->recordExitInterview($offboarding, Auth::user(), $request->validated());

            return back()->with('success', 'Exit interview recorded — final settlement worksheet generated.');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function skipExitInterview($id, SkipExitInterviewRequest $request)
    {
        $offboarding = OffboardingRequest::findOrFail($id);

        try {
            $this->offboarding->skipExitInterview($offboarding, Auth::user(), $request->reason);

            return back()->with('success', 'Exit interview skipped — final settlement worksheet generated.');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /* ============================================================
     |  NOTICE PERIOD OVERRIDES
     ============================================================ */

    public function requestNoticeOverride($id, RequestNoticeOverrideRequest $request)
    {
        $offboarding = OffboardingRequest::findOrFail($id);

        $this->offboarding->requestNoticeOverride(
            $offboarding, Auth::user(), $request->type, $request->reason,
            \Carbon\Carbon::parse($request->requested_last_working_date)
        );

        return back()->with('success', 'Notice period change requested.');
    }

    public function decideNoticeOverride($id, $overrideId, DecideNoticeOverrideRequest $request)
    {
        $override = OffboardingNoticeOverride::where('offboarding_request_id', $id)->findOrFail($overrideId);

        $this->offboarding->decideNoticeOverride($override, Auth::user(), $request->boolean('approve'), $request->decision_notes);

        return back()->with('success', 'Notice period override decision recorded.');
    }

    /* ============================================================
     |  FINAL SETTLEMENT
     ============================================================ */

    public function addSettlementLine($id, AddSettlementLineRequest $request)
    {
        $offboarding = OffboardingRequest::findOrFail($id);

        $this->settlement->addManualLine(
            $offboarding, Auth::user(), $request->line_type, $request->boolean('is_addition'),
            $request->label, (float) $request->amount, $request->notes
        );

        return back()->with('success', 'Settlement line added.');
    }

    public function overrideSettlementLine($id, $itemId, OverrideSettlementLineRequest $request)
    {
        $item = OffboardingSettlementItem::where('offboarding_request_id', $id)->findOrFail($itemId);

        $this->settlement->overrideLine($item, Auth::user(), $request->amount !== null ? (float) $request->amount : null, $request->notes);

        return back()->with('success', 'Settlement line updated.');
    }

    public function finalizeSettlement($id)
    {
        $offboarding = OffboardingRequest::findOrFail($id);
        $this->settlement->finalize($offboarding, Auth::user());

        return back()->with('success', 'Final settlement finalized. Ready to mark as paid.');
    }

    public function markSettlementPaid($id, MarkSettlementPaidRequest $request)
    {
        $offboarding = OffboardingRequest::findOrFail($id);
        $this->settlement->markPaid($offboarding, Auth::user(), $request->payment_reference);
        $this->offboarding->maybeAdvanceStage($offboarding->fresh());

        return back()->with('success', 'Final settlement marked as paid.');
    }

    /* ============================================================
     |  COMPLETE
     ============================================================ */

    public function complete($id)
    {
        $offboarding = OffboardingRequest::findOrFail($id);

        try {
            $this->offboarding->complete($offboarding, Auth::user());

            return back()->with('success', 'Offboarding completed. The employee account has been deactivated.');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /* ============================================================
     |  REMARKS
     ============================================================ */

    public function updateRemarks($id, Request $request)
    {
        $offboarding = OffboardingRequest::findOrFail($id);

        $data = $request->validate([
            'hr_remarks' => 'nullable|string|max:2000',
            'finance_remarks' => 'nullable|string|max:2000',
            'it_remarks' => 'nullable|string|max:2000',
        ]);

        $offboarding->update($data);

        if ($request->expectsJson()) {
            return response()->json(['success' => true]);
        }

        return back()->with('success', 'Remarks saved.');
    }
}
