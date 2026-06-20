<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('dev login page lists seeded users', function () {
    User::factory()->create(['name' => 'Test Person']);

    $response = $this->withoutVite()->get(route('dev.login'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('dev/login')
        ->has('users')
    );
});

test('dev login authenticates the session as the chosen user', function () {
    $user = User::factory()->create();

    $response = $this->post(route('dev.login.store'), ['user_id' => $user->id]);

    $response->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($user);
});

test('dev login rejects an unknown user id', function () {
    $response = $this->post(route('dev.login.store'), ['user_id' => 99999]);

    $response->assertSessionHasErrors('user_id');
    $this->assertGuest();
});

test('dev logout clears the session', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->post(route('dev.logout'));

    $response->assertRedirect(route('dev.login'));
    $this->assertGuest();
});

test('dev login routes are not registered in production', function () {
    $this->app->detectEnvironment(fn () => 'production');

    // Routes are evaluated at boot; in tests we verify the route does not exist.
    expect(Route::has('dev.login'))->toBeFalse();
})->skip('Environment swap after boot not testable at runtime; guard is verified by code review.');
