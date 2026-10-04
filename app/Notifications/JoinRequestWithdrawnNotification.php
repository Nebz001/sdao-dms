<?php

namespace App\Notifications;

use App\Mail\JoinRequestWithdrawnMail;
use App\Models\OrganizationJoinRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Notifications\Notification;

/**
 * Fired to a student whose pending join request was closed automatically
 * because they became an officer by another route. Sent ONLY when the request
 * was for a DIFFERENT organization than the one they just joined — if they
 * asked to join the very org that then bound them, the "you're now an officer"
 * notice already says everything and a second one would be noise. The student
 * is the one person who may still be waiting on an answer that will never come.
 */
class JoinRequestWithdrawnNotification extends Notification implements ShouldQueue
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
        return (new JoinRequestWithdrawnMail($this->joinRequest))->to($notifiable->email);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'join_request_withdrawn',
            'title' => "Join request closed — {$this->joinRequest->organization->name}",
            'body' => 'You became an officer of an organization, so this request was closed. Nothing more to do.',
            'url' => route('dashboard', absolute: false),
            'document_id' => null,
            'form_type' => null,
            'organization' => $this->joinRequest->organization->name,
            'status' => 'withdrawn',
        ];
    }
}
