<?php

namespace App\Mail;

use App\Models\OrganizationJoinRequest;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Queued to an org's adviser/officers the moment a student files a request
 * to join (App\Organizations\RequestToJoinOrganization) — the join-request
 * equivalent of ApproverHandOffMail's hand-off. Dispatched via ->queue() so
 * a mail-provider failure never blocks the request itself; $tries/backoff()
 * retry a transient failure before giving up to failed_jobs.
 */
class JoinRequestReceivedMail extends Mailable
{
    use Queueable, SerializesModels;

    /** Retry attempts for a transient mail-provider failure (rate limits, timeouts). */
    public int $tries = 3;

    public function __construct(
        public readonly User $recipient,
        public readonly OrganizationJoinRequest $joinRequest,
    ) {
        $this->joinRequest->loadMissing(['user', 'organization']);
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
        return new Envelope(subject: $this->subjectLine());
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.join-request-received',
            with: [
                'recipientName' => $this->recipient->name,
                'studentName' => $this->joinRequest->user->name,
                'studentEmail' => $this->joinRequest->user->email,
                'organizationName' => $this->joinRequest->organization->name,
                'reviewUrl' => route('review.join-requests.index'),
            ],
        );
    }

    /**
     * Also reused by JoinRequestReceivedNotification::toArray() so the mail
     * subject and the bell's title can never say something different for
     * the same event.
     */
    public function subjectLine(): string
    {
        return "Join request: {$this->joinRequest->user->name} wants to join {$this->joinRequest->organization->name}";
    }
}
