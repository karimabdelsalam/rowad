<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo('/admin/login');
        // Office domains must switch to the office database before the session and the
        // signed-in user are loaded (Livewire update requests only carry the 'web' group).
        $middleware->web(prepend: [
            \App\Http\Middleware\InitializeTenancyIfNotCentral::class,
        ]);
        $middleware->web(append: [
            \App\Http\Middleware\SetLocale::class,
            \App\Http\Middleware\EnsureLicensed::class, // self-hosted copies only
        ]);
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);
        $middleware->append(\App\Http\Middleware\LogQueryCount::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Wildcard DNS sends every *.aqarnova.com here: a subdomain no office owns gets a 404 page, not a server error.
        $exceptions->render(function (\Stancl\Tenancy\Exceptions\TenantCouldNotBeIdentifiedOnDomainException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json(['message' => 'Not found.'], 404);
            }
            return response()->view('errors.office-not-found', ['home' => $request->getScheme().'://'.config('rowad.tenancy.base_domain')
                .(in_array($request->getPort(), [80, 443], true) ? '' : ':'.$request->getPort())], 404);
        });
        $exceptions->dontReport(\Stancl\Tenancy\Exceptions\TenantCouldNotBeIdentifiedOnDomainException::class);
    })->create();
