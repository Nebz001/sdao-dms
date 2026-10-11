<?php

namespace App\Support;

use Illuminate\Contracts\Session\Session;

/**
 * Per login acceptance of the Privacy Notice. Acceptance lives in the session,
 * so logging out, expiry and the idle timeout all end it, and every login
 * (App\Listeners\ForgetPrivacyNoticeAcceptance) clears it again. The durable
 * record is the append-only privacy_notice_acceptances table.
 */
final class PrivacyNotice
{
    /** Bump when the notice text changes; it is stored with every acceptance. */
    public const string VERSION = '2026-10-11';

    public const string SESSION_KEY = 'privacy_notice_accepted';

    /** Where to send the user once they accept (the page they were headed to). */
    public const string INTENDED_KEY = 'privacy_notice_intended';

    public static function isAccepted(Session $session): bool
    {
        return $session->get(self::SESSION_KEY) === self::VERSION;
    }

    public static function markAccepted(Session $session): void
    {
        $session->put(self::SESSION_KEY, self::VERSION);
    }

    public static function forget(Session $session): void
    {
        $session->forget(self::SESSION_KEY);
    }
}
