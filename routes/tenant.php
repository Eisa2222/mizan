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
| Two middleware layers:
|   - Tenancy stack (InitializeTenancy + PreventAccessFromCentralDomains
|     + ApplySystemSettings) runs on EVERY tenant request.
|   - subscription.active gates all routes except login/impersonate/
|     password-setup — those must remain reachable on a suspended tenant
|     so the owner can log in to renew.
*/

// Tenant routes that SHOULD reach even suspended tenants (login, billing
// actions, impersonation redemption) — no subscription gate.
Route::middleware([
    'web',
    InitializeTenancyByDomainOrSubdomain::class,
    PreventAccessFromCentralDomains::class,
    'apply-system-settings',
])->group(function () {

    // Impersonation redemption — SuperAdmin generates a token centrally,
    // user lands here on the tenant domain and gets logged in as the
    // target user. Token expires after 5 minutes.
    Route::get('/impersonate/{token}', function (string $token) {
        return \Stancl\Tenancy\Features\UserImpersonation::makeResponse($token);
    })->name('impersonate');

    // Tenant password setup — new admins land here from TenantWelcomeMail
    // with a 48-hour signed URL. Lives inside tenant context so the
    // password write hits the tenant DB, not central.
    Route::get('/password/setup/{token}', [\App\Http\Controllers\TenantPasswordSetupController::class, 'show'])
        ->middleware('signed')
        ->name('tenant.password.setup');
    Route::post('/password/setup', [\App\Http\Controllers\TenantPasswordSetupController::class, 'store'])
        ->name('tenant.password.setup.store');

    // Billing dashboard — must remain reachable on suspended/expired
    // tenants so owners can see their status and hop to the central
    // Checkout to renew.
    Route::get('/billing',                   [\App\Http\Controllers\TenantBillingController::class, 'index'])->name('tenant.billing');
    Route::post('/billing/renew/{plan}',     [\App\Http\Controllers\TenantBillingController::class, 'renew'])->name('tenant.billing.renew');
});

// Tenant routes gated by active subscription. Phase 4 ships the
// middleware + plumbing; actual app routes (documents, tasks, tenders,
// dashboard…) get moved here in the cutover release once existing orgs
// have been converted via `saas:convert-org-to-tenant`.
Route::middleware([
    'web',
    InitializeTenancyByDomainOrSubdomain::class,
    PreventAccessFromCentralDomains::class,
    'apply-system-settings',
    'subscription.active',
])->group(function () {
    // Placeholder — the real tenant app routes move here during cutover.
    // The /app redirect inside web.php keeps legacy users funneled to
    // /dashboard on their tenant subdomain once migrated.
});
