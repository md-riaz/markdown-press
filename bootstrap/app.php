<?php

use App\Http\Middleware\ApiTokenAuth;
use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\LocaleMiddleware;
use App\Http\Middleware\ThrottleAuthenticatedApiRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'auth.apitoken' => ApiTokenAuth::class,
            'auth.api.throttle' => ThrottleAuthenticatedApiRequests::class,
            'role' => EnsureUserHasRole::class,
            'locale' => LocaleMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
