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
 * Tells an organization's officers an approver left a remark on one of their
 * documents. A remark is a note, not a decision: nothing about the
 * document's status changed, and the wording must never read as if it had.
 * Reused by DocumentRemarkNotification::toArray() for the bell, so the email
 * subject and the in-app row always say the same thing.
 */
class DocumentRemarkMail extends Mailable
{
    use Queueable, SerializesModels;

    /** Retry attempts for a transient mail-provider failure (rate limits, timeouts). */
    public int $tries = 3;

    public function __construct(
        public readonly User $recipient,
        public readonly Document $document,
        public readonly string $authorName,
        public readonly string $body,
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
        return "New remark on: {$this->document->title}";
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.document-remark',
            with: [
                'recipientName' => $this->recipient->name,
                'authorName' => $this->authorName,
                'formTypeLabel' => $this->document->form_type->label(),
                'organizationName' => $this->document->organization->name,
                'documentTitle' => $this->document->title,
                'remark' => $this->body,
                'documentUrl' => DocumentUrls::forSubmitter($this->document),
            ],
        );
    }
}
