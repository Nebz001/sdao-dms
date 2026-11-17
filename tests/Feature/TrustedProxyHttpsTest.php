<?php

test('asset urls are generated as https when the request arrives via a proxy claiming https', function () {
    // What Railway sets automatically (config/trustedproxy.php); off elsewhere.
    config(['trustedproxy.proxies' => '*']);

    $response = $this->withHeaders([
        'X-Forwarded-Proto' => 'https',
    ])->get('/login');

    $response->assertOk();
    $response->assertSee('https://', false);
    $response->assertDontSee('src="http://', false);
    $response->assertDontSee('href="http://', false);
});

test('a proxy header is ignored when no proxy is trusted', function () {
    config(['trustedproxy.proxies' => null]);

    $response = $this->withHeaders(['X-Forwarded-Proto' => 'https'])->get('/login');

    $response->assertOk();
    $response->assertDontSee('src="https://', false);
    $response->assertDontSee('href="https://', false);
});

test('asset urls stay on the requests own scheme with no proxy header present', function () {
    $response = $this->get('/login');

    $response->assertOk();
    $response->assertDontSee('src="https://', false);
    $response->assertDontSee('href="https://', false);
});
