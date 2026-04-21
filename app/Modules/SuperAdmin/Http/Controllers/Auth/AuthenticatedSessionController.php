<?php

namespace Modules\SuperAdmin\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Modules\SuperAdmin\Http\Requests\SuperAdminLoginRequest;

/**
 * Login/logout for the `super_admin` guard. Kept deliberately separate
 * from the tenant `web` guard so a leaked tenant session cookie can't
 * access the SaaS ops panel.
 */
class AuthenticatedSessionController extends Controller
{
    public function create(): View
    {
        return view('super-admin.auth.login');
    }

    public function store(SuperAdminLoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        return redirect()->intended(route('super-admin.dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('super_admin')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('super-admin.login');
    }
}
