<?php

namespace Modules\SuperAdmin\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Self-service profile page for the SaaS operator logged in via the
 * super_admin guard. Three separate forms to keep password change
 * validation isolated from identity updates.
 */
class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('super-admin.profile.edit', [
            'admin' => $request->user('super_admin'),
        ]);
    }

    /**
     * Update name/email. Email is unique across super_admins — the
     * current user's row is excluded from the unique check so they can
     * keep the same address while changing name only.
     */
    public function update(Request $request): RedirectResponse
    {
        $admin = $request->user('super_admin');

        $data = $request->validate([
            'name'  => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', Rule::unique('super_admins', 'email')->ignore($admin->id)],
        ]);

        $admin->update($data);

        return back()->with('success', 'تم حفظ الملف الشخصي.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $admin = $request->user('super_admin');

        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password'         => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if (! Hash::check($data['current_password'], $admin->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'كلمة المرور الحالية غير صحيحة.',
            ]);
        }

        $admin->forceFill([
            'password' => Hash::make($data['password']),
        ])->save();

        // Cycle session ID to invalidate any other active sessions.
        $request->session()->regenerate();

        return back()->with('success', 'تم تحديث كلمة المرور.');
    }
}
