<?php

namespace App\Http\Controllers\Settings;

use App\Concerns\ProfileValidationRules;
use App\Http\Controllers\Controller;
use App\Identity\EmailVerification\EmailVerificationCodeService;
use App\Models\EmailVerificationCode;
use App\Models\User;
use App\Support\FlashToast;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A student's personal email: the address every notification is mailed at
 * (User::notificationEmail()). It has NO domain rule, so a typo would silently
 * stop all mail; a change is therefore never written directly. A code is sent
 * to the NEW address and the change only happens once it is entered, the same
 * gate the school email has (ProfileController). The school email, which is
 * the login, is a separate form with its own domain rule and its own code.
 */
class PersonalEmailController extends Controller
{
    use ProfileValidationRules;

    private const string PURPOSE = 'profile-personal-email-change';

    private const string SESSION_KEY = 'pending_personal_email';

    public function update(Request $request, EmailVerificationCodeService $codes): RedirectResponse
    {
        $user = $this->student($request);

        $data = $request->validate(
            ['personal_email' => $this->personalEmailRules()],
            $this->personalEmailMessages(),
        );
        $email = Str::lower(trim($data['personal_email']));

        if ($email === Str::lower((string) $user->personal_email)) {
            return to_route('profile.edit')
                ->with('flash', FlashToast::make('No change', 'That is already your personal email.', 'info'));
        }

        $codes->issue(email: $email, purpose: self::PURPOSE, userId: $user->id);

        $request->session()->put(self::SESSION_KEY, $email);

        return to_route('profile.verify-personal-email')
            ->with('flash', FlashToast::make('Check your personal email', "We sent a verification code to {$email} to confirm it.", 'info'));
    }

    public function verify(Request $request): RedirectResponse|Response
    {
        $this->student($request);
        $email = $request->session()->get(self::SESSION_KEY);

        if (! is_string($email)) {
            return $this->redirectToEditWithoutPendingChange();
        }

        return Inertia::render('settings/verify-email-change', [
            'kind' => 'personal',
            'email' => $email,
            'resendCooldownSeconds' => (int) config('school.verification_code.resend_cooldown_seconds'),
        ]);
    }

    public function verifyStore(Request $request, EmailVerificationCodeService $codes): RedirectResponse
    {
        $user = $this->student($request);
        $email = $request->session()->get(self::SESSION_KEY);

        if (! is_string($email)) {
            return $this->redirectToEditWithoutPendingChange();
        }

        $request->validate(['code' => ['required', 'string', 'size:6']]);

        $record = $codes->verify($email, self::PURPOSE, $request->string('code')->toString());

        $user->personal_email = $email;
        $user->save();

        $record->update(['user_id' => $user->id]);

        $request->session()->forget(self::SESSION_KEY);

        return to_route('profile.edit')
            ->with('flash', FlashToast::make('Personal email updated', 'Notifications will now be sent to your new address.'));
    }

    public function resend(Request $request, EmailVerificationCodeService $codes): RedirectResponse
    {
        $user = $this->student($request);
        $email = $request->session()->get(self::SESSION_KEY);

        if (! is_string($email)) {
            return $this->redirectToEditWithoutPendingChange();
        }

        $previous = EmailVerificationCode::query()
            ->where('email', $email)
            ->where('purpose', self::PURPOSE)
            ->whereNull('consumed_at')
            ->latest('id')
            ->first();

        $codes->issue($email, self::PURPOSE, userId: $previous?->user_id ?? $user->id);

        return to_route('profile.verify-personal-email')
            ->with('flash', FlashToast::make('New code sent', "We sent a fresh verification code to {$email}.", 'info'));
    }

    /**
     * Only a student account has a personal email to manage: everyone else has
     * the single address they were provisioned with.
     */
    private function student(Request $request): User
    {
        $user = $request->user();

        abort_unless($user->isStudentAccount(), 403);

        return $user;
    }

    private function redirectToEditWithoutPendingChange(): RedirectResponse
    {
        return to_route('profile.edit')
            ->with('flash', FlashToast::warning('Code expired', 'Start the personal email change again to receive a new code.'));
    }
}
