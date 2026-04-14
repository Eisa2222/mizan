<?php

namespace App\Http\Controllers;

use App\Jobs\DraftMemoJob;
use App\Models\LegalDocument;
use App\Services\ElasticsearchService;
use App\Services\TextExtractorService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MemoController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request)
    {
        $docs = LegalDocument::where('org_id', $request->user()->org_id)
            ->where('kind', LegalDocument::KIND_MEMO)
            ->latest()
            ->paginate(15);

        return view('memos.index', compact('docs'));
    }

    public function show(Request $request, LegalDocument $document)
    {
        abort_if($document->kind !== LegalDocument::KIND_MEMO, 404);
        abort_if($document->org_id !== $request->user()->org_id, 403);

        $document->load([
            'chunks', 'articleUpdates.creator', 'versions.uploader',
            'relationsFrom.toDocument', 'relationsTo.fromDocument', 'uploader',
        ]);

        return view('memos.show', compact('document'));
    }

    public function create()
    {
        return view('memos.create');
    }

    public function store(Request $request)
    {

        $data = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'nullable|string',
            'file' => 'nullable|file|mimes:pdf,doc,docx|max:20480',
        ]);

        $data['org_id'] = $request->user()->org_id;
        $data['uploaded_by'] = $request->user()->id;
        $data['kind'] = LegalDocument::KIND_MEMO;
        $data['type'] = 1;

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $data['file_path'] = $file->store('documents', 'public');
            $data['file_name'] = $file->getClientOriginalName();
            $data['file_size'] = $file->getSize();

            if (empty($data['content'])) {
                $extracted = app(TextExtractorService::class)
                    ->extract(Storage::disk('public')->path($data['file_path']));
                if ($extracted) $data['content'] = $extracted;
            }
        }

        unset($data['file']);
        $document = LegalDocument::create($data);

        if ($document->content) {
            app(ElasticsearchService::class)->reindexDocument($document);
            DraftMemoJob::dispatchSync($document);
        }

        return redirect()->route('memos.show', $document)
            ->with('success', 'تم رفع المسودة وتحليلها.');
    }
}
