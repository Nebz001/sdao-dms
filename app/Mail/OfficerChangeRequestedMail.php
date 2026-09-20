<?php

namespace App\Mail;

use App\Models\OfficerChangeRequest;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Queued to every SDAO member the moment an officer files a change request
 * (App\Organizations\RequestOfficerChange). Dispatched via ->queue() so a
 * mail-provider failure never blocks the request itself; $tries/backoff()
 * retry a transient failure before giving up to failed_jobs.
 */
class OfficerChangeRequestedMail extends Mailable
{
    use Queueable, SerializesModels;

    /** Retry attempts for a transient mail-provider failure (rate limits, timeouts). */
    public int $tries = 3;

    public function __construct(
        public readonly User $recipient,
        public readonly OfficerChangeRequest $changeRequest,
    ) {
        $this->changeRequest->loadMissing(['organization', 'requester', 'nominee']);
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

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.officer-change-requested',
            with: [
                'recipientName' => $this->recipient->name,
                'requesterName' => $this->changeRequest->requester->name,
                'positionLabel' => $this->changeRequest->position->label(),
                'nomineeName' => $this->changeRequest->nominee->name,
                'organizationName' => $this->changeRequest->organization->name,
                'reason' => $this->changeRequest->reason,
                'reviewUrl' => route('admin.officer-change-requests.index'),
            ],
        );
    }

    /**
     * Also reused by OfficerChangeRequestedNotification::toArray() so the
     * mail subject and the bell's title can never say something different
     * for the same event.
     */
    public function subjectLine(): string
    {
        return "Officer change requested — {$this->changeRequest->organization->name}";
    }
}
