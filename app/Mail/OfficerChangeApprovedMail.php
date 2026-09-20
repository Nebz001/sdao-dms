<?php

namespace App\Mail;

use App\Models\OfficerChangeRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Queued to the requesting officer and the nominee the moment their officer
 * change request is approved (App\Organizations\Admin\ApproveOfficerChange).
 * Dispatched via ->queue() so a mail-provider failure never blocks the
 * underlying turnover; $tries/backoff() retry a transient failure before
 * giving up to failed_jobs.
 */
class OfficerChangeApprovedMail extends Mailable
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
            subject: "Officer change approved — {$this->changeRequest->organization->name}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.officer-change-approved',
            with: [
                'nomineeName' => $this->changeRequest->nominee->name,
                'positionLabel' => $this->changeRequest->position->label(),
                'organizationName' => $this->changeRequest->organization->name,
                'organizationUrl' => route('organizations.mine'),
            ],
        );
    }
}
