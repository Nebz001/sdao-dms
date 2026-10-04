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
 * Queued to a student the moment the organization's adviser binds them as an
 * officer (App\Organizations\BindOrganizationOfficer). ->queue() so a
 * mail-provider failure never blocks the bind itself.
 */
class OfficerSeatGrantedMail extends Mailable
{
    use Queueable, SerializesModels;

    /** Retry attempts for a transient mail-provider failure (rate limits, timeouts). */
    public int $tries = 3;

    public function __construct(
        public readonly Organization $organization,
        public readonly OfficerPosition $position,
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
            subject: "You're now {$this->position->label()} — {$this->organization->name}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.officer-seat-granted',
            with: [
                'positionLabel' => $this->position->label(),
                'organizationName' => $this->organization->name,
                'dashboardUrl' => route('dashboard'),
            ],
        );
    }
}
