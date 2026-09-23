<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Helpers\TenantHelper;

class OnboardingStartedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $candidate;
    public $jobOpening;
    public $assignment;
    public $company;
    public $tenantId;

    public function __construct($candidate, $jobOpening, $assignment, $tenantId = null)
    {
        $this->candidate = $candidate;
        $this->jobOpening = $jobOpening;
        $this->assignment = $assignment;
        $this->tenantId = $tenantId ?? ($jobOpening->tenant_id ?? 1);
        $this->company = TenantHelper::getTenantDetails($this->tenantId);
    }

    public function build()
    {
        return $this->subject('Welcome Aboard - Onboarding Started for ' . $this->jobOpening->title)
            ->view('client.emails.onboarding_started');
    }
}
