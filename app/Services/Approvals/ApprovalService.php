<?php

namespace App\Services\Approvals;

use App\Events\AttendanceDomainEvent;
use App\Models\ApprovalAction;
use App\Models\ApprovalDelegation;
use App\Models\ApprovalRequest;
use App\Models\ApprovalWorkflow;
use App\Models\ApprovalWorkflowStep;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Tier 2 / T2-A — the multi-level approval engine.
 *
 * `open()` returns null when the tenant has no active workflow for the type, so
 * a caller can fall back to its existing single-approver code unchanged.
 */
class ApprovalService
{
    /** request_type => outcome handler class */
    private const HANDLERS = [
        'regularization' => RegularizationApprovalHandler::class,
        'overtime' => OvertimeApprovalHandler::class,
        'leave' => LeaveApprovalHandler::class,
        // Payroll rebuild — Phase 5.
        'payroll_revision' => PayrollRevisionApprovalHandler::class,
        'payroll_bonus' => PayrollBonusApprovalHandler::class,
        'payroll_run' => PayrollRunApprovalHandler::class,
        // Offboarding — same handler for both; only the seeded workflow
        // (2-level Manager->HR vs 1-level HR-only) differs. See
        // OffboardingService::ensureDefaultWorkflow().
        'offboarding' => OffboardingApprovalHandler::class,
        'shift_request' => ShiftRequestApprovalHandler::class,
        'offboarding_termination' => OffboardingApprovalHandler::class,
    ];

    /**
     * Start a workflow for a subject. Null => no workflow configured.
     */
    public function open(string $type, Model $subject, User $requester): ?ApprovalRequest
    {
        $tenantId = (int) ($subject->tenant_id ?: $requester->tenant_id);
        $subjectUser = $this->subjectUser($subject);

        $workflow = ApprovalWorkflow::where('tenant_id', $tenantId)
            ->where('request_type', $type)
            ->where('is_active', true)
            ->with('steps')
            ->get()
            ->first(fn (ApprovalWorkflow $w) => $w->steps->isNotEmpty() && $w->matchesUser($subjectUser));

        if (! $workflow) {
            return null;
        }

        $existing = ApprovalRequest::where('subject_type', $subject::class)
            ->where('subject_id', $subject->getKey())
            ->where('status', 'pending')
            ->first();
        if ($existing) {
            return $existing;
        }

        $request = ApprovalRequest::create([
            'tenant_id' => $tenantId,
            'request_type' => $type,
            'subject_type' => $subject::class,
            'subject_id' => $subject->getKey(),
            'workflow_id' => $workflow->id,
            'current_level' => 1,
            'status' => 'pending',
            'requested_by' => $requester->id,
        ]);

        $this->notifyLevel($request);

        return $request;
    }

    /**
     * Convenience for legacy controllers: route a decision through a workflow if
     * the tenant has one, else return null so the caller keeps its old path.
     */
    public function decide(string $type, Model $subject, User $actor, string $action, ?string $remarks = null): ?ApprovalRequest
    {
        $request = ApprovalRequest::where('subject_type', $subject::class)
            ->where('subject_id', $subject->getKey())
            ->where('status', 'pending')
            ->first();

        if (! $request) {
            $requester = $this->subjectUser($subject) ?? $actor;
            $request = $this->open($type, $subject, $requester);
        }

        if (! $request) {
            return null; // no workflow configured — caller does the legacy approval
        }

        return $this->act($request, $actor, $action, $remarks);
    }

    /**
     * Record an approve / reject on the current level. Advances or finalises.
     */
    public function act(ApprovalRequest $request, User $actor, string $action, ?string $remarks = null): ApprovalRequest
    {
        if (! in_array($action, ['approved', 'rejected'], true)) {
            throw new RuntimeException("Unsupported action: {$action}");
        }
        if ($request->status !== 'pending') {
            throw new RuntimeException("Request is already {$request->status}.");
        }
        if (! $this->currentApprovers($request)->contains($actor->id)) {
            throw new RuntimeException('You are not an approver for the current level.');
        }

        return DB::transaction(function () use ($request, $actor, $action, $remarks) {
            ApprovalAction::create([
                'approval_request_id' => $request->id,
                'level' => $request->current_level,
                'actor_id' => $actor->id,
                'action' => $action,
                'remarks' => $remarks,
                'acted_at' => now(),
            ]);

            if ($action === 'rejected') {
                return $this->finalise($request, 'rejected', $actor, $remarks);
            }

            $step = $this->step($request);
            if ($step && $step->quorum === 'all') {
                $needed = $this->resolveApprovers($step, $this->subjectUser($this->subjectOf($request)));
                $approvedIds = ApprovalAction::where('approval_request_id', $request->id)
                    ->where('level', $request->current_level)
                    ->where('action', 'approved')
                    ->pluck('actor_id')->unique();
                if ($needed->diff($approvedIds)->isNotEmpty()) {
                    return $request; // still waiting on other approvers at this level
                }
            }

            $lastLevel = (int) $request->workflow->steps->max('level');
            if ($request->current_level >= $lastLevel) {
                return $this->finalise($request, 'approved', $actor, $remarks);
            }

            $request->update(['current_level' => $request->current_level + 1]);
            $this->notifyLevel($request->refresh());

            return $request;
        });
    }

    /**
     * SLA sweep — call from a scheduled command.
     */
    public function tick(): int
    {
        $acted = 0;
        $pending = ApprovalRequest::where('status', 'pending')->with('workflow.steps')->get();

        foreach ($pending as $request) {
            $step = $this->step($request);
            if (! $step || ! $step->sla_hours) {
                continue;
            }

            $since = ApprovalAction::where('approval_request_id', $request->id)
                ->where('level', $request->current_level)->max('acted_at')
                ?? $request->updated_at;

            $hoursWaiting = \Illuminate\Support\Carbon::parse($since)->diffInHours(now(), true);
            if ($hoursWaiting < $step->sla_hours) {
                continue;
            }

            $system = User::find($request->requested_by);
            match ($step->on_breach) {
                'auto_approve' => $this->breachAutoApprove($request),
                'escalate' => $this->breachEscalate($request),
                default => $this->notifyLevel($request), // 'notify'
            };
            $acted++;
        }

        return $acted;
    }

    // ------------------------------------------------------------------

    public function currentApprovers(ApprovalRequest $request): Collection
    {
        $step = $this->step($request);
        if (! $step) {
            return collect();
        }

        $direct = $this->resolveApprovers($step, $this->subjectUser($this->subjectOf($request)));

        $delegates = ApprovalDelegation::where('tenant_id', $request->tenant_id)
            ->where('is_active', true)
            ->whereIn('delegator_id', $direct)
            ->get()
            ->filter(fn ($d) => $d->coversToday($request->request_type))
            ->pluck('delegate_id');

        return $direct->merge($delegates)->unique()->values();
    }

    public function resolveApprovers(ApprovalWorkflowStep $step, ?User $subjectUser): Collection
    {
        return match ($step->approver_type) {
            'user' => collect([(int) $step->approver_ref]),
            'role' => User::where('tenant_id', $subjectUser?->tenant_id)
                ->where('role', $step->approver_ref)->pluck('id'),
            'department_head' => $this->departmentHead($subjectUser),
            default => $this->reportingHead($subjectUser), // 'reporting_head'
        };
    }

    private function finalise(ApprovalRequest $request, string $status, User $actor, ?string $remarks): ApprovalRequest
    {
        $request->update(['status' => $status, 'resolved_at' => now()]);

        $handlerClass = self::HANDLERS[$request->request_type] ?? null;
        if ($handlerClass) {
            /** @var ApprovalOutcomeHandler $handler */
            $handler = app($handlerClass);
            in_array($status, ['approved', 'auto_approved'], true)
                ? $handler->approved($request, $actor)
                : $handler->rejected($request, $actor, $remarks);
        }

        event(new AttendanceDomainEvent(
            "{$request->request_type}.decided",
            (int) $request->tenant_id,
            ['approval_request_id' => $request->id, 'subject_id' => $request->subject_id, 'status' => $status]
        ));

        $this->notifyRequester($request, $status);

        return $request;
    }

    private function breachAutoApprove(ApprovalRequest $request): void
    {
        ApprovalAction::create([
            'approval_request_id' => $request->id,
            'level' => $request->current_level,
            'actor_id' => null,
            'action' => 'auto_approved',
            'remarks' => 'SLA breach auto-approval',
            'acted_at' => now(),
        ]);
        $actor = User::find($request->requested_by) ?? new User(['id' => 0, 'role' => 'system']);
        $lastLevel = (int) $request->workflow->steps->max('level');
        if ($request->current_level >= $lastLevel) {
            $this->finalise($request, 'auto_approved', $actor, 'SLA breach');
        } else {
            $request->update(['current_level' => $request->current_level + 1]);
            $this->notifyLevel($request->refresh());
        }
    }

    private function breachEscalate(ApprovalRequest $request): void
    {
        ApprovalAction::create([
            'approval_request_id' => $request->id,
            'level' => $request->current_level,
            'actor_id' => null,
            'action' => 'escalated',
            'remarks' => 'SLA breach escalation',
            'acted_at' => now(),
        ]);
        $lastLevel = (int) $request->workflow->steps->max('level');
        if ($request->current_level < $lastLevel) {
            $request->update(['current_level' => $request->current_level + 1]);
        }
        $this->notifyLevel($request->refresh());
    }

    private function step(ApprovalRequest $request): ?ApprovalWorkflowStep
    {
        return $request->workflow?->steps->firstWhere('level', $request->current_level);
    }

    private function subjectOf(ApprovalRequest $request): Model
    {
        return $request->subject_type::withoutGlobalScopes()->findOrFail($request->subject_id);
    }

    private function subjectUser(Model $subject): ?User
    {
        $userId = $subject->user_id ?? null;

        return $userId ? User::with('jobDetails')->find($userId) : null;
    }

    private function reportingHead(?User $user): Collection
    {
        if (! $user) {
            return collect();
        }

        return $user->reportingHeads()->pluck('users.id');
    }

    private function departmentHead(?User $user): Collection
    {
        if (! $user || ! $user->jobDetails?->department) {
            return collect();
        }
        // Heuristic: an HR/admin in the same tenant. Tenants that need a true
        // department head map it via approver_type 'user'.
        return User::where('tenant_id', $user->tenant_id)
            ->whereIn('role', ['hr', 'admin'])->pluck('id');
    }

    private function notifyLevel(ApprovalRequest $request): void
    {
        try {
            $approverIds = $this->currentApprovers($request)->all();
            if (empty($approverIds)) {
                return;
            }

            $label = str_replace('_', ' ', $request->request_type);
            $approvers = User::whereIn('id', $approverIds)->get();
            \Illuminate\Support\Facades\Notification::send($approvers, new \App\Notifications\CustomNotification(
                'Approval needed',
                "A {$label} request needs your approval (level {$request->current_level}).",
                ['type' => 'approval_pending', 'approval_request_id' => $request->id, 'request_type' => $request->request_type],
            ));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('notifyLevel failed: ' . $e->getMessage());
        }
    }

    /** Request types whose outcome handler already sends its own, specific employee notification. */
    private const HANDLER_NOTIFIES = ['leave', 'overtime', 'regularization', 'shift_request'];

    private function notifyRequester(ApprovalRequest $request, string $status): void
    {
        try {
            // Avoid a second, generic "Request approved" next to e.g. "✅ Leave Approved".
            if (in_array($request->request_type, self::HANDLER_NOTIFIES, true)) {
                return;
            }
            $requester = User::find($request->requested_by);
            if (! $requester) {
                return;
            }
            $label = ucfirst(str_replace('_', ' ', $request->request_type));
            $approved = in_array($status, ['approved', 'auto_approved'], true);
            $requester->notify(new \App\Notifications\CustomNotification(
                ($approved ? '✅ ' : '❌ ') . $label . ' Request ' . ($approved ? 'Approved' : 'Rejected'),
                "Your {$label} request has been " . ($approved ? 'approved' : 'rejected')
                    . ($status === 'auto_approved' ? ' automatically (approval time limit passed).' : '.'),
                ['type' => 'approval_result', 'approval_request_id' => $request->id, 'status' => $status],
            ));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('notifyRequester failed: ' . $e->getMessage());
        }
    }
}
