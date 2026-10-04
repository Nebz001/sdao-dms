<?php

namespace App\Mail;

use App\Models\OrganizationJoinRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Queued to a student whose pending request to join an organization was closed
 * automatically because they have since become an officer elsewhere
 * (OrganizationMembershipService::withdrawPendingJoinRequestsFor). ->queue() so
 * a mail-provider failure never blocks the seat change that triggered it.
 */
class JoinRequestWithdrawnMail extends Mailable
{
    use Queueable, SerializesModels;

    /** Retry attempts for a transient mail-provider failure (rate limits, timeouts). */
    public int $tries = 3;

    public function __construct(public readonly OrganizationJoinRequest $joinRequest)
    {
        $this->joinRequest->loadMissing('organization');
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
            subject: "Your join request was closed — {$this->joinRequest->organization->name}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.join-request-withdrawn',
            with: [
                'organizationName' => $this->joinRequest->organization->name,
                'dashboardUrl' => route('dashboard'),
            ],
        );
    }
}
