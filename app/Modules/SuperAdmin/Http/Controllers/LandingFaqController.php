<?php

namespace Modules\SuperAdmin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\LandingFaq;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LandingFaqController extends Controller
{
    public function index(): View
    {
        return view('super-admin.landing.faqs.index', [
            'faqs' => LandingFaq::orderBy('sort_order')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'question'  => ['required', 'string', 'max:500'],
            'answer'    => ['required', 'string', 'max:5000'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $data['sort_order'] = (int) LandingFaq::max('sort_order') + 1;

        LandingFaq::create($data);

        return back()->with('success', 'تم إضافة السؤال.');
    }

    public function update(Request $request, LandingFaq $faq): RedirectResponse
    {
        $faq->update($request->validate([
            'question'  => ['required', 'string', 'max:500'],
            'answer'    => ['required', 'string', 'max:5000'],
            'is_active' => ['sometimes', 'boolean'],
        ]));

        return back()->with('success', 'تم تحديث السؤال.');
    }

    public function destroy(LandingFaq $faq): RedirectResponse
    {
        $faq->delete();
        return back()->with('success', 'تم حذف السؤال.');
    }

    public function reorder(Request $request): JsonResponse
    {
        $ids = $request->validate([
            'ids'   => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:landing_faqs,id'],
        ])['ids'];

        foreach ($ids as $index => $id) {
            LandingFaq::where('id', $id)->update(['sort_order' => $index]);
        }

        return response()->json(['ok' => true]);
    }
}
