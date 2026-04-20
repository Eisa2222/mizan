<?php

namespace Modules\TenderReviews\Actions;

use App\Jobs\TenderReviewJob;
use App\Models\LegalDocument;
use App\Models\User;
use App\Services\ElasticsearchService;
use App\Services\TenderSimilarityService;
use App\Services\TextExtractorService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Uploads a tender-for-review document, extracts its text, runs the
 * synchronous AI review, and compares it against known tenders. Heavy I/O
 * (60-120s); the caller extends the PHP time limit before invoking.
 */
class StoreTenderReviewAction
{
    public function __construct(
        private readonly TextExtractorService $extractor,
        private readonly ElasticsearchService $elasticsearch,
        private readonly TenderSimilarityService $similarity,
    ) {
    }

    /**
     * @param  array{title:string, tender_type:?string, sector:?string, summary:?string}  $data
     */
    public function execute(User $user, array $data, UploadedFile $file): LegalDocument
    {
        $storedPath = $file->store('documents', 'public');

        $payload = [
            'title'       => $data['title'],
            'summary'     => $data['summary'] ?? null,
            'file_path'   => $storedPath,
            'file_name'   => $file->getClientOriginalName(),
            'file_size'   => $file->getSize(),
            'org_id'      => $user->org_id,
            'uploaded_by' => $user->id,
            'kind'        => LegalDocument::KIND_TENDER_REVIEW,
            'type'        => 1,
            'metadata'    => [
                'tender_type' => $data['tender_type'] ?? null,
                'sector'      => $data['sector']      ?? null,
            ],
        ];

        $extracted = $this->extractor->extract(Storage::disk('public')->path($storedPath));
        if ($extracted !== null && $extracted !== '') {
            $payload['content'] = $extracted;
        }

        $document = LegalDocument::create($payload);

        if ($document->content) {
            $this->elasticsearch->reindexDocument($document);
            TenderReviewJob::dispatchSync($document);
            try {
                $this->similarity->compareReview($document->fresh());
            } catch (Throwable) {
                // Similarity is best-effort — a failure must not block the review.
            }
        }

        return $document;
    }
}
