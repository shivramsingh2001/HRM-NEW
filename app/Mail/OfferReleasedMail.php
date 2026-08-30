<?php
// app/Mail/OfferReleasedMail.php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Helpers\TenantHelper;

class OfferReleasedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $candidate;
    public $jobOpening;
    public $offer;
    public $joiningDate;
    public $company;
    public $tenantId;

    public function __construct($candidate, $jobOpening, $offer, $joiningDate, $tenantId = null)
    {
        $this->candidate = $candidate;
        $this->jobOpening = $jobOpening;
        $this->offer = $offer;
        $this->joiningDate = $joiningDate;
        $this->tenantId = $tenantId ?? ($jobOpening->tenant_id ?? 1);
        $this->company = TenantHelper::getTenantDetails($this->tenantId);
    }

    public function build()
    {
        return $this->subject('Job Offer Letter - ' . $this->jobOpening->title)
            ->view('client.emails.offer_released');
    }
}