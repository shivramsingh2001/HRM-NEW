<?php
// app/Mail/InterviewFeedbackMail.php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Helpers\TenantHelper;

class InterviewFeedbackMail extends Mailable
{
    use Queueable, SerializesModels;

    public $candidate;
    public $jobOpening;
    public $interview;
    public $decision;
    public $comments;
    public $company;
    public $tenantId;


    public function __construct($candidate, $jobOpening, $interview, $decision, $comments, $tenantId = null)
    {
        $this->candidate = $candidate;
        $this->jobOpening = $jobOpening;
        $this->interview = $interview;
        $this->decision = $decision;
        $this->comments = $comments;
        $this->tenantId = $tenantId ?? ($jobOpening->tenant_id ?? 1);
        $this->company = TenantHelper::getTenantDetails($this->tenantId);
    }

    public function build()
    {
        $subject = match ($this->decision) {
            'selected' => 'Good News! You have been selected - ' . $this->jobOpening->title,
            'next_round' => 'Update: Moving to Next Round - ' . $this->jobOpening->title,
            'rejected' => 'Update on your application - ' . $this->jobOpening->title,
            default => 'Interview Feedback - ' . $this->jobOpening->title,
        };

        return $this->subject($subject)
            ->view('client.emails.interview_feedback');
    }
}
