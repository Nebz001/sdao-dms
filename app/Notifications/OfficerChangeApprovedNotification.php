<?php

namespace App\Notifications;

use App\Mail\OfficerChangeApprovedMail;
use App\Models\OfficerChangeRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Notifications\Notification;

/**
 * Fired to the requesting officer and the nominee the moment an SDAO admin
 * approves an officer change request
 * (App\Organizations\Admin\ApproveOfficerChange) — sent to both recipients
 * with the same content, since neither the requester nor the nominee needs
 * a different message for "the change went through."
 */
class OfficerChangeApprovedNotification extends Notification implements ShouldQueue
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
        return (new OfficerChangeApprovedMail($this->changeRequest))->to($notifiable->email);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'officer_change_approved',
            'title' => "Officer change approved — {$this->changeRequest->organization->name}",
            'body' => "{$this->changeRequest->nominee->name} is now {$this->changeRequest->position->label()}.",
            'url' => route('organizations.mine', absolute: false),
            'document_id' => null,
            'form_type' => null,
            'organization' => $this->changeRequest->organization->name,
            'status' => 'approved',
        ];
    }
}
