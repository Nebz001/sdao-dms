<?php

namespace App\Notifications;

use App\Mail\OrganizationAdviserChangedMail;
use App\Models\Organization;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Notifications\Notification;

/**
 * Fired to an organization's active officers when SDAO changes the
 * organization's adviser, so they know who now reviews their documents and
 * manages their officers. Same mail + database split as the other seat notices.
 */
class OrganizationAdviserChangedNotification extends Notification implements ShouldQueue
{
    use Queueable;

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
        return (new OrganizationAdviserChangedMail($this->organization, $this->adviserName))->to($notifiable->email);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'organization_adviser_changed',
            'title' => "{$this->organization->name} has a new adviser",
            'body' => "Your adviser is now {$this->adviserName}. Anything waiting on your adviser goes to them.",
            'url' => route('organizations.mine', absolute: false),
            'document_id' => null,
            'form_type' => null,
            'organization' => $this->organization->name,
            'status' => null,
        ];
    }
}
