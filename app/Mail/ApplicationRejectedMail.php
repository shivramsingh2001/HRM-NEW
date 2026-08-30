<?php
// app/Mail/ApplicationRejectedMail.php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Helpers\TenantHelper;

class ApplicationRejectedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $candidate;
    public $jobOpening;
    public $reason;
    public $company;
    public $tenantId;

    public function __construct($candidate, $jobOpening, $reason, $tenantId = null)
    {
        $this->candidate = $candidate;
        $this->jobOpening = $jobOpening;
        $this->reason = $reason;
        $this->tenantId = $tenantId ?? ($jobOpening->tenant_id ?? 1);
        $this->company = TenantHelper::getTenantDetails($this->tenantId);
    }

    public function build()
    {
        return $this->subject('Update on your application - ' . $this->jobOpening->title)
            ->view('emails.recruitment.application_rejected');
    }
}