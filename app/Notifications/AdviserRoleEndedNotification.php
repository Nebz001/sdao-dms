<?php

namespace App\Notifications;

use App\Mail\AdviserRoleEndedMail;
use App\Models\Organization;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Notifications\Notification;

/**
 * Fired to an adviser the moment SDAO replaces them on an organization. Carries
 * only the fact — never who replaced them, which is the organization's
 * business, the same rule as OfficerSeatEndedNotification. A deactivated
 * account can't sign in to see the bell, so it gets mail only.
 */
class AdviserRoleEndedNotification extends Notification implements ShouldQueue
{
    use Queueable;

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

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return $this->accountDeactivated ? ['mail'] : ['mail', 'database'];
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
        return (new AdviserRoleEndedMail($this->organization, $this->accountDeactivated))->to($notifiable->email);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'adviser_role_ended',
            'title' => "Your adviser role has ended — {$this->organization->name}",
            'body' => 'You are no longer the adviser of this organization. Documents you already reviewed stay in your history.',
            'url' => route('dashboard', absolute: false),
            'document_id' => null,
            'form_type' => null,
            'organization' => $this->organization->name,
            'status' => 'ended',
        ];
    }
}
