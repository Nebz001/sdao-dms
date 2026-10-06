<?php

namespace App\Http\Controllers\Auth;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Enums\AccountStatus;
use App\Http\Controllers\Controller;
use App\Identity\EmailVerification\EmailVerificationCodeService;
use App\Models\EmailVerificationCode;
use App\Models\User;
use App\Support\FlashToast;
use App\Support\PersonName;
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
            // "Join an existing organization" choice (register.tsx's first
            // fieldset) — rides along in the payload below purely to decide
            // verifyStore()'s post-verification redirect. No account exists
            // yet to persist it on, and it has no other effect. Nullable,
            // not required: register.tsx always sends one, but nothing else
            // hitting this endpoint (existing tests included) needs to.
            'intended_path' => ['nullable', 'string', 'in:register_new,join_existing'],
        ], $this->personNameMessages());

        // Hashed immediately (never held plaintext) and the whole payload is
        // additionally encrypted at rest via EmailVerificationCode's cast.
        $codes->issue(
            email: $data['email'],
            purpose: self::PURPOSE,
            payload: [
                'first_name' => PersonName::stripTitle($data['first_name']),
                'last_name' => PersonName::clean($data['last_name']),
                'password' => Hash::make($data['password']),
                'id_number' => $data['id_number'],
                'intended_path' => $data['intended_path'] ?? 'register_new',
            ],
        );

        $request->session()->put('pending_registration_email', $data['email']);

        return to_route('register.verify')
            ->with('flash', FlashToast::make('Check your email', "We sent a verification code to {$data['email']}.", 'info'));
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

        // A code issued before first/last name existed carries only 'name';
        // split it so an in-flight registration still completes.
        $legacy = isset($payload['first_name']) ? null : PersonName::split((string) ($payload['name'] ?? ''));

        $user = User::create([
            'first_name' => $payload['first_name'] ?? $legacy['first'],
            'last_name' => $payload['last_name'] ?? ($legacy['last'] ?: null),
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

        // "Join an existing organization" choice, captured back in store()
        // (see its docblock note). `?? 'register_new'` covers a code that
        // was re-issued via resend() from a payload predating this field —
        // same as today's behavior, straight to the dashboard.
        $intendedPath = $payload['intended_path'] ?? 'register_new';

        if ($intendedPath === 'join_existing') {
            return to_route('organizations.join.create')
                ->with('flash', FlashToast::make('Account created', 'Next, find your organization so its adviser can add you as an officer.'));
        }

        return to_route('dashboard')->with('flash', FlashToast::make('Account created', 'Welcome. SDAO still needs to verify your account before you can submit documents.'));
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

        return to_route('register.verify')->with('flash', FlashToast::make('New code sent', "We sent a fresh verification code to {$email}.", 'info'));
    }

    private function redirectToStartOver(): RedirectResponse
    {
        return to_route('register')
            ->with('flash', FlashToast::warning('Code expired', 'Start your registration again to receive a new code.'));
    }
}
