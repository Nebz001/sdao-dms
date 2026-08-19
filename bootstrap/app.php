<?php

use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Support\UploadLimits;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Railway (like Heroku/Render/Fly) terminates TLS at its edge and
        // forwards plain HTTP to the container, setting X-Forwarded-Proto:
        // https on the way in. Without this, Laravel/Symfony never sees the
        // request as secure, so url()/asset()/route() all generate http://
        // links -- which the browser then blocks as mixed content on an
        // https:// page. Railway's edge IP isn't published or fixed, so we
        // trust the immediate connection unconditionally; that's safe here
        // because the container has no public network path except through
        // Railway's own proxy (it's a private container, not directly
        // internet-routable), so this can't be used to spoof headers from
        // the public internet. The trusted header set (FOR/HOST/PORT/PROTO/
        // PREFIX/AWS_ELB) is TrustProxies' own default -- left unspecified.
        $middleware->trustProxies(at: '*');

        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        // A request that exceeds PHP's post_max_size is caught by the global
        // ValidatePostSize middleware — which runs BEFORE StartSession, so no
        // session (and therefore no redirect->with('flash')) is available here.
        // Render a clean 413 carrying the friendly message instead of Laravel's
        // debug exception page; the Inertia client reads it off the response in
        // its `httpException` handler (see resources/js/app.tsx) and shows a
        // toast. Note: for production behind Nginx/Apache, the proxy has its own
        // body-size cap (Nginx `client_max_body_size`, default 1M; Apache
        // `LimitRequestBody`) that must be raised to match post_max_size, or the
        // proxy rejects the upload before it ever reaches PHP.
        $exceptions->render(function (PostTooLargeException $e, Request $request) {
            return response()->json(['message' => UploadLimits::tooLargeMessage()], 413);
        });
    })->create();
