<?php

namespace App\Http\Controllers;

use App\Models\Discussion;
use App\Models\LegalDocument;
use Illuminate\Http\Request;

class DiscussionController extends Controller
{
    public function store(Request $request, LegalDocument $document)
    {
        abort_if($document->org_id !== $request->user()->org_id, 403);

        $data = $request->validate([
            'title' => 'required|string|max:200',
            'body' => 'required|string|max:5000',
            'visibility' => 'nullable|in:public,org,private',
        ]);

        Discussion::create([
            'document_id' => $document->id,
            'user_id' => $request->user()->id,
            'title' => $data['title'],
            'body' => $data['body'],
            'visibility' => $data['visibility'] ?? 'org',
        ]);

        return back()->with('success', 'تم إنشاء النقاش');
    }

    public function show(Request $request, Discussion $discussion)
    {
        abort_if($discussion->document->org_id !== $request->user()->org_id, 403);
        $discussion->load(['user', 'document', 'replies.user']);
        return view('discussions.show', compact('discussion'));
    }

    public function reply(Request $request, Discussion $discussion)
    {
        abort_if($discussion->document->org_id !== $request->user()->org_id, 403);

        $data = $request->validate([
            'body' => 'required|string|max:5000',
        ]);

        $discussion->replies()->create([
            'user_id' => $request->user()->id,
            'body' => $data['body'],
        ]);

        return back()->with('success', 'تم إضافة الرد');
    }

    public function destroy(Request $request, Discussion $discussion)
    {
        abort_unless($discussion->user_id === $request->user()->id, 403);
        $discussion->delete();
        return back()->with('success', 'تم حذف النقاش');
    }
}
