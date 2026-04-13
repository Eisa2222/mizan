<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BrandingController extends Controller
{
    public function edit(Request $request)
    {
        $org = $request->user()->organization;
        if (! $org) abort(403, 'لا توجد مؤسسة مرتبطة بحسابك.');

        return view('branding.edit', compact('org'));
    }

    public function update(Request $request)
    {
        $org = $request->user()->organization;
        if (! $org) abort(403);

        $data = $request->validate([
            'name_ar'       => 'required|string|max:255',
            'name_en'       => 'nullable|string|max:255',
            'header_text'   => 'nullable|string|max:500',
            'footer_text'   => 'nullable|string|max:500',
            'primary_color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'accent_color'  => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'phone'         => 'nullable|string|max:50',
            'email'         => 'nullable|email|max:200',
            'website'       => 'nullable|string|max:300',
            'address'       => 'nullable|string|max:500',
            'logo'          => 'nullable|image|mimes:png,jpg,jpeg,svg,webp|max:2048',
        ]);

        if ($request->hasFile('logo')) {
            // Delete old logo
            if ($org->logo_path) {
                Storage::disk('public')->delete($org->logo_path);
            }
            $data['logo_path'] = $request->file('logo')->store('branding', 'public');
        }
        unset($data['logo']);

        $org->update($data);

        return redirect()->route('branding.edit')->with('success', 'تم حفظ هوية المؤسسة بنجاح.');
    }

    public function removeLogo(Request $request)
    {
        $org = $request->user()->organization;
        if (! $org) abort(403);

        if ($org->logo_path) {
            Storage::disk('public')->delete($org->logo_path);
            $org->update(['logo_path' => null]);
        }

        return redirect()->route('branding.edit')->with('success', 'تم حذف الشعار.');
    }
}
