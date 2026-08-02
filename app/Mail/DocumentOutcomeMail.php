<?php

namespace App\Mail;

use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Models\Document;
use App\Models\User;
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
                'documentUrl' => $this->documentUrl(),
            ],
        );
    }

    private function outcomeLabel(): string
    {
        return match ($this->outcome) {
            DocumentStatus::Approved => 'approved',
            DocumentStatus::Rejected => 'rejected',
            DocumentStatus::Returned => 'returned for revision',
            default => 'updated',
        };
    }

    private function documentUrl(): string
    {
        return match ($this->document->form_type) {
            FormType::OrganizationRegistration => route('registrations.show', $this->document),
            FormType::OrganizationRenewal => route('renewals.show', $this->document),
            FormType::ActivityCalendar => route('activity-calendars.show', $this->document),
            FormType::ActivityProposal => route('activity-proposals.show', $this->document),
            FormType::AfterActivityReport => route('reports.show', $this->document),
        };
    }
}
