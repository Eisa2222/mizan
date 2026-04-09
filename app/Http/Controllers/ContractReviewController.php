<?php

namespace App\Http\Controllers;

use App\Jobs\ReviewContractJob;
use App\Models\LegalDocument;
use App\Services\ElasticsearchService;
use App\Services\TextExtractorService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ContractReviewController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request)
    {
        $docs = LegalDocument::where('org_id', $request->user()->org_id)
            ->where('kind', LegalDocument::KIND_CONTRACT_REVIEW)
            ->latest()
            ->paginate(15);

        return view('contract-reviews.index', compact('docs'));
    }

    public function show(Request $request, LegalDocument $document)
    {
        abort_if($document->kind !== LegalDocument::KIND_CONTRACT_REVIEW, 404);
        abort_if($document->org_id !== $request->user()->org_id, 403);

        $document->load([
            'chunks', 'articleUpdates.creator', 'versions.uploader',
            'relationsFrom.toDocument', 'relationsTo.fromDocument', 'uploader',
        ]);

        return view('contract-reviews.show', compact('document'));
    }

    public function create()
    {
        return view('contract-reviews.create');
    }

    public function store(Request $request)
    {

        $data = $request->validate([
            'title' => 'required|string|max:255',
            'summary' => 'nullable|string|max:5000',
            'file' => 'required|file|mimes:pdf,doc,docx|max:20480',
        ]);

        $file = $request->file('file');
        $data['file_path'] = $file->store('documents', 'public');
        $data['file_name'] = $file->getClientOriginalName();
        $data['file_size'] = $file->getSize();
        $data['org_id'] = $request->user()->org_id;
        $data['uploaded_by'] = $request->user()->id;
        $data['kind'] = LegalDocument::KIND_CONTRACT_REVIEW;
        $data['type'] = 1;

        $extracted = app(TextExtractorService::class)
            ->extract(Storage::disk('public')->path($data['file_path']));
        if ($extracted) {
            $data['content'] = $extracted;
        }

        unset($data['file']);
        $document = LegalDocument::create($data);

        if ($document->content) {
            app(ElasticsearchService::class)->reindexDocument($document);
            ReviewContractJob::dispatch($document);
        }

        return redirect()->route('contract-reviews.show', $document)
            ->with('success', 'تم رفع العقد للمراجعة. جاري التحليل في الخلفية.');
    }
}
