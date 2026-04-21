<?php

namespace Modules\Profile\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Modules\Profile\Actions\UpdateProfileAction;
use Modules\Profile\Http\Requests\DeleteProfileRequest;
use Modules\Profile\Http\Requests\UpdateProfileRequest;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    public function update(UpdateProfileRequest $request, UpdateProfileAction $action): RedirectResponse
    {
        $action->execute($request->user(), $request->validated());

        return redirect()->route('profile.edit')->with('status', 'profile-updated');
    }

    public function destroy(DeleteProfileRequest $request): RedirectResponse
    {
        $request->validated();

        $user = $request->user();

        // Guard: super-admin cannot self-delete, and the last super-admin
        // account can never be removed — orphaning the system would lock
        // out administrative recovery.
        if ($user->hasRole(UserRole::SuperAdmin)) {
            $remaining = User::query()
                ->where('role', UserRole::SuperAdmin->value)
                ->where('id', '!=', $user->id)
                ->count();

            if ($remaining === 0) {
                return back()
                    ->withErrors(
                        ['password' => 'لا يمكن حذف حساب المدير العام الأخير في النظام.'],
                        'userDeletion',
                    );
            }
        }

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
