<?php

namespace App\Http\Controllers;

use App\Models\Annotation;
use App\Models\LegalDocument;
use Illuminate\Http\Request;

class AnnotationController extends Controller
{
    public function store(Request $request, LegalDocument $document)
    {
        abort_if($document->org_id !== $request->user()->org_id, 403);

        $data = $request->validate([
            'selected_text' => 'required|string|max:5000',
            'comment' => 'nullable|string|max:2000',
            'color' => 'required|in:gold,blue,green,red',
            'visibility' => 'nullable|in:public,org,private',
        ]);

        Annotation::create([
            'document_id' => $document->id,
            'user_id' => $request->user()->id,
            'selected_text' => $data['selected_text'],
            'comment' => $data['comment'] ?? null,
            'color' => $data['color'],
            'visibility' => $data['visibility'] ?? 'org',
        ]);

        return back()->with('success', 'تمت إضافة الملاحظة');
    }

    public function destroy(Request $request, Annotation $annotation)
    {
        abort_unless($annotation->user_id === $request->user()->id, 403);
        $annotation->delete();
        return back()->with('success', 'تم حذف الملاحظة');
    }
}
