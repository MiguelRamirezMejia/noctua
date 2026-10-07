<?php

use App\Activity\ActivityLog;
use App\Http\Middleware\EnsureUserHasPublisher;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RecordPageVisits;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        // Detrás de un túnel o proxy (Cloudflare Tunnel, balanceador) la
        // petición llega por HTTP: se confía en X-Forwarded-* para generar
        // enlaces https y conocer la IP real. En producción conviene limitarlo
        // a las IP del proxy.
        $middleware->trustProxies(at: '*');

        // Los espacios al principio o al final de un fragmento son parte del texto
        // («un » + «dragón»): no se recortan.
        $middleware->trimStrings(except: ['pages.*.design.elements.*.spans.*.text']);

        $middleware->web(append: [
            HandleAppearance::class,
            EnsureUserHasPublisher::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            RecordPageVisits::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Los errores inesperados también aparecen en el registro de actividad.
        $exceptions->report(function (Throwable $e): void {
            app(ActivityLog::class)->error($e);
        });

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
