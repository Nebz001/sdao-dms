<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

class ResetUserPassword implements ResetsUserPasswords
{
    use PasswordValidationRules;

    /**
     * Validate and reset the user's forgotten password.
     *
     * @param  array<string, string>  $input
     */
    public function reset(User $user, array $input): void
    {
        Validator::make($input, [
            'password' => $this->passwordRules(),
        ])->validate();

        // A reset link proves control of the mailbox, so a temporary password
        // no longer needs a separate forced change.
        $attributes = ['password' => $input['password']];

        if ($user->must_change_password) {
            $attributes['must_change_password'] = false;
        }

        $user->forceFill($attributes)->save();

        $user->revokeAllApiTokens();
    }
}
