<?php
// app/Mail/InterviewerInvitationMail.php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class InterviewerInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public $interviewer;
    public $candidate;
    public $jobOpening;
    public $interview;

    public function __construct($interviewer, $candidate, $jobOpening, $interview)
    {
        $this->interviewer = $interviewer;
        $this->candidate = $candidate;
        $this->jobOpening = $jobOpening;
        $this->interview = $interview;
    }

    public function build()
    {
        return $this->subject('Interview Scheduled - Action Required: ' . $this->jobOpening->title)
            ->view('client.emails.interviewer_invitation');
    }
}
