<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\ApprovalDelegation;
use App\Models\ApprovalWorkflow;
use App\Models\ApprovalWorkflowStep;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Tier 2 / T2-A — manage approval workflows + out-of-office delegations.
 */
class ApprovalWorkflowController extends Controller
{
    private const TYPES = ['regularization', 'overtime', 'leave', 'manual_attendance', 'offboarding', 'offboarding_termination'];

    public function index()
    {
        $tenantId = (int) Auth::user()->tenant_id;

        $workflows = ApprovalWorkflow::with('steps')
            ->where('tenant_id', $tenantId)
            ->orderBy('request_type')->get();

        $delegations = ApprovalDelegation::where('tenant_id', $tenantId)
            ->orderByDesc('is_active')->orderByDesc('ends_on')->get();

        $users = User::where('tenant_id', $tenantId)
            ->where('status', 1)
            ->orderBy('name')->get(['id', 'name', 'role']);

        return view('client.settings.approvals', [
            'workflows' => $workflows,
            'delegations' => $delegations,
            'users' => $users,
            'types' => self::TYPES,
        ]);
    }

    public function storeWorkflow(Request $request)
    {
        $tenantId = (int) Auth::user()->tenant_id;

        $data = $request->validate([
            'id' => ['nullable', 'integer'],
            'name' => ['required', 'string', 'max:120'],
            'request_type' => ['required', 'in:' . implode(',', self::TYPES)],
            'is_active' => ['nullable', 'boolean'],
            'steps' => ['required', 'array', 'min:1', 'max:6'],
            'steps.*.approver_type' => ['required', 'in:reporting_head,role,user,department_head'],
            'steps.*.approver_ref' => ['nullable', 'string', 'max:100'],
            'steps.*.quorum' => ['required', 'in:any,all'],
            'steps.*.sla_hours' => ['nullable', 'integer', 'min:1', 'max:2160'],
            'steps.*.on_breach' => ['required', 'in:notify,auto_approve,escalate'],
        ]);

        DB::transaction(function () use ($data, $tenantId, $request) {
            $workflow = ApprovalWorkflow::updateOrCreate(
                ['id' => $data['id'] ?? null, 'tenant_id' => $tenantId],
                [
                    'name' => $data['name'],
                    'request_type' => $data['request_type'],
                    'is_active' => (bool) $request->boolean('is_active'),
                    'created_by' => Auth::id(),
                ]
            );

            $workflow->steps()->delete();
            foreach (array_values($data['steps']) as $i => $step) {
                ApprovalWorkflowStep::create([
                    'workflow_id' => $workflow->id,
                    'level' => $i + 1,
                    'approver_type' => $step['approver_type'],
                    'approver_ref' => $step['approver_ref'] ?? null,
                    'quorum' => $step['quorum'],
                    'sla_hours' => $step['sla_hours'] ?? null,
                    'on_breach' => $step['on_breach'],
                ]);
            }
        });

        return back()->with('success', 'Approval workflow saved.');
    }

    public function destroyWorkflow($id)
    {
        $tenantId = (int) Auth::user()->tenant_id;
        $wf = ApprovalWorkflow::where('tenant_id', $tenantId)->findOrFail($id);
        $wf->steps()->delete();
        $wf->delete();

        return back()->with('success', 'Workflow removed.');
    }

    public function storeDelegation(Request $request)
    {
        $tenantId = (int) Auth::user()->tenant_id;

        $data = $request->validate([
            'delegator_id' => ['required', 'integer'],
            'delegate_id' => ['required', 'integer', 'different:delegator_id'],
            'request_types' => ['nullable', 'array'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
        ]);

        ApprovalDelegation::create([
            'tenant_id' => $tenantId,
            'delegator_id' => $data['delegator_id'],
            'delegate_id' => $data['delegate_id'],
            'request_types' => $data['request_types'] ?: null,
            'starts_on' => $data['starts_on'],
            'ends_on' => $data['ends_on'],
            'is_active' => true,
        ]);

        return back()->with('success', 'Delegation added.');
    }

    public function destroyDelegation($id)
    {
        $tenantId = (int) Auth::user()->tenant_id;
        ApprovalDelegation::where('tenant_id', $tenantId)->findOrFail($id)->delete();

        return back()->with('success', 'Delegation removed.');
    }
}
