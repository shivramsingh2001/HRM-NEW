<?php
// app/Mail/InterviewCancelledMail.php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class InterviewCancelledMail extends Mailable
{
    use Queueable, SerializesModels;

    public $candidate;
    public $jobOpening;
    public $interview;
    public $reason;

    public function __construct($candidate, $jobOpening, $interview, $reason = null)
    {
        $this->candidate = $candidate;
        $this->jobOpening = $jobOpening;
        $this->interview = $interview;
        $this->reason = $reason;
    }

    public function build()
    {
        return $this->subject('Interview Cancelled - ' . $this->jobOpening->title)
            ->view('client.emails.interview_cancelled');
    }
}
