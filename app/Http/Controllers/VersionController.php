<?php

namespace App\Http\Controllers;

use App\Jobs\DiffDocumentVersionJob;
use App\Models\DocumentVersion;
use App\Models\LegalDocument;
use App\Services\TextExtractorService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Handles uploading a new version of an existing document.
 *
 * Flow:
 *   1. Validate + store the new file under storage/public/documents/
 *   2. Try synchronous text extraction (PDF/DOCX/TXT). If it fails for a
 *      PDF the user will see an error — OCR fallback for new versions is
 *      out of scope for now (the original document still has its OCR'd
 *      content from the first upload).
 *   3. Insert a DocumentVersion row with auto-incremented version_number.
 *   4. Dispatch DiffDocumentVersionJob which compares articles, creates
 *      auto ArticleUpdate rows, swaps in the new content, and reindexes.
 *
 * Authorization: same as LegalDocumentPolicy::update — uploader, legal
 * counsel+ in the same org, or higher.
 */
class VersionController extends Controller
{
    use AuthorizesRequests;

    public function store(Request $request, LegalDocument $document): RedirectResponse
    {
        $this->authorize('update', $document);

        $request->validate([
            'file' => 'required|file|mimes:pdf,doc,docx,txt|max:20480',
        ]);

        $file = $request->file('file');
        $storedPath = $file->store('documents', 'public');

        // Try synchronous extraction. Versions are always document files
        // (PDF/DOCX/TXT), not images, so no OCR fallback path here.
        $extracted = app(TextExtractorService::class)
            ->extract(Storage::disk('public')->path($storedPath));

        if ($extracted === null || trim($extracted) === '') {
            // Roll back the upload — the file has no usable text and we
            // can't diff against it. The original document is unchanged.
            Storage::disk('public')->delete($storedPath);
            return back()->withErrors([
                'file' => 'تعذّر استخراج نص من الملف المرفوع. تأكد أن الملف نصي وقابل للقراءة.',
            ]);
        }

        // Compute the next version_number for this document.
        $nextVersion = ((int) $document->versions()->max('version_number')) + 1;

        $version = DocumentVersion::create([
            'document_id'   => $document->id,
            'version_number' => $nextVersion,
            'file_path'     => $storedPath,
            'file_name'     => $file->getClientOriginalName(),
            'file_size'     => $file->getSize(),
            'content'       => $extracted,
            'uploaded_by'   => $request->user()->id,
        ]);

        DiffDocumentVersionJob::dispatch($version);

        return redirect()
            ->route('documents.show', $document)
            ->with('success', 'تم رفع النسخة الجديدة. جاري مقارنتها بالنسخة الحالية في الخلفية.');
    }
}
