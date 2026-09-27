<?php

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Route;

/**
 * These register their own throwaway routes under the real `api`
 * middleware group (never the mobile controllers themselves) purely to
 * exercise bootstrap/app.php's api-only exception handling in isolation,
 * before any real mobile route exists.
 */
test('a request with no Accept header still gets a 401 JSON body, not a redirect', function () {
    Route::middleware(['api', 'auth:sanctum'])->get('/api/_test/protected', fn () => response()->json(['ok' => true]));

    $response = $this->withHeaders(['Accept' => null])->getJson('/api/_test/protected');

    // getJson() itself sets an Accept header; assert against a raw get()
    // with the header explicitly stripped to prove the ABSENCE of the
    // header is what's being tolerated, not a header we injected.
    $response = $this->withHeaders(['Accept' => ''])->get('/api/_test/protected');

    $response->assertStatus(401);
    $response->assertHeader('content-type', 'application/json');
    $response->assertJson(['message' => 'Unauthenticated.']);
});

test('an unknown api route returns a generic 404 JSON message', function () {
    $response = $this->getJson('/api/this-route-does-not-exist');

    $response->assertStatus(404);
    $response->assertJson(['message' => 'Not found.']);
});

test('a specific abort(404, ...) message on an api route is left untouched', function () {
    Route::middleware(['api'])->get('/api/_test/specific-404', function () {
        abort(404, 'Activity Proposal not found.');
    });

    $response = $this->getJson('/api/_test/specific-404');

    $response->assertStatus(404);
    $response->assertJson(['message' => 'Activity Proposal not found.']);
});

test('a Gate-denied AuthorizationException renders the contract 403 message on api routes', function () {
    Route::middleware(['api', 'auth:sanctum'])->get('/api/_test/gate-denied', function () {
        throw new AuthorizationException;
    });

    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    $response = $this->withHeaders(['Authorization' => "Bearer {$token}"])
        ->getJson('/api/_test/gate-denied');

    $response->assertStatus(403);
    $response->assertJson(['message' => 'You are not allowed to perform this action.']);
});

test('a raw abort(403) renders the contract 403 message on api routes', function () {
    Route::middleware(['api', 'auth:sanctum'])->get('/api/_test/raw-403', function () {
        abort(403);
    });

    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    $response = $this->withHeaders(['Authorization' => "Bearer {$token}"])
        ->getJson('/api/_test/raw-403');

    $response->assertStatus(403);
    $response->assertJson(['message' => 'You are not allowed to perform this action.']);
});

test('an unhandled exception on an api route returns a plain 500 JSON message with debug off', function () {
    config(['app.debug' => false]);

    Route::middleware(['api'])->get('/api/_test/boom', function () {
        throw new RuntimeException('some internal detail that must not leak');
    });

    $response = $this->getJson('/api/_test/boom');

    $response->assertStatus(500);
    $response->assertJson(['message' => 'Server Error']);
    $response->assertDontSee('some internal detail that must not leak');
});
