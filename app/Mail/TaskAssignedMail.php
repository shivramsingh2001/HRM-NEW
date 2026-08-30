<?php

namespace App\Mail;

use App\Models\Task;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TaskAssignedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $task;
    public $toUser;
    public $fromUser;
    public $type; // 'assigned' or 'created'

    /**
     * Create a new message instance.
     */
    public function __construct(Task $task, User $toUser, User $fromUser, string $type = 'assigned')
    {
        $this->task = $task;
        $this->toUser = $toUser;
        $this->fromUser = $fromUser;
        $this->type = $type;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $subject = $this->type === 'assigned' 
            ? 'New Task Assigned: ' . $this->task->title
            : 'Task Created: ' . $this->task->title;

        return new Envelope(
            subject: $subject,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'client.emails.task-assigned',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}