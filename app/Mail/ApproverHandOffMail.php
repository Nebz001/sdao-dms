<?php

namespace App\Mail;

use App\Enums\FormType;
use App\Enums\TransitionAction;
use App\Models\Document;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Queued to an approver the moment ApprovalEngine::activateStep hands a
 * document to them (invariant #9's actual delivery channel — the trigger
 * itself lives in RecordingApproverNotifier, unchanged). Dispatched via
 * ->queue() rather than ->send() so mail-provider latency/rate-limits are
 * never on the request path; $tries/backoff() retry a transient provider
 * failure (e.g. Mailtrap's per-second rate limit) with spacing before giving
 * up to failed_jobs.
 *
 * $triggerAction distinguishes a genuine first hand-off (Submitted, Advanced)
 * from a Resubmitted reactivation of the SAME step the recipient already
 * reviewed and returned (invariant #2: current_step_position holds at the
 * returning step). Wording-only — see mail.approver-hand-off.
 */
class ApproverHandOffMail extends Mailable
{
    use Queueable, SerializesModels;

    /** Retry attempts for a transient mail-provider failure (rate limits, timeouts). */
    public int $tries = 3;

    public function __construct(
        public readonly User $approver,
        public readonly Document $document,
        public readonly int $stepPosition,
        public readonly TransitionAction $triggerAction,
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
        $subject = $this->triggerAction === TransitionAction::Resubmitted
            ? "Resubmitted for your review: {$this->document->title}"
            : "Action needed: {$this->document->title}";

        return new Envelope(subject: $subject);
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
                'isResubmission' => $this->triggerAction === TransitionAction::Resubmitted,
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
