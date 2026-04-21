<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

/**
 * Lands at `/password/setup/{token}?email=...` inside tenant context.
 * The signed URL was issued by CreateTenantJob for 48h. Shows a form
 * that hashes the chosen password, saves it on the tenant admin user,
 * then logs them in and redirects to /dashboard.
 */
class TenantPasswordSetupController extends Controller
{
    public function show(Request $request, string $token): View
    {
        return view('auth.tenant-setup', [
            'token' => $token,
            'email' => $request->query('email'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'token'                 => ['required', 'string'],
            'email'                 => ['required', 'email'],
            'password'              => ['required', 'confirmed', 'min:8'],
        ]);

        $status = Password::broker('tenants')->reset(
            $data,
            function (User $user, string $password) {
                $user->forceFill([
                    'password'          => Hash::make($password),
                    'email_verified_at' => $user->email_verified_at ?? now(),
                    'remember_token'    => \Illuminate\Support\Str::random(60),
                ])->save();

                Auth::login($user);
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return redirect()->route('dashboard');
        }

        return back()->withErrors(['email' => __($status)]);
    }
}
