<?php

namespace Modules\Documents\Actions;

use App\Models\LegalDocument;
use App\Services\ElasticsearchService;

/**
 * Updates the document's editable body fields and keeps Elasticsearch in sync.
 * Used by manual correction flows (broken Arabic ligatures, OCR artifacts).
 */
class UpdateDocumentContentAction
{
    public function __construct(private readonly ElasticsearchService $elasticsearch)
    {
    }

    /**
     * @param  array{content?:?string, summary?:?string}  $data
     */
    public function execute(LegalDocument $document, array $data): LegalDocument
    {
        $document->update($data);
        $fresh = $document->fresh();

        $this->elasticsearch->reindexDocument($fresh);

        return $fresh;
    }
}
