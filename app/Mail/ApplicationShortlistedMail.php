<?php
// app/Mail/ApplicationShortlistedMail.php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Helpers\TenantHelper;

class ApplicationShortlistedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $candidate;
    public $jobOpening;
    public $remarks;
    public $company;
    public $tenantId;

    public function __construct($candidate, $jobOpening, $remarks = null, $tenantId = null)
    {
        $this->candidate = $candidate;
        $this->jobOpening = $jobOpening;
        $this->remarks = $remarks;
        $this->tenantId = $tenantId ?? ($jobOpening->tenant_id ?? 1);
        $this->company = TenantHelper::getTenantDetails($this->tenantId);
    }

    public function build()
    {
        return $this->subject('Congratulations! Your application has been shortlisted - ' . $this->jobOpening->title)
                    ->view('client.emails.application_shortlisted');
    }
}