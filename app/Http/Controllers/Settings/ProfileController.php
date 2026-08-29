<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileDeleteRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Identity\EmailVerification\EmailVerificationCodeService;
use App\Models\EmailVerificationCode;
use App\Registrations\WithdrawInFlightRegistrations;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    private const string EMAIL_CHANGE_PURPOSE = 'profile-email-change';

    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/profile', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Update the user's profile information. A school-email change is never
     * written directly here — it's gated behind a verification code (see
     * verifyEmail*()) so the account's email can't change to an unproven
     * address, matching the same rule registration enforces.
     */
    public function update(ProfileUpdateRequest $request, EmailVerificationCodeService $codes): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validated();
        $emailUnchanged = $data['email'] === $user->email;

        $user->name = $data['name'];
        $user->save();

        if ($emailUnchanged) {
            return to_route('profile.edit')->with('flash', ['message' => __('Profile updated.')]);
        }

        $codes->issue(email: $data['email'], purpose: self::EMAIL_CHANGE_PURPOSE, userId: $user->id);

        $request->session()->put('pending_profile_email', $data['email']);

        return to_route('profile.verify-email')
            ->with('flash', ['message' => "Name updated. We've sent a verification code to {$data['email']}."]);
    }

    /**
     * Show the code-entry screen for a pending school-email change.
     */
    public function verifyEmail(Request $request): RedirectResponse|Response
    {
        $email = $request->session()->get('pending_profile_email');

        if (! is_string($email)) {
            return $this->redirectToEditWithoutPendingChange();
        }

        return Inertia::render('settings/verify-email-change', [
            'email' => $email,
            'resendCooldownSeconds' => (int) config('school.verification_code.resend_cooldown_seconds'),
        ]);
    }

    /**
     * Consume the code and, only on a match, actually write the new email.
     */
    public function verifyEmailStore(Request $request, EmailVerificationCodeService $codes): RedirectResponse
    {
        $email = $request->session()->get('pending_profile_email');

        if (! is_string($email)) {
            return $this->redirectToEditWithoutPendingChange();
        }

        $request->validate(['code' => ['required', 'string', 'size:6']]);

        $record = $codes->verify($email, self::EMAIL_CHANGE_PURPOSE, $request->string('code')->toString());

        $user = $request->user();
        $user->email = $email;
        $user->email_verified_at = now();
        $user->save();

        $record->update(['user_id' => $user->id]);

        $request->session()->forget('pending_profile_email');

        return to_route('profile.edit')->with('flash', ['message' => __('Email address updated.')]);
    }

    public function verifyEmailResend(Request $request, EmailVerificationCodeService $codes): RedirectResponse
    {
        $email = $request->session()->get('pending_profile_email');

        if (! is_string($email)) {
            return $this->redirectToEditWithoutPendingChange();
        }

        $previous = EmailVerificationCode::query()
            ->where('email', $email)
            ->where('purpose', self::EMAIL_CHANGE_PURPOSE)
            ->whereNull('consumed_at')
            ->latest('id')
            ->first();

        $codes->issue($email, self::EMAIL_CHANGE_PURPOSE, userId: $previous?->user_id ?? $request->user()->id);

        return to_route('profile.verify-email')->with('flash', ['message' => "We've sent a new code to {$email}."]);
    }

    /**
     * Delete the user's profile.
     */
    public function destroy(ProfileDeleteRequest $request, WithdrawInFlightRegistrations $withdrawRegistrations): RedirectResponse
    {
        $user = $request->user();

        Auth::logout();

        // Must happen before the delete, in the same transaction: once the
        // user row is gone, documents.submitted_by nulls out (nullOnDelete)
        // and there is no way to find "this user's" in-flight registrations
        // anymore — they'd sit in the review queue with no submitter, and
        // approving one would hit a NOT NULL constraint (see
        // WithdrawInFlightRegistrations's docblock).
        DB::transaction(function () use ($user, $withdrawRegistrations) {
            $withdrawRegistrations->execute($user);
            $user->delete();
        });

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    private function redirectToEditWithoutPendingChange(): RedirectResponse
    {
        return to_route('profile.edit')
            ->with('flash', ['message' => 'Start the email change again to receive a new code.']);
    }
}
