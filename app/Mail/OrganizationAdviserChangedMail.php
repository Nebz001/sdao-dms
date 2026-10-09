<?php

namespace App\Mail;

use App\Models\Organization;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Queued to an organization's active officers when its adviser changes.
 */
class OrganizationAdviserChangedMail extends Mailable
{
    use Queueable, SerializesModels;

    /** Retry attempts for a transient mail-provider failure (rate limits, timeouts). */
    public int $tries = 3;

    public function __construct(
        public readonly Organization $organization,
        public readonly string $adviserName,
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
        return new Envelope(subject: "{$this->organization->name} has a new adviser");
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.organization-adviser-changed',
            with: [
                'organizationName' => $this->organization->name,
                'adviserName' => $this->adviserName,
                'organizationUrl' => route('organizations.mine'),
            ],
        );
    }
}
