<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * A scheduled report (Reports → Scheduled Reports) with the export attached.
 * Sent synchronously by ReportScheduleRunner, one mail per recipient.
 * `fileContent` is null when the file is too large to attach.
 */
class ScheduledReportMail extends Mailable
{
    public function __construct(
        public string $companyName,
        public string $scheduleName,
        public string $reportLabel,
        public string $periodLabel,
        public ?string $customSubject,
        public ?string $customMessage,
        public string $fileName,
        public ?string $fileContent,
        public string $mime,
        public int $fileSize,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->customSubject ?: "{$this->reportLabel} – {$this->periodLabel} | {$this->companyName}",
        );
    }

    public function content(): Content
    {
        return new Content(view: 'client.emails.scheduled-report');
    }

    /** @return array<int, Attachment> */
    public function attachments(): array
    {
        if ($this->fileContent === null) {
            return [];
        }

        return [Attachment::fromData(fn () => $this->fileContent, $this->fileName)->withMime($this->mime)];
    }
}
