<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomainOrSubdomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

/*
|--------------------------------------------------------------------------
| Tenant Routes
|--------------------------------------------------------------------------
|
| Loaded by TenancyServiceProvider::mapRoutes(). Any request to a host
| NOT listed in config('tenancy.central_domains') is resolved here.
|
| Phase 1 ships a placeholder root route so /tenancy tests succeed. Phases
| 2-4 will gradually migrate the existing app routes here (documents,
| tenders, dashboard, ...) and wrap them with the 'subscription.active'
| middleware once CheckSubscription is introduced in Phase 4.
|
*/

Route::middleware([
    'web',
    InitializeTenancyByDomainOrSubdomain::class,
    PreventAccessFromCentralDomains::class,
])->group(function () {

    // Impersonation redemption — SuperAdmin generates a token centrally,
    // user lands here on the tenant domain and gets logged in as the
    // target user. Token expires after 5 minutes.
    Route::get('/impersonate/{token}', function (string $token) {
        return \Stancl\Tenancy\Features\UserImpersonation::makeResponse($token);
    })->name('impersonate');

    // Phase 4 will mount the real tenant app here (dashboard, documents,
    // tasks, tenders, etc.) — moved from routes/web.php. For now nothing
    // else lives on the tenant side so GET / stays central (landing).
});
