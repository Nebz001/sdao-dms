<?php

namespace App\Notifications;

use App\Mail\JoinRequestApprovedMail;
use App\Models\OrganizationJoinRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Notifications\Notification;

/**
 * Fired to the requesting student the moment an adviser or active officer
 * approves their join request (App\Organizations\ApproveJoinRequest). See
 * AccountVerifiedNotification's docblock for the mail/database channel split.
 */
class JoinRequestApprovedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /** Retry attempts for a transient mail-provider failure (rate limits, timeouts). */
    public int $tries = 3;

    public function __construct(public readonly OrganizationJoinRequest $joinRequest)
    {
        $this->joinRequest->loadMissing('organization');
    }

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
        return (new JoinRequestApprovedMail($this->joinRequest))->to($notifiable->email);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'join_request_approved',
            'title' => "You're in — {$this->joinRequest->organization->name}",
            'body' => 'Your request to join was approved. You now have officer access.',
            'url' => route('dashboard', absolute: false),
            'document_id' => null,
            'form_type' => null,
            'organization' => $this->joinRequest->organization->name,
            'status' => 'approved',
        ];
    }
}
