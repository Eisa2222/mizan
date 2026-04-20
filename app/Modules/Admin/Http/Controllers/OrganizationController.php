<?php

namespace Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Modules\Admin\Actions\CreateOrganizationWithAdminAction;
use Modules\Admin\Http\Requests\StoreOrganizationRequest;
use Modules\Admin\Queries\OrganizationsWithUserCountsQuery;

class OrganizationController extends Controller
{
    public function index(OrganizationsWithUserCountsQuery $query): View
    {
        return view('admin.organizations', [
            'orgs' => $query->run(),
        ]);
    }

    public function create(): View
    {
        return view('admin.create-organization');
    }

    public function store(StoreOrganizationRequest $request, CreateOrganizationWithAdminAction $action): RedirectResponse
    {
        $organization = $action->execute($request->toData());

        return redirect()
            ->route('admin.organizations')
            ->with('success', __('admin.flash.organization_created', ['name' => $organization->name_ar]));
    }
}
