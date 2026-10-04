<?php

namespace App\Notifications;

use App\Enums\OfficerPosition;
use App\Mail\OfficerAccountDeactivatedMail;
use App\Models\Organization;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Notifications\Notification;

/**
 * Fired to an organization's adviser when SDAO deactivates one of its
 * officers' accounts (App\Identity\Admin\DeactivateAccount). The seat ends
 * immediately, without the adviser being involved, so they are told which seat
 * emptied and how many active officers remain.
 */
class OfficerAccountDeactivatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

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
        return (new OfficerAccountDeactivatedMail($this->organization, $this->officerName, $this->position, $this->remainingOfficers))->to($notifiable->email);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $remaining = match (true) {
            $this->remainingOfficers === 0 => 'The organization now has no active officers.',
            $this->remainingOfficers === 1 => 'One active officer remains.',
            default => "{$this->remainingOfficers} active officers remain.",
        };

        return [
            'kind' => 'officer_account_deactivated',
            'title' => "{$this->position->label()} seat ended — {$this->organization->name}",
            'body' => "{$this->officerName}'s account was deactivated by SDAO. {$remaining}",
            'url' => route('officers.index', $this->organization, absolute: false),
            'document_id' => null,
            'form_type' => null,
            'organization' => $this->organization->name,
            'status' => 'ended',
        ];
    }
}
