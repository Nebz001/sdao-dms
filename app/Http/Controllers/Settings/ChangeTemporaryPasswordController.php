<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ChangeTemporaryPasswordRequest;
use App\Support\FlashToast;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The only page a user flagged must_change_password can use (see
 * EnsurePasswordIsChanged). Deliberately outside the settings layout.
 */
class ChangeTemporaryPasswordController extends Controller
{
    public function edit(Request $request): Response|RedirectResponse
    {
        if (! $request->user()->must_change_password) {
            return to_route('dashboard');
        }

        return Inertia::render('auth/change-password', [
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]);
    }

    public function update(ChangeTemporaryPasswordRequest $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user->must_change_password) {
            return to_route('dashboard');
        }

        $user->forceFill([
            'password' => $request->password,
            'must_change_password' => false,
        ])->save();

        // The user stays signed in here. Every other browser and every mobile
        // token that used the old password is ended.
        $user->endOtherSessions($request->session()->getId());
        $user->revokeAllApiTokens();

        return to_route('dashboard')
            ->with('flash', FlashToast::make('Password changed', 'You can now use the app.'));
    }
}
