<?php

namespace App\Providers;

use App\Enums\Permission;
use App\Models\ArticleUpdate;
use App\Models\User;
use App\Policies\ArticleUpdatePolicy;
use App\View\Composers\SidebarComposer;
use App\View\Composers\TopbarComposer;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Security: fail-fast if debug mode sneaks into a production deploy.
        // Stops the app before any request is handled, so no stack traces leak.
        if (app()->environment('production') && config('app.debug')) {
            throw new \RuntimeException(
                'SECURITY: APP_DEBUG must be false in production (OWASP A05:2021).',
            );
        }

        // Belt-and-suspenders for `expose_php = Off` — PHP's SAPI inserts
        // X-Powered-By before Laravel middleware runs, so SecurityHeaders
        // alone can't suppress it. Removing here catches the SAPI-origin
        // header that leaks PHP version (audit #10).
        if (function_exists('header_remove')) {
            @header_remove('X-Powered-By');
        }

        // Arabic locale for Carbon (so diffForHumans returns "منذ X دقيقة")
        // and for app helpers. Must be set in boot() BEFORE views render.
        app()->setLocale(config('app.locale', 'ar'));
        \Carbon\Carbon::setLocale('ar');

        // Auto-discovery handles model→policy mapping for `view`/`delete`/etc,
        // but `createForDocument` is invoked via Gate::authorize on the class
        // string, which Laravel can't auto-resolve. Register explicitly.
        Gate::policy(ArticleUpdate::class, ArticleUpdatePolicy::class);

        // Dotted permissions ('documents.view', 'tenders.approve', ...) resolve
        // against the user's role permission set and short-circuit the Gate
        // pipeline. Non-dotted abilities like 'view'/'update' fall through
        // to their model-specific policy (LegalDocumentPolicy, TaskPolicy, ...).
        Gate::before(function (?User $user, string $ability) {
            if ($user === null) {
                return null;
            }

            if (Permission::tryFrom($ability) !== null) {
                return $user->hasPermission($ability);
            }

            return null;
        });

        View::composer('layouts.topbar', TopbarComposer::class);
        View::composer('layouts.sidebar', SidebarComposer::class);
    }
}
