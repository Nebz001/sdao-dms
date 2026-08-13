<?php

namespace App\Mail;

use App\Models\OrganizationJoinRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Queued to the requesting student the moment their join request is
 * declined (App\Organizations\DeclineJoinRequest). Dispatched via ->queue()
 * so a mail-provider failure never blocks the decision itself; $tries/backoff()
 * retry a transient failure before giving up to failed_jobs.
 */
class JoinRequestDeclinedMail extends Mailable
{
    use Queueable, SerializesModels;

    /** Retry attempts for a transient mail-provider failure (rate limits, timeouts). */
    public int $tries = 3;

    public function __construct(public readonly OrganizationJoinRequest $joinRequest)
    {
        $this->joinRequest->loadMissing(['user', 'organization']);
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
        return new Envelope(
            subject: "Update on your request to join {$this->joinRequest->organization->name}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.join-request-declined',
            with: [
                'studentName' => $this->joinRequest->user->name,
                'organizationName' => $this->joinRequest->organization->name,
                'comment' => $this->joinRequest->decision_comment,
                'dashboardUrl' => route('dashboard'),
            ],
        );
    }
}
