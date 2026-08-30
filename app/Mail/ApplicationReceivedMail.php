<?php
// app/Mail/ApplicationReceivedMail.php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Helpers\TenantHelper;

class ApplicationReceivedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $candidate;
    public $jobOpening;
    public $company;
    public $tenantId;
    public $candidateName;
    public $jobTitle;
    public $jobCode;
    public $applicationDate;

    public function __construct($candidate, $jobOpening, $tenantId = null)
    {
        $this->candidate = $candidate;
        $this->jobOpening = $jobOpening;
        $this->tenantId = $tenantId ?? ($jobOpening->tenant_id ?? 1);
        $this->company = TenantHelper::getTenantDetails($this->tenantId);
        $this->candidateName = $candidate->first_name . ' ' . $candidate->last_name;
        $this->jobTitle = $jobOpening->title;
        $this->jobCode = $jobOpening->job_code;
        $this->applicationDate = now()->format('d M Y');
    }

    public function build()
    {
        return $this->subject('Application Received - ' . $this->jobTitle)
            ->view('client.emails.application_received');
    }
}