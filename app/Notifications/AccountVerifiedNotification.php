<?php

namespace App\Notifications;

use App\Mail\AccountVerifiedMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Notifications\Notification;

/**
 * Fired the moment SDAO marks a self-registered student's account Verified
 * (trigger unchanged, still VerifyAccount::execute()). See
 * ApproverHandOffNotification's docblock for the mail/database channel split.
 */
class AccountVerifiedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /** Retry attempts for a transient mail-provider failure (rate limits, timeouts). */
    public int $tries = 3;

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
        return (new AccountVerifiedMail($notifiable))->to($notifiable->email);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'account_verified',
            'title' => 'Your SDAO account has been verified',
            'body' => 'You can now submit documents and be bound to an organization.',
            'url' => route('dashboard'),
            'document_id' => null,
            'form_type' => null,
            'organization' => null,
            'status' => null,
        ];
    }
}
