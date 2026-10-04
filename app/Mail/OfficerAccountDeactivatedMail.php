<?php

namespace App\Mail;

use App\Enums\OfficerPosition;
use App\Models\Organization;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Queued to an organization's adviser when SDAO deactivates the account of one
 * of its officers (App\Identity\Admin\DeactivateAccount), so a seat that ended
 * without the adviser's involvement never leaves the org short of officers
 * unannounced. ->queue() so a mail-provider failure never blocks the
 * deactivation itself.
 */
class OfficerAccountDeactivatedMail extends Mailable
{
    use Queueable, SerializesModels;

    /** Retry attempts for a transient mail-provider failure (rate limits, timeouts). */
    public int $tries = 3;

    public function __construct(
        public readonly Organization $organization,
        public readonly string $officerName,
        public readonly OfficerPosition $position,
        public readonly int $remainingOfficers,
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
        return new Envelope(
            subject: "{$this->position->label()} seat ended — {$this->organization->name}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.officer-account-deactivated',
            with: [
                'organizationName' => $this->organization->name,
                'officerName' => $this->officerName,
                'positionLabel' => $this->position->label(),
                'remainingOfficers' => $this->remainingOfficers,
                'officersUrl' => route('officers.index', $this->organization),
            ],
        );
    }
}
