<?php

namespace App\Notifications;

use App\Enums\OfficerPosition;
use App\Enums\OfficerSeatEndReason;
use App\Mail\OfficerSeatEndedMail;
use App\Models\Organization;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Notifications\Notification;

/**
 * Fired to an officer the moment their seat ends — replaced by an adviser
 * bind or an approved change request, or deactivated from Manage Officers.
 * Until now they lost access with no word at all. Carries only the fact and a
 * coarse reason (OfficerSeatEndReason) — never the replacement's name or any
 * change-request details, which are the organization's business.
 */
class OfficerSeatEndedNotification extends Notification implements ShouldQueue
{
    use Queueable;

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

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * @return array<string, string>
     */
    public function viaConnections(): array
    {
        return ['database' => 'sync', 'mail' => 'database'];
    }

    public function toMail(object $notifiable): Mailable
    {
        return (new OfficerSeatEndedMail($this->organization, $this->position, $this->reason))->to($notifiable->email);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'officer_seat_ended',
            'title' => "Your {$this->position->label()} seat has ended — {$this->organization->name}",
            'body' => $this->reason->sentence(),
            'url' => route('dashboard', absolute: false),
            'document_id' => null,
            'form_type' => null,
            'organization' => $this->organization->name,
            'status' => 'ended',
        ];
    }
}
