<?php

namespace App\Mail;

use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\User;
use App\Support\DocumentUrls;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Queued to a document's original submitter the moment ApprovalEngine
 * reaches one of the three outcomes a student cannot otherwise see: final
 * approval, rejection, or return-for-revision (the trigger lives in
 * MailingSubmitterNotifier, called from ApprovalEngine::approve/reject/
 * returnForRevision). Dispatched via ->queue() so a mail-provider failure
 * never blocks the underlying document transition; $tries/backoff() retry a
 * transient failure before giving up to failed_jobs — mirrors
 * ApproverHandOffMail.
 */
class DocumentOutcomeMail extends Mailable
{
    use Queueable, SerializesModels;

    /** Retry attempts for a transient mail-provider failure (rate limits, timeouts). */
    public int $tries = 3;

    public function __construct(
        public readonly User $submitter,
        public readonly Document $document,
        public readonly DocumentStatus $outcome,
        public readonly ?string $comment = null,
    ) {
        $this->document->loadMissing('organization');
    }

    /**
     * Seconds to wait before each retry, spacing attempts past a transient
     * provider rate limit.
     *
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: match ($this->outcome) {
                DocumentStatus::Approved => "Approved: {$this->document->title}",
                DocumentStatus::Rejected => "Rejected: {$this->document->title}",
                DocumentStatus::Returned => "Returned for revision: {$this->document->title}",
                default => "Update on: {$this->document->title}",
            },
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.document-outcome',
            with: [
                'submitterName' => $this->submitter->name,
                'outcomeLabel' => $this->outcomeLabel(),
                'formTypeLabel' => $this->document->form_type->label(),
                'organizationName' => $this->document->organization->name,
                'documentTitle' => $this->document->title,
                'comment' => $this->comment,
                'documentUrl' => DocumentUrls::forSubmitter($this->document),
            ],
        );
    }

    /**
     * Also reused by DocumentOutcomeNotification::toArray() (the bell's copy
     * of this same wording) so the email subject/body and the in-app row can
     * never say something different for the same outcome.
     */
    public function outcomeLabel(): string
    {
        return match ($this->outcome) {
            DocumentStatus::Approved => 'approved',
            DocumentStatus::Rejected => 'rejected',
            DocumentStatus::Returned => 'returned for revision',
            default => 'updated',
        };
    }
}
