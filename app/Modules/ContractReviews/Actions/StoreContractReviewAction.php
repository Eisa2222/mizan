<?php

namespace Modules\ContractReviews\Actions;

use App\Jobs\ReviewContractJob;
use App\Models\LegalDocument;
use App\Models\User;
use App\Services\ElasticsearchService;
use App\Services\TextExtractorService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Uploads a contract, extracts its text, indexes it, and runs the AI review
 * synchronously. The heavy work (text extraction + Claude call) can take
 * 60-120s so the caller raises the PHP execution limit up front.
 */
class StoreContractReviewAction
{
    public function __construct(
        private readonly TextExtractorService $extractor,
        private readonly ElasticsearchService $elasticsearch,
    ) {
    }

    public function execute(User $user, string $title, ?string $summary, UploadedFile $file): LegalDocument
    {
        $storedPath = $file->store('documents', 'public');

        $payload = [
            'title'       => $title,
            'summary'     => $summary,
            'file_path'   => $storedPath,
            'file_name'   => $file->getClientOriginalName(),
            'file_size'   => $file->getSize(),
            'org_id'      => $user->org_id,
            'uploaded_by' => $user->id,
            'kind'        => LegalDocument::KIND_CONTRACT_REVIEW,
            'type'        => 1,
        ];

        $extracted = $this->extractor->extract(Storage::disk('public')->path($storedPath));
        if ($extracted !== null && $extracted !== '') {
            $payload['content'] = $extracted;
        }

        $document = LegalDocument::create($payload);

        if ($document->content) {
            $this->elasticsearch->reindexDocument($document);
            ReviewContractJob::dispatchSync($document);
        }

        return $document;
    }
}
