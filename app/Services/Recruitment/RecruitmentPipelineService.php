<?php

namespace App\Services\Recruitment;

use App\Mail\ApplicationRejectedMail;
use App\Mail\ApplicationShortlistedMail;
use App\Mail\InterviewCancelledMail;
use App\Mail\InterviewFeedbackMail;
use App\Mail\InterviewerInvitationMail;
use App\Mail\InterviewRescheduledMail;
use App\Mail\InterviewScheduledMail;
use App\Mail\OfferAcceptedHRMail;
use App\Mail\OfferAcceptedMail;
use App\Mail\OfferReleasedMail;
use App\Mail\OfferRejectedMail;
use App\Models\Candidate;
use App\Models\Interview;
use App\Models\JobApplication;
use App\Models\JobOffer;
use App\Models\JobOpening;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Single choke point for every job_applications.current_stage transition
 * (mirrors the AssetLifecycleService pattern used elsewhere in this app).
 * Controllers validate the HTTP request, then call one method here — no
 * stage-transition logic should live in a controller.
 */
class RecruitmentPipelineService
{
    public function __construct(protected OnboardingService $onboardingService)
    {
    }

    /**
     * A job_openings.status === 'closed' job should not accept forward
     * pipeline movement on its applications (it previously did — nothing
     * guarded against it).
     */
    protected function assertJobOpen(JobOpening $jobOpening): void
    {
        if ($jobOpening->status === JobOpening::STATUS_CLOSED) {
            throw new \RuntimeException('This job opening is closed. Reopen it before taking further action on its applications.');
        }
    }

    protected function notify(callable $send, string $context): void
    {
        try {
            $send();
        } catch (\Throwable $e) {
            Log::error("Recruitment email failed ({$context}): " . $e->getMessage());
        }
    }

    // ─────────────────────────────────────────────
    //  CV SHORTLIST / REJECT
    // ─────────────────────────────────────────────

    public function shortlist(JobApplication $application, ?string $remarks = null): JobApplication
    {
        $application->loadMissing(['candidate', 'jobOpening']);
        $this->assertJobOpen($application->jobOpening);

        if ($application->current_stage !== JobApplication::STAGE_APPLICATION_RECEIVED) {
            throw new \RuntimeException('Application cannot be shortlisted at this stage.');
        }

        DB::transaction(function () use ($application, $remarks) {
            $application->moveToStage(JobApplication::STAGE_CV_SHORTLISTED, $remarks ?: 'CV shortlisted');
        });

        $this->notify(fn () => Mail::to($application->candidate->email)->send(
            new ApplicationShortlistedMail($application->candidate, $application->jobOpening)
        ), 'shortlist');

        return $application->fresh(['candidate', 'jobOpening']);
    }

    public function rejectApplication(JobApplication $application, ?string $remarks = null): JobApplication
    {
        $application->loadMissing(['candidate', 'jobOpening']);

        $rejectableStages = [
            JobApplication::STAGE_APPLICATION_RECEIVED,
            JobApplication::STAGE_CV_SHORTLISTED,
            JobApplication::STAGE_INTERVIEW_SCHEDULED,
            JobApplication::STAGE_INTERVIEW_COMPLETED,
        ];

        if (!in_array($application->current_stage, $rejectableStages, true)) {
            throw new \RuntimeException('Application cannot be rejected at this stage.');
        }

        $newStage = $application->current_stage === JobApplication::STAGE_APPLICATION_RECEIVED
            ? JobApplication::STAGE_CV_REJECTED
            : JobApplication::STAGE_REJECTED;

        DB::transaction(function () use ($application, $newStage, $remarks) {
            $application->moveToStage($newStage, $remarks, 'rejected');

            // Any interview still scheduled for a now-rejected application
            // would otherwise dangle forever with status=scheduled.
            $application->interviews()
                ->where('status', Interview::STATUS_SCHEDULED)
                ->get()
                ->each(fn (Interview $interview) => $interview->cancel('Application rejected'));
        });

        $this->notify(fn () => Mail::to($application->candidate->email)->send(
            new ApplicationRejectedMail($application->candidate, $application->jobOpening, $remarks)
        ), 'reject');

        return $application->fresh(['candidate', 'jobOpening']);
    }

    // ─────────────────────────────────────────────
    //  INTERVIEW SCHEDULING / CANCEL / RESCHEDULE
    // ─────────────────────────────────────────────

    public function scheduleInterview(JobApplication $application, array $data): Interview
    {
        $application->loadMissing(['candidate', 'jobOpening']);
        $this->assertJobOpen($application->jobOpening);

        $schedulableStages = [JobApplication::STAGE_CV_SHORTLISTED, JobApplication::STAGE_INTERVIEW_COMPLETED];
        if (!in_array($application->current_stage, $schedulableStages, true)) {
            throw new \RuntimeException('Interview cannot be scheduled at this stage (' . $application->current_stage . ').');
        }

        $nextRound = (int) $application->interviews()->max('interview_round') + 1;

        $interview = DB::transaction(function () use ($application, $data, $nextRound) {
            $interview = Interview::create([
                'tenant_id' => $application->tenant_id,
                'interview_code' => $this->generateInterviewCode(),
                'job_application_id' => $application->id,
                'candidate_id' => $application->candidate_id,
                'recruitment_stage_id' => $data['recruitment_stage_id'] ?? null,
                'interview_round' => $nextRound,
                'round_name' => $data['round_name'],
                'interview_type' => $data['interview_type'],
                'interviewer_id' => $data['interviewer_id'],
                'co_interviewer_ids' => json_encode($data['co_interviewer_ids'] ?? []),
                'scheduled_date' => $data['scheduled_date'],
                'scheduled_time' => $data['scheduled_time'],
                'duration_minutes' => $data['duration_minutes'],
                'meeting_link' => $data['meeting_link'] ?? null,
                'location' => $data['location'] ?? null,
                'instructions' => $data['instructions'] ?? null,
                'status' => Interview::STATUS_SCHEDULED,
                'created_by' => auth()->id(),
            ]);

            $application->moveToStage(
                JobApplication::STAGE_INTERVIEW_SCHEDULED,
                "Interview scheduled: Round {$nextRound} – {$data['round_name']}"
            );

            return $interview;
        });

        $this->notify(fn () => Mail::to($application->candidate->email)->send(
            new InterviewScheduledMail($application->candidate, $application->jobOpening, $interview)
        ), 'interview-scheduled-candidate');

        $interviewer = User::find($data['interviewer_id']);
        if ($interviewer) {
            $this->notify(fn () => Mail::to($interviewer->email)->send(
                new InterviewerInvitationMail($interviewer, $application->candidate, $application->jobOpening, $interview)
            ), 'interview-scheduled-interviewer');
        }

        return $interview->fresh(['application.candidate', 'application.jobOpening', 'interviewer']);
    }

    public function cancelInterview(Interview $interview, ?string $reason = null): Interview
    {
        $interview->loadMissing(['application.candidate', 'application.jobOpening']);

        if ($interview->status !== Interview::STATUS_SCHEDULED) {
            throw new \RuntimeException('Only a scheduled interview can be cancelled.');
        }

        DB::transaction(function () use ($interview, $reason) {
            $interview->cancel($reason);
            $interview->application->moveToStage(
                JobApplication::STAGE_CV_SHORTLISTED,
                'Interview cancelled: ' . ($reason ?: 'no reason given')
            );
        });

        $this->notify(fn () => Mail::to($interview->application->candidate->email)->send(
            new InterviewCancelledMail($interview->application->candidate, $interview->application->jobOpening, $interview, $reason)
        ), 'interview-cancelled');

        return $interview->fresh();
    }

    public function rescheduleInterview(Interview $interview, string $newDate, string $newTime, ?string $reason = null): Interview
    {
        $interview->loadMissing(['application.candidate', 'application.jobOpening']);

        if ($interview->status !== Interview::STATUS_SCHEDULED) {
            throw new \RuntimeException('Only a scheduled interview can be rescheduled.');
        }

        $newInterview = DB::transaction(fn () => $interview->reschedule($newDate, $newTime, $reason));

        $this->notify(fn () => Mail::to($interview->application->candidate->email)->send(
            new InterviewRescheduledMail($interview->application->candidate, $interview->application->jobOpening, $interview, $newInterview, $reason)
        ), 'interview-rescheduled');

        return $newInterview->fresh();
    }

    // ─────────────────────────────────────────────
    //  INTERVIEW FEEDBACK / DECISION
    // ─────────────────────────────────────────────

    public function submitInterviewFeedback(Interview $interview, array $data): Interview
    {
        $interview->loadMissing(['application.candidate', 'application.jobOpening']);

        if ($interview->status === Interview::STATUS_COMPLETED) {
            throw new \RuntimeException('Feedback has already been submitted for this interview.');
        }

        $application = $interview->application;

        DB::transaction(function () use ($interview, $application, $data) {
            $interview->update([
                'status' => Interview::STATUS_COMPLETED,
                'outcome' => $data['decision'],
                'feedback' => $data['comments'],
                'rating' => $data['overall_rating'] ?? null,
                'completed_at' => now(),
            ]);

            $interview->feedbacks()->create([
                'tenant_id' => $interview->tenant_id,
                'interviewer_id' => auth()->id(),
                'technical_skill' => $data['technical_skill'] ?? null,
                'communication_skill' => $data['communication_skill'] ?? null,
                'problem_solving' => $data['problem_solving'] ?? null,
                'cultural_fit' => $data['cultural_fit'] ?? null,
                'experience_relevance' => $data['experience_relevance'] ?? null,
                'overall_rating' => $data['overall_rating'] ?? null,
                'strengths' => $data['strengths'] ?? null,
                'weaknesses' => $data['weaknesses'] ?? null,
                'comments' => $data['comments'],
                'recommendation' => $data['recommendation'],
                'next_round_suggested' => $data['next_round_suggested'] ?? null,
            ]);

            switch ($data['decision']) {
                case 'selected':
                    $application->moveToStage(
                        JobApplication::STAGE_INTERVIEW_COMPLETED,
                        "Selected in {$interview->round_name}. Ready for offer."
                    );
                    break;

                case 'rejected':
                    $application->moveToStage(
                        JobApplication::STAGE_REJECTED,
                        "Rejected in {$interview->round_name}: {$data['comments']}"
                    );
                    break;

                case 'next_round':
                    $application->moveToStage(
                        JobApplication::STAGE_CV_SHORTLISTED,
                        "Advancing to next round after {$interview->round_name}"
                            . (!empty($data['next_round_suggested']) ? ". Next: {$data['next_round_suggested']}" : ''),
                        'next_round'
                    );
                    break;

                case 'on_hold':
                    $application->moveToStage(
                        JobApplication::STAGE_CV_SHORTLISTED,
                        "On hold after {$interview->round_name}: {$data['comments']}",
                        'on_hold'
                    );
                    break;
            }
        });

        $this->notify(function () use ($application, $interview, $data) {
            if (in_array($data['decision'], ['selected', 'rejected', 'next_round'], true)) {
                Mail::to($application->candidate->email)->send(
                    new InterviewFeedbackMail($application->candidate, $application->jobOpening, $interview, $data['decision'], $data['comments'])
                );
            }
        }, 'interview-feedback');

        return $interview->fresh(['application.candidate', 'application.jobOpening', 'feedbacks']);
    }

    // ─────────────────────────────────────────────
    //  OFFER RELEASE / ACCEPT / REJECT
    // ─────────────────────────────────────────────

    public function releaseOffer(JobApplication $application, array $data): JobOffer
    {
        $application->loadMissing(['candidate', 'jobOpening']);
        $this->assertJobOpen($application->jobOpening);

        if (!$application->canReleaseOffer()) {
            throw new \RuntimeException('Offer can only be released after interview is completed.');
        }

        $offer = DB::transaction(function () use ($application, $data) {
            $offer = JobOffer::create([
                'tenant_id' => $application->tenant_id,
                'offer_code' => $this->generateOfferCode(),
                'job_application_id' => $application->id,
                'candidate_id' => $application->candidate_id,
                'offer_date' => now(),
                'joining_date' => $data['joining_date'],
                'designation_id' => $data['designation_id'],
                'department_id' => $data['department_id'],
                'reporting_head' => $data['reporting_head'] ?? null,
                'employment_type' => $data['employment_type'],
                'offer_status' => JobOffer::STATUS_DRAFT,
                'offered_ctc' => $data['offered_ctc'],
                'basic_salary' => $data['basic_salary'] ?? null,
                'hra' => $data['hra'] ?? null,
                'other_allowances' => $data['other_allowances'] ?? null,
                'variable_pay' => $data['variable_pay'] ?? null,
                'created_by' => auth()->id(),
            ]);

            $offer->send(); // JobOffer::send(): status -> sent, sent_at, moves application -> offer_released

            return $offer;
        });

        $this->notify(fn () => Mail::to($application->candidate->email)->send(
            new OfferReleasedMail($application->candidate, $application->jobOpening, $offer, $data['joining_date'])
        ), 'offer-released');

        return $offer->fresh(['application.candidate', 'application.jobOpening']);
    }

    public function markOfferAccepted(JobOffer $offer, ?string $negotiationDetails = null): JobOffer
    {
        $offer->loadMissing(['application.candidate', 'application.jobOpening']);
        $application = $offer->application;

        if ($application->current_stage !== JobApplication::STAGE_OFFER_RELEASED) {
            throw new \RuntimeException('Offer is not in a state that can be accepted.');
        }

        $assignment = DB::transaction(function () use ($offer, $negotiationDetails) {
            $offer->accept($negotiationDetails); // JobOffer::accept(): sets acceptance_date correctly, moves application -> offer_accepted
            return $this->onboardingService->startOnboarding($offer->fresh());
        });

        $this->notify(fn () => Mail::to($offer->application->candidate->email)->send(
            new OfferAcceptedMail($offer->application->candidate, $offer->application->jobOpening, $offer->joining_date)
        ), 'offer-accepted-candidate');

        $this->notify(function () use ($offer) {
            $hrUsers = User::whereIn('role', ['hr', 'admin'])->get();
            foreach ($hrUsers as $hr) {
                Mail::to($hr->email)->send(new OfferAcceptedHRMail($offer->application->candidate, $offer->application->jobOpening));
            }
        }, 'offer-accepted-hr');

        return $offer->fresh(['application.candidate', 'application.jobOpening']);
    }

    public function markOfferRejected(JobOffer $offer, string $reason): JobOffer
    {
        $offer->loadMissing(['application.candidate', 'application.jobOpening']);
        $application = $offer->application;

        if ($application->current_stage !== JobApplication::STAGE_OFFER_RELEASED) {
            throw new \RuntimeException('Offer is not in a state that can be rejected.');
        }

        DB::transaction(function () use ($offer, $reason) {
            $offer->reject($reason); // JobOffer::reject(): moves application -> offer_rejected
        });

        $this->notify(function () use ($offer) {
            $hrUsers = User::whereIn('role', ['hr', 'admin'])->get();
            foreach ($hrUsers as $hr) {
                Mail::to($hr->email)->send(new OfferRejectedMail($offer->application->candidate, $offer->application->jobOpening));
            }
        }, 'offer-rejected');

        return $offer->fresh(['application.candidate', 'application.jobOpening']);
    }

    // ─────────────────────────────────────────────
    //  CODE GENERATORS
    // ─────────────────────────────────────────────

    /**
     * max(id)+1 is not collision-proof (a rolled-back transaction leaves an
     * auto_increment gap, so a later max(id) can under-count and reproduce
     * an already-used code) — retry-until-unique like generateCandidateCode()
     * /generateApplicationCode() in RecruitmentController already do.
     */
    protected function generateInterviewCode(): string
    {
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $nextId = (Interview::max('id') ?? 0) + 1 + $attempt;
            $code = 'INT' . str_pad($nextId, 6, '0', STR_PAD_LEFT);
            if (!Interview::where('interview_code', $code)->exists()) {
                return $code;
            }
        }
        return 'INT' . date('YmdHis') . rand(100, 999);
    }

    protected function generateOfferCode(): string
    {
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $nextId = (JobOffer::max('id') ?? 0) + 1 + $attempt;
            $code = 'OFF' . date('Y') . str_pad($nextId, 5, '0', STR_PAD_LEFT);
            if (!JobOffer::where('offer_code', $code)->exists()) {
                return $code;
            }
        }
        return 'OFF' . date('Y') . date('YmdHis') . rand(100, 999);
    }
}
