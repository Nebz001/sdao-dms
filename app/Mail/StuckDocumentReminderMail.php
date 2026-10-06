<?php

namespace App\Mail;

use App\Models\Document;
use App\Models\User;
use App\Support\DocumentUrls;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The "Remind" an SDAO admin sends from the Stuck Documents page. Two
 * audiences, one Mailable: the approver a document is waiting on (links to
 * the review screen) or, when it was returned for revision, the
 * organization's officers (links to their own view of the document).
 * Reused by StuckDocumentReminderNotification::toArray() for the bell, so the
 * email subject and the in-app row always say the same thing.
 */
class StuckDocumentReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    /** Retry attempts for a transient mail-provider failure (rate limits, timeouts). */
    public int $tries = 3;

    public function __construct(
        public readonly User $recipient,
        public readonly Document $document,
        public readonly bool $toApprover,
        public readonly int $idleDays,
    ) {
        $this->document->loadMissing('organization');
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine());
    }

    public function subjectLine(): string
    {
        return $this->toApprover
            ? "Reminder: {$this->document->title} is waiting for your review"
            : "Reminder: {$this->document->title} needs your changes";
    }

    /** "5 days", "1 day", or "less than a day". */
    public function idleText(): string
    {
        return match (true) {
            $this->idleDays < 1 => 'less than a day',
            $this->idleDays === 1 => '1 day',
            default => "{$this->idleDays} days",
        };
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.stuck-document-reminder',
            with: [
                'recipientName' => $this->recipient->name,
                'formTypeLabel' => $this->document->form_type->label(),
                'organizationName' => $this->document->organization->name,
                'documentTitle' => $this->document->title,
                'idleText' => $this->idleText(),
                'toApprover' => $this->toApprover,
                'documentUrl' => $this->toApprover
                    ? DocumentUrls::forReviewer($this->document)
                    : DocumentUrls::forSubmitter($this->document),
            ],
        );
    }
}
