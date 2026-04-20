<?php

namespace Modules\Documents\Actions;

use App\Jobs\AnalyzeCaseJob;
use App\Jobs\AnalyzeContractJob;
use App\Jobs\DraftMemoJob;
use App\Jobs\ExtractDocumentTextJob;
use App\Jobs\GenerateSuggestedQuestionsJob;
use App\Jobs\ReviewContractJob;
use App\Models\LegalDocument;
use App\Services\ElasticsearchService;

/**
 * After a LegalDocument is persisted, this action decides what happens next:
 *   • if OCR is needed → dispatch ExtractDocumentTextJob (ES reindex happens in the job)
 *   • otherwise        → reindex ES inline + dispatch the kind-specific AI analysis job
 *
 * Returns the translation key the controller should flash as a success message.
 */
class DispatchDocumentPipelineAction
{
    public function __construct(private readonly ElasticsearchService $elasticsearch)
    {
    }

    public function execute(LegalDocument $document, bool $needsOcr): string
    {
        if ($needsOcr) {
            ExtractDocumentTextJob::dispatch($document);

            return 'documents.flash.uploaded_pending_ocr';
        }

        $this->elasticsearch->reindexDocument($document);

        match ($document->kind) {
            LegalDocument::KIND_CONTRACT        => AnalyzeContractJob::dispatch($document),
            LegalDocument::KIND_CASE            => AnalyzeCaseJob::dispatch($document),
            LegalDocument::KIND_CONTRACT_REVIEW => ReviewContractJob::dispatch($document),
            LegalDocument::KIND_MEMO            => DraftMemoJob::dispatch($document),
            default                             => GenerateSuggestedQuestionsJob::dispatch($document),
        };

        return match ($document->kind) {
            LegalDocument::KIND_CONTRACT        => 'documents.flash.uploaded_contract',
            LegalDocument::KIND_CASE            => 'documents.flash.uploaded_case',
            LegalDocument::KIND_CONTRACT_REVIEW => 'documents.flash.uploaded_contract_review',
            LegalDocument::KIND_MEMO            => 'documents.flash.uploaded_memo',
            default                             => 'documents.flash.uploaded_document',
        };
    }
}
