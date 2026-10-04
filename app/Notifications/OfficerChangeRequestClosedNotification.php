<?php

namespace App\Notifications;

use App\Mail\OfficerChangeRequestClosedMail;
use App\Models\OfficerChangeRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Notifications\Notification;

/**
 * Fired to the officer who filed an officer change request that was closed
 * automatically because its nominee can no longer be considered. Unlike the
 * request withdrawn when the REQUESTER loses their seat (silent — they have no
 * authority left), this requester is still a sitting officer waiting on an
 * answer that would otherwise never come, so they are told, and that they can
 * file again. It never says why the nominee is unavailable.
 */
class OfficerChangeRequestClosedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /** Retry attempts for a transient mail-provider failure (rate limits, timeouts). */
    public int $tries = 3;

    public function __construct(public readonly OfficerChangeRequest $changeRequest)
    {
        $this->changeRequest->loadMissing(['organization', 'nominee']);
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
        return (new OfficerChangeRequestClosedMail($this->changeRequest))->to($notifiable->email);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'officer_change_withdrawn',
            'title' => "Officer change request closed — {$this->changeRequest->organization->name}",
            'body' => "{$this->changeRequest->nominee->name} can no longer be considered, so your request was closed. You can file a new one.",
            'url' => route('organizations.officer-change.create', absolute: false),
            'document_id' => null,
            'form_type' => null,
            'organization' => $this->changeRequest->organization->name,
            'status' => 'withdrawn',
        ];
    }
}
