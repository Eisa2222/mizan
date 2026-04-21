<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate the /super-admin/* area. Redirects unauthenticated visitors to
 * the SuperAdmin login page and rejects tenant-`web` sessions — the
 * two guards are fully isolated, so a logged-in tenant user visiting
 * /super-admin must log in separately with their SuperAdmin credentials.
 *
 * Also enforces the `is_active` flag so a SuperAdmin can be revoked
 * without deleting the row (useful for ex-staff).
 */
class EnsureSuperAdminAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        $guard = Auth::guard('super_admin');

        if (! $guard->check()) {
            return redirect()->route('super-admin.login');
        }

        $user = $guard->user();
        if ($user !== null && method_exists($user, 'isActive') && ! $user->isActive()) {
            $guard->logout();
            $request->session()->invalidate();

            return redirect()
                ->route('super-admin.login')
                ->withErrors(['email' => 'تم تعطيل هذا الحساب. تواصل مع مدير النظام.']);
        }

        return $next($request);
    }
}
