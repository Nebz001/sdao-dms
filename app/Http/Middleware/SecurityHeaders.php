<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sends the response security headers for every web request.
 *
 * X-Content-Type-Options and X-Frame-Options are deliberately NOT set here:
 * the production web server already sends them, and a second copy would be a
 * duplicate header. Everything else lives in this one place.
 */
class SecurityHeaders
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Registers the nonce on Vite so every tag it renders carries it, and
        // exposes the same value to the views via Vite::cspNonce().
        $nonce = Vite::useCspNonce();

        $response = $next($request);

        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', $this->permissionsPolicy());

        $cspHeader = config('security.csp_report_only')
            ? 'Content-Security-Policy-Report-Only'
            : 'Content-Security-Policy';
        $response->headers->set($cspHeader, $this->contentSecurityPolicy($nonce, $request->isSecure()));

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }

    /**
     * Features the app never uses are switched off for the page and any frame.
     */
    private function permissionsPolicy(): string
    {
        return implode(', ', [
            'accelerometer=()',
            'camera=()',
            'geolocation=()',
            'gyroscope=()',
            'magnetometer=()',
            'microphone=()',
            'payment=()',
            'usb=()',
        ]);
    }

    /**
     * Every source is same-origin: fonts are self-hosted from /build, and
     * attachments stream through Laravel (never straight from Supabase), so
     * previews only add blob: for object URLs the page creates itself.
     */
    private function contentSecurityPolicy(string $nonce, bool $isSecure): string
    {
        $scriptSrc = ["'self'", "'nonce-{$nonce}'"];
        $connectSrc = ["'self'"];
        $styleSrc = ["'self'", "'unsafe-inline'"];
        $fontSrc = ["'self'", 'data:'];
        $imgSrc = ["'self'", 'data:', 'blob:'];

        if (Vite::isRunningHot()) {
            $devServer = $this->viteDevServer();

            if ($devServer !== null) {
                $scriptSrc[] = $devServer['http'];
                $styleSrc[] = $devServer['http'];
                $fontSrc[] = $devServer['http'];
                $imgSrc[] = $devServer['http'];
                $connectSrc[] = $devServer['http'];
                $connectSrc[] = $devServer['ws'];
            }
        }

        $directives = [
            'default-src' => ["'self'"],
            'script-src' => $scriptSrc,
            'style-src' => $styleSrc,
            'font-src' => $fontSrc,
            'img-src' => $imgSrc,
            'connect-src' => $connectSrc,
            'frame-src' => ["'self'", 'blob:'],
            'object-src' => ["'none'"],
            'base-uri' => ["'self'"],
            'form-action' => ["'self'"],
            'frame-ancestors' => ["'self'"],
        ];

        $policy = [];

        foreach ($directives as $directive => $sources) {
            $policy[] = $directive.' '.implode(' ', $sources);
        }

        if ($isSecure) {
            $policy[] = 'upgrade-insecure-requests';
        }

        return implode('; ', $policy);
    }

    /**
     * The Vite dev server's http and ws origins, read from the hot file.
     *
     * @return array{http: string, ws: string}|null
     */
    private function viteDevServer(): ?array
    {
        $hotFile = Vite::hotFile();
        $url = is_file($hotFile) ? trim((string) file_get_contents($hotFile)) : '';
        $parts = parse_url($url);

        if (! isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        $authority = $parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');

        return [
            'http' => $parts['scheme'].'://'.$authority,
            'ws' => ($parts['scheme'] === 'https' ? 'wss' : 'ws').'://'.$authority,
        ];
    }
}
