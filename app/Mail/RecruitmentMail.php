<?php
// app/Mail/RecruitmentMail.php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

abstract class RecruitmentMail extends Mailable
{
    use Queueable, SerializesModels;

    protected $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    protected function getLogoUrl()
    {
        return url('/images/logo.png');
    }

    protected function getCompanyName()
    {
        return config('app.name', 'Our Company');
    }
}