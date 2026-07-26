<?php

namespace App\Http\Requests\Settings;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Laravel\Fortify\InteractsWithTwoFactorState;

class TwoFactorAuthenticationRequest extends FormRequest
{
    use InteractsWithTwoFactorState;

    /**
     * Grace period, in seconds, given to a user mid-2FA-setup before their
     * unconfirmed secret is treated as abandoned and wiped. See the override
     * of neverFinishedConfirmingTwoFactorAuthentication() below for why this
     * exists — 15 minutes covers a first-time authenticator-app install +
     * scan + a brief interruption, while staying far tighter than this
     * page's own password-confirmation (3h) and session (120min) ceilings.
     */
    public const CONFIRMATION_GRACE_SECONDS = 900;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [];
    }

    /**
     * Overrides Laravel\Fortify\InteractsWithTwoFactorState's version, which
     * is:
     *
     *     return ! $this->session()->hasOldInput('code') &&
     *         is_null($this->user()->two_factor_confirmed_at) &&
     *         $this->session()->get('two_factor_confirming_at', 0) != $currentTime;
     *
     * `two_factor_confirming_at` is stamped once, on the single request
     * immediately after "Enable 2FA." That `!= $currentTime` check is an
     * exact-Unix-second match — only the request that just set the value can
     * ever satisfy it. Every subsequent GET to this page (a refresh, browser
     * back/forward, switching to another settings tab and back) before the
     * user finishes confirming would otherwise trip this and silently wipe
     * their in-progress two_factor_secret via DisableTwoFactorAuthentication,
     * with no signal to the frontend. Replaced with a grace-period window
     * that preserves the original "clean up abandoned setups" intent without
     * requiring the confirmation to complete in a single request cycle.
     */
    protected function neverFinishedConfirmingTwoFactorAuthentication(int $currentTime): bool
    {
        if ($this->session()->hasOldInput('code')) {
            return false;
        }

        if (! is_null($this->user()->two_factor_confirmed_at)) {
            return false;
        }

        $confirmingAt = (int) $this->session()->get('two_factor_confirming_at', 0);

        return $confirmingAt < $currentTime - self::CONFIRMATION_GRACE_SECONDS;
    }
}
