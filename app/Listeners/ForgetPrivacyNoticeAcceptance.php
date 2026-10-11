<?php

namespace App\Listeners;

use App\Support\PrivacyNotice;
use Illuminate\Auth\Events\Login;

/**
 * A new login always asks again: password, passkey, remember-me and the
 * post-registration login all fire Login. Mobile api tokens have no session.
 */
class ForgetPrivacyNoticeAcceptance
{
    public function handle(Login $event): void
    {
        $request = request();

        if ($request->hasSession()) {
            PrivacyNotice::forget($request->session());
        }
    }
}
