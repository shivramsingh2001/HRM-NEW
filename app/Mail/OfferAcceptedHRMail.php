<?php
// app/Mail/OfferAcceptedHRMail.php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Helpers\TenantHelper;

class OfferAcceptedHRMail extends Mailable
{
    use Queueable, SerializesModels;

    public $candidate;
    public $jobOpening;
    public $company;
    public $tenantId;

    public function __construct($candidate, $jobOpening, $tenantId = null)
    {
        $this->candidate = $candidate;
        $this->jobOpening = $jobOpening;
        $this->tenantId = $tenantId ?? ($jobOpening->tenant_id ?? 1);
        $this->company = TenantHelper::getTenantDetails($this->tenantId);
    }

    public function build()
    {
        return $this->subject('Offer Accepted - ' . $this->candidate->first_name . ' ' . $this->candidate->last_name)
            ->view('client.emails.offer_accepted_hr');
    }
}