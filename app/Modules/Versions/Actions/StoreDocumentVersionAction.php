<?php

namespace Modules\Versions\Actions;

use App\Jobs\DiffDocumentVersionJob;
use App\Models\DocumentVersion;
use App\Models\LegalDocument;
use App\Models\User;
use App\Services\TextExtractorService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Uploads a new version of a document:
 *   1. Stores the file
 *   2. Extracts text synchronously (no OCR fallback for versions)
 *   3. Inserts a DocumentVersion row with the next version_number
 *   4. Dispatches DiffDocumentVersionJob to compare articles + reindex
 *
 * Throws RuntimeException if extraction fails so the caller can roll back
 * the upload and surface a validation-style error.
 */
class StoreDocumentVersionAction
{
    public function __construct(private readonly TextExtractorService $extractor)
    {
    }

    public function execute(LegalDocument $document, User $user, UploadedFile $file): DocumentVersion
    {
        $storedPath = $file->store('documents', 'public');

        $extracted = $this->extractor->extract(Storage::disk('public')->path($storedPath));

        if ($extracted === null || trim($extracted) === '') {
            Storage::disk('public')->delete($storedPath);

            throw new RuntimeException(__('versions.errors.extraction_failed'));
        }

        $nextVersion = ((int) $document->versions()->max('version_number')) + 1;

        $version = DocumentVersion::create([
            'document_id'    => $document->id,
            'version_number' => $nextVersion,
            'file_path'      => $storedPath,
            'file_name'      => $file->getClientOriginalName(),
            'file_size'      => $file->getSize(),
            'content'        => $extracted,
            'uploaded_by'    => $user->id,
        ]);

        DiffDocumentVersionJob::dispatch($version);

        return $version;
    }
}
