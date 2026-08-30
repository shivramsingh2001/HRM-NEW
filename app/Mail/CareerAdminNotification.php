<?php

namespace App\Mail;

use App\Models\Career;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Queue\SerializesModels;

class CareerAdminNotification extends Mailable
{
    use Queueable, SerializesModels;

    public $career;

    /**
     * Create a new message instance.
     */
    public function __construct(Career $career)
    {
        $this->career = $career;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'New Job Application: ' . $this->career->position . ' - ' . $this->career->name,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.career.admin-notification',
        );
    }

    /**
     * Get the attachments for the message.
     */
    public function attachments(): array
    {
        $attachments = [];
        
        if ($this->career->cv_file && file_exists(public_path($this->career->cv_file))) {
            $attachments[] = Attachment::fromPath(public_path($this->career->cv_file))
                ->as('cv_' . str_replace(' ', '_', $this->career->name) . '.' . pathinfo($this->career->cv_file, PATHINFO_EXTENSION))
                ->withMime(mime_content_type(public_path($this->career->cv_file)));
        }
        
        return $attachments;
    }
}