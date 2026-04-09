<?php

namespace App\Providers;

use App\Models\ArticleUpdate;
use App\Policies\ArticleUpdatePolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Auto-discovery handles model→policy mapping for `view`/`delete`/etc,
        // but `createForDocument` is invoked via Gate::authorize on the class
        // string, which Laravel can't auto-resolve. Register explicitly.
        Gate::policy(ArticleUpdate::class, ArticleUpdatePolicy::class);
    }
}
