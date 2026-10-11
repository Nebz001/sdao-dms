<?php

namespace App\Http\Responses;

use App\Support\PrivacyNotice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;

/**
 * Every successful login lands on the Privacy Notice first. The page the user
 * was headed to (Laravel's intended url) is kept for after they accept.
 */
class LoginResponse implements LoginResponseContract
{
    public function toResponse($request): JsonResponse|RedirectResponse
    {
        if ($request->wantsJson()) {
            return new JsonResponse(['two_factor' => false]);
        }

        $intended = $request->session()->pull('url.intended');

        if (is_string($intended)) {
            $request->session()->put(PrivacyNotice::INTENDED_KEY, $intended);
        }

        return redirect()->route('privacy-notice.show');
    }
}
