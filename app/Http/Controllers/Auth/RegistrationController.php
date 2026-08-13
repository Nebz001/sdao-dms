<?php

namespace App\Http\Controllers\Auth;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Enums\AccountStatus;
use App\Http\Controllers\Controller;
use App\Identity\EmailVerification\EmailVerificationCodeService;
use App\Models\EmailVerificationCode;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Owns self-registration end to end, replacing Fortify's built-in
 * registration feature (disabled in config/fortify.php) so a verification
 * code can be inserted between "form submitted" and "account exists" — per
 * CLAUDE.md, a school email must be confirmed as real BEFORE an account
 * exists, so no `User` row is created until the code matches.
 */
class RegistrationController extends Controller
{
    use PasswordValidationRules, ProfileValidationRules;

    private const string PURPOSE = 'registration';

    public function create(): Response
    {
        return Inertia::render('auth/register', [
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]);
    }

    public function store(Request $request, EmailVerificationCodeService $codes): RedirectResponse
    {
        $data = $request->validate([
            ...$this->profileRules(audience: 'student'),
            'password' => $this->passwordRules(),
            'id_number' => $this->idNumberRules(required: true),
        ]);

        // Hashed immediately (never held plaintext) and the whole payload is
        // additionally encrypted at rest via EmailVerificationCode's cast.
        $codes->issue(
            email: $data['email'],
            purpose: self::PURPOSE,
            payload: [
                'name' => $data['name'],
                'password' => Hash::make($data['password']),
                'id_number' => $data['id_number'],
            ],
        );

        $request->session()->put('pending_registration_email', $data['email']);

        return to_route('register.verify')
            ->with('flash', ['message' => "We've sent a verification code to {$data['email']}."]);
    }

    public function verify(Request $request): RedirectResponse|Response
    {
        $email = $request->session()->get('pending_registration_email');

        if (! is_string($email)) {
            return $this->redirectToStartOver();
        }

        return Inertia::render('auth/verify-registration-code', [
            'email' => $email,
            'resendCooldownSeconds' => (int) config('school.verification_code.resend_cooldown_seconds'),
        ]);
    }

    public function verifyStore(Request $request, EmailVerificationCodeService $codes): RedirectResponse
    {
        $email = $request->session()->get('pending_registration_email');

        if (! is_string($email)) {
            return $this->redirectToStartOver();
        }

        $request->validate(['code' => ['required', 'string', 'size:6']]);

        $record = $codes->verify($email, self::PURPOSE, $request->string('code')->toString());
        $payload = $record->payload ?? [];

        $user = User::create([
            'name' => $payload['name'],
            'email' => $email,
            'password' => $payload['password'],
            'id_number' => $payload['id_number'],
            'email_verified_at' => now(),
            // Self-registered students await SDAO review via the Pending
            // Accounts queue before they can submit or be adviser-bound.
            'account_status' => AccountStatus::Unverified,
        ]);

        $record->update(['user_id' => $user->id]);

        $request->session()->forget('pending_registration_email');

        event(new Registered($user));

        Auth::login($user);

        return to_route('dashboard')->with('flash', ['message' => 'Account created — welcome!']);
    }

    public function resend(Request $request, EmailVerificationCodeService $codes): RedirectResponse
    {
        $email = $request->session()->get('pending_registration_email');

        if (! is_string($email)) {
            return $this->redirectToStartOver();
        }

        $previous = EmailVerificationCode::query()
            ->where('email', $email)
            ->where('purpose', self::PURPOSE)
            ->whereNull('consumed_at')
            ->latest('id')
            ->first();

        if ($previous === null) {
            return $this->redirectToStartOver();
        }

        $codes->issue($email, self::PURPOSE, $previous->payload, $previous->user_id);

        return to_route('register.verify')->with('flash', ['message' => "We've sent a new code to {$email}."]);
    }

    private function redirectToStartOver(): RedirectResponse
    {
        return to_route('register')
            ->with('flash', ['message' => 'Start your registration again to receive a new code.']);
    }
}
