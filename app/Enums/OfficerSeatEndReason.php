<?php

namespace App\Enums;

/**
 * Why an officer's seat ended, for the notice sent to that officer
 * (App\Notifications\OfficerSeatEndedNotification). Deliberately coarse: the
 * notice tells someone who no longer holds a seat THAT it ended and roughly
 * why, never who replaced them or any request details — that is the
 * organization's business, not theirs.
 */
enum OfficerSeatEndReason: string
{
    /** Someone else was bound or approved into the seat (adviser bind or an approved change request). */
    case Replaced = 'replaced';

    /** The organization's adviser deactivated the officer from Manage Officers. */
    case Deactivated = 'deactivated';

    public function sentence(): string
    {
        return match ($this) {
            self::Replaced => 'The organization\'s officers were changed.',
            self::Deactivated => 'The organization\'s adviser deactivated your officer seat.',
        };
    }
}
