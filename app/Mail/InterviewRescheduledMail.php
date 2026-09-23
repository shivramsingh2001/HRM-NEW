<?php
// app/Mail/InterviewRescheduledMail.php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class InterviewRescheduledMail extends Mailable
{
    use Queueable, SerializesModels;

    public $candidate;
    public $jobOpening;
    public $oldInterview;
    public $newInterview;
    public $reason;

    public function __construct($candidate, $jobOpening, $oldInterview, $newInterview, $reason = null)
    {
        $this->candidate = $candidate;
        $this->jobOpening = $jobOpening;
        $this->oldInterview = $oldInterview;
        $this->newInterview = $newInterview;
        $this->reason = $reason;
    }

    public function build()
    {
        return $this->subject('Interview Rescheduled - ' . $this->jobOpening->title)
            ->view('client.emails.interview_rescheduled');
    }
}
