<?php

namespace App\Mail;

use App\Models\OfficerChangeRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Queued to the officer who filed an officer change request that the system
 * closed because its nominee can no longer be considered
 * (OrganizationMembershipService::withdrawPendingChangeRequestsNaming). Says
 * nothing about WHY the nominee is unavailable. ->queue() so a mail-provider
 * failure never blocks the deactivation that triggered it.
 */
class OfficerChangeRequestClosedMail extends Mailable
{
    use Queueable, SerializesModels;

    /** Retry attempts for a transient mail-provider failure (rate limits, timeouts). */
    public int $tries = 3;

    public function __construct(public readonly OfficerChangeRequest $changeRequest)
    {
        $this->changeRequest->loadMissing(['organization', 'nominee']);
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
            subject: "Your officer change request was closed — {$this->changeRequest->organization->name}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.officer-change-request-closed',
            with: [
                'positionLabel' => $this->changeRequest->position->label(),
                'nomineeName' => $this->changeRequest->nominee->name,
                'organizationName' => $this->changeRequest->organization->name,
                'requestUrl' => route('organizations.officer-change.create'),
            ],
        );
    }
}
