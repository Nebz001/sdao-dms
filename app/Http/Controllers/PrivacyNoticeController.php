<?php

namespace App\Http\Controllers;

use App\Models\PrivacyNoticeAcceptance;
use App\Support\FlashToast;
use App\Support\PrivacyNotice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class PrivacyNoticeController extends Controller
{
    public function show(Request $request): Response|RedirectResponse
    {
        if (PrivacyNotice::isAccepted($request->session())) {
            return redirect()->route('dashboard');
        }

        return Inertia::render('privacy-notice', [
            'version' => PrivacyNotice::VERSION,
        ]);
    }

    public function accept(Request $request): RedirectResponse
    {
        $request->validate([
            'agree' => ['accepted'],
        ], [
            'agree.accepted' => 'Tick the box to confirm you have read and agree to the Privacy Notice.',
        ]);

        PrivacyNoticeAcceptance::create([
            'user_id' => $request->user()->id,
            'notice_version' => PrivacyNotice::VERSION,
            'ip_address' => $request->ip(),
            'accepted_at' => now(),
        ]);

        PrivacyNotice::markAccepted($request->session());

        $intended = $request->session()->pull(PrivacyNotice::INTENDED_KEY);

        $destination = is_string($intended) && $this->isOwnUrl($request, $intended)
            ? $intended
            : route('dashboard');

        return redirect($destination)
            ->with('flash', FlashToast::make('Privacy Notice accepted', 'You can now use the system.'));
    }

    /** Declining ends the session and returns to the login page. */
    public function decline(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function isOwnUrl(Request $request, string $url): bool
    {
        $parts = parse_url($url);

        return $parts !== false && ($parts['host'] ?? null) === $request->getHost();
    }
}
