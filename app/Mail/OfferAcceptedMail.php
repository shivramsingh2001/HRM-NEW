<?php
// app/Mail/OfferAcceptedMail.php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Helpers\TenantHelper;

class OfferAcceptedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $candidate;
    public $jobOpening;
    public $joiningDate;
    public $company;
    public $tenantId;

    public function __construct($candidate, $jobOpening, $joiningDate, $tenantId = null)
    {
        $this->candidate = $candidate;
        $this->jobOpening = $jobOpening;
        $this->joiningDate = $joiningDate;
        $this->tenantId = $tenantId ?? ($jobOpening->tenant_id ?? 1);
        $this->company = TenantHelper::getTenantDetails($this->tenantId);
    }

    public function build()
    {
        return $this->subject('Offer Accepted - Welcome to the team! - ' . $this->jobOpening->title)
            ->view('client.emails.offer_accepted');
    }
}