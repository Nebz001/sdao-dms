<?php

namespace App\Notifications;

use App\Enums\Role;
use App\Mail\ApproverProvisionedMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Notifications\Notification;

/**
 * Fired the moment SDAO provisions a new approver account (trigger unchanged,
 * still ProvisionApprover::execute()). See ApproverHandOffNotification's
 * docblock for the mail/database channel split.
 *
 * The temporary password is carried on this notification (not read from a
 * shared constant) so the mailed content and the value ProvisionApprover
 * actually hashed can never drift apart. It is deliberately NOT included in
 * toArray() — that payload is persisted to the notifications table and shown
 * in the in-app bell, which is far less access-controlled than a single
 * email; only the mailed copy carries the password.
 */
class ApproverProvisionedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /** Retry attempts for a transient mail-provider failure (rate limits, timeouts). */
    public int $tries = 3;

    public function __construct(
        public readonly Role $role,
        public readonly string $temporaryPassword,
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
        return (new ApproverProvisionedMail($notifiable, $this->role, $this->temporaryPassword))->to($notifiable->email);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'approver_provisioned',
            'title' => 'Your SDAO approver account has been created',
            'body' => "You've been added as {$this->role->label()}. Check your email for your login details.",
            'url' => route('security.edit', absolute: false),
            'document_id' => null,
            'form_type' => null,
            'organization' => null,
            'status' => null,
        ];
    }
}
