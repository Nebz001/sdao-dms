<?php

namespace App\Mail;

use App\Models\OfficerChangeRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Queued to the requesting officer the moment their officer change request
 * is declined (App\Organizations\Admin\DeclineOfficerChange). Dispatched via
 * ->queue() so a mail-provider failure never blocks the decision itself;
 * $tries/backoff() retry a transient failure before giving up to failed_jobs.
 */
class OfficerChangeDeclinedMail extends Mailable
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
            subject: "Update on your officer change request — {$this->changeRequest->organization->name}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.officer-change-declined',
            with: [
                'positionLabel' => $this->changeRequest->position->label(),
                'nomineeName' => $this->changeRequest->nominee->name,
                'organizationName' => $this->changeRequest->organization->name,
                'comment' => $this->changeRequest->decision_comment,
                'organizationUrl' => route('organizations.mine'),
            ],
        );
    }
}
