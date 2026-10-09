<?php

namespace App\Mail;

use App\Models\Organization;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Queued to an adviser whose role for an organization just ended. Never names
 * the replacement.
 */
class AdviserRoleEndedMail extends Mailable
{
    use Queueable, SerializesModels;

    /** Retry attempts for a transient mail-provider failure (rate limits, timeouts). */
    public int $tries = 3;

    public function __construct(
        public readonly Organization $organization,
        public readonly bool $accountDeactivated,
    ) {}

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Your adviser role has ended — {$this->organization->name}");
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.adviser-role-ended',
            with: [
                'organizationName' => $this->organization->name,
                'accountDeactivated' => $this->accountDeactivated,
            ],
        );
    }
}
