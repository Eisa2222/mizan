<?php

namespace Modules\Admin\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Admin\Actions\CreateUserAction;
use Modules\Admin\Actions\UpdateUserRoleAction;
use Modules\Admin\Http\Requests\StoreUserRequest;
use Modules\Admin\Http\Requests\UpdateUserRoleRequest;
use Modules\Admin\Queries\OrganizationDropdownQuery;
use Modules\Admin\Queries\UsersListQuery;

class UserController extends Controller
{
    public function index(Request $request, UsersListQuery $query, OrganizationDropdownQuery $orgs): View
    {
        $orgId = $request->query('org');
        $users = $query->paginate($orgId !== null ? (int) $orgId : null);

        return view('admin.users', [
            'users' => $users,
            'orgs'  => $orgs->run(),
            'roles' => UserRole::options(),
        ]);
    }

    public function create(OrganizationDropdownQuery $orgs): View
    {
        return view('admin.create-user', [
            'orgs'  => $orgs->run(),
            'roles' => UserRole::cases(),
        ]);
    }

    public function store(StoreUserRequest $request, CreateUserAction $action): RedirectResponse
    {
        $user = $action->execute($request->toData());

        return redirect()
            ->route('admin.users')
            ->with('success', __('admin.flash.user_created', ['name' => $user->name]));
    }

    public function updateRole(UpdateUserRoleRequest $request, User $user, UpdateUserRoleAction $action): RedirectResponse
    {
        $role = $request->role();
        $action->execute($user, $role);

        return back()->with('success', __('admin.flash.role_updated', [
            'name' => $user->name,
            'role' => $role->label(),
        ]));
    }
}
