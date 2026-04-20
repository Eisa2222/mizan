<?php

namespace Modules\Branding\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Branding\Actions\RemoveBrandingLogoAction;
use Modules\Branding\Actions\UpdateBrandingAction;
use Modules\Branding\Http\Requests\UpdateBrandingRequest;

class BrandingController extends Controller
{
    public function edit(Request $request): View
    {
        $org = $request->user()->organization;

        abort_unless($org !== null, 403, __('branding.errors.no_organization'));

        return view('branding.edit', compact('org'));
    }

    public function update(UpdateBrandingRequest $request, UpdateBrandingAction $action): RedirectResponse
    {
        $org = $request->user()->organization;

        $action->execute($org, $request->validated(), $request->file('logo'));

        return redirect()
            ->route('branding.edit')
            ->with('success', __('branding.flash.updated'));
    }

    public function removeLogo(Request $request, RemoveBrandingLogoAction $action): RedirectResponse
    {
        $org = $request->user()->organization;

        abort_unless($org !== null, 403);

        $action->execute($org);

        return redirect()
            ->route('branding.edit')
            ->with('success', __('branding.flash.logo_removed'));
    }
}
