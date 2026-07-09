<?php

namespace App\Mail;

use App\Enums\FormType;
use App\Models\Document;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent synchronously to an approver the moment ApprovalEngine::activateStep
 * hands a document to them (invariant #9's actual delivery channel — the
 * trigger itself lives in RecordingApproverNotifier, unchanged).
 */
class ApproverHandOffMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $approver,
        public readonly Document $document,
        public readonly int $stepPosition,
    ) {
        $this->document->loadMissing('organization');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Action needed: {$this->document->title}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.approver-hand-off',
            with: [
                'approverName' => $this->approver->name,
                'formTypeLabel' => $this->document->form_type->label(),
                'organizationName' => $this->document->organization->name,
                'documentTitle' => $this->document->title,
                'reviewUrl' => $this->reviewUrl(),
            ],
        );
    }

    private function reviewUrl(): string
    {
        return match ($this->document->form_type) {
            FormType::OrganizationRegistration => route('review.registrations.show', $this->document),
            FormType::OrganizationRenewal => route('review.renewals.show', $this->document),
            FormType::ActivityCalendar => route('review.activity-calendars.show', $this->document),
            FormType::ActivityProposal => route('review.activity-proposals.show', $this->document),
            FormType::AfterActivityReport => route('review.reports.show', $this->document),
        };
    }
}
