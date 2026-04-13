<?php

namespace App\Jobs;

use App\Models\LegalDocument;
use App\Services\TenderSimilarityService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Runs similarity comparison for an uploaded tender review
 * against previously reviewed/generated tenders in the same org.
 */
class AnalyzeReviewSimilarityJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 120;

    public function __construct(public LegalDocument $document) {}

    public function handle(TenderSimilarityService $similarity): void
    {
        if ($this->document->kind !== LegalDocument::KIND_TENDER_REVIEW) return;

        try {
            $similarity->compareReview($this->document);
        } catch (\Throwable $e) {
            Log::warning('ReviewSimilarity failed', [
                'document_id' => $this->document->id,
                'error'       => $e->getMessage(),
            ]);
        }
    }
}
