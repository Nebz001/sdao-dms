<?php

use App\Support\UploadLimits;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Request;

it('reports the post_max_size ceiling in whole megabytes', function () {
    expect(UploadLimits::postMaxSizeMb())->toBeInt()->toBeGreaterThan(0);
});

it('builds a friendly too-large message that states the limit', function () {
    $message = UploadLimits::tooLargeMessage();

    expect($message)
        ->toContain('too large')
        ->toContain('max total size')
        ->toContain((string) UploadLimits::postMaxSizeMb().' MB');
});

it('renders a PostTooLargeException as a clean 413 JSON message, not the debug page', function () {
    $request = Request::create('/registrations', 'POST');

    $response = app(ExceptionHandler::class)->render(
        $request,
        new PostTooLargeException('The POST data is too large.'),
    );

    expect($response->getStatusCode())->toBe(413);
    expect($response->headers->get('content-type'))->toContain('application/json');

    $payload = json_decode($response->getContent(), true);

    expect($payload)->toHaveKey('message');
    expect($payload['message'])->toBe(UploadLimits::tooLargeMessage());
    // Must NOT be Laravel's HTML debug exception page.
    expect($response->getContent())->not->toContain('<!DOCTYPE html>');
});

it('turns an oversized real request into the friendly 413 through the full middleware stack', function () {
    // ValidatePostSize (global middleware) compares CONTENT_LENGTH against
    // post_max_size and runs BEFORE auth/CSRF, so an unauthenticated POST that
    // declares a body far larger than any sane limit trips it exactly as a real
    // oversized upload would — proving the end-to-end path returns the friendly
    // 413, not the raw exception page.
    $response = $this->call('POST', '/registrations', [], [], [], [
        'CONTENT_LENGTH' => (string) (500 * 1024 * 1024), // 500 MB
    ]);

    $response->assertStatus(413);
    $response->assertJson(['message' => UploadLimits::tooLargeMessage()]);
});
