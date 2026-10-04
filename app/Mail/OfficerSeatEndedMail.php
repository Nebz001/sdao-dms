<?php

namespace App\Mail;

use App\Enums\OfficerPosition;
use App\Enums\OfficerSeatEndReason;
use App\Models\Organization;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Queued to an officer whose seat just ended — replaced by a bind or an
 * approved change request, or deactivated by the adviser. ->queue() so a
 * mail-provider failure never blocks the change itself.
 */
class OfficerSeatEndedMail extends Mailable
{
    use Queueable, SerializesModels;

    /** Retry attempts for a transient mail-provider failure (rate limits, timeouts). */
    public int $tries = 3;

    public function __construct(
        public readonly Organization $organization,
        public readonly OfficerPosition $position,
        public readonly OfficerSeatEndReason $reason,
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
            subject: "Your {$this->position->label()} seat has ended — {$this->organization->name}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.officer-seat-ended',
            with: [
                'positionLabel' => $this->position->label(),
                'organizationName' => $this->organization->name,
                'reasonSentence' => $this->reason->sentence(),
            ],
        );
    }
}
