<?php

namespace App\Services\Recruitment;

use App\Mail\OnboardingStartedMail;
use App\Models\CandidateDocument;
use App\Models\JobApplication;
use App\Models\JobOffer;
use App\Models\OnboardingAssignment;
use App\Models\OnboardingTask;
use App\Models\OnboardingTaskItem;
use App\Models\RecruitmentWorkflowLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class OnboardingService
{
    /**
     * Called once an offer is accepted. Creates the onboarding_assignments
     * row (1:1 with both the candidate and the offer) and seeds its
     * checklist from the tenant's onboarding_tasks catalog (shared/global
     * rows with tenant_id = NULL, plus any tenant-specific ones).
     */
    public function startOnboarding(JobOffer $offer): OnboardingAssignment
    {
        $offer->loadMissing(['application.candidate', 'application.jobOpening']);
        $application = $offer->application;

        $assignment = DB::transaction(function () use ($offer, $application) {
            $assignment = OnboardingAssignment::create([
                'tenant_id' => $offer->tenant_id,
                'assignment_code' => $this->generateAssignmentCode(),
                'candidate_id' => $offer->candidate_id,
                'job_offer_id' => $offer->id,
                'onboarding_status' => OnboardingAssignment::STATUS_IN_PROGRESS,
                'start_date' => $offer->joining_date ?? now()->addDays(15),
                'expected_completion_date' => $offer->joining_date,
                'created_by' => auth()->id(),
            ]);

            $tasks = OnboardingTask::forTenant($offer->tenant_id)->active()->ordered()->get();
            foreach ($tasks as $task) {
                OnboardingTaskItem::create([
                    'tenant_id' => $offer->tenant_id,
                    'onboarding_assignment_id' => $assignment->id,
                    'onboarding_task_id' => $task->id,
                    'status' => OnboardingTaskItem::STATUS_PENDING,
                    'due_date' => $task->default_days_to_complete
                        ? now()->addDays($task->default_days_to_complete)
                        : $assignment->expected_completion_date,
                ]);
            }

            $application->moveToStage(
                JobApplication::STAGE_ONBOARDING,
                'Onboarding started: ' . $assignment->assignment_code,
                RecruitmentWorkflowLog::ACTION_ONBOARDING_STARTED
            );

            return $assignment;
        });

        try {
            Mail::to($application->candidate->email)->send(
                new OnboardingStartedMail($application->candidate, $application->jobOpening, $assignment)
            );
        } catch (\Throwable $e) {
            Log::error('Failed to send onboarding started email: ' . $e->getMessage());
        }

        return $assignment->fresh(['taskItems.task']);
    }

    public function verifyDocument(CandidateDocument $document, ?string $remarks = null): CandidateDocument
    {
        $document->verify($remarks);
        return $document->fresh();
    }

    public function rejectDocument(CandidateDocument $document): CandidateDocument
    {
        $document->reject();
        return $document->fresh();
    }

    public function uploadDocument(OnboardingAssignment $assignment, array $data): CandidateDocument
    {
        return CandidateDocument::create([
            'tenant_id' => $assignment->tenant_id,
            'candidate_id' => $assignment->candidate_id,
            'document_type' => $data['document_type'],
            'document_name' => $data['document_name'] ?? null,
            'file_url' => $data['file_url'],
            'file_size' => $data['file_size'] ?? null,
            'mime_type' => $data['mime_type'] ?? null,
            'uploaded_by' => auth()->id(),
            'uploaded_at' => now(),
        ]);
    }

    public function updateTaskItem(OnboardingTaskItem $item, string $status, ?string $remarks = null): OnboardingTaskItem
    {
        if (!array_key_exists($status, OnboardingTaskItem::$statuses)) {
            throw new \RuntimeException('Invalid onboarding task status.');
        }

        if (in_array($status, [OnboardingTaskItem::STATUS_COMPLETED, OnboardingTaskItem::STATUS_SKIPPED], true)) {
            $status === OnboardingTaskItem::STATUS_COMPLETED
                ? $item->markCompleted($remarks)
                : $item->markSkipped($remarks);
        } else {
            $item->update(['status' => $status, 'remarks' => $remarks ?? $item->remarks]);
        }

        return $item->fresh();
    }

    /**
     * Guards that every mandatory task item is completed/skipped before
     * flipping the assignment to completed and advancing the application
     * to STAGE_ONBOARDED (the gate hire() checks before it will run).
     */
    public function completeOnboarding(OnboardingAssignment $assignment): OnboardingAssignment
    {
        $assignment->loadMissing(['taskItems.task', 'jobOffer.application']);

        $incomplete = $assignment->taskItems->filter(function (OnboardingTaskItem $item) {
            $mandatory = $item->task?->is_mandatory ?? true;
            return $mandatory && !in_array($item->status, [OnboardingTaskItem::STATUS_COMPLETED, OnboardingTaskItem::STATUS_SKIPPED], true);
        });

        if ($incomplete->isNotEmpty()) {
            throw new \RuntimeException('All mandatory onboarding tasks must be completed first: '
                . $incomplete->map(fn ($i) => $i->task?->task_name)->filter()->implode(', '));
        }

        DB::transaction(function () use ($assignment) {
            $assignment->markCompleted();

            $application = $assignment->jobOffer->application;
            $application->moveToStage(
                JobApplication::STAGE_ONBOARDED,
                'Onboarding checklist completed: ' . $assignment->assignment_code,
                RecruitmentWorkflowLog::ACTION_ONBOARDING_COMPLETED
            );
        });

        return $assignment->fresh();
    }

    protected function generateAssignmentCode(): string
    {
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $nextId = (OnboardingAssignment::max('id') ?? 0) + 1 + $attempt;
            $code = 'ONB' . date('Y') . str_pad($nextId, 5, '0', STR_PAD_LEFT);
            if (!OnboardingAssignment::where('assignment_code', $code)->exists()) {
                return $code;
            }
        }
        return 'ONB' . date('Y') . date('YmdHis') . rand(100, 999);
    }
}
