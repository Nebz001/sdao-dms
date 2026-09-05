<?php

namespace App\Notifications;

use App\Mail\RenewalWindowOpenedMail;
use App\Support\AcademicPeriod;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Notifications\Notification;

/**
 * Fired to the active officers of every organization whose renewal just came
 * due, the moment SDAO advances the current term to 3rd
 * (App\Renewals\OpenRenewalSeason) — the renewal-season equivalent of
 * invariant #9's approver hand-off. See ApproverHandOffNotification's
 * docblock for the mail/database channel split.
 */
class RenewalWindowOpenedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /** Retry attempts for a transient mail-provider failure (rate limits, timeouts). */
    public int $tries = 3;

    public function __construct(public readonly AcademicPeriod $period) {}

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
        return (new RenewalWindowOpenedMail($notifiable, $this->period))->to($notifiable->email);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $mail = new RenewalWindowOpenedMail($notifiable, $this->period);

        return [
            'kind' => 'renewal_window_opened',
            'title' => $mail->subjectLine(),
            'body' => "Renewal is now open for {$this->period->nextAcademicYear()}.",
            // Relative — NotificationPresenter::toRelativePath() requires it
            // for Inertia's XHR router.visit() to follow the link.
            'url' => route('renewals.create', absolute: false),
            'document_id' => null,
            'form_type' => null,
            'organization' => null,
            'status' => null,
        ];
    }
}
