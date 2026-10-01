<?php

namespace Tests;

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
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
