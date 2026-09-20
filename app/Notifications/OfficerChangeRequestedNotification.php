<?php

namespace App\Notifications;

use App\Mail\OfficerChangeRequestedMail;
use App\Models\OfficerChangeRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Notifications\Notification;

/**
 * Fired to every SDAO member the moment a current president/secretary files
 * an officer change request (App\Organizations\RequestOfficerChange) — the
 * officer-change equivalent of invariant #9's approver hand-off. See
 * JoinRequestReceivedNotification's docblock for the mail/database channel
 * split.
 */
class OfficerChangeRequestedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /** Retry attempts for a transient mail-provider failure (rate limits, timeouts). */
    public int $tries = 3;

    public function __construct(public readonly OfficerChangeRequest $changeRequest)
    {
        $this->changeRequest->loadMissing(['organization', 'requester', 'nominee']);
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
        return (new OfficerChangeRequestedMail($notifiable, $this->changeRequest))->to($notifiable->email);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $mail = new OfficerChangeRequestedMail($notifiable, $this->changeRequest);

        return [
            'kind' => 'officer_change_requested',
            'title' => $mail->subjectLine(),
            'body' => "{$this->changeRequest->position->label()} change requested \u{2022} {$this->changeRequest->organization->name}",
            'url' => route('admin.officer-change-requests.index', absolute: false),
            'document_id' => null,
            'form_type' => null,
            'organization' => $this->changeRequest->organization->name,
            'status' => null,
        ];
    }
}
