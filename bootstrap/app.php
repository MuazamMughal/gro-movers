<?php

use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\HandleRedirects;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\VerifyFormOrigin;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        // Public forms post from static HTML pages, which cannot carry a session CSRF token.
        // They are protected by VerifyFormOrigin, rate limits, a honeypot and a signed start time.
        $middleware->preventRequestForgery(except: ['forms/*']);

        $middleware->alias(['form.origin' => VerifyFormOrigin::class]);

        // Redirects run before routing (old URLs may match no route at all);
        // security headers wrap every response.
        $middleware->prepend(HandleRedirects::class);
        $middleware->append(SecurityHeaders::class);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // T19: an admin page the user's role does not allow goes back to the dashboard with a message.
        $exceptions->render(function (AccessDeniedHttpException $e, Request $request) {
            if (! $request->is('admin', 'admin/*') || $request->expectsJson() || ! $request->user() || $request->routeIs('dashboard')) {
                return null;
            }

            Inertia::flash('toast', ['type' => 'error', 'message' => 'Your role does not include access to that page.']);

            return to_route('dashboard');
        });
    })->create();
