<?php

use Illuminate\Support\Facades\Vite;

it('sends referrer, permissions and content security policy headers on a normal page', function () {
    $response = $this->get('https://localhost/login');

    $response->assertOk();
    $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');

    $permissions = $response->headers->get('Permissions-Policy');
    foreach (['camera=()', 'microphone=()', 'geolocation=()', 'payment=()', 'usb=()'] as $feature) {
        expect($permissions)->toContain($feature);
    }

    $csp = $response->headers->get('Content-Security-Policy');
    expect($csp)
        ->toContain("default-src 'self'")
        ->toContain("object-src 'none'")
        ->toContain("base-uri 'self'")
        ->toContain("form-action 'self'")
        ->toContain("frame-ancestors 'self'")
        ->toContain('upgrade-insecure-requests')
        ->not->toContain('unsafe-eval')
        ->not->toContain('*');
});

it('sends HSTS only on secure requests', function () {
    $this->get('http://localhost/login')->assertHeaderMissing('Strict-Transport-Security');

    $this->get('https://localhost/login')
        ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
});

it('honours X-Forwarded-Proto from the proxy when deciding to send HSTS', function () {
    $this->get('/login', ['X-Forwarded-Proto' => 'https'])
        ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
});

it('never sends X-Frame-Options or X-Content-Type-Options itself, since the web server does', function () {
    $this->get('/login')
        ->assertHeaderMissing('X-Frame-Options')
        ->assertHeaderMissing('X-Content-Type-Options');
});

it('uses a nonce in the policy that matches every inline script tag', function () {
    $response = $this->get('/login');

    preg_match("/script-src [^;]*'nonce-([^']+)'/", $response->headers->get('Content-Security-Policy'), $policy);
    expect($policy)->toHaveCount(2);

    preg_match_all('/<script\b([^>]*)>/i', $response->getContent(), $tags);
    $executable = array_filter($tags[1], fn (string $attrs) => ! str_contains($attrs, 'application/json'));

    expect($executable)->not->toBeEmpty();
    foreach ($executable as $attrs) {
        expect($attrs)->toContain('nonce="'.$policy[1].'"');
    }
});

it('uses a fresh nonce on every request', function () {
    $nonce = fn () => preg_match("/'nonce-([^']+)'/", $this->get('/login')->headers->get('Content-Security-Policy'), $m) ? $m[1] : null;

    expect($nonce())->not->toBe($nonce());
});

it('can send the policy as report-only', function () {
    config(['security.csp_report_only' => true]);

    $this->get('/login')
        ->assertHeader('Content-Security-Policy-Report-Only')
        ->assertHeaderMissing('Content-Security-Policy');
});

it('allows the Vite dev server only while it is running hot', function () {
    expect($this->get('/login')->headers->get('Content-Security-Policy'))->not->toContain('5173');

    $hot = storage_path('framework/testing-hot-csp');
    file_put_contents($hot, 'http://localhost:5173');
    Vite::useHotFile($hot);

    try {
        $csp = $this->get('/login')->headers->get('Content-Security-Policy');
    } finally {
        unlink($hot);
    }

    expect($csp)
        ->toContain('http://localhost:5173')
        ->toContain('ws://localhost:5173')
        ->not->toContain('upgrade-insecure-requests'); // plain-http dev
});
