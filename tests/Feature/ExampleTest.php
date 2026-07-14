<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests visiting home see the public landing page', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('welcome'));
});

test('authenticated users visiting home are redirected to the dashboard', function () {
    $this->actingAs(User::factory()->create());

    $response = $this->get(route('home'));

    $response->assertRedirect(route('dashboard'));
});
