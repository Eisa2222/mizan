<?php

namespace Modules\SuperAdmin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\LandingFeature;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LandingFeatureController extends Controller
{
    public function index(): View
    {
        return view('super-admin.landing.features.index', [
            'features' => LandingFeature::orderBy('sort_order')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title'       => ['required', 'string', 'max:100'],
            'description' => ['required', 'string', 'max:500'],
            'icon'        => ['nullable', 'string', 'max:5000'],
            'is_active'   => ['sometimes', 'boolean'],
        ]);

        $data['sort_order'] = (int) LandingFeature::max('sort_order') + 1;

        LandingFeature::create($data);

        return back()->with('success', 'تم إضافة الميزة.');
    }

    public function update(Request $request, LandingFeature $feature): RedirectResponse
    {
        $feature->update($request->validate([
            'title'       => ['required', 'string', 'max:100'],
            'description' => ['required', 'string', 'max:500'],
            'icon'        => ['nullable', 'string', 'max:5000'],
            'is_active'   => ['sometimes', 'boolean'],
        ]));

        return back()->with('success', 'تم تحديث الميزة.');
    }

    public function destroy(LandingFeature $feature): RedirectResponse
    {
        $feature->delete();
        return back()->with('success', 'تم حذف الميزة.');
    }

    /**
     * Drag-and-drop reorder. Accepts `ids[]` in the desired order and
     * rewrites sort_order on each row in a single transaction.
     */
    public function reorder(Request $request): JsonResponse
    {
        $ids = $request->validate([
            'ids'   => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:landing_features,id'],
        ])['ids'];

        foreach ($ids as $index => $id) {
            LandingFeature::where('id', $id)->update(['sort_order' => $index]);
        }

        return response()->json(['ok' => true]);
    }
}
