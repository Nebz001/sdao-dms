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
