<?php

namespace Tests;

use App\Support\PrivacyNotice;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Vite;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    /**
     * Point Vite at a hot file that never exists, so a running dev server's
     * public/hot cannot turn asset URLs into http://[::1]:5173 links and make
     * asset URL assertions depend on the developer's machine.
     */
    protected function setUp(): void
    {
        parent::setUp();

        Vite::useHotFile(storage_path('framework/testing-no-vite-hot'));

        // Tests act as someone who already accepted the Privacy Notice, so the
        // gate (EnsurePrivacyNoticeAccepted) does not block every other test.
        // PrivacyNoticeGateTest starts from a session without it.
        $this->withSession([PrivacyNotice::SESSION_KEY => PrivacyNotice::VERSION]);
    }

    /** For tests whose session was replaced (database driver, logout) after setUp. */
    protected function acceptPrivacyNotice(): static
    {
        return $this->withSession([PrivacyNotice::SESSION_KEY => PrivacyNotice::VERSION]);
    }

    protected function withoutPrivacyNoticeAcceptance(): static
    {
        return $this->flushSession();
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
