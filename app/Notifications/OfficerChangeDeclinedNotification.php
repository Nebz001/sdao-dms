<?php

namespace App\Notifications;

use App\Mail\OfficerChangeDeclinedMail;
use App\Models\OfficerChangeRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Fired to the requesting officer the moment an SDAO admin declines their
 * officer change request (App\Organizations\Admin\DeclineOfficerChange).
 */
class OfficerChangeDeclinedNotification extends Notification implements ShouldQueue
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
        return (new OfficerChangeDeclinedMail($this->changeRequest))->to($notifiable->email);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'officer_change_declined',
            'title' => "Officer change declined — {$this->changeRequest->organization->name}",
            // SDAO's reason, when they gave one — the in-app notice is often
            // the only place a requester looks. Capped for the bell; the mail
            // carries it in full.
            'body' => $this->changeRequest->decision_comment
                ? 'Not approved this time. Reason: '.Str::limit(preg_replace('/\s+/', ' ', trim($this->changeRequest->decision_comment)), 160)
                : 'Your officer change request was not approved this time.',
            'url' => route('organizations.mine', absolute: false),
            'document_id' => null,
            'form_type' => null,
            'organization' => $this->changeRequest->organization->name,
            'status' => 'declined',
        ];
    }
}
