<?php

namespace Modules\Documents\Actions;

use App\Models\LegalDocument;
use App\Services\TextExtractorService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Documents\DTOs\StoreDocumentData;

/**
 * Persists a new LegalDocument and prepares it for downstream processing.
 *
 * Sets metadata['extraction_status']='pending' when OCR is needed so the
 * caller can dispatch ExtractDocumentTextJob after creation. Synchronous
 * text extraction is attempted inline for cheap formats (PDF/DOCX/TXT).
 */
class StoreDocumentAction
{
    public function __construct(private readonly TextExtractorService $extractor)
    {
    }

    /**
     * @return array{document:LegalDocument,needs_ocr:bool}
     */
    public function execute(StoreDocumentData $data): array
    {
        $payload = [
            'org_id'           => $data->orgId,
            'uploaded_by'      => $data->uploadedBy,
            'title'            => $data->title,
            'title_en'         => $data->titleEn,
            'type'             => $data->type,
            'kind'             => $data->kind,
            'summary'          => $data->summary,
            'content'          => $data->content,
            'issued_at'        => $data->issuedAt,
            'reference_number' => $data->referenceNumber,
            'source_entity'    => $data->sourceEntity,
        ];

        $needsOcr = false;

        if ($data->file instanceof UploadedFile) {
            [$filePayload, $extractedContent, $needsOcr] = $this->processFile($data->file, $payload['content']);

            $payload = array_merge($payload, $filePayload);
            if ($extractedContent !== null) {
                $payload['content'] = $extractedContent;
            }
            if ($needsOcr) {
                $payload['metadata'] = array_merge($payload['metadata'] ?? [], [
                    'extraction_status' => 'pending',
                ]);
            }
        }

        $document = LegalDocument::create($payload);

        return ['document' => $document, 'needs_ocr' => $needsOcr];
    }

    /**
     * @return array{0: array<string,mixed>, 1: ?string, 2: bool}
     */
    private function processFile(UploadedFile $file, ?string $existingContent): array
    {
        $storedPath = $file->store('documents', 'public');

        // Arabic Windows browsers (and some legacy desktop apps) occasionally
        // submit filename bytes in CP-1256 instead of UTF-8. Persisting those
        // bytes as-is leaves unreadable ?????? in the DB (audit #6). Detect
        // non-UTF-8 input and transcode before storing.
        $rawName = $file->getClientOriginalName();
        if (! mb_check_encoding($rawName, 'UTF-8')) {
            $converted = @iconv('WINDOWS-1256', 'UTF-8//IGNORE', $rawName);
            if ($converted !== false && mb_check_encoding($converted, 'UTF-8')) {
                $rawName = $converted;
            }
        }

        $filePayload = [
            'file_path' => $storedPath,
            'file_name' => $rawName,
            'file_size' => $file->getSize(),
        ];

        if (! empty($existingContent)) {
            return [$filePayload, null, false];
        }

        $extension = strtolower($file->getClientOriginalExtension());
        $imageExtensions = ['jpg', 'jpeg', 'png', 'tiff', 'tif', 'webp'];

        if (in_array($extension, $imageExtensions, true)) {
            return [$filePayload, null, true];
        }

        $extracted = $this->extractor->extract(Storage::disk('public')->path($storedPath));

        if ($extracted !== null && $extracted !== '') {
            return [$filePayload, $extracted, false];
        }

        $needsOcr = $extension === 'pdf';

        return [$filePayload, null, $needsOcr];
    }
}
