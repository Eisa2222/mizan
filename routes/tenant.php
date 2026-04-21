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

    // Tenant password setup — new admins land here from TenantWelcomeMail
    // with a 48-hour signed URL. The password write hits the tenant DB,
    // not central, because this route is inside the tenancy group.
    Route::get('/password/setup/{token}', [\App\Http\Controllers\TenantPasswordSetupController::class, 'show'])
        ->middleware('signed')
        ->name('tenant.password.setup');
    Route::post('/password/setup', [\App\Http\Controllers\TenantPasswordSetupController::class, 'store'])
        ->name('tenant.password.setup.store');

    // Phase 4 will mount the real tenant app here (dashboard, documents,
    // tasks, tenders, etc.) — moved from routes/web.php.
});
