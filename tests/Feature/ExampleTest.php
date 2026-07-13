<?php

use App\Models\User;

test('guests visiting home are redirected to login', function () {
    $response = $this->get(route('home'));

    $response->assertRedirect(route('login'));
});

test('authenticated users visiting home are redirected to the dashboard', function () {
    $this->actingAs(User::factory()->create());

    $response = $this->get(route('home'));

    $response->assertRedirect(route('dashboard'));
});
