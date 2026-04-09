<?php

namespace App\Http\Controllers;

use App\Jobs\AnalyzeCaseJob;
use App\Jobs\AnalyzeContractJob;
use App\Jobs\DraftMemoJob;
use App\Jobs\ExtractDocumentTextJob;
use App\Jobs\GenerateSuggestedQuestionsJob;
use App\Jobs\ReviewContractJob;
use App\Models\LegalDocument;
use App\Services\ElasticsearchService;
use App\Services\TextExtractorService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class DocumentController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request)
    {
        $this->authorize('viewAny', LegalDocument::class);
        $user = $request->user();

        // Documents index shows only regular documents (kind=document).
        // Contract reviews and memos have their own dedicated pages.
        $query = LegalDocument::query()
            ->where('org_id', $user->org_id)
            ->where('kind', LegalDocument::KIND_DOCUMENT)
            ->where(function ($q) use ($user) {
                $q->where('is_private', false)
                  ->orWhere('uploaded_by', $user->id)
                  ->orWhereExists(function ($sub) use ($user) {
                      $sub->select(DB::raw(1))
                          ->from('folder_documents')
                          ->join('folders', 'folders.id', '=', 'folder_documents.folder_id')
                          ->leftJoin('folder_members', function ($j) use ($user) {
                              $j->on('folder_members.folder_id', '=', 'folder_documents.folder_id')
                                ->where('folder_members.user_id', '=', $user->id);
                          })
                          ->whereColumn('folder_documents.document_id', 'legal_documents.id')
                          ->where(function ($w) use ($user) {
                              $w->where('folders.owner_id', $user->id)
                                ->orWhereNotNull('folder_members.id');
                          });
                  });
            })
            ->latest();

        if ($search = $request->query('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('title_en', 'like', "%{$search}%")
                  ->orWhere('summary', 'like', "%{$search}%")
                  ->orWhere('content', 'like', "%{$search}%");
            });
        }

        if ($type = $request->query('type')) {
            $query->where('type', $type);
        }

        $documents = $query->paginate(15)->withQueryString();

        return view('documents.index', compact('documents'));
    }

    public function create()
    {
        $this->authorize('create', LegalDocument::class);
        return view('documents.create');
    }

    public function store(Request $request)
    {
        $this->authorize('create', LegalDocument::class);
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'title_en' => 'nullable|string|max:255',
            'type' => 'required|integer|between:1,7',
            // kind is forced to 'document' here — contract_review and memo
            // have their own dedicated controllers and upload pages.
            // 'kind' => ['nullable', 'string', Rule::in(array_keys(LegalDocument::KINDS))],
            'summary' => 'nullable|string|max:5000',
            'content' => 'nullable|string',
            'issued_at' => 'nullable|date',
            'reference_number' => 'nullable|string|max:100',
            'source_entity' => 'nullable|string|max:200',
            'file' => 'nullable|file|mimes:pdf,doc,docx,jpg,jpeg,png,tiff,tif,webp|max:20480',
        ]);
        // Documents page always creates regular documents
        $data['kind'] = LegalDocument::KIND_DOCUMENT;

        $data['org_id'] = $request->user()->org_id;
        $data['uploaded_by'] = $request->user()->id;

        // Track whether OCR will need to run after the document is saved.
        $needsOcr = false;

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $data['file_path'] = $file->store('documents', 'public');
            $data['file_name'] = $file->getClientOriginalName();
            $data['file_size'] = $file->getSize();

            $ext = strtolower($file->getClientOriginalExtension());
            $isImage = in_array($ext, ['jpg', 'jpeg', 'png', 'tiff', 'tif', 'webp'], true);

            if (empty($data['content'])) {
                if ($isImage) {
                    // Images can only be handled by OCR — defer to background job.
                    $needsOcr = true;
                    $data['metadata'] = array_merge($data['metadata'] ?? [], [
                        'extraction_status' => 'pending',
                    ]);
                } else {
                    // Try synchronous text extraction first (cheap PDF/DOCX/TXT).
                    $extracted = app(TextExtractorService::class)
                        ->extract(Storage::disk('public')->path($data['file_path']));
                    if ($extracted !== null && $extracted !== '') {
                        $data['content'] = $extracted;
                    } elseif ($ext === 'pdf') {
                        // Scanned PDF or broken cmap — fall back to OCR job.
                        $needsOcr = true;
                        $data['metadata'] = array_merge($data['metadata'] ?? [], [
                            'extraction_status' => 'pending',
                        ]);
                    }
                }
            }
        }

        unset($data['file']);
        $document = LegalDocument::create($data);

        if ($needsOcr) {
            // OCR runs in the background; reindex happens inside the job after success.
            // For contract/case kinds we still chain analysis, but ExtractDocumentTextJob
            // currently just fills content + reindexes — analysis dispatch happens here
            // synchronously in the redirect path. The tradeoff is OCR'd contracts skip
            // analysis until the user re-triggers it. Acceptable for v1.
            ExtractDocumentTextJob::dispatch($document);
            return redirect()
                ->route('documents.show', $document)
                ->with('success', 'تم رفع المستند. جاري استخراج المحتوى عبر OCR في الخلفية — حدّث الصفحة بعد دقائق.');
        }

        // Chunk + bulk index immediately (synchronous; move to a queue when traffic grows)
        app(ElasticsearchService::class)->reindexDocument($document);

        // Dispatch kind-specific AI analysis (jobs degrade gracefully when AI is unset)
        match ($document->kind) {
            LegalDocument::KIND_CONTRACT        => AnalyzeContractJob::dispatch($document),
            LegalDocument::KIND_CASE            => AnalyzeCaseJob::dispatch($document),
            LegalDocument::KIND_CONTRACT_REVIEW => ReviewContractJob::dispatch($document),
            LegalDocument::KIND_MEMO            => DraftMemoJob::dispatch($document),
            default                             => GenerateSuggestedQuestionsJob::dispatch($document),
        };

        $msg = match ($document->kind) {
            LegalDocument::KIND_CONTRACT        => 'تم رفع العقد بنجاح. جاري تحليل المخاطر في الخلفية.',
            LegalDocument::KIND_CASE            => 'تم رفع القضية بنجاح. جاري تحليلها في الخلفية.',
            LegalDocument::KIND_CONTRACT_REVIEW => 'تم رفع العقد للمراجعة. جاري المراجعة الشاملة في الخلفية.',
            LegalDocument::KIND_MEMO            => 'تم رفع المسودة. جاري تحليلها وتقديم التوصيات في الخلفية.',
            default                             => 'تم رفع المستند بنجاح وفهرسته.',
        };

        return redirect()->route('documents.index')->with('success', $msg);
    }

    public function show(LegalDocument $document, Request $request)
    {
        $this->authorize('view', $document);

        // Eager-load everything the show page renders so we don't N+1 across
        // the article index, timeline, related-documents and version history.
        $document->load([
            'chunks',
            'articleUpdates.creator',
            'articleUpdates.sourceDocument',
            'versions.uploader',
            'relationsFrom.toDocument',
            'relationsFrom.creator',
            'relationsTo.fromDocument',
            'relationsTo.creator',
            'uploader',
        ]);

        return view('documents.show', compact('document'));
    }

    public function destroy(LegalDocument $document, Request $request)
    {
        $this->authorize('delete', $document);

        if ($document->file_path) {
            Storage::disk('public')->delete($document->file_path);
        }
        app(ElasticsearchService::class)->deleteDocument($document->id);
        $document->delete();

        return redirect()->route('documents.index')->with('success', 'تم حذف المستند');
    }

    /**
     * Update the document's content directly. Used for manual correction of
     * extraction artifacts (broken Arabic ligatures, OCR errors, etc.).
     * Reindexes Elasticsearch after the update so search stays in sync.
     */
    public function updateContent(Request $request, LegalDocument $document)
    {
        $this->authorize('update', $document);

        $data = $request->validate([
            'content' => 'nullable|string|max:1000000',
            'summary' => 'nullable|string|max:5000',
        ]);

        $document->update($data);
        app(ElasticsearchService::class)->reindexDocument($document->fresh());

        return redirect()
            ->route('documents.show', $document)
            ->with('success', 'تم تحديث المحتوى وإعادة الفهرسة.');
    }

    /**
     * Title-prefix autocomplete used by the "link related document" modal
     * on the show page. Restricts to the caller's org and returns at most
     * 10 hits as JSON. The current document is excluded so users don't
     * accidentally link a document to itself.
     */
    public function autocomplete(Request $request)
    {
        $this->authorize('viewAny', LegalDocument::class);

        $query = trim((string) $request->query('q', ''));
        $excludeId = (int) $request->query('exclude', 0);
        if (mb_strlen($query) < 1) {
            return response()->json(['results' => []]);
        }

        $hits = LegalDocument::query()
            ->where('org_id', $request->user()->org_id)
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->where(function ($q) use ($query) {
                $q->where('title', 'like', "%{$query}%")
                  ->orWhere('title_en', 'like', "%{$query}%")
                  ->orWhere('reference_number', 'like', "%{$query}%");
            })
            ->orderBy('title')
            ->limit(10)
            ->get(['id', 'title', 'title_en', 'type', 'reference_number'])
            ->map(fn (LegalDocument $d) => [
                'id'               => $d->id,
                'title'            => $d->title,
                'title_en'         => $d->title_en,
                'type_label'       => $d->type_label,
                'reference_number' => $d->reference_number,
            ]);

        return response()->json(['results' => $hits]);
    }
}
