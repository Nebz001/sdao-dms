<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent synchronously to a self-registered student the moment SDAO marks their
 * account Rejected via the Pending Accounts queue (RejectAccount::execute).
 */
class AccountRejectedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly User $account) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your SDAO account application was not approved',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.account-rejected',
            with: [
                'accountName' => $this->account->name,
            ],
        );
    }
}
