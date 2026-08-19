<?php

test('asset urls are generated as https when the request arrives via a proxy claiming https', function () {
    $response = $this->withHeaders([
        'X-Forwarded-Proto' => 'https',
    ])->get('/login');

    $response->assertOk();
    $response->assertSee('https://', false);
    $response->assertDontSee('src="http://', false);
    $response->assertDontSee('href="http://', false);
});

test('asset urls stay on the requests own scheme with no proxy header present', function () {
    $response = $this->get('/login');

    $response->assertOk();
    $response->assertDontSee('src="https://', false);
    $response->assertDontSee('href="https://', false);
});
