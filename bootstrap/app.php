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
            'role'                  => \App\Http\Middleware\EnsureRole::class,
            'super-admin'           => \Modules\Admin\Http\Middleware\EnsureSuperAdmin::class,
            'super-admin.auth'      => \App\Http\Middleware\EnsureSuperAdminAuthenticated::class,
            'apply-system-settings' => \App\Http\Middleware\ApplySystemSettings::class,
        ]);

        // Security headers + SaaS system settings on every central web
        // response. ApplySystemSettings pulls Moyasar keys, mail creds,
        // app_name from system_settings into Config before controllers run.
        $middleware->web(append: [
            \App\Http\Middleware\SecurityHeaders::class,
            \App\Http\Middleware\ApplySystemSettings::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
