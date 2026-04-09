<?php

namespace App\Http\Controllers;

use App\Models\Folder;
use App\Models\LegalDocument;
use App\Models\User;
use App\Services\ElasticsearchService;
use App\Services\TextExtractorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class FolderController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $folders = Folder::query()
            ->where('org_id', $user->org_id)
            ->whereNull('parent_id')
            ->where(function ($q) use ($user) {
                $q->where('owner_id', $user->id)
                  ->orWhereHas('members', fn ($m) => $m->where('user_id', $user->id));
            })
            ->withCount(['documents', 'children', 'members'])
            ->latest()
            ->get();

        return view('folders.index', compact('folders'));
    }

    public function create()
    {
        return view('folders.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:200',
            'description' => 'nullable|string|max:1000',
            'parent_id' => 'nullable|integer|exists:folders,id',
        ]);

        $data['org_id'] = $request->user()->org_id;
        $data['owner_id'] = $request->user()->id;

        $folder = Folder::create($data);

        return redirect()->route('folders.show', $folder)->with('success', 'تم إنشاء المجلد');
    }

    public function show(Request $request, Folder $folder)
    {
        abort_unless($folder->isAccessibleBy($request->user()), 403);

        $folder->load([
            'owner',
            'members.user',
            'documents' => fn ($q) => $q->latest('folder_documents.created_at'),
            'children' => fn ($q) => $q->withCount('documents'),
        ]);

        $orgUsers = User::where('org_id', $folder->org_id)
            ->whereNotIn('id', $folder->members->pluck('user_id')->push($folder->owner_id))
            ->orderBy('name')
            ->get();

        return view('folders.show', compact('folder', 'orgUsers'));
    }

    public function destroy(Request $request, Folder $folder)
    {
        abort_unless($folder->owner_id === $request->user()->id, 403);
        $folder->delete();
        return redirect()->route('folders.index')->with('success', 'تم حذف المجلد');
    }

    public function addMember(Request $request, Folder $folder)
    {
        abort_unless($folder->owner_id === $request->user()->id, 403);

        $data = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'role' => 'required|in:viewer,editor,admin',
        ]);

        $target = User::findOrFail($data['user_id']);
        abort_if($target->org_id !== $folder->org_id, 422);

        $folder->members()->updateOrCreate(
            ['user_id' => $data['user_id']],
            ['role' => $data['role']]
        );

        return back()->with('success', 'تم إضافة العضو');
    }

    public function removeMember(Request $request, Folder $folder, int $userId)
    {
        abort_unless($folder->owner_id === $request->user()->id, 403);
        $folder->members()->where('user_id', $userId)->delete();
        return back()->with('success', 'تم إزالة العضو');
    }

    public function uploadDocument(Request $request, Folder $folder)
    {
        abort_unless($folder->isAccessibleBy($request->user()), 403);

        $data = $request->validate([
            'title' => 'required|string|max:255',
            'type' => 'required|integer|between:1,7',
            'summary' => 'nullable|string|max:5000',
            'content' => 'nullable|string',
            'source_entity' => 'nullable|string|max:200',
            'file' => 'nullable|file|mimes:pdf,doc,docx,txt|max:20480',
        ]);

        $payload = [
            'org_id'        => $folder->org_id,
            'title'         => $data['title'],
            'type'          => $data['type'],
            'summary'       => $data['summary'] ?? null,
            'content'       => $data['content'] ?? null,
            'source_entity' => $data['source_entity'] ?? null,
            'uploaded_by'   => $request->user()->id,
            'is_private'    => true, // private to folder members
        ];

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $payload['file_path'] = $file->store('documents', 'public');
            $payload['file_name'] = $file->getClientOriginalName();
            $payload['file_size'] = $file->getSize();

            // Extract full text from the uploaded file
            if (empty($payload['content'])) {
                $extracted = app(TextExtractorService::class)
                    ->extract(Storage::disk('public')->path($payload['file_path']));
                if ($extracted !== null && $extracted !== '') {
                    $payload['content'] = $extracted;
                }
            }
        }

        $doc = LegalDocument::create($payload);

        // Attach to folder
        $folder->documents()->attach($doc->id, ['added_by' => $request->user()->id]);

        // Chunk + index
        app(ElasticsearchService::class)->reindexDocument($doc);

        return back()->with('success', 'تم رفع المستند للمجلد');
    }

    public function removeDocument(Request $request, Folder $folder, int $documentId)
    {
        abort_unless($folder->isAccessibleBy($request->user()), 403);
        $folder->documents()->detach($documentId);
        return back()->with('success', 'تمت إزالة المستند');
    }
}
