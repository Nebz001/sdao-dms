<?php

namespace App\Mail;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Queued to a newly admin-provisioned approver the moment SDAO creates their
 * account (ProvisionApprover::execute). The account already has a real,
 * working password by the time this mail is dispatched — this is how the
 * approver learns it, not how the account becomes usable. Dispatched via the
 * owning notification's mail channel (queued) so a mail-provider failure
 * never blocks provisioning itself; $tries/backoff() retry a transient
 * failure before giving up to failed_jobs.
 */
class ApproverProvisionedMail extends Mailable
{
    use Queueable, SerializesModels;

    /** Retry attempts for a transient mail-provider failure (rate limits, timeouts). */
    public int $tries = 3;

    public function __construct(
        public readonly User $account,
        public readonly Role $role,
        public readonly string $temporaryPassword,
    ) {}

    /**
     * Seconds to wait before each retry, spacing attempts past a transient
     * provider rate limit.
     *
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your SDAO approver account has been created',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.approver-provisioned',
            with: [
                'accountName' => $this->account->name,
                'roleLabel' => $this->role->label(),
                'email' => $this->account->email,
                'temporaryPassword' => $this->temporaryPassword,
                'loginUrl' => route('login'),
                'securityUrl' => route('security.edit'),
            ],
        );
    }
}
