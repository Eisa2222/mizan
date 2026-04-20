<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        // Health endpoint disabled: `/up` reveals framework details and
        // returns a full HTML page. Re-enable and IP-whitelist if infra
        // needs it for load-balancer probes (audit #22).
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role'        => \App\Http\Middleware\EnsureRole::class,
            'super-admin' => \Modules\Admin\Http\Middleware\EnsureSuperAdmin::class,
        ]);

        // Security headers on every web response (OWASP + audit #2/#10).
        $middleware->web(append: [
            \App\Http\Middleware\SecurityHeaders::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
