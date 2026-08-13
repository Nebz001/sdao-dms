<?php

namespace App\Notifications;

use App\Mail\JoinRequestReceivedMail;
use App\Models\OrganizationJoinRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Notifications\Notification;

/**
 * Fired to an org's adviser and active officers the moment a student files
 * a request to join (App\Organizations\RequestToJoinOrganization) — the
 * join-request equivalent of invariant #9's approver hand-off. See
 * ApproverHandOffNotification's docblock for the mail/database channel split.
 */
class JoinRequestReceivedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /** Retry attempts for a transient mail-provider failure (rate limits, timeouts). */
    public int $tries = 3;

    public function __construct(public readonly OrganizationJoinRequest $joinRequest)
    {
        $this->joinRequest->loadMissing(['user', 'organization']);
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
        return (new JoinRequestReceivedMail($notifiable, $this->joinRequest))->to($notifiable->email);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $mail = new JoinRequestReceivedMail($notifiable, $this->joinRequest);

        return [
            'kind' => 'join_request_received',
            'title' => $mail->subjectLine(),
            'body' => "Requesting to join \u{2022} {$this->joinRequest->organization->name}",
            'url' => route('review.join-requests.index'),
            'document_id' => null,
            'form_type' => null,
            'organization' => $this->joinRequest->organization->name,
            'status' => null,
        ];
    }
}
