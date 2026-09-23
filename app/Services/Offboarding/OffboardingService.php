<?php

namespace App\Services\Offboarding;

use App\Models\ApprovalRequest;
use App\Models\ApprovalWorkflow;
use App\Models\ApprovalWorkflowStep;
use App\Models\AssetAssignment;
use App\Models\ExitInterview;
use App\Models\OffboardingClearanceTask;
use App\Models\OffboardingClearanceTemplate;
use App\Models\OffboardingNoticeOverride;
use App\Models\OffboardingRequest;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserJobDetail;
use App\Services\Approvals\ApprovalService;
use App\Services\Asset\AssetLifecycleService;
use App\Services\AuditLogger;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Single choke point for the Offboarding lifecycle — the app-level state
 * machine that replaces the dropped `update_offboarding_status` MySQL
 * trigger. `status`/`current_stage` are written only here (and by
 * OffboardingApprovalHandler for the approval-decision transitions).
 */
class OffboardingService
{
    public function __construct(
        protected ApprovalService $approvals,
        protected AuditLogger $audit,
        protected OffboardingNotificationService $notifications,
    ) {
    }

    /* ============================================================
     |  NOTICE PERIOD
     ============================================================ */

    public function requiredNoticeDays(int $tenantId): int
    {
        $tenant = Tenant::find($tenantId);

        return (int) ($tenant->notice_period ?? config('offboarding.default_notice_period_days'));
    }

    /* ============================================================
     |  SUBMIT / APPROVAL
     ============================================================ */

    public function submit(array $data, User $actor): OffboardingRequest
    {
        $reason = $data['reason'];
        $rules = config("offboarding.reason_rules.{$reason}");
        if (! $rules) {
            throw new RuntimeException('Unknown offboarding reason.');
        }
        if (! in_array($actor->role, $rules['creatable_by'], true)) {
            throw new RuntimeException('You are not allowed to create an offboarding request for this reason.');
        }

        $employeeId = in_array($actor->role, ['admin', 'hr'], true) && ! empty($data['employee_id'])
            ? (int) $data['employee_id']
            : $actor->id;

        $employee = User::withoutGlobalScopes()->findOrFail($employeeId);

        if ((int) $employee->status === 0) {
            throw new RuntimeException('This employee is already inactive — offboarding may already be complete.');
        }

        $hasActive = OffboardingRequest::where('employee_id', $employeeId)
            ->whereIn('status', [OffboardingRequest::STATUS_PENDING_APPROVAL, OffboardingRequest::STATUS_APPROVED])
            ->exists();
        if ($hasActive) {
            throw new RuntimeException('This employee already has an active offboarding request.');
        }

        $tenantId = (int) $actor->tenant_id;
        $lastWorkingDate = Carbon::parse($data['last_working_date']);
        $noticeDays = $rules['requires_notice'] ? $this->requiredNoticeDays($tenantId) : 0;

        if ($rules['requires_notice']) {
            $minDate = Carbon::today()->addDays($noticeDays);
            if ($lastWorkingDate->lessThan($minDate)) {
                throw new RuntimeException("Last working date must be at least {$noticeDays} day(s) from today ({$minDate->toDateString()}).");
            }
        }

        return DB::transaction(function () use ($data, $actor, $employeeId, $tenantId, $reason, $lastWorkingDate, $noticeDays) {
            $request = OffboardingRequest::create([
                'tenant_id' => $tenantId,
                'employee_id' => $employeeId,
                'request_date' => now()->toDateString(),
                'last_working_date' => $lastWorkingDate->toDateString(),
                'original_last_working_date' => $lastWorkingDate->toDateString(),
                'notice_period_days_required' => $noticeDays,
                'resignation_date' => $data['resignation_date'] ?? now()->toDateString(),
                'reason' => $reason,
                'reason_detail' => $data['reason_detail'] ?? null,
                'feedback' => $data['feedback'] ?? null,
                'eligible_for_rehire' => $data['eligible_for_rehire'] ?? true,
                'status' => OffboardingRequest::STATUS_PENDING_APPROVAL,
                'current_stage' => OffboardingRequest::STAGE_PENDING_APPROVAL,
                'created_by' => $actor->id,
            ]);

            $this->audit->record('tenant_user', $actor->id, $tenantId, 'offboarding.created', 'OffboardingRequest', $request->id, [], $request->toArray());

            $this->openApproval($request);

            try {
                $this->notifications->notifySubmitted($request->fresh());
            } catch (\Throwable $e) {
                // never block submission on a notification failure
            }

            return $request;
        });
    }

    public function openApproval(OffboardingRequest $request): void
    {
        $type = $request->approvalRequestType();
        $this->ensureDefaultWorkflow((int) $request->tenant_id, $type);
        $this->approvals->open($type, $request, $request->employee);
    }

    /**
     * Idempotent self-heal: this app has no reliable tenant-provisioning hook
     * (provisioning lives in the separate hrm-superadmin app) to seed an
     * approval workflow at tenant-creation time, so guarantee one exists the
     * moment it's first needed instead. Safe to call repeatedly.
     */
    public function ensureDefaultWorkflow(int $tenantId, string $type): void
    {
        $exists = ApprovalWorkflow::where('tenant_id', $tenantId)
            ->where('request_type', $type)
            ->where('is_active', true)
            ->exists();
        if ($exists) {
            return;
        }

        $workflow = ApprovalWorkflow::create([
            'tenant_id' => $tenantId,
            'request_type' => $type,
            'name' => $type === 'offboarding_termination' ? 'Termination Approval (default)' : 'Offboarding Approval (default)',
            'is_active' => true,
        ]);

        $hrRef = $this->pickHrApproverRole($tenantId);

        if ($type === 'offboarding_termination') {
            // Termination: HR-only, single level — no manager step, matching
            // config('offboarding.reason_rules.termination.requires_notice') = false.
            ApprovalWorkflowStep::create([
                'workflow_id' => $workflow->id, 'level' => 1,
                'approver_type' => 'role', 'approver_ref' => $hrRef, 'quorum' => 'any', 'on_breach' => 'notify',
            ]);
        } else {
            ApprovalWorkflowStep::create([
                'workflow_id' => $workflow->id, 'level' => 1,
                'approver_type' => 'reporting_head', 'approver_ref' => null, 'quorum' => 'any', 'on_breach' => 'notify',
            ]);
            ApprovalWorkflowStep::create([
                'workflow_id' => $workflow->id, 'level' => 2,
                'approver_type' => 'role', 'approver_ref' => $hrRef, 'quorum' => 'any', 'on_breach' => 'notify',
            ]);
        }
    }

    /**
     * A tenant might have no 'hr'-role user yet (small company where the
     * owner is 'admin' only) — fall back to 'admin' so the HR-level step
     * always has at least one live approver instead of getting permanently
     * stuck. Tenants can reconfigure this later via Settings > Approvals.
     */
    private function pickHrApproverRole(int $tenantId): string
    {
        $hasHr = User::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)->where('role', 'hr')->where('status', 1)->exists();

        return $hasHr ? 'hr' : 'admin';
    }

    public function decide(OffboardingRequest $request, User $actor, string $action, ?string $remarks = null): OffboardingRequest
    {
        $approvalRequest = ApprovalRequest::where('subject_type', OffboardingRequest::class)
            ->where('subject_id', $request->id)
            ->where('status', 'pending')
            ->first();

        if (! $approvalRequest) {
            throw new RuntimeException('No pending approval found for this request.');
        }

        $level = $approvalRequest->current_level;
        $type = $request->approvalRequestType();

        $this->approvals->act($approvalRequest, $actor, $action, $remarks);

        $this->mirrorReviewColumns($request, $type, $level, $action, $actor, $remarks);

        return $request->fresh();
    }

    /**
     * Keeps manager_review_status/hr_review_status (+ their *_by/*_at/*_comments)
     * as denormalized mirrors of the real ApprovalService decision, since the
     * mobile API's employee-facing timeline (Api\Offboarding\offboardingController)
     * still reads these two columns directly and isn't itself rebuilt onto the
     * generic engine. ApprovalOutcomeHandler::approved()/rejected() only fires
     * on the FINAL outcome (last level approved, or any level rejected) — this
     * covers the intermediate "level 1 (manager) approved, still waiting on
     * level 2 (HR)" case the handler alone can't see.
     */
    private function mirrorReviewColumns(OffboardingRequest $request, string $type, int $level, string $action, User $actor, ?string $remarks): void
    {
        $isManagerLevel = $type === 'offboarding' && $level === 1;
        $isHrLevel = ($type === 'offboarding' && $level === 2) || ($type === 'offboarding_termination' && $level === 1);

        $updates = [];
        if ($isManagerLevel) {
            $updates['manager_review_status'] = $action;
            $updates['manager_review_by'] = $actor->id;
            $updates['manager_review_at'] = now();
            $updates['manager_review_comments'] = $remarks;
        } elseif ($isHrLevel) {
            $updates['hr_review_status'] = $action;
            $updates['hr_review_by'] = $actor->id;
            $updates['hr_review_at'] = now();
            $updates['hr_review_comments'] = $remarks;
            if ($type === 'offboarding_termination') {
                // No manager step exists for termination — keep the mirror
                // column from reading as permanently "pending" once HR decides.
                $updates['manager_review_status'] = 'approved';
            }
        }

        if ($updates) {
            $request->update($updates);
        }
    }

    /* ============================================================
     |  KNOWLEDGE TRANSFER
     ============================================================ */

    public function startKnowledgeTransfer(OffboardingRequest $request, User $actor): void
    {
        $this->assertStage($request, OffboardingRequest::STAGE_KNOWLEDGE_TRANSFER);
        $request->update(['knowledge_transfer_status' => 'in_progress']);
        $this->audit->record('tenant_user', $actor->id, (int) $request->tenant_id, 'offboarding.kt_started', 'OffboardingRequest', $request->id);
    }

    public function completeKnowledgeTransfer(OffboardingRequest $request, User $actor, ?string $notes = null): void
    {
        DB::transaction(function () use ($request, $actor, $notes) {
            $locked = OffboardingRequest::whereKey($request->id)->lockForUpdate()->first();
            $this->assertStage($locked, OffboardingRequest::STAGE_KNOWLEDGE_TRANSFER);

            $locked->update([
                'knowledge_transfer_status' => 'completed',
                'knowledge_transfer_completed_at' => now(),
                'knowledge_transfer_notes' => $notes,
                'current_stage' => OffboardingRequest::STAGE_CLEARANCE,
            ]);
            $this->audit->record('tenant_user', $actor->id, (int) $locked->tenant_id, 'offboarding.kt_completed', 'OffboardingRequest', $locked->id);

            $this->startClearance($locked);
            $this->maybeAdvanceStage($locked);
        });
    }

    /* ============================================================
     |  CLEARANCE
     ============================================================ */

    public function startClearance(OffboardingRequest $request): void
    {
        $templates = OffboardingClearanceTemplate::where('tenant_id', $request->tenant_id)
            ->applicableTo($request->reason)->get();

        if ($templates->isEmpty()) {
            $templates = OffboardingClearanceTemplate::whereNull('tenant_id')
                ->applicableTo($request->reason)->get();
        }

        $usingFallback = $templates->isEmpty();
        if ($usingFallback) {
            $templates = collect(OffboardingClearanceTemplate::$defaults);
        }

        foreach ($templates as $template) {
            $templateId = $usingFallback ? null : $template->id;
            $label = is_array($template) ? $template['label'] : $template->label;
            $category = is_array($template) ? $template['category'] : $template->category;

            OffboardingClearanceTask::firstOrCreate(
                [
                    'offboarding_request_id' => $request->id,
                    'source' => OffboardingClearanceTask::SOURCE_CHECKLIST,
                    'clearance_template_id' => $templateId,
                    'label' => $label,
                ],
                [
                    'tenant_id' => $request->tenant_id,
                    'category' => $category,
                    'status' => OffboardingClearanceTask::STATUS_PENDING,
                ]
            );
        }

        $openAssignments = AssetAssignment::withoutGlobalScopes()
            ->where('tenant_id', $request->tenant_id)
            ->where('user_id', $request->employee_id)
            ->whereIn('status', ['assigned', 'pending_acceptance'])
            ->with('asset')
            ->get();

        foreach ($openAssignments as $assignment) {
            $assetLabel = trim(($assignment->asset->name ?? 'Asset') . ' (' . ($assignment->asset->asset_code ?? '—') . ')');
            OffboardingClearanceTask::firstOrCreate(
                [
                    'offboarding_request_id' => $request->id,
                    'source' => OffboardingClearanceTask::SOURCE_ASSET,
                    'asset_assignment_id' => $assignment->id,
                ],
                [
                    'tenant_id' => $request->tenant_id,
                    'category' => 'it',
                    'label' => $assetLabel,
                    'status' => OffboardingClearanceTask::STATUS_PENDING,
                ]
            );
        }

        $noTasks = $request->clearanceTasks()->count() === 0;
        // asset_return_status/document_return_status are legacy columns
        // (enum: pending/partial/completed — no 'in_progress' value) still
        // read by the mobile API's employee-facing timeline
        // (Api\Offboarding\offboardingController) — the new design tracks
        // everything through the unified clearance_status + clearance_tasks
        // instead, so both simply mirror it now, mapped to their own enum.
        $request->update([
            'clearance_status' => $noTasks ? 'completed' : 'in_progress',
            'asset_return_status' => $noTasks ? 'completed' : 'partial',
            'document_return_status' => $noTasks ? 'completed' : 'partial',
        ]);
    }

    public function completeClearanceTask(OffboardingClearanceTask $task, User $actor, string $status, ?string $remarks = null): void
    {
        DB::transaction(function () use ($task, $actor, $status, $remarks) {
            $task->update([
                'status' => $status,
                'completed_by' => $actor->id,
                'completed_at' => now(),
                'remarks' => $remarks,
            ]);

            if ($task->source === OffboardingClearanceTask::SOURCE_ASSET
                && $status === OffboardingClearanceTask::STATUS_COMPLETED
                && $task->asset_assignment_id) {
                $assignment = AssetAssignment::withoutGlobalScopes()->find($task->asset_assignment_id);
                if ($assignment && in_array($assignment->status, ['assigned', 'pending_acceptance'], true)) {
                    app(AssetLifecycleService::class)->returnAsset($assignment, $actor, ['remarks' => $remarks]);
                }
            }

            $this->audit->record('tenant_user', $actor->id, (int) $task->tenant_id, 'offboarding.clearance_task_updated', 'OffboardingClearanceTask', $task->id, [], ['status' => $status]);

            $this->maybeAdvanceStage($task->offboardingRequest);
        });
    }

    /* ============================================================
     |  EXIT INTERVIEW
     ============================================================ */

    public function recordExitInterview(OffboardingRequest $request, User $actor, array $data): ExitInterview
    {
        return DB::transaction(function () use ($request, $actor, $data) {
            $locked = OffboardingRequest::whereKey($request->id)->lockForUpdate()->first();
            $this->assertStage($locked, OffboardingRequest::STAGE_EXIT_INTERVIEW);

            $interview = ExitInterview::updateOrCreate(
                ['offboarding_request_id' => $locked->id],
                array_merge($data, [
                    'tenant_id' => $locked->tenant_id,
                    'employee_id' => $locked->employee_id,
                    'interviewer_id' => $actor->id,
                    'status' => ExitInterview::STATUS_COMPLETED,
                    'created_by' => $actor->id,
                ])
            );

            $locked->update([
                'exit_interview_status' => 'completed',
                'exit_interview_date' => $interview->interview_date,
                'exit_interview_conducted_by' => $actor->id,
                'current_stage' => OffboardingRequest::STAGE_SETTLEMENT,
            ]);
            $this->audit->record('tenant_user', $actor->id, (int) $locked->tenant_id, 'offboarding.exit_interview_recorded', 'OffboardingRequest', $locked->id);

            app(OffboardingSettlementService::class)->generateWorksheet($locked->fresh());

            return $interview;
        });
    }

    public function skipExitInterview(OffboardingRequest $request, User $actor, string $reason): void
    {
        DB::transaction(function () use ($request, $actor, $reason) {
            $locked = OffboardingRequest::whereKey($request->id)->lockForUpdate()->first();
            $this->assertStage($locked, OffboardingRequest::STAGE_EXIT_INTERVIEW);

            $locked->update([
                'exit_interview_skipped' => true,
                'exit_interview_skip_reason' => $reason,
                'current_stage' => OffboardingRequest::STAGE_SETTLEMENT,
            ]);
            $this->audit->record('tenant_user', $actor->id, (int) $locked->tenant_id, 'offboarding.exit_interview_skipped', 'OffboardingRequest', $locked->id, [], ['reason' => $reason]);

            app(OffboardingSettlementService::class)->generateWorksheet($locked->fresh());
        });
    }

    /* ============================================================
     |  NOTICE PERIOD OVERRIDES
     ============================================================ */

    public function requestNoticeOverride(OffboardingRequest $request, User $actor, string $type, string $reason, Carbon $newDate): OffboardingNoticeOverride
    {
        return DB::transaction(function () use ($request, $actor, $type, $reason, $newDate) {
            $override = OffboardingNoticeOverride::create([
                'tenant_id' => $request->tenant_id,
                'offboarding_request_id' => $request->id,
                'type' => $type,
                'reason' => $reason,
                'previous_last_working_date' => $request->last_working_date,
                'requested_last_working_date' => $newDate->toDateString(),
                'requested_by' => $actor->id,
                'requested_at' => now(),
                'status' => OffboardingNoticeOverride::STATUS_PENDING,
            ]);

            $this->audit->record('tenant_user', $actor->id, (int) $request->tenant_id, 'offboarding.notice_override_requested', 'OffboardingRequest', $request->id, [], ['type' => $type, 'requested_last_working_date' => $newDate->toDateString()]);

            return $override;
        });
    }

    public function decideNoticeOverride(OffboardingNoticeOverride $override, User $actor, bool $approve, ?string $notes = null): void
    {
        DB::transaction(function () use ($override, $actor, $approve, $notes) {
            $override->update([
                'status' => $approve ? OffboardingNoticeOverride::STATUS_APPROVED : OffboardingNoticeOverride::STATUS_REJECTED,
                'approved_by' => $actor->id,
                'approved_at' => now(),
                'decision_notes' => $notes,
            ]);

            $request = $override->offboardingRequest;

            if ($approve) {
                $old = $request->last_working_date?->toDateString();
                $request->update(['last_working_date' => $override->requested_last_working_date]);
                $this->audit->record('tenant_user', $actor->id, (int) $request->tenant_id, 'offboarding.notice_override_approved', 'OffboardingRequest', $request->id, ['last_working_date' => $old], ['last_working_date' => $override->requested_last_working_date->toDateString()]);

                if ($request->current_stage === OffboardingRequest::STAGE_SETTLEMENT) {
                    app(OffboardingSettlementService::class)->recalculateNoticeShortfall($request->fresh());
                }
            } else {
                $this->audit->record('tenant_user', $actor->id, (int) $request->tenant_id, 'offboarding.notice_override_rejected', 'OffboardingRequest', $request->id);
            }

            try {
                $this->notifications->notifyNoticeOverrideDecided($override->fresh(), $approve);
            } catch (\Throwable $e) {
            }
        });
    }

    /* ============================================================
     |  CANCEL / REOPEN / COMPLETE
     ============================================================ */

    public function cancel(OffboardingRequest $request, User $actor, string $reason): void
    {
        if (in_array($request->status, [OffboardingRequest::STATUS_COMPLETED, OffboardingRequest::STATUS_CANCELLED, OffboardingRequest::STATUS_REJECTED], true)) {
            throw new RuntimeException('This request cannot be cancelled from its current status.');
        }

        DB::transaction(function () use ($request, $actor, $reason) {
            $locked = OffboardingRequest::whereKey($request->id)->lockForUpdate()->first();

            $pendingApproval = ApprovalRequest::where('subject_type', OffboardingRequest::class)
                ->where('subject_id', $locked->id)->where('status', 'pending')->first();
            $pendingApproval?->update(['status' => 'cancelled', 'resolved_at' => now()]);

            $locked->update([
                'status' => OffboardingRequest::STATUS_CANCELLED,
                'current_stage' => OffboardingRequest::STAGE_CANCELLED,
                'cancelled_by' => $actor->id,
                'cancelled_at' => now(),
                'cancelled_reason' => $reason,
            ]);

            $this->audit->record('tenant_user', $actor->id, (int) $locked->tenant_id, 'offboarding.cancelled', 'OffboardingRequest', $locked->id, [], ['reason' => $reason]);

            try {
                $this->notifications->notifyCancelled($locked->fresh());
            } catch (\Throwable $e) {
            }
        });
    }

    public function reopenStage(OffboardingRequest $request, User $actor, string $stage, string $reason): void
    {
        $allowed = [
            OffboardingRequest::STAGE_KNOWLEDGE_TRANSFER, OffboardingRequest::STAGE_CLEARANCE,
            OffboardingRequest::STAGE_EXIT_INTERVIEW, OffboardingRequest::STAGE_SETTLEMENT,
        ];
        if (! in_array($stage, $allowed, true)) {
            throw new RuntimeException('That stage cannot be reopened.');
        }

        DB::transaction(function () use ($request, $actor, $stage, $reason) {
            $locked = OffboardingRequest::whereKey($request->id)->lockForUpdate()->first();

            $updates = ['current_stage' => $stage];
            $updates += match ($stage) {
                OffboardingRequest::STAGE_KNOWLEDGE_TRANSFER => ['knowledge_transfer_status' => 'in_progress'],
                OffboardingRequest::STAGE_CLEARANCE => ['clearance_status' => 'in_progress'],
                OffboardingRequest::STAGE_EXIT_INTERVIEW => ['exit_interview_status' => 'scheduled', 'exit_interview_skipped' => false],
                OffboardingRequest::STAGE_SETTLEMENT => ['final_settlement_status' => 'processing'],
                default => [],
            };
            $locked->update($updates);

            $this->audit->record('tenant_user', $actor->id, (int) $locked->tenant_id, 'offboarding.stage_reopened', 'OffboardingRequest', $locked->id, [], ['stage' => $stage, 'reason' => $reason]);
        });
    }

    public function complete(OffboardingRequest $request, User $actor): void
    {
        DB::transaction(function () use ($request, $actor) {
            $locked = OffboardingRequest::whereKey($request->id)->lockForUpdate()->first();

            if ($locked->knowledge_transfer_status !== 'completed'
                || $locked->clearance_status !== 'completed'
                || $locked->final_settlement_status !== 'paid') {
                throw new RuntimeException('Cannot complete offboarding until knowledge transfer, clearance, and final settlement are all finished.');
            }

            $now = now();
            $locked->update([
                'status' => OffboardingRequest::STATUS_COMPLETED,
                'current_stage' => OffboardingRequest::STAGE_COMPLETED,
                'completed_at' => $now,
                'offboarding_completed_at' => $now,
            ]);

            $locked->employee?->update(['status' => 0]);
            UserJobDetail::withoutGlobalScopes()
                ->where('user_id', $locked->employee_id)
                ->update(['leaving_date' => $locked->last_working_date]);

            $this->audit->record('tenant_user', $actor->id, (int) $locked->tenant_id, 'offboarding.completed', 'OffboardingRequest', $locked->id);

            try {
                $this->notifications->notifyCompleted($locked->fresh());
            } catch (\Throwable $e) {
            }
        });
    }

    /* ============================================================
     |  STATE MACHINE HELPERS
     ============================================================ */

    private function assertStage(OffboardingRequest $request, string $expectedStage): void
    {
        if ($request->current_stage !== $expectedStage) {
            throw new RuntimeException("This action is not valid at the current stage ({$request->current_stage}).");
        }
    }

    /**
     * Public: called after any clearance-task update and (by the controller)
     * after the settlement worksheet is marked paid, so both flows share one
     * place that decides whether the stepper should move forward.
     */
    public function maybeAdvanceStage(OffboardingRequest $request): void
    {
        $request->refresh();

        if ($request->current_stage === OffboardingRequest::STAGE_CLEARANCE) {
            $unresolved = $request->clearanceTasks()
                ->whereNotIn('status', [
                    OffboardingClearanceTask::STATUS_COMPLETED,
                    OffboardingClearanceTask::STATUS_WAIVED,
                    OffboardingClearanceTask::STATUS_NOT_APPLICABLE,
                ])->exists();

            $request->update([
                'clearance_status' => $unresolved ? 'in_progress' : 'completed',
                // legacy mirrors — see startClearance() for why these stay in sync
                'asset_return_status' => $unresolved ? 'partial' : 'completed',
                'document_return_status' => $unresolved ? 'partial' : 'completed',
            ]);
            if (! $unresolved) {
                $request->update(['current_stage' => OffboardingRequest::STAGE_EXIT_INTERVIEW]);
            }
        } elseif ($request->current_stage === OffboardingRequest::STAGE_SETTLEMENT) {
            if ($request->final_settlement_status === 'paid') {
                $request->update(['current_stage' => OffboardingRequest::STAGE_READY_TO_COMPLETE]);
            }
        }
    }
}
