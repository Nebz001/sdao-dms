<?php

namespace App\Notifications;

use App\Enums\OfficerPosition;
use App\Mail\OfficerSeatGrantedMail;
use App\Models\Organization;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Notifications\Notification;

/**
 * Fired to a student the moment the organization's adviser binds them as an
 * officer through Manage Officers (App\Organizations\BindOrganizationOfficer).
 * The other two ways to gain a seat already notify their own recipients
 * (an approved officer change request -> OfficerChangeApprovedNotification,
 * an approved join request -> JoinRequestApprovedNotification), so every path
 * to a seat now tells the person who received it.
 */
class OfficerSeatGrantedNotification extends Notification implements ShouldQueue
{
    use Queueable;

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
        return (new OfficerSeatGrantedMail($this->organization, $this->position))->to($notifiable->email);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'officer_seat_granted',
            'title' => "You're now {$this->position->label()} — {$this->organization->name}",
            'body' => 'You can now submit documents for this organization.',
            'url' => route('dashboard', absolute: false),
            'document_id' => null,
            'form_type' => null,
            'organization' => $this->organization->name,
            'status' => 'approved',
        ];
    }
}
