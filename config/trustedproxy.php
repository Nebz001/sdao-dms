<?php

/*
|--------------------------------------------------------------------------
| Trusted proxies
|--------------------------------------------------------------------------
|
| Read per request by Illuminate's TrustProxies middleware. A forwarded header
| (X-Forwarded-For/Proto/Host...) is believed ONLY when the connection really
| came from a proxy we trust; otherwise it is ignored and the request's IP is
| the socket's own. Trusting it when nothing sits in front of the app lets any
| client pick its own IP and walk around every per-IP rate limit.
|
| Resolved in this order:
|   1. TRUSTED_PROXIES: '*' (trust the connecting proxy), or a comma-separated
|      list of IPs/CIDRs. Set it to pin Railway's proxy range explicitly.
|   2. Running on Railway (it injects RAILWAY_ENVIRONMENT_NAME): '*'. Railway
|      terminates TLS at its edge and forwards to a container with no other
|      public path. '*' trusts only the immediate peer, and Symfony takes the
|      right-most untrusted X-Forwarded-For entry, so a header the client sent
|      itself is not used.
|   3. Anywhere else (local, CI, a bare server): none.
|
*/

$configured = env('TRUSTED_PROXIES');

if (! is_string($configured) || trim($configured) === '') {
    $configured = env('RAILWAY_ENVIRONMENT_NAME') ? '*' : null;
}

return [
    'proxies' => $configured,
];
