<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Mobile\MobileLoginRequest;
use App\Http\Resources\Mobile\MobileUserResource;
use App\Identity\MobileAccess;
use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Fortify;

class AuthController extends Controller
{
    public function __construct(private readonly MobileAccess $mobileAccess) {}

    public function login(MobileLoginRequest $request): JsonResponse
    {
        $email = Str::lower($request->string('email')->toString());
        $user = User::where('email', $email)->first();

        if ($user === null) {
            event(new Failed('web', null, $request->only('email', 'password')));

            throw ValidationException::withMessages([
                'email' => [trans('passwords.user')],
            ]);
        }

        if (! Hash::check($request->string('password')->toString(), $user->password)) {
            event(new Failed('web', $user, $request->only('email', 'password')));

            throw ValidationException::withMessages([
                'password' => [trans('auth.password')],
            ]);
        }

        $this->verifyTwoFactorIfEnabled($request, $user);

        if (! $this->mobileAccess->canAccess($user) || ! $this->hasStaffEmail($user)) {
            abort(403, 'You are not allowed to perform this action.');
        }

        $token = $user->createToken($request->string('device_name')->toString());

        return response()->json(['token' => $token->plainTextToken]);
    }

    public function user(Request $request): MobileUserResource
    {
        return new MobileUserResource($request->user(), $this->mobileAccess);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(null, 204);
    }

    /**
     * `hasEnabledTwoFactorAuthentication()` (Laravel\Fortify\TwoFactorAuthenticatable)
     * is confirm-aware: with Fortify's confirm option on (config/fortify.php),
     * it is only true once two_factor_confirmed_at is also set — an
     * abandoned, unconfirmed setup never blocks login. Verification itself
     * goes through Fortify's own TwoFactorAuthenticationProvider — the same
     * class the web's TwoFactorLoginRequest::hasValidCode() uses — so the
     * clock-drift tolerance (Google2FA's window) is identical on both.
     */
    private function verifyTwoFactorIfEnabled(Request $request, User $user): void
    {
        if (! $user->hasEnabledTwoFactorAuthentication()) {
            return;
        }

        if ($request->filled('recovery_code')) {
            $matched = collect($user->recoveryCodes())
                ->first(fn ($code) => hash_equals($code, (string) $request->input('recovery_code')));

            if ($matched === null) {
                throw ValidationException::withMessages([
                    'recovery_code' => ['The provided recovery code was invalid.'],
                ]);
            }

            $user->replaceRecoveryCode($matched);

            return;
        }

        if ($request->filled('code')) {
            $valid = app(TwoFactorAuthenticationProvider::class)->verify(
                Fortify::currentEncrypter()->decrypt($user->two_factor_secret),
                (string) $request->input('code'),
            );

            if (! $valid) {
                throw ValidationException::withMessages([
                    'code' => ['The provided two factor authentication code was invalid.'],
                ]);
            }

            return;
        }

        throw ValidationException::withMessages([
            'code' => ['Two-factor authentication code required.'],
        ]);
    }

    /**
     * Login-only, on top of MobileAccess::canAccess() — an admin-provisioned
     * approver account is expected to already sit on the staff domain (the
     * production read-only check confirmed 0/48 accounts fail this), so
     * this is a defensive belt against a future data-integrity slip, not a
     * per-request re-check (see EnsureMobileAccess, which re-checks
     * canAccess() alone on every subsequent request).
     */
    private function hasStaffEmail(User $user): bool
    {
        $domain = Str::lower(Str::afterLast($user->email, '@'));

        return in_array($domain, config('school.email_domains.staff'), true);
    }
}
