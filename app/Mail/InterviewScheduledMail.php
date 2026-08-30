<?php
// app/Mail/InterviewScheduledMail.php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Helpers\TenantHelper;

class InterviewScheduledMail extends Mailable
{
    use Queueable, SerializesModels;

    public $candidate;
    public $jobOpening;
    public $interview;
    public $company;
    public $tenantId;

    public function __construct($candidate, $jobOpening, $interview, $tenantId = null)
    {
        $this->candidate = $candidate;
        $this->jobOpening = $jobOpening;
        $this->interview = $interview;
        $this->tenantId = $tenantId ?? ($jobOpening->tenant_id ?? 1);
        $this->company = TenantHelper::getTenantDetails($this->tenantId);
    }

    public function build()
    {
        return $this->subject('Interview Scheduled - ' . $this->jobOpening->title . ' - Round ' . $this->interview->interview_round)
                    ->view('client.emails.interview_scheduled');
    }
}