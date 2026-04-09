<?php

namespace App\Http\Controllers;

use App\Jobs\TenderReviewJob;
use App\Models\LegalDocument;
use App\Services\ElasticsearchService;
use App\Services\TextExtractorService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TenderReviewController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request)
    {
        $docs = LegalDocument::where('org_id', $request->user()->org_id)
            ->where('kind', LegalDocument::KIND_TENDER_REVIEW)
            ->latest()
            ->paginate(15);

        return view('tender-reviews.index', compact('docs'));
    }

    public function create()
    {
        return view('tender-reviews.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'tender_type' => 'nullable|string|max:100',
            'sector' => 'nullable|string|max:100',
            'summary' => 'nullable|string|max:5000',
            'file' => 'required|file|mimes:pdf,doc,docx|max:40960',
        ]);

        $file = $request->file('file');
        $data['file_path'] = $file->store('documents', 'public');
        $data['file_name'] = $file->getClientOriginalName();
        $data['file_size'] = $file->getSize();
        $data['org_id'] = $request->user()->org_id;
        $data['uploaded_by'] = $request->user()->id;
        $data['kind'] = LegalDocument::KIND_TENDER_REVIEW;
        $data['type'] = 1;

        // Store tender metadata
        $data['metadata'] = [
            'tender_type' => $data['tender_type'] ?? null,
            'sector' => $data['sector'] ?? null,
        ];
        unset($data['tender_type'], $data['sector']);

        $extracted = app(TextExtractorService::class)
            ->extract(Storage::disk('public')->path($data['file_path']));
        if ($extracted) {
            $data['content'] = $extracted;
        }

        unset($data['file']);
        $document = LegalDocument::create($data);

        if ($document->content) {
            app(ElasticsearchService::class)->reindexDocument($document);
            TenderReviewJob::dispatch($document);
        }

        return redirect()->route('tender-reviews.show', $document)
            ->with('success', 'تم رفع الكراسة. جاري المراجعة والفحص في الخلفية.');
    }

    public function show(Request $request, LegalDocument $document)
    {
        abort_if($document->kind !== LegalDocument::KIND_TENDER_REVIEW, 404);
        abort_if($document->org_id !== $request->user()->org_id, 403);

        $document->load(['uploader']);

        $annotations = \App\Models\Annotation::with('user')
            ->where('document_id', $document->id)
            ->visibleTo($request->user())
            ->latest()->get();

        $discussions = \App\Models\Discussion::with(['user', 'replies'])
            ->where('document_id', $document->id)
            ->visibleTo($request->user())
            ->latest()->get();

        return view('tender-reviews.show', compact('document', 'annotations', 'discussions'));
    }
}
