<?php

use App\Http\Middleware\CheckRole;
use App\Http\Middleware\EnsureAccountActive;
use App\Http\Middleware\EnsureParcelAvailable;
use App\Http\Middleware\SelectTabSession;
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
    ->withCommands()

    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(prepend: SelectTabSession::class);

        $middleware->alias([
            'role' => CheckRole::class,
            'account.active' => EnsureAccountActive::class,
            'parcel.available' => EnsureParcelAvailable::class,
        ]);

        $middleware->trustProxies(
            at: env('TRUSTED_PROXIES') ?? (env('APP_ENV') === 'local' ? '*' : null),
            headers: Request::HEADER_X_FORWARDED_FOR |
            Request::HEADER_X_FORWARDED_HOST |
            Request::HEADER_X_FORWARDED_PORT |
            Request::HEADER_X_FORWARDED_PROTO |
            Request::HEADER_X_FORWARDED_PREFIX,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
