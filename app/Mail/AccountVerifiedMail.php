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
 * account Verified via the Pending Accounts queue (VerifyAccount::execute).
 */
class AccountVerifiedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly User $account) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your SDAO account has been verified',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.account-verified',
            with: [
                'accountName' => $this->account->name,
                'loginUrl' => route('login'),
            ],
        );
    }
}
